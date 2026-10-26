<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;

/**
 * XS-7: cash on delivery is a promise to be there and pay. A customer whose
 * cash orders keep ending badly after work started (store cooked, rider
 * rode) pays online or from the wallet instead.
 *
 * Bad = cash order in the last 90 days that failed at delivery, was
 * canceled after pickup, or was canceled by the customer after the store
 * confirmed it. Business setting cod_block_after_bad_orders (default 2,
 * 0 = off).
 */
class CodRiskService
{
    private const DAYS = 90;

    public static function threshold(): int
    {
        return (int) (Helpers::get_business_settings('cod_block_after_bad_orders') ?? 2);
    }

    public static function badCashOrders(int $userId): int
    {
        return (int) Order::withoutGlobalScopes()
            ->where('user_id', $userId)->where('is_guest', 0)
            ->where('payment_method', 'cash_on_delivery')
            ->where('created_at', '>=', now()->subDays(self::DAYS))
            ->where(function ($q) {
                $q->where('order_status', 'failed')
                    ->orWhere(fn ($c) => $c->where('order_status', 'canceled')->whereNotNull('picked_up'))
                    ->orWhere(fn ($c) => $c->where('order_status', 'canceled')->where('canceled_by', 'customer')->whereNotNull('confirmed'));
            })
            ->count();
    }

    public static function isBlocked(?int $userId): bool
    {
        $threshold = self::threshold();
        if (!$userId || $threshold <= 0) {
            return false;
        }
        return Cache::remember("cod_risk_{$userId}", now()->addMinutes(10), fn () => self::badCashOrders($userId) >= $threshold);
    }
}
