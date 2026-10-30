<?php

namespace App\Services;

use App\Models\AddOn;
use App\Models\AddonCategory;
use App\Models\Item;
use App\Models\Store;
use App\Scopes\StoreScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * AO-1: a store's add-on library. A group is an `addon_categories` row with
 * `store_id` set and a pick rule; its choices are the store's `add_ons` rows.
 *
 * Add-ons still in a shared admin category (added from the web panels) show as
 * a group too; saving one moves its add-ons into a new group of the store's own.
 */
class StoreAddonGroupService
{
    public const MAX_CHOICES = 30;

    /** The store app's Add-ons screen: every group with its choices and how many items use it. */
    public static function library(Store $store): array
    {
        $addons = AddOn::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')
            ->where('store_id', $store->id)->orderBy('id')->get();
        $own = AddonCategory::withoutGlobalScope('translate')->where('store_id', $store->id)->orderBy('id')->get();
        $shared = AddonCategory::withoutGlobalScope('translate')->whereNull('store_id')
            ->whereIn('id', $addons->pluck('addon_category_id')->filter()->unique())->get();
        $itemAddons = self::itemAddons($store);

        $groups = [];
        foreach ($own->concat($shared) as $category) {
            $groups[] = self::format($category, $addons->where('addon_category_id', $category->id), $itemAddons);
        }
        $loose = $addons->whereNull('addon_category_id');
        if ($loose->isNotEmpty()) {
            $groups[] = self::format(null, $loose, $itemAddons);
        }
        return $groups;
    }

    /**
     * Creates or updates a group and its choices in one go. Choices left out of
     * the list are deleted; `loose` saves the add-ons that have no group. Returns the saved group, or an error message.
     *
     * @param array{id?: int|null, loose?: bool, name: string, is_required: bool, min: int, max: int, choices: array<int, array{id?: int|null, name: string, price: float}>} $data
     */
    public static function save(Store $store, array $data): array|string
    {
        $choices = collect($data['choices'])->map(fn ($c) => ['id' => $c['id'] ?? null, 'name' => trim($c['name']), 'price' => round((float) $c['price'], 2)])
            ->filter(fn ($c) => $c['name'] !== '')->values();
        if ($choices->isEmpty()) {
            return translate('messages.addon_group_needs_choice');
        }

        $category = isset($data['id']) ? AddonCategory::withoutGlobalScope('translate')->find($data['id']) : null;
        if ($category && $category->store_id && $category->store_id != $store->id) {
            return translate('messages.not_found');
        }

        $current = AddOn::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->with('translations')
            ->where('store_id', $store->id)
            ->when($category, fn ($q) => $q->where('addon_category_id', $category->id), fn ($q) => $q->whereNull('addon_category_id'))
            ->get()->keyBy('id');
        if (!$category && empty($data['loose'])) {
            $current = collect(); // a brand-new group replaces nothing
        }
        $ownIds = AddOn::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->pluck('id')->all();
        if ($choices->pluck('id')->filter()->diff($ownIds)->isNotEmpty()) {
            return translate('messages.not_found');
        }

        $count = $choices->count();
        $max = max(1, min((int) $data['max'], $count));
        $required = (bool) $data['is_required'];
        $min = $required ? max(1, min((int) $data['min'], $max)) : 0;

        $group = DB::transaction(function () use ($store, $data, $category, $current, $choices, $required, $min, $max) {
            if (!$category || !$category->store_id) {
                $group = new AddonCategory();
                $group->store_id = $store->id;
                $group->module_id = $store->module_id;
                $group->source_category_id = $category?->id;
            } else {
                $group = $category;
            }
            $group->name = trim($data['name']);
            $group->status = 1;
            $group->is_required = $required;
            $group->min_choices = $min;
            $group->max_choices = $max;
            $group->save();

            $kept = [];
            foreach ($choices as $choice) {
                $addon = $choice['id'] ? AddOn::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')->with('translations')->find($choice['id']) : new AddOn();
                if (!$addon->exists) {
                    $addon->store_id = $store->id;
                    $addon->status = 1;
                } elseif ($addon->getRawOriginal('name') !== $choice['name'] || $addon->name !== $choice['name']) {
                    // The store types one name; old per-language names would hide it.
                    $addon->translations()->where('key', 'name')->delete();
                }
                $addon->name = $choice['name'];
                $addon->price = $choice['price'];
                $addon->addon_category_id = $group->id;
                $addon->save();
                $kept[] = $addon->id;
            }

            self::deleteAddons($store, $current->keys()->diff($kept));
            return $group;
        });

        $addons = AddOn::withoutGlobalScope(StoreScope::class)->withoutGlobalScope('translate')
            ->where('addon_category_id', $group->id)->orderBy('id')->get();
        return self::format($group->fresh(), $addons, self::itemAddons($store));
    }

    /** Deletes a group's choices (taking them off every item) and the group if it is the store's own. */
    public static function delete(Store $store, ?int $groupId): void
    {
        $category = $groupId ? AddonCategory::find($groupId) : null;
        if ($groupId && (!$category || ($category->store_id && $category->store_id != $store->id))) {
            return;
        }
        $ids = AddOn::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)
            ->when($groupId, fn ($q) => $q->where('addon_category_id', $groupId), fn ($q) => $q->whereNull('addon_category_id'))
            ->pluck('id');

