<?php

namespace App\Services;

use App\Models\EmployeeRole;
use App\Models\Store;
use App\Models\StoreDevice;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Carbon\Carbon;

/**
 * ST-1: Team & roles in the store app. The app speaks in seven permissions;
 * a role still stores the 6amMart module keys the vendor panel and the app's
 * gating read, so each permission stands for a group of them.
 */
class StoreTeamService
{
    /** App permission => module keys. The first key decides whether it is on. */
    public const PERMS = [
        'orders' => ['order', 'pos', 'chat'],
        'prep' => ['prep'],
        'menu' => ['item', 'addon'],
        'reviews' => ['reviews'],
        'reports' => ['report'],
        'payouts' => ['wallet', 'my_wallet', 'bank_info'],
        'admin' => ['store_setup', 'my_shop', 'employee', 'custom_role', 'campaign', 'coupon', 'banner'],
    ];

    /** Starter roles: key => [English name, permissions]. `prep` is the kitchen or the pickers. */
    private const STARTERS = [
        'manager' => ['Manager', ['orders', 'prep', 'menu', 'reviews', 'reports']],
        'cashier' => ['Cashier', ['orders']],
        'kitchen' => ['Kitchen', ['prep']],
        'picker' => ['Picker', ['prep']],
    ];

    public static function isFood(Store $store): bool
    {
        return $store->module?->module_type == 'food';
    }

    /** Adds the starter roles a store is missing (a name already taken is left alone). */
    public static function ensureStarters(Store $store): void
    {
        $roles = EmployeeRole::withoutGlobalScope('translate')->where('store_id', $store->id)->get(['id', 'name', 'system_key']);
        $keys = ['manager', 'cashier', self::isFood($store) ? 'kitchen' : 'picker'];
        foreach ($keys as $key) {
            [$name, $perms] = self::STARTERS[$key];
            if ($roles->contains('system_key', $key) || $roles->contains(fn ($r) => strcasecmp($r->name, $name) == 0)) {
                continue;
            }
            $role = new EmployeeRole();
            $role->name = $name;
            $role->modules = json_encode(self::toModules($perms));
            $role->status = 1;
            $role->store_id = $store->id;
            $role->system_key = $key;
            $role->save();
        }
    }

    /** @param string[] $perms  @param string[] $keep module keys outside the map, kept as they were */
    public static function toModules(array $perms, array $keep = []): array
    {
        $modules = $keep;
        foreach (self::PERMS as $perm => $keys) {
            if (in_array($perm, $perms)) {
                $modules = array_merge($modules, $keys);
            }
        }
        return array_values(array_unique($modules));
    }

    public static function toPerms(array $modules): array
    {
        return array_keys(array_filter(self::PERMS, fn ($keys) => in_array($keys[0], $modules)));
    }

    public static function modulesOf(?EmployeeRole $role): array
    {
        return $role ? (array) json_decode($role->modules ?? '[]', true) : [];
    }

    /** Module keys the app's map does not cover (vendor panel extras). */
    public static function unmapped(array $modules): array
    {
        $mapped = array_merge(...array_values(self::PERMS));
        return array_values(array_diff($modules, $mapped));
    }

    public static function status(VendorEmployee $e): string
    {
        if (isset($e->status) && !$e->status) {
            return 'suspended';
        }
        // Never signed in on any phone yet.
        return $e->auth_token || $e->last_active_at ? 'active' : 'invited';
    }

    public static function overview(Store $store, Vendor $owner, ?VendorEmployee $me): array
    {
        self::ensureStarters($store);
        $roles = EmployeeRole::withoutGlobalScope('translate')->where('store_id', $store->id)->orderBy('id')->get();
        $members = VendorEmployee::where('store_id', $store->id)->orderBy('id')->get();
        $count = $members->countBy('employee_role_id');

        return [
            'me' => $me?->id,
            'module_type' => $store->module?->module_type,
            'owner' => [
                'name' => trim($owner->f_name . ' ' . $owner->l_name),
                'phone' => $owner->phone,
                'last_active_at' => self::iso($owner->last_active_at ?? null),
                'created_at' => self::iso($owner->created_at),
            ],
            'members' => $members->map(fn (VendorEmployee $e) => [
                'id' => $e->id,
                'name' => trim($e->f_name . ' ' . $e->l_name),
                'phone' => $e->phone,
                'role_id' => $e->employee_role_id,
                'status' => self::status($e),
                'last_active_at' => self::iso($e->last_active_at ?? null),
                'created_at' => self::iso($e->created_at),
            ])->values(),
            'roles' => $roles->map(fn (EmployeeRole $r) => [
                'id' => $r->id,
                'key' => $r->system_key,
                'name' => $r->name,
                'perms' => self::toPerms(self::modulesOf($r)),
                'members' => $count[$r->id] ?? 0,
            ])->values(),
        ];
    }

    /** Signs a member out of every phone and stops their pushes. */
    public static function signOut(VendorEmployee $e): void
    {
        $e->auth_token = null;
        $e->firebase_token = null;
        $e->save();
        StoreDevice::where('vendor_employee_id', $e->id)->delete();
    }

    /** Throttled: at most one write per caller per 5 minutes. */
    public static function touch(Vendor|VendorEmployee $who): void
    {
        $last = $who->last_active_at ?? null;
        if ($last && Carbon::parse($last)->diffInSeconds() < 300) {
            return;
        }
        try {
            $who->last_active_at = now();
            $who->saveQuietly();
        } catch (\Throwable $e) {
            // Before the ST-1 migration runs; never fail a request over this.
        }
    }

    private static function iso($time): ?string
    {
        return $time ? Carbon::parse($time)->toIso8601String() : null;
    }
}
