<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SM-7: one push at the end of the day with the numbers an owner checks:
 * orders, earnings, cancellations and average prep time, against yesterday.
 * Sent only to stores that had orders today.
 */
class StoreDailySummaryService
{
    /** @return array{orders:int, canceled:int, earnings:float, prep_minutes:?int} */
    public static function dayStats(Store $store, Carbon $day): array
    {
        // The day is a Cairo day; timestamps are stored in the app timezone.
        [$from, $to] = self::dayBounds($day);

        $orders = Order::withoutGlobalScopes()->from('orders')
            ->where('store_id', $store->id)
            ->whereBetween('created_at', [$from, $to])
            ->where(fn ($q) => $q->where('order_type', '!=', 'pos')->orWhereNull('order_type'))
            ->toBase()
            ->selectRaw("COUNT(CASE WHEN order_status IN ('delivered', 'refunded') THEN 1 END) AS done")
            ->selectRaw("COUNT(CASE WHEN order_status IN ('canceled', 'failed') THEN 1 END) AS canceled")
            ->selectRaw("AVG(CASE WHEN handover IS NOT NULL AND COALESCE(processing, confirmed) IS NOT NULL
                THEN TIMESTAMPDIFF(SECOND, COALESCE(processing, confirmed), handover) END) AS prep_seconds")
            ->first();

        $earnings = (float) DB::table('order_transactions')
            ->where('vendor_id', $store->vendor_id)
            ->whereBetween('created_at', [$from, $to])
            ->sum('store_amount');

        return [
            'orders' => (int) ($orders->done ?? 0),
            'canceled' => (int) ($orders->canceled ?? 0),
            'earnings' => round($earnings, 2),
            'prep_minutes' => $orders->prep_seconds !== null ? (int) round($orders->prep_seconds / 60) : null,
        ];
    }

    /** Start and end of [$day] (in its own timezone), in the app timezone. */
    public static function dayBounds(Carbon $day): array
    {
        $appTz = config('app.timezone');
        return [$day->copy()->startOfDay()->setTimezone($appTz), $day->copy()->endOfDay()->setTimezone($appTz)];
    }

    /** The push text in [$lang] ('en' or 'ar'). Pure apart from the currency setting. */
    public static function message(array $today, array $yesterday, string $lang): array
    {
        $ar = $lang === 'ar';
        $money = Helpers::format_currency($today['earnings']);
        $diff = $today['orders'] - $yesterday['orders'];
        $trend = $diff === 0 ? ($ar ? 'مثل أمس' : 'same as yesterday')
            : ($ar ? ($diff > 0 ? "+$diff عن أمس" : "$diff عن أمس") : ($diff > 0 ? "+$diff vs yesterday" : "$diff vs yesterday"));

        $parts = [$ar ? "{$today['orders']} طلب ($trend)" : "{$today['orders']} orders ($trend)", $money];
        if ($today['canceled'] > 0) {
            $parts[] = $ar ? "{$today['canceled']} ملغي" : "{$today['canceled']} canceled";
        }
        if ($today['prep_minutes'] !== null) {
            $parts[] = $ar ? "متوسط التجهيز {$today['prep_minutes']} د" : "avg prep {$today['prep_minutes']} min";
        }
        return [
            'title' => $ar ? 'ملخص اليوم' : "Today's summary",
            'description' => implode(' · ', $parts),
        ];
    }

    /** Sends the summary to every store with orders today. Returns how many stores. */
    public static function sendAll(?int $onlyStoreId = null, bool $dryRun = false, ?callable $report = null): int
    {
        $tz = config('placestovisit.timezone', 'Africa/Cairo');
        $today = now($tz);
        $yesterday = $today->copy()->subDay();
        $sent = 0;

        $storeIds = Order::withoutGlobalScopes()
            ->whereBetween('created_at', self::dayBounds($today))
            ->when($onlyStoreId, fn ($q) => $q->where('store_id', $onlyStoreId))
            ->distinct()->pluck('store_id');

        foreach (Store::withoutGlobalScopes()->whereIn('id', $storeIds)->get() as $store) {
            $stats = self::dayStats($store, $today);
            if ($stats['orders'] + $stats['canceled'] === 0) {
                continue;
            }
            $phones = StorePushService::tokensByLanguage($store);
            if (!$phones) {
                continue;
            }
            $before = self::dayStats($store, $yesterday);
            foreach ($phones as $lang => $tokens) {
                $data = self::message($stats, $before, $lang) + ['order_id' => '', 'image' => '', 'type' => 'daily_summary'];
                if ($report) {
                    $report($store, $lang, count($tokens), $data['description']);
                }
                if (!$dryRun) {
                    foreach ($tokens as $token) {
                        Helpers::send_push_notif_to_device($token, $data);
                    }
                }
            }
            $sent++;
        }
        return $sent;
    }
}
