<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Store;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Search across every module at once — the Home dashboard's search, which has
 * no module to scope to.
 *
 * The per-module endpoints (`items/search`, `stores/search`) read the current
 * module from the `moduleId` header and 403 without it, so they cannot serve a
 * screen that is deliberately module-less. This one answers the question the
 * dashboard actually asks — "where can I get this?" — as stores, each carrying
 * the handful of its products that matched, so the app can group them by kind
 * of store (restaurants / groceries / shops) without a second call.
 *
 * A store is a hit when its own name or cuisine matches or when it sells a
 * match; a store that matched on name alone carries its best sellers instead of
 * an empty rail.
 *
 * Sent WITH a `moduleId` (the Restaurants home's search) the same query is
 * scoped to that module — one endpoint, two screens.
 */
class GlobalSearchController extends Controller
{
    /** Stores per response. The screen is a single scroll, not a paginated list. */
    private const MAX_STORES = 20;

    /** Products shown on each store's rail. */
    private const ITEMS_PER_STORE = 8;

    public function search(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            return response()->json(['errors' => [
                ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')],
            ]], 403);
        }
        $validator = Validator::make($request->all(), ['name' => 'required']);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $zoneIds = json_decode($request->header('zoneId'), true) ?: [];
        $longitude = (float) $request->header('longitude', 0);
        $latitude = (float) $request->header('latitude', 0);
        $keys = array_values(array_filter(explode(' ', trim($request['name']))));
        $limit = min((int) ($request['limit'] ?? self::MAX_STORES), 40);

        if (empty($zoneIds) || empty($keys)) {
            return response()->json(['stores' => []], 200);
        }

        $itemMatches = function ($q) use ($keys) {
            foreach ($keys as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
            $q->applyRelationShipSearch(
                relationships: ['translations' => 'value', 'tags' => 'tag', 'category' => 'name'],
                searchParameter: $keys
            );
        };

        $stores = Store::WithOpenWithDeliveryTime($longitude, $latitude)
            ->withMaxItemDiscount()
            ->with(['discount' => fn ($q) => $q->validate(), 'module:id,module_type,variant', 'cuisines:id,name'])
            ->active()
            ->weekday()
            ->whereIn('zone_id', $zoneIds)
            ->when(config('module.current_module_data'), fn ($q, $module) => $q->where('module_id', $module['id']))
            ->whereHas('module', fn ($q) => $q->active()->notParcel()->notRental())
            ->whereHas('zone.modules', fn ($q) => $q->whereColumn('modules.id', 'stores.module_id'))
            ->where(function ($q) use ($keys, $itemMatches) {
                $q->where(function ($n) use ($keys) {
                    foreach ($keys as $value) {
                        $n->orWhere('name', 'like', "%{$value}%");
                    }
                })
                    ->orWhereHas('translations', function ($t) use ($keys) {
                        foreach ($keys as $value) {
                            $t->orWhere('value', 'like', "%{$value}%");
                        }
                    })
                    ->orWhereHas('cuisines', function ($c) use ($keys) {
                        foreach ($keys as $value) {
                            $c->orWhere('name', 'like', "%{$value}%");
                        }
                    })
                    ->orWhereHas('items', function ($i) use ($itemMatches) {
                        $i->active()->where($itemMatches);
                    });
            })
            ->orderByDesc('open')
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 ELSE 1 END', ["%{$request['name']}%"])
            ->orderBy('distance')
            ->limit($limit)
            ->get();

        if ($stores->isEmpty()) {
            return response()->json(['stores' => []], 200);
        }

        $storeIds = $stores->pluck('id')->all();

        // One query for every store's matches, trimmed per store in PHP: a
        // per-store query would be up to twenty round trips for one keystroke.
        $matched = Item::active()
            ->with('store')
            ->whereIn('store_id', $storeIds)
            ->where($itemMatches)
            ->orderByRaw('FIELD(name, ?) DESC', [$request['name']])
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id');

        // Stores that matched on name alone: their best sellers stand in.
        $nameOnly = array_values(array_diff($storeIds, $matched->keys()->all()));
        $fallback = collect();
        if (!empty($nameOnly)) {
            $fallback = Item::active()
                ->with('store')
                ->whereIn('store_id', $nameOnly)
                ->orderByDesc('order_count')
                ->get()
                ->groupBy('store_id');
        }

        $locale = app()->getLocale();
        $out = [];
        foreach ($stores as $store) {
            $rows = ($matched[$store->id] ?? $fallback[$store->id] ?? collect())
                ->take(self::ITEMS_PER_STORE);
            $items = Helpers::product_data_formatting($rows->values()->all(), true, false, $locale);
            foreach ($items as $item) {
                $item['store_logo_full_url'] = $store->logo_full_url;
                $item['store_open'] = (bool) ((int) $store->open === 1);
            }

            $rating = StoreLogic::calculate_store_rating(array_pad((array) ($store->rating ?: []), 5, 0));
            $out[] = [
                'id' => $store->id,
                'name' => $store->name,
                'logo_full_url' => $store->logo_full_url,
                'module_id' => $store->module_id,
                'module_type' => $store->module?->module_type,
                'variant' => $store->module?->variant,
                'delivery_time' => $store->delivery_time,
                'distance' => $store->distance === null ? null : round($store->distance / 1000, 1),
                'open' => (bool) ((int) $store->open === 1),
                'cuisines' => $store->cuisines->pluck('name')->values()->all(),
                'avg_rating' => (float) ($rating['rating'] ?? 0),
                'rating_count' => (int) ($rating['total'] ?? 0),
                'free_delivery' => (bool) $store->free_delivery,
                // The store's own scheduled discount wins; otherwise the
                // deepest item markdown — the same "up to X%" claim the home
                // store cards make.
                'offer_percent' => (int) (
                    $store->discount && $store->discount->discount_type === 'percent'
                        ? $store->discount->discount
                        : ($store->max_item_discount ?? 0)
                ),
                'items' => $items,
            ];
        }

        return response()->json(['stores' => $out], 200);
    }
}
