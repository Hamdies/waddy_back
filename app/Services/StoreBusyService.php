<?php

namespace App\Services;

use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Busy mode (SM-4). Real kitchens get slammed; the store can say so in one
 * tap instead of closing and forgetting to reopen:
 * - extra: +10 / +20 minutes on every new order's prep time for an hour
 *   (customer ETAs and the prep-time suggestion include it);
 * - pause: closed to new orders for 30 minutes, then reopened by the
 *   scheduler (`stores:end-busy`, every minute).
 */
class StoreBusyService
{
    public const EXTRA_OPTIONS = [10, 20];
    public const EXTRA_FOR_MINUTES = 60;
    public const PAUSE_OPTIONS = [15, 30, 60];

    public static function extraMinutes(?Store $store): int
    {
        if (!$store || !$store->busy_extra_minutes || !$store->busy_until) {
            return 0;
        }
        return Carbon::parse($store->busy_until)->isFuture() ? (int) $store->busy_extra_minutes : 0;
    }

    public static function setExtra(Store $store, int $minutes): void
    {
        $store->busy_extra_minutes = $minutes;
        $store->busy_until = now()->addMinutes(self::EXTRA_FOR_MINUTES);
        $store->save();
    }

    public static function pause(Store $store, int $minutes, ?string $exceptToken = null): void
    {
        $store->paused_until = now()->addMinutes($minutes);
        $store->active = 0;
        $store->save();
        StorePushService::storeStatusChanged($store, $exceptToken);
    }

    /** Back to normal now: no extra minutes, and reopened if it was paused. */
    public static function off(Store $store, ?string $exceptToken = null): void
    {
        $wasPaused = $store->paused_until !== null;
        $store->busy_extra_minutes = null;
        $store->busy_until = null;
        $store->paused_until = null;
        if ($wasPaused) {
            $store->active = 1;
        }
        $store->save();
        if ($wasPaused) {
            StorePushService::storeStatusChanged($store, $exceptToken);
        }
    }

    /** What the app shows. */
    public static function state(Store $store): array
    {
        $paused = $store->paused_until && Carbon::parse($store->paused_until)->isFuture();
        $extra = self::extraMinutes($store);
        return [
            'mode' => $paused ? 'pause' : ($extra > 0 ? 'extra' : 'off'),
            'extra_minutes' => $extra,
            'until' => $paused ? Carbon::parse($store->paused_until)->toIso8601String()
                : ($extra > 0 ? Carbon::parse($store->busy_until)->toIso8601String() : null),
        ];
    }

    /**
     * Scheduler: reopen stores whose pause ended and forget expired extra
     * minutes. A store the owner reopened by hand is left alone.
     */
    public static function endExpired(): int
    {
        $count = 0;
        foreach (Store::withoutGlobalScopes()->whereNotNull('paused_until')->where('paused_until', '<=', now())->get() as $store) {
            $store->paused_until = null;
            if (!$store->active) {
                $store->active = 1;
                $count++;
            }
            $store->save();
            StorePushService::storeStatusChanged($store);
        }
        Store::withoutGlobalScopes()->whereNotNull('busy_until')->where('busy_until', '<=', now())
            ->update(['busy_extra_minutes' => null, 'busy_until' => null]);
        return $count;
    }
}