        DB::transaction(function () use ($store, $category, $ids) {
            self::deleteAddons($store, $ids);
            if ($category?->store_id) {
                $category->delete();
            }
        });
    }

    /**
     * Checks the customer's add-ons against the item's group rules. Groups the
     * item has no active choice from are skipped; a minimum is capped at the
     * number of choices the item offers. Works on the raw item or the formatted
     * one. Modules without add-ons (only food has them) are never checked, since
     * the customer app shows no picker there. Returns null when the pick is fine.
     *
     * @param array<int> $selectedIds
     */
    public static function ruleError($item, array $selectedIds): ?string
    {
        $moduleType = $item->module->module_type ?? null;
        if ($moduleType && !(config('module.' . $moduleType)['add_on'] ?? false)) {
            return null;
        }
        $offered =is_string($item->add_ons ?? null) ? json_decode($item->add_ons, true) : ($item->add_ons ?? []);
        $offered = collect($offered)->map(fn ($a) => is_object($a) ? ($a->id ?? null) : (is_array($a) ? ($a['id'] ?? null) : $a))->filter()->all();
        if (empty($offered)) {
            return null;
        }
        $addons = AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $offered)->active()->get(['id', 'addon_category_id']);
        $groups = AddonCategory::whereNotNull('store_id')->whereIn('id', $addons->pluck('addon_category_id')->filter()->unique())->get();
        $selected = collect($selectedIds)->map(fn ($id) => (int) $id)->unique();

        foreach ($groups as $group) {
            $ids = $addons->where('addon_category_id', $group->id)->pluck('id');
            $picked = $selected->intersect($ids)->count();
            $min = $group->is_required ? min((int) $group->min_choices, $ids->count()) : 0;
            if ($picked < $min) {
                return translate('messages.addon_group_pick_at_least', ['count' => $min, 'group' => $group->name]);
            }
            if ($group->max_choices && $picked > $group->max_choices) {
                return translate('messages.addon_group_pick_up_to', ['count' => $group->max_choices, 'group' => $group->name]);
            }
        }
        return null;
    }

    /**
     * The rule shown to customers with each add-on, keyed by group id. Only
     * store groups have one; shared admin categories pick freely as before.
     *
     * @return Collection<int, array{id: int, name: string, required: bool, min: int, max: int|null}>
     */
    public static function rulesFor(Collection $addons): Collection
    {
        $ids = $addons->pluck('addon_category_id')->filter()->unique();
        if ($ids->isEmpty()) {
            return collect();
        }
        return AddonCategory::withoutGlobalScope('translate')->whereNotNull('store_id')->whereIn('id', $ids)->get()
            ->mapWithKeys(fn ($g) => [$g->id => [
                'id' => $g->id,
                'name' => $g->name,
                'required' => (bool) $g->is_required,
                'min' => (int) $g->min_choices,
                'max' => $g->max_choices ? (int) $g->max_choices : null,
            ]]);
    }

    private static function format(?AddonCategory $category, Collection $addons, Collection $itemAddons): array
    {
        $ids = $addons->pluck('id')->all();
        $count = count($ids);
        $max = $category?->max_choices ? min((int) $category->max_choices, max(1, $count)) : max(1, $count);
        return [
            'id' => $category?->id,
            'name' => $category?->name ?? translate('messages.addon_group_default'),
            'shared' => !$category?->store_id,
            'is_required' => (bool) ($category->is_required ?? false),
            'min' => (int) ($category->min_choices ?? 0),
            'max' => $max,
            'uses' => $itemAddons->filter(fn ($list) => !empty(array_intersect($list, $ids)))->count(),
            'choices' => $addons->values()->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'price' => (float) $a->price, 'status' => (int) $a->status])->all(),
        ];
    }

    /** @return Collection<int, array<int>> each of the store's items mapped to its add-on ids */
    private static function itemAddons(Store $store): Collection
    {
        return Item::withoutGlobalScopes()->where('store_id', $store->id)->pluck('add_ons', 'id')
            ->map(fn ($raw) => array_map('intval', json_decode($raw ?: '[]', true) ?: []))
            ->filter();
    }

    /** Deletes add-ons and takes their ids off the store's items. */
    private static function deleteAddons(Store $store, Collection $ids): void
    {
        $ids = $ids->map(fn ($id) => (int) $id)->values();
        if ($ids->isEmpty()) {
            return;
        }
        foreach (self::itemAddons($store) as $itemId => $list) {
            $left = array_values(array_diff($list, $ids->all()));
            if (count($left) !== count($list)) {
                Item::withoutGlobalScopes()->where('id', $itemId)->update(['add_ons' => json_encode($left)]);
            }
        }
        $addons = AddOn::withoutGlobalScope(StoreScope::class)->whereIn('id', $ids)->where('store_id', $store->id)->get();
        foreach ($addons as $addon) {
            $addon->translations()->delete();
            $addon->taxVats()->delete();
            $addon->delete();
        }
    }
}
