<?php

namespace App\Services;

use App\Models\BusinessSetting;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\OrderTransaction;
use App\Models\Review;
use App\Models\Store;
use App\Support\AppClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The store app's Reports screen: sales by hour or day, the same numbers
 * for the period before, order health, top sellers and where the money went.
 *
 * Periods run up to now on Cairo days. "today" is compared with yesterday up
 * to the same time, "week" (7 days) and "month" (30 days) with the same
 * stretch just before. Sales are delivered orders' amounts, by when the
 * order was placed.
 */
class StoreSalesReportService
{
    public const PERIODS = ['today' => 1, 'week' => 7, 'month' => 30];

    /**
     * [from, to, prevFrom, prevTo] in app-local time.
     *
     * @return Carbon[]
     */
    public static function window(string $period, Carbon $now): array
    {
        $now = $now->copy()->timezone(AppClock::timezone());
        $days = self::PERIODS[$period];
        $from = $now->copy()->startOfDay()->subDays($days - 1);
        return [$from, $now, $from->copy()->subDays($days), $now->copy()->subDays($days)];
    }

    /**
     * Empty buckets for the window: 24 hours for today, else one per day.
     * Keys are local 'Y-m-d H:00' for hours and 'Y-m-d' for days.
     */
    public static function buckets(string $period, Carbon $from): array
    {
        $out = [];
        if ($period === 'today') {
            for ($h = 0; $h < 24; $h++) {
                $out[$from->copy()->addHours($h)->format('Y-m-d H:00')] = ['orders' => 0, 'sales' => 0.0];
            }
            return $out;
        }
        for ($d = 0; $d < self::PERIODS[$period]; $d++) {
            $out[$from->copy()->addDays($d)->format('Y-m-d')] = ['orders' => 0, 'sales' => 0.0];
        }
        return $out;
    }

    public static function bucketKey(string $period, $createdAt): string
    {
        $local = Carbon::parse($createdAt)->timezone(AppClock::timezone());
        return $local->format($period === 'today' ? 'Y-m-d H:00' : 'Y-m-d');
    }

    /** Minutes from "processing" to "handover"; outliers (over 3 h) are left out. */
    public static function prepMinutes(Collection $orders): ?float
    {
        $mins = $orders->filter(fn ($o) => $o->processing && $o->handover)
            ->map(fn ($o) => Carbon::parse($o->processing)->diffInSeconds(Carbon::parse($o->handover), false) / 60)
            ->filter(fn ($m) => $m >= 0 && $m <= 180);
        return $mins->isEmpty() ? null : round($mins->avg(), 1);
    }

    /** Placed orders sorted into how they ended. */
    public static function health(Collection $orders): array
    {
        $delivered = $orders->where('order_status', 'delivered');
        $canceled = $orders->where('order_status', 'canceled');
        return [
            'placed' => $orders->count(),
            'delivered' => $delivered->count(),
            'rejected' => $canceled->where('canceled_by', 'store')->count(),
            'canceled' => $canceled->where('canceled_by', '!=', 'store')->count(),
            'failed' => $orders->where('order_status', 'failed')->count(),
            'refunded' => $orders->where('order_status', 'refunded')->count(),
            'late' => $delivered->filter(fn ($o) => $o->promised_delivery_at && $o->delivered
                && Carbon::parse($o->delivered)->gt(Carbon::parse($o->promised_delivery_at)))->count(),
        ];
    }

    private static function totals(Collection $orders, ?Collection $ratings): array
    {
        $delivered = $orders->where('order_status', 'delivered');
        $sales = (float) $delivered->sum('order_amount');
        return [
            'sales' => round($sales, 2),
            'orders' => $delivered->count(),
            'avg_order_value' => $delivered->count() ? round($sales / $delivered->count(), 2) : 0,
            'prep_minutes' => self::prepMinutes($delivered),
            'rating' => $ratings && $ratings->count() ? round($ratings->avg(), 2) : null,
            'rating_count' => $ratings ? $ratings->count() : 0,
        ];
    }

    public static function build(Store $store, string $period, ?Carbon $now = null): array
    {
        [$from, $to, $prevFrom, $prevTo] = self::window($period, $now ?? now());
        $utc = fn (Carbon $c) => $c->copy()->timezone('UTC');

        $orders = Order::where('store_id', $store->id)
            ->whereBetween('created_at', [$utc($prevFrom), $utc($to)])
            ->get(['id', 'order_amount', 'order_status', 'canceled_by', 'payment_method', 'created_at',
                'processing', 'handover', 'delivered', 'promised_delivery_at']);
        $current = $orders->filter(fn ($o) => $o->created_at->gte($utc($from)));
        $previous = $orders->filter(fn ($o) => $o->created_at->lt($utc($from)) && $o->created_at->lte($utc($prevTo)));

        $buckets = self::buckets($period, $from);
        foreach ($current->where('order_status', 'delivered') as $o) {
            $key = self::bucketKey($period, $o->created_at);
            if (isset($buckets[$key])) {
                $buckets[$key]['orders']++;
                $buckets[$key]['sales'] += (float) $o->order_amount;
            }
        }

        $reviews = Review::where('store_id', $store->id)->whereBetween('created_at', [$utc($prevFrom), $utc($to)])
            ->get(['rating', 'created_at']);
        $rated = fn (Carbon $a, Carbon $b) => $reviews->filter(fn ($r) => $r->created_at->between($utc($a), $utc($b)))->pluck('rating');

        $deliveredIds = $current->where('order_status', 'delivered')->pluck('id');
        $top = OrderDetail::whereIn('order_id', $deliveredIds)
            ->selectRaw('item_id, SUM(quantity) as qty, SUM((price - discount_on_item) * quantity + total_add_on_price) as sales, MAX(item_details) as details')
            ->groupBy('item_id')->orderByDesc('sales')->limit(5)->get()
            ->map(fn ($d) => [
                'item_id' => $d->item_id,
                'name' => json_decode($d->details, true)['name'] ?? '',
                'quantity' => (int) $d->qty,
                'sales' => round((float) $d->sales, 2),
            ])->values();

        $transactions = OrderTransaction::whereIn('order_id', $deliveredIds)->get(['admin_commission', 'store_amount']);
        $refunds = (float) $current->where('order_status', 'refunded')->sum('order_amount');
        $gross = (float) $current->where('order_status', 'delivered')->sum('order_amount') + $refunds;
        $commission = (float) $transactions->sum('admin_commission');
        $net = (float) $transactions->sum('store_amount');

        return [
            'period' => $period,
            'from' => $from->format('Y-m-d H:i'),
            'to' => $to->format('Y-m-d H:i'),
            'buckets' => collect($buckets)->map(fn ($b, $k) => ['start' => $k, 'orders' => $b['orders'], 'sales' => round($b['sales'], 2)])->values(),
            'current' => self::totals($current, $rated($from, $to)),
            'previous' => self::totals($previous, $rated($prevFrom, $prevTo)),
            'health' => self::health($current),
            'top_items' => $top,
            'earnings' => [
                'gross' => round($gross, 2),
                'refunds' => round($refunds, 2),
                'commission' => round($commission, 2),
                'commission_percentage' => (float) ($store->comission ?? BusinessSetting::where('key', 'admin_commission')->value('value') ?? 0),
                // Delivery fees, taxes and discounts, so the rows add up to net.
                'other' => round($gross - $refunds - $commission - $net, 2),
                'cash_collected' => round((float) $current->where('order_status', 'delivered')->where('payment_method', 'cash_on_delivery')->sum('order_amount'), 2),
                'net' => round($net, 2),
            ],
        ];
    }
}
