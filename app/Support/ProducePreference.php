<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The one question produce asks before it goes in the cart.
 *
 * An admin sets `categories.prep_option` on a category or sub-category:
 * `ripeness` for fruit, `use` for vegetables. Every item under it then asks
 * the shopper to pick one answer, stored as a code on the cart line and the
 * order line. The answer never changes price or stock — it is an instruction
 * to whoever picks the order.
 */
final class ProducePreference
{
    /** Question => the answers it accepts, as stored codes. */
    public const OPTIONS = [
        'ripeness' => ['ready_to_eat', 'ripe_later'],
        'use' => ['salad', 'cooking'],
    ];

    /** @var array<int, string>|null category id => prep option */
    private static ?array $categoryOptions = null;

    public static function isOption(?string $option): bool
    {
        return $option !== null && array_key_exists($option, self::OPTIONS);
    }

    /** The code if it is one of the known answers, else null. */
    public static function sanitize(?string $preference): ?string
    {
        if ($preference === null || $preference === '') {
            return null;
        }
        foreach (self::OPTIONS as $answers) {
            if (in_array($preference, $answers, true)) {
                return $preference;
            }
        }
        return null;
    }

    /**
     * The question an item asks, from its own category first, then the
     * chain in `category_ids` (sub-category before main), or null.
     *
     * Categories with an option are loaded once per request, so formatting
     * a list of items costs one query, not one per item.
     */
    public static function forItem($item): ?string
    {
        if (self::$categoryOptions === null) {
            self::$categoryOptions = DB::table('categories')
                ->whereNotNull('prep_option')
                ->pluck('prep_option', 'id')
                ->map(fn ($o) => (string) $o)
                ->all();
        }
        if (empty(self::$categoryOptions)) {
            return null;
        }

        $ids = [];
        if (!empty($item['category_id'])) {
            $ids[] = (int) $item['category_id'];
        }
        $chain = $item['category_ids'] ?? [];
        if (is_string($chain)) {
            $chain = json_decode($chain, true) ?? [];
        }
        // Deepest first: a "Vegetables" sub-category beats its "Fruit & Veg"
        // parent if both carry an option.
        usort($chain, fn ($a, $b) => (int) data_get($b, 'position') <=> (int) data_get($a, 'position'));
        foreach ($chain as $link) {
            $id = (int) data_get($link, 'id');
            if ($id) {
                $ids[] = $id;
            }
        }

        foreach ($ids as $id) {
            $option = self::$categoryOptions[$id] ?? null;
            if (self::isOption($option)) {
                return $option;
            }
        }
        return null;
    }

    /** The answer as words for an admin or vendor screen, or null. */
    public static function label(?string $preference): ?string
    {
        $code = self::sanitize($preference);
        return $code ? translate('messages.preference_' . $code) : null;
    }
}
