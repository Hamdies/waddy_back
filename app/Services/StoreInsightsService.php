<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Numbers the store app uses to be smarter, computed from the store's own
 * history (SM-1).
 *
 * Kitchen time is handover minus the moment the store started on the order:
 * `processing` when the app set it, else `confirmed` (most orders only have
 * the latter). Orders are grouped by size so a 1-item coffee does not set
 * the time for a 10-item family order.
 */
class StoreInsightsService
{
    private const HISTORY_DAYS = 60;
    private const MIN_SAMPLES = 5;
    private const MIN_MINUTES = 3;
    private const MAX_MINUTES = 120;

    /** Item-count buckets: [label, min items, max items]. */
    private const BUCKETS = [
        ['small', 1, 2],
        ['medium', 3, 5],
        ['large', 6, PHP_INT_MAX],
    ];

    public static function bucketFor(int $items): string
    {
        foreach (self::BUCKETS as [$label, $min, $max]) {
            if ($items >= $min && $items <= $max) {
                return $label;
            }
        }
        return 'small';
    }

    /**
     * Suggested prep minutes for [$order], or null when the store has too
     * little history. Falls back from "orders this size" to "all orders".
     *
     * @return array{minutes:int, samples:int, basis:string}|null
     */
    public static function prepTimeSuggestion(Order $order): ?array
    {
        $items = (int) DB::table('order_details')->where('order_id', $order->id)->sum('quantity');
        $bucket = self::bucketFor(max(1, $items));

        $byBucket = self::kitchenMinutes($order->store_id);
        // A busy store (SM-4) is slower right now than its history says.
        $busy = StoreBusyService::extraMinutes($order->store);
        $sameSize = $byBucket[$bucket] ?? [];
        if (count($sameSize) >= self::MIN_SAMPLES) {
            return ['minutes' => self::median($sameSize) + $busy, 'samples' => count($sameSize), 'basis' => 'similar_size', 'busy_minutes' => $busy];
        }
        $all = array_merge(...array_values($byBucket ?: [[]]));
        if (count($all) >= self::MIN_SAMPLES) {
            return ['minutes' => self::median($all) + $busy, 'samples' => count($all), 'basis' => 'all_orders', 'busy_minutes' => $busy];
        }
        return null;
    }

    /**
     * Kitchen minutes of the store's recent orders, per size bucket. Cached
     * for 30 minutes: it changes slowly and is read on every confirm.
     *
     * @return array<string, int[]>
     */
    public static function kitchenMinutes(int $storeId): array
    {
        return Cache::remember("store_kitchen_minutes_{$storeId}", now()->addMinutes(30), function () use ($storeId) {
            $rows = Order::withoutGlobalScopes()
                ->from('orders')
                ->where('orders.store_id', $storeId)
                ->whereNotNull('orders.handover')
                ->where(fn ($q) => $q->whereNotNull('orders.processing')->orWhereNotNull('orders.confirmed'))
                ->where('orders.created_at', '>=', now()->subDays(self::HISTORY_DAYS))
                ->leftJoin('order_details', 'order_details.order_id', '=', 'orders.id')
                ->groupBy('orders.id', 'orders.handover', 'orders.processing', 'orders.confirmed')
                ->selectRaw('TIMESTAMPDIFF(SECOND, COALESCE(orders.processing, orders.confirmed), orders.handover) AS seconds')
                ->selectRaw('COALESCE(SUM(order_details.quantity), 1) AS items')
                ->toBase()
                ->get();

            $byBucket = [];
            foreach ($rows as $row) {
                $minutes = (int) round($row->seconds / 60);
                // Outside this range the timestamps are wrong (handover tapped
                // late, order confirmed the day before), not the kitchen.
                if ($minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES) {
                    continue;
                }
                $byBucket[self::bucketFor((int) $row->items)][] = $minutes;
            }
            return $byBucket;
        });
    }

    public static function median(array $values): int
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return (int) round($n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2);
    }
}
