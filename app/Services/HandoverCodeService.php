<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * XS-5: the rider confirms pickup with a 4-digit code the store app shows.
 * Ends wrong-order pickups and "rider says picked up, store says no".
 *
 * The code is derived from the order id and the app key, so nothing is
 * stored. Off until business setting handover_code_status = 1: rider app
 * builds before this do not send a code, and would be blocked.
 */
class HandoverCodeService
{
    private const MAX_ATTEMPTS = 5;
    private const LOCK_MINUTES = 15;

    public static function enabled(): bool
    {
        return (string) (Helpers::get_business_settings('handover_code_status') ?? '0') === '1';
    }

    public static function code(int $orderId): string
    {
        $hash = hash_hmac('sha256', "handover:$orderId", (string) config('app.key'));
        return str_pad((string) (hexdec(substr($hash, 0, 8)) % 10000), 4, '0', STR_PAD_LEFT);
    }

    /** Store orders delivered by a rider; parcels and take-away have no store handover. */
    public static function appliesTo(Order $order): bool
    {
        return $order->order_type === 'delivery' && $order->store_id !== null;
    }

    /**
     * Null when the pickup may go ahead, else an error [code, message, http status].
     */
    public static function check(Order $order, ?string $given): ?array
    {
        if (!self::enabled() || !self::appliesTo($order)) {
            return null;
        }
        $key = "handover_code_attempts_{$order->id}";
        if (Cache::get($key, 0) >= self::MAX_ATTEMPTS) {
            return ['handover_code', translate('messages.handover_code_too_many_attempts'), 429];
        }
        if ($given !== null && hash_equals(self::code($order->id), trim($given))) {
            Cache::forget($key);
            return null;
        }
        Cache::put($key, Cache::get($key, 0) + 1, now()->addMinutes(self::LOCK_MINUTES));
        return ['handover_code', translate('messages.handover_code_wrong'), 406];
    }
}
