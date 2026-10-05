<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Item;
use App\Models\Store;
use App\CentralLogics\Helpers;
use App\CentralLogics\StoreLogic;
use App\Http\Controllers\Controller;
use App\Services\Search\ItemSearch;
use App\Services\Search\SearchQuery;
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

    /** Kept narrower than ItemSearch::RELATIONSHIPS: this runs per keystroke across every module. */
    private const ITEM_RELATIONSHIPS = ['translations' => 'value', 'tags' => 'tag', 'category' => 'name'];

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
        $search = SearchQuery::fromString($request['name']);
        $limit = max(1, min((int) ($request['limit'] ?? self::MAX_STORES), 40));

        if (empty($zoneIds) || $search->isEmpty()) {
            return response()->json(['stores' => []], 200);
        }

        $mode = 'all';
        $stores = $this->matchingStores($search, $mode, $zoneIds, $longitude, $latitude, $limit);
        // Nothing matches every word: fall back to any of them, as items/search does.
        if ($stores->isEmpty() && $search->hasMultipleTerms()) {
            $mode = 'any';
            $stores = $this->matchingStores($search, $mode, $zoneIds, $longitude, $latitude, $limit);
        }

        if ($stores->isEmpty()) {
            return response()->json(['stores' => []], 200);
        }

        $storeIds = $stores->pluck('id')->all();

        // One query for every store's matches, trimmed per store in PHP: a
        // per-store query would be up to twenty round trips for one keystroke.
        $matched = Item::active()
            ->with('store')
            ->whereIn('store_id', $storeIds)
            ->tap(fn ($q) => ItemSearch::applyTextMatch($q, $search, ['items.name'], self::ITEM_RELATIONSHIPS, $mode))
            ->tap(fn ($q) => ItemSearch::orderByRelevance($q, $search, 'items.name'))
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id');

        // Stores that matched on name alone: their best sellers stand in.
        $nameOnly = array_values(array_diff($storeIds, $matched->keys()->all()));
        $fallback = $this->bestSellers($nameOnly);

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

    /**
     * A store is a hit when its own name, translation or cuisine matches, or
     * when it sells a matching item.
     */
    private function matchingStores(SearchQuery $search, string $mode, array $zoneIds, float $longitude, float $latitude, int $limit)
    {
        return Store::WithOpenWithDeliveryTime($longitude, $latitude)
            ->withMaxItemDiscount()
            ->with(['discount' => fn ($q) => $q->validate(), 'module:id,module_type,variant', 'cuisines:id,name'])
            ->active()
            ->weekday()
            ->whereIn('zone_id', $zoneIds)
            ->when(config('module.current_module_data'), fn ($q, $module) => $q->where('module_id', $module['id']))
            ->whereHas('module', fn ($q) => $q->active()->notParcel()->notRental())
            ->whereHas('zone.modules', fn ($q) => $q->whereColumn('modules.id', 'stores.module_id'))
            ->where(function ($q) use ($search, $mode) {
                $q->where(fn ($own) => ItemSearch::applyTextMatch($own, $search, ['stores.name'], ['translations' => 'value', 'cuisines' => 'name'], $mode))
                    ->orWhereHas('items', function ($i) use ($search, $mode) {
                        ItemSearch::applyTextMatch($i->active(), $search, ['items.name'], self::ITEM_RELATIONSHIPS, $mode);
                    });
            })
            ->orderByDesc('open')
            ->tap(fn ($q) => ItemSearch::orderByRelevance($q, $search, 'stores.name'))
            ->orderBy('distance')
            ->limit($limit)
            ->get();
    }

    /**
     * Each store's top sellers, ITEMS_PER_STORE apiece. One UNION ALL of
     * per-store LIMITs, so a store with thousands of products costs eight rows.
     */
    private function bestSellers(array $storeIds)
    {
        if (empty($storeIds)) {
            return collect();
        }

        $ids = null;
        foreach ($storeIds as $storeId) {
            $part = Item::active()
                ->select('items.id')
                ->where('store_id', $storeId)
                ->orderByDesc('order_count')
                ->limit(self::ITEMS_PER_STORE)
                ->toBase();
            $ids = $ids ? $ids->unionAll($part) : $part;
        }

        return Item::with('store')
            ->whereIn('id', $ids->pluck('id'))
            ->orderByDesc('order_count')
            ->get()
            ->groupBy('store_id');
    }
}
