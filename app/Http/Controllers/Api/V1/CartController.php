<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use Illuminate\Http\Request;
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
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user_id = $user ? $user->id : $request['guest_id'];
        $is_guest = $user ? 0 : 1;
        $model = $request->model === 'Item' ? 'App\Models\Item' : 'App\Models\ItemCampaign';
        $item = $request->model === 'Item' ? Item::find($request->item_id) : ItemCampaign::find($request->item_id);

        $cart = Cart::where('item_id', $request->item_id)
            ->where('item_type', $model)
            ->where('user_id', $user_id)
            ->where('is_guest', $is_guest)
            ->where('module_id', $request->header('moduleId'))
            ->first();

        if ($cart && json_decode($cart->variation ?? '""', true) == $request->variation) {
            return response()->json([
                'errors' => [
                    ['code' => 'cart_item', 'message' => translate('messages.Item_already_exists')]
                ]
            ], 403);
        }

        if ($item->maximum_cart_quantity && ($request->quantity > $item->maximum_cart_quantity)) {
            return response()->json([
                'errors' => [
                    ['code' => 'cart_item_limit', 'message' => translate('messages.maximum_cart_quantity_exceeded')]
                ]
            ], 403);
        }

        $cart = new Cart();
        $cart->user_id = $user_id;
        $cart->module_id = $request->header('moduleId');
        $cart->item_id = $request->item_id;
        $cart->is_guest = $is_guest;
        $cart->add_on_ids = isset($request->add_on_ids) ? json_encode($request->add_on_ids) : json_encode([]);
        $cart->add_on_qtys = isset($request->add_on_qtys) ? json_encode($request->add_on_qtys) : json_encode([]);
        $cart->item_type = $request->model;
        $cart->price = $request->price;
        $cart->quantity = $request->quantity;
        $cart->variation = isset($request->variation) ? json_encode($request->variation) : json_encode([]);
        $cart->save();

        $item->carts()->save($cart);

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
        $item = $cart->item_type === 'App\Models\Item' ? Item::find($cart->item_id) : ItemCampaign::find($cart->item_id);

        if ($item->maximum_cart_quantity && ($request->quantity > $item->maximum_cart_quantity)) {
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
        $cart->save();

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
