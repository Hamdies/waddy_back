<?php

namespace App\Services\Search;

use App\CentralLogics\Helpers;
use App\Models\Category;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customer item search, shared by items/search and
 * get-combined-data?data_type=searched so the two can't drift apart again.
 */
class ItemSearch
{
    /** Item relations whose text also counts as a match, relation => column. */
    public const RELATIONSHIPS = [
        'translations' => 'value',
        'tags' => 'tag',
        'category' => 'name',
        'category.parent' => 'name',
        'nutritions' => 'nutrition',
        'allergies' => 'allergy',
        'generic' => 'generic_name',
        'ecommerce_item_details.brand' => 'name',
        'pharmacy_item_details.common_condition' => 'name',
    ];

    public const MAX_LIMIT = 100;

    /**
     * Restricts $query to rows matching the search text.
     *
     * $mode 'all' requires every term to match (in any of the columns or
     * relations); 'any' accepts a row matching at least one term.
     */
    public static function applyTextMatch(Builder $query, SearchQuery $search, array $columns, array $relationships, string $mode = 'all'): Builder
    {
        if ($search->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        $matchTerm = function (Builder $q, string $term) use ($columns, $relationships) {
            $like = SearchQuery::contains($term);
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
            foreach ($relationships as $relation => $field) {
                $q->orWhereHas($relation, fn ($r) => $r->where($field, 'like', $like));
            }
        };

        return $query->where(function (Builder $q) use ($search, $mode, $matchTerm) {
            foreach ($search->terms() as $term) {
                if ($mode === 'all') {
                    $q->where(fn ($t) => $matchTerm($t, $term));
                } else {
                    $matchTerm($q, $term);
                }
            }
        });
    }

    /** Exact name match first, then name prefix, then name contains, then the rest. */
    public static function orderByRelevance(Builder $query, SearchQuery $search, string $column): Builder
    {
        return $query->orderByRaw(
            "CASE WHEN {$column} = ? THEN 0 WHEN {$column} LIKE ? THEN 1 WHEN {$column} LIKE ? THEN 2 ELSE 3 END",
            [$search->text(), SearchQuery::startsWith($search->text()), SearchQuery::contains($search->text())]
        );
    }

    public static function limit($value, int $default = 10): int
    {
        $limit = is_numeric($value) ? (int) $value : $default;
        return max(1, min($limit, self::MAX_LIMIT));
    }

    public static function page($value): int
    {
        return is_numeric($value) ? max(1, (int) $value) : 1;
    }

    public static function idList($value): array
    {
        if (!$value) {
            return [];
        }
        $ids = is_array($value) ? $value : json_decode($value, true);
        return is_array($ids) ? array_values(array_filter($ids, 'is_numeric')) : [];
    }

    /**
     * The full items/search response: products for the requested page plus
     * the top-level categories they belong to.
     */
    public static function search(Request $request, string $zoneId): array
    {
        $search = SearchQuery::fromString($request['name']);
        $limit = self::limit($request['limit']);
        $offset = self::page($request['offset']);

        $query = self::buildQuery($request, $zoneId, $search, 'all');
        $items = (clone $query)->paginate($limit, ['*'], 'page', $offset);

        // Nothing contains every word: fall back to matching any of them
        // rather than showing an empty screen.
        if ($items->total() === 0 && $search->hasMultipleTerms()) {
            $query = self::buildQuery($request, $zoneId, $search, 'any');
            $items = (clone $query)->paginate($limit, ['*'], 'page', $offset);
        }

        $data = [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'products' => $items->items(),
            'categories' => self::categories($query),
        ];

        $data['products'] = Helpers::product_data_formatting($data['products'], true, false, app()->getLocale());
        // Search results are shown grouped by store (logo, window, closed
        // state), so each row names its store flat instead of making the app
        // dig through the nested relation.
        foreach ($data['products'] as $product) {
            $store = $product->store;
            $product['store_logo_full_url'] = $store?->logo_full_url;
            $product['store_open'] = (bool) ($store && $store->active && (int) $store->open === 1);
        }

        return $data;
    }

    private static function buildQuery(Request $request, string $zoneId, SearchQuery $search, string $mode): Builder
    {
        $zoneIds = json_decode($zoneId, true) ?: [];
        $module = config('module.current_module_data');
        $categoryIds = self::idList($request['category_ids']);
        $brandIds = self::idList($request['brand_ids']);
        $filter = $request['filter'] ? (is_array($request['filter']) ? $request['filter'] : str_getcsv(trim($request['filter'], '[]'), ',')) : [];
        $type = $request->query('type', 'all');
        $min = (float) $request->query('min_price', 0);
        $max = (float) $request->query('max_price', 0);
        $ratingCount = $request->query('rating_count');
        $sortBy = $request->query('sort_by', 'default');

        $query = Item::active()->type($type)
            ->with('store', function ($query) {
                $query->withCount(['campaigns' => function ($query) {
                    $query->Running();
                }]);
            })
            ->select(['items.*'])
            ->selectSub(function ($subQuery) {
                $subQuery->selectRaw('active as temp_available')
                    ->from('stores')
                    ->whereColumn('stores.id', 'items.store_id');
            }, 'temp_available');

        if ((Helpers::get_business_settings('product_search_default_status', false) ?? '1') != '1') {
            $unavailable = Helpers::getPriorityList('product_search_sort_by_unavailable', 'unavailable');
            $tempClosed = Helpers::getPriorityList('product_search_sort_by_temp_closed', 'temp_closed');

            if (($module['module_type'] ?? null) !== 'food') {
                if ($unavailable == 'remove') {
                    $query->where('stock', '>', 0);
                } elseif ($unavailable == 'last') {
                    $query->orderByRaw('CASE WHEN stock = 0 THEN 1 ELSE 0 END');
                }
            }

            if ($tempClosed == 'remove') {
                $query->having('temp_available', '>', 0);
            } elseif ($tempClosed == 'last') {
                $query->orderByDesc('temp_available');
            }
        }

        $query->when($request->category_id, function ($query) use ($request) {
                $query->whereHas('category', function ($q) use ($request) {
                    return $q->whereId($request->category_id)->orWhere('parent_id', $request->category_id);
                });
            })
            ->when($categoryIds, function ($query) use ($categoryIds) {
                $query->whereHas('category', function ($q) use ($categoryIds) {
                    return $q->whereIn('id', $categoryIds)->orWhereIn('parent_id', $categoryIds);
                });
            })
            ->when($brandIds, function ($query) use ($brandIds) {
                $query->whereHas('ecommerce_item_details', fn ($q) => $q->whereHas('brand', fn ($q) => $q->whereIn('id', $brandIds)));
            })
            ->when($request->store_id, function ($query) use ($request) {
                return $query->where('store_id', $request->store_id);
            })
            ->whereHas('module.zones', function ($query) use ($zoneIds, $filter) {
                $query->whereIn('zones.id', $zoneIds)
                    ->when(in_array('free_delivery', $filter), fn ($q) => $q->where('free_delivery', 1))
                    ->when(in_array('coupon', $filter), fn ($q) => $q->has('activeCoupons'));
            })
            ->whereHas('store', function ($query) use ($zoneIds, $module) {
                $query->when($module, function ($query) use ($module) {
                    $query->where('module_id', $module['id'])->whereHas('zone.modules', function ($query) use ($module) {
                        $query->where('modules.id', $module['id']);
                    });
                })->whereIn('zone_id', $zoneIds);
            })
            ->when($ratingCount, fn ($query) => $query->where('avg_rating', '>=', $ratingCount))
            ->when($min > 0, fn ($query) => $query->where('price', '>=', $min))
            ->when($max > 0, fn ($query) => $query->where('price', '<=', $max))
            ->when(in_array('discounted', $filter), fn ($query) => $query->Discounted()->orderBy('discount', 'desc'))
            ->when(in_array('available_now', $filter), function ($query) {
                $query->where(function ($q) {
                    $currentTime = now()->format('H:i:s');
                    $q->whereRaw('(available_time_starts < available_time_ends AND TIME(?) BETWEEN available_time_starts AND available_time_ends)', [$currentTime])
                        ->orWhereRaw('(available_time_starts > available_time_ends AND (TIME(?) >= available_time_starts OR TIME(?) <= available_time_ends))', [$currentTime, $currentTime]);
                });
            })
            ->when(in_array('top_rated', $filter), fn ($query) => $query->withCount('reviews')->orderBy('reviews_count', 'desc'))
            ->when(in_array('popular', $filter), fn ($query) => $query->popular())
            ->when(in_array('high', $filter), fn ($query) => $query->orderBy('price', 'desc'))
            ->when(in_array('low', $filter), fn ($query) => $query->orderBy('price', 'asc'));

        self::applyTextMatch($query, $search, ['items.name'], self::RELATIONSHIPS, $mode);

        match ($sortBy) {
            'price_low_to_high' => $query->orderBy('price', 'asc'),
            'price_high_to_low' => $query->orderBy('price', 'desc'),
            'rating' => $query->orderByDesc('avg_rating'),
            'popularity' => $query->orderByDesc('order_count'),
            'newest' => $query->orderByDesc('items.created_at'),
            // A correlated subquery rather than a join: joining stores made
            // unqualified columns like name and status ambiguous.
            'distance' => $query->orderByRaw(
                '(select 6371 * acos(cos(radians(?)) * cos(radians(stores.latitude)) * cos(radians(stores.longitude) - radians(?)) + sin(radians(?)) * sin(radians(stores.latitude))) from stores where stores.id = items.store_id)',
                [(float) $request->header('latitude', 0), (float) $request->header('longitude', 0), (float) $request->header('latitude', 0)]
            ),
            default => $query,
        };

        // Explicit sorts above win; relevance orders everything they tie on.
        return self::orderByRelevance($query, $search, 'items.name');
    }

    /** Top-level categories of every matching item, found in SQL rather than by loading every match. */
    private static function categories(Builder $query)
    {
        $categoryIds = DB::query()
            ->fromSub((clone $query)->reorder(), 'matched')
            ->distinct()
            ->pluck('category_id');

        return Category::withCount(['products', 'childes'])->with(['childes' => function ($query) {
                $query->withCount(['products', 'childes']);
            }])
            ->shared()
            ->where(['position' => 0, 'status' => 1])
            ->when(config('module.current_module_data'), function ($query) {
                $query->module(config('module.current_module_data')['id']);
            })
            ->whereIn('id', $categoryIds)
            ->orderBy('priority', 'desc')->get();
    }
}
