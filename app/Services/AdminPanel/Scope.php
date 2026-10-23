<?php

namespace App\Services\AdminPanel;

use Illuminate\Support\Facades\DB;

/**
 * The admin panel's view of the platform: the four product modules
 * (Food, Grocery, Pets, Waddy Spots) and the admin's zone restriction.
 *
 * Pets runs as a grocery module with `variant = pets`, and Spots is the
 * `places` module, so a panel module is a set of `modules` rows, not one id.
 */
class Scope
{
    public const MODULES = ['food', 'grocery', 'pets', 'spots'];

    private static ?array $map = null;

    /** module id => panel code (food|grocery|pets|spots|other) */
    public static function map(): array
    {
        if (self::$map === null) {
            self::$map = DB::table('modules')->get(['id', 'module_type', 'variant'])
                ->mapWithKeys(fn ($m) => [$m->id => self::classify($m->module_type, $m->variant)])
                ->all();
        }
        return self::$map;
    }

    public static function classify(?string $type, ?string $variant): string
    {
        if ($variant === 'pets') {
            return 'pets';
        }
        return match ($type) {
            'food' => 'food',
            'grocery' => 'grocery',
            'places' => 'spots',
            default => 'other',
        };
    }

    public static function code(?int $moduleId): string
    {
        return self::map()[$moduleId] ?? 'other';
    }

    /** Module ids for a panel module; null means "every module". */
    public static function ids(string $mod): ?array
    {
        if (!in_array($mod, self::MODULES, true)) {
            return null;
        }
        return array_keys(array_filter(self::map(), fn ($code) => $code === $mod));
    }

    public static function normalize(?string $mod): string
    {
        return in_array($mod, self::MODULES, true) ? $mod : 'all';
    }

    /** Zone an admin is restricted to (same rule as App\Scopes\ZoneScope). */
    public static function adminZone(): ?int
    {
        $admin = auth('admin')->user();
        return $admin && $admin->role_id != 1 && $admin->zone_id ? (int) $admin->zone_id : null;
    }

    public static function timezone(): string
    {
        return config('placestovisit.timezone', 'Africa/Cairo');
    }
}
