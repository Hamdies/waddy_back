<?php

namespace App\Support;

use App\Models\DeliveryMan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SEC-3. `delivery_men.auth_token` stores sha256(token), so a database dump or
 * backup no longer hands out working rider sessions. Lookup stays one indexed
 * equality query.
 */
class RiderToken
{
    public const IDLE_DAYS = 30;
    /** Write `auth_token_used_at` at most this often, not on every request. */
    private const TOUCH_EVERY_MINUTES = 60;

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** A new token for a login; the rider keeps the plain one, we keep the hash. */
    public static function issue(DeliveryMan $dm): string
    {
        $token = Str::random(120);
        self::write($dm, ['auth_token' => self::hash($token), 'auth_token_used_at' => now()]);
        return $token;
    }

    public static function resolve(string $token): ?DeliveryMan
    {
        $dm = DeliveryMan::where('auth_token', self::hash($token))->first();

        if (!$dm) {
            // Issued before SEC-3: stored as is. Hash it in place on first use.
            // A stored hash is 64 hex chars, so it can never be replayed as a
            // plain token through this branch.
            if (strlen($token) === 64 && ctype_xdigit($token)) {
                return null;
            }
            $dm = DeliveryMan::where('auth_token', $token)->first();
            if (!$dm) {
                return null;
            }
            self::write($dm, ['auth_token' => self::hash($token), 'auth_token_used_at' => now()]);
            return $dm;
        }

        if (!self::hasUsedAt()) {
            return $dm;
        }
        $usedAt = $dm->auth_token_used_at ? \Illuminate\Support\Carbon::parse($dm->auth_token_used_at) : null;
        if (self::idleTooLong($usedAt, now())) {
            self::write($dm, ['auth_token' => null]);
            return null;
        }
        if (!$usedAt || $usedAt->lt(now()->subMinutes(self::TOUCH_EVERY_MINUTES))) {
            self::write($dm, ['auth_token_used_at' => now()]);
        }
        return $dm;
    }

    /** No timestamp yet (hashed before the column existed) counts as fresh. */
    public static function idleTooLong($usedAt, $now): bool
    {
        return $usedAt !== null && $usedAt->lt($now->copy()->subDays(self::IDLE_DAYS));
    }

    public static function revoke(DeliveryMan $dm): void
    {
        self::write($dm, ['auth_token' => null]);
    }

    /**
     * Straight to the table: no observers, and `updated_at` keeps meaning a
     * profile change, not "the app made a request".
     */
    private static function write(DeliveryMan $dm, array $values): void
    {
        if (!self::hasUsedAt()) {
            unset($values['auth_token_used_at']);
        }
        DB::table('delivery_men')->where('id', $dm->id)->update($values);
        $dm->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /** Until the 2026_11_10 migration runs. */
    private static function hasUsedAt(): bool
    {
        static $has = null;
        return $has ??= Schema::hasColumn('delivery_men', 'auth_token_used_at');
    }
}
