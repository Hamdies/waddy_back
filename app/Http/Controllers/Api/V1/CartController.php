<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Support\ProducePreference;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    private function formatCartCollection($carts)
    {
        return $carts->map(function ($data) {
            try {
                $data->add_on_ids = json_decode($data->add_on_ids, true);
                $data->add_on_qtys = json_decode($data->add_on_qtys, true);
                $data->variation = json_decode($data->variation, true);
                $data->item = Helpers::cart_product_data_formatting(
                    $data->item,
                    $data->variation,
                    $data->add_on_ids,
                    $data->add_on_qtys,
                    false,
                    app()->getLocale()
                );

                return $data;
            } catch (\Throwable $e) {
                logger()->warning('Skipping malformed cart item while formatting cart response', [
                    'cart_id' => $data->id ?? null,
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter()->values();
    }

    public function get_carts(Request $request)
    {
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $validator = Validator::make($request->all(), [
            'guest_id' => $user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;

        $carts = $this->formatCartCollection(
            Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->get()
                ->filter(function ($data) {
                    return $data->item !== null;
                })
        );

        return response()->json($carts, 200);
    }

    public function add_to_cart(Request $request)
    {
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $validator = Validator::make($request->all(), [
            'guest_id' => $user ? 'nullable' : 'required',
            'item_id' => 'required|integer',
            'model' => 'required|string|in:Item,ItemCampaign',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
            'preference' => 'nullable|string|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;
        // Produce answer (ripeness / salad-or-cooking). Not required here: an
        // app build that predates it must still be able to add fruit.
        $preference = ProducePreference::sanitize($request->preference);
        $model = $request->model === 'Item' ? Item::class : ItemCampaign::class;
        $cartTypes = $request->model === 'Item'
            ? [Item::class, 'Item']
            : [ItemCampaign::class, 'ItemCampaign'];
        $item = $request->model === 'Item' ? Item::find($request->item_id) : ItemCampaign::find($request->item_id);
        if (!$item) {
            return response()->json([
                'errors' => [['code' => 'item', 'message' => translate('messages.not_found')]]
            ], 404);
        }

        return $this->withLineLock($is_guest, $user_id, $request->item_id, function () use ($request, $user_id, $is_guest, $preference, $model, $cartTypes, $item) {
            // The same item with the same variation, the same add-ons AND the
            // same produce answer is one line; a different answer ("ripe
            // later" next to "ready to eat") or other add-ons are a line of
            // their own.
            $addOnKey = $this->addOnKey($request->add_on_ids, $request->add_on_qtys);
            $cart = Cart::where('item_id', $request->item_id)
                ->whereIn('item_type', $cartTypes)
                ->where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->where('module_id', $request->header('moduleId'))
                ->get()
                ->first(fn ($line) => json_decode($line->variation ?? '""', true) == $request->variation
                    && ($line->preference ?: null) === $preference
                    && $this->addOnKey($line->add_on_ids, $line->add_on_qtys) === $addOnKey);

            // Adding what is already in the cart grows that line. It used to
            // 403 "Item already exists", which every "+" that stays a "+"
            // (search, favourites, Order Again) hit on its second tap. The
            // limit applies to the line's total, not to this request alone.
            $total = ($cart?->quantity ?? 0) + $request->quantity;
            if ($item->maximum_cart_quantity && $total > $item->maximum_cart_quantity) {
                return response()->json([
                    'errors' => [
                        ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                    ]
                ], 403);
            }

            if ($cart) {
                // Atomic UPDATE. The line keeps the price it was created at;
                // the order is repriced at placement anyway.
                $cart->increment('quantity', $request->quantity);
            } else {
                $this->insertLine($request, $user_id, $is_guest, $preference, $model, $item);
            }

            return response()->json($this->userCart($user_id, $is_guest), 200);
        });
    }

    private function insertLine(Request $request, $user_id, int $is_guest, ?string $preference, string $model, $item): void
    {
        $cart = new Cart();
        $cart->user_id = $user_id;
        $cart->module_id = $request->header('moduleId');
        $cart->item_id = $request->item_id;
        $cart->is_guest = $is_guest;
        $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : json_encode([]);
        $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : json_encode([]);
        $cart->item_type = $model;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = isset($request->variation) ? json_encode($request->variation) : json_encode([]);
        $cart->preference = $preference;
        $cart->save();

        $item->carts()->save($cart);
    }

    private function userCart($user_id, int $is_guest)
    {
        return $this->formatCartCollection(
            Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->get()
                ->filter(function ($data) {
                    return $data->item !== null;
                })
        );
    }

    /**
     * Runs [$write] holding one lock per (user or guest, item), so two taps
     * landing together serialize: they can neither both read quantity 1 and
     * write 2, nor both miss the line and insert it twice. A unique index
     * cannot do this — the line identity includes variation and add-ons
     * stored as JSON text. Backed by the `cache_locks` table.
     */
    private function withLineLock(int $is_guest, $user_id, $item_id, callable $write)
    {
        try {
            return Cache::lock("cart:{$is_guest}:{$user_id}:{$item_id}", 5)->block(3, $write);
        } catch (LockTimeoutException $e) {
            return response()->json([
                'errors' => [['code' => 'cart_busy', 'message' => translate('messages.cart_busy_try_again')]]
            ], 429);
        }
    }

    /**
     * Add-ons as sorted "id:qty" pairs. Sorting ids and quantities separately
     * would make {1:2, 2:1} equal {1:1, 2:2}.
     */
    private function addOnKey($ids, $qtys): string
    {
        $ids = is_string($ids) ? json_decode($ids, true) : $ids;
        $qtys = is_string($qtys) ? json_decode($qtys, true) : $qtys;
        $ids = is_array($ids) ? array_values($ids) : [];
        $qtys = is_array($qtys) ? array_values($qtys) : [];

        $pairs = [];
        foreach ($ids as $i => $id) {
            $pairs[] = (int) $id . ':' . (int) ($qtys[$i] ?? 1);
        }
        sort($pairs, SORT_STRING);

        return implode(',', $pairs);
    }

    public function update_cart(Request $request)
    {
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $user ? 'nullable' : 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;
        $cart = Cart::find($request->cart_id);
        if (!$cart) {
            return response()->json($this->userCart($user_id, $is_guest), 200);
        }

        // Same lock as add_to_cart, so a stepper write and a "+" on the same
        // line cannot interleave.
        return $this->withLineLock($is_guest, $user_id, $cart->item_id, function () use ($request, $user_id, $is_guest, $cart) {
            $cart->refresh();
            $item = in_array($cart->item_type, [Item::class, 'Item'], true)
                ? Item::find($cart->item_id)
                : ItemCampaign::find($cart->item_id);

            if ($item?->maximum_cart_quantity && ($request->quantity > $item->maximum_cart_quantity)) {
                return response()->json([
                    'errors' => [
                        ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                    ]
                ], 403);
            }

            $cart->user_id = $user_id;
            $cart->module_id = $request->header('moduleId');
            $cart->is_guest = $is_guest;
            $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : $cart->add_on_ids;
            $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : $cart->add_on_qtys;
            $cart->price = $request->price;
            $cart->quantity = $request->quantity;
            $cart->variation = isset($request->variation) ? json_encode($request->variation) : $cart->variation;
            if ($request->has('preference')) {
                $cart->preference = ProducePreference::sanitize($request->preference);
            }
            $cart->save();

            return response()->json($this->userCart($user_id, $is_guest), 200);
        });
    }

    public function remove_cart_item(Request $request)
    {
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $validator = Validator::make($request->all(), [
            'cart_id' => 'required',
            'guest_id' => $user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;

        $cart = Cart::find($request->cart_id);
        $cart?->delete();

        $carts = $this->formatCartCollection(
            Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->where('module_id', $request->header('moduleId'))
                ->get()
                ->filter(function ($data) {
                    return $data->item !== null;
                })
        );

        return response()->json($carts, 200);
    }

    public function remove_cart(Request $request)
    {
        $user = $request->user instanceof \App\Models\User ? $request->user : null;
        $validator = Validator::make($request->all(), [
            'guest_id' => $user ? 'nullable' : 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;

        $carts = Cart::where('user_id', $user_id)->where('is_guest', $is_guest)->get();

        foreach ($carts as $cart) {
            $cart?->delete();
        }

        $carts = $this->formatCartCollection(
            Cart::where('user_id', $user_id)
                ->where('is_guest', $is_guest)
                ->get()
                ->filter(function ($data) {
                    return $data->item !== null;
                })
        );

        return response()->json($carts, 200);
    }
}
