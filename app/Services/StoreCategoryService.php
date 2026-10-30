<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreCategorySetting;
use App\Support\AppClock;
use Illuminate\Support\Collection;

/**
 * CT-1: a store's own category settings (priority, order, hidden, schedule)
 * and the tree the store app's Categories screen shows.
 *
 * The tree holds the shared categories the store has items in plus the
 * store's own (`categories.store_id`). Only the store's own can be renamed,
 * moved or deleted; every category can take settings.
 */
class StoreCategoryService
{
    private const RANK = ['high' => 0, 'normal' => 1, 'low' => 2];

    /** @return Collection<int, StoreCategorySetting> keyed by category_id */
    public static function settings(int $storeId): Collection
    {
        return StoreCategorySetting::where('store_id', $storeId)->get()->keyBy('category_id');
    }

    /** Whether a category with this setting shows to customers right now. */
    public static function showsNow(?StoreCategorySetting $s): bool
    {
        if (!$s) {
            return true;
        }
        if ($s->hidden) {
            return false;
        }
        $now = AppClock::now();
        return match ($s->schedule) {
            'morning' => $now->hour >= 8 && $now->hour < 12,
            'evening' => $now->hour >= 18 || $now->hour < 4,
            'weekend' => in_array($now->dayOfWeek, [4, 5, 6], true), // Thu, Fri, Sat
            default => true,
        };
    }

    /**
     * Sorts categories the way this store set them: priority level, then the
     * store's order, then the admin's priority and id.
     */
    public static function sort(Collection $categories, Collection $settings): Collection
    {
        return $categories->sort(function ($a, $b) use ($settings) {
            $sa = $settings->get($a->id);
            $sb = $settings->get($b->id);
            return [self::RANK[$sa->priority ?? 'normal'] ?? 1, $sa?->sort ?? PHP_INT_MAX, -(int) $a->priority, $a->id]
                <=> [self::RANK[$sb->priority ?? 'normal'] ?? 1, $sb?->sort ?? PHP_INT_MAX, -(int) $b->priority, $b->id];
        })->values();
    }

    /** Customer side: drop hidden or off-schedule categories, then sort. */
    public static function forCustomers(Collection $categories, int $storeId): Collection
    {
        $settings = self::settings($storeId);
        if ($settings->isEmpty()) {
            return $categories->values();
        }
        $shown = $categories->filter(function ($c) use ($settings) {
            // A subcategory follows its parent's visibility.
            $top = $c->position == 0 ? $c->id : $c->parent_id;
            return self::showsNow($settings->get($top));
        });
        return self::sort($shown, $settings);
    }

    /** Categories this store may see: shared ones of its module, and its own. */
    public static function visibleTo($query, Store $store)
    {
        return $query->where('module_id', $store->module_id)
            ->where(fn ($q) => $q->whereNull('categories.store_id')->orWhere('categories.store_id', $store->id));
    }

    /** The Categories screen: two levels with item and out-of-stock counts. */
    public static function tree(Store $store): array
    {
        $food = $store->module?->module_type === 'food';
        // "Out" is what customers can't order: switched off for food (no stock), stock 0 elsewhere.
        $counts = Item::withoutGlobalScopes()->where('store_id', $store->id)
            ->selectRaw('category_id, COUNT(*) as n, SUM(' . ($food ? 'status = 0' : 'stock <= 0') . ') as out_n')
            ->groupBy('category_id')->get()->keyBy('category_id');

        $used = Category::whereIn('id', $counts->keys())->get(['id', 'parent_id', 'position']);
        $own = Category::where('store_id', $store->id)->get(['id', 'parent_id', 'position']);
        $subIds = $used->merge($own)->where('position', '!=', 0)->pluck('id');
        $topIds = $used->merge($own)->map(fn ($c) => $c->position == 0 ? $c->id : $c->parent_id)->unique();

        $all = Category::where('status', 1)->whereIn('id', $topIds->merge($subIds)->unique())->get();
        $settings = self::settings($store->id);
        $tops = self::sort($all->where('position', 0), $settings);

        $row = function (Category $c, array $extra) use ($settings, $store) {
            $s = $settings->get($c->id);
            return array_merge([
                'id' => $c->id,
                'name' => $c->name,
                'own' => $c->store_id === $store->id,
                'priority' => $s->priority ?? 'normal',
            ], $extra);
        };

        return $tops->map(function (Category $top) use ($all, $settings, $counts, $row) {
            $subs = self::sort($all->where('parent_id', $top->id), $settings)
                ->map(fn (Category $sub) => $row($sub, [
                    'items' => (int) ($counts->get($sub->id)->n ?? 0),
                    'out' => (int) ($counts->get($sub->id)->out_n ?? 0),
                ]))->values();
            $s = $settings->get($top->id);
            return $row($top, [
                'hidden' => (bool) ($s->hidden ?? false),
                'schedule' => $s->schedule ?? 'always',
                // Items filed straight under the category count too.
                'items' => (int) ($counts->get($top->id)->n ?? 0) + $subs->sum('items'),
                'out' => (int) ($counts->get($top->id)->out_n ?? 0) + $subs->sum('out'),
                'subs' => $subs,
            ]);
        })->values()->all();
    }

    /** Items filed under the category or any of its subcategories. */
    public static function itemCount(Store $store, Category $category): int
    {
        $ids = Category::where('parent_id', $category->id)->pluck('id')->push($category->id);
        return Item::withoutGlobalScopes()->where('store_id', $store->id)->whereIn('category_id', $ids)->count();
    }

    /** Saves the given settings fields, creating the row on first use. */
    public static function set(Store $store, int $categoryId, array $fields): void
    {
        StoreCategorySetting::updateOrCreate(['store_id' => $store->id, 'category_id' => $categoryId], $fields);
    }
}
