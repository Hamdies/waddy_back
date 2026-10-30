<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Store;
use App\Models\User;
use App\Support\AppClock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The store app's Insights screen (IN-2): top items and slow movers, when
 * orders come in, and who comes back. Delivered orders only, by when they
 * were placed, on Cairo days; each period is compared with the same stretch
 * just before it.
 *
 * A customer is "returning" in a period when they ordered at least twice in
 * it, or had ordered from the store before it. Guest orders count as orders
 * but not as customers (they have no lasting id).
 */
class StorePerformanceInsightsService
{
    public const PERIODS = ['week' => 7, 'month' => 30, 'quarter' => 90];

    private const TOP_ITEMS = 8;
    private const SLOW_ITEMS = 3;
    private const REGULARS = 5;

    /** [from, to, prevFrom, prevTo] in app-local time. */
    public static function window(string $period, Carbon $now): array
    {
        $now = $now->copy()->timezone(AppClock::timezone());
        $days = self::PERIODS[$period];
        $from = $now->copy()->startOfDay()->subDays($days - 1);
        return [$from, $now, $from->copy()->subDays($days), $now->copy()->subDays($days)];
    }

    /**
     * How many times each weekday (0 = Sunday) falls in [from, to], so busy
     * hours can be averaged per day instead of summed.
     *
     * @return int[]
     */
    public static function dayCounts(Carbon $from, Carbon $to): array
    {
        $out = array_fill(0, 7, 0);
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $out[$d->dayOfWeek]++;
        }
        return $out;
    }

    /**
     * Orders per local weekday (0 = Sunday) and hour.
     *
     * @param  iterable  $createdAt  stored timestamps
     * @return int[][] 7 × 24
     */
    public static function byDayHour(iterable $createdAt): array
    {
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        foreach ($createdAt as $at) {
            $local = Carbon::parse($at, AppClock::storageTimezone())->timezone(AppClock::timezone());
            $grid[$local->dayOfWeek][$local->hour]++;
        }
        return $grid;
    }

    /**
     * Customer numbers for one period.
     *
     * @param  Collection  $orders  the period's delivered, non-guest orders (user_id, order_amount)
     * @param  array<int, bool>  $orderedBefore  user ids that had ordered before the period
     */
    public static function customers(Collection $orders, array $orderedBefore): array
    {
        $returning = 0;
        $returningOrders = 0;
        $returningSales = 0.0;
        $newSales = 0.0;
        $freq = [0, 0, 0, 0]; // 1, 2, 3–4, 5+ orders in the period
        foreach ($orders->groupBy('user_id') as $userId => $rows) {
            $n = $rows->count();
            $sales = (float) $rows->sum('order_amount');
            $freq[$n >= 5 ? 3 : ($n >= 3 ? 2 : $n - 1)]++;
            if ($n >= 2 || isset($orderedBefore[$userId])) {
                $returning++;
                $returningOrders += $n;
                $returningSales += $sales;
            } else {
                $newSales += $sales;
            }
        }
        $total = array_sum($freq);
        return [
            'total' => $total,
            'returning' => $returning,
            'new' => $total - $returning,
            'returning_orders' => $returningOrders,
            'returning_sales' => round($returningSales, 2),
            'new_sales' => round($newSales, 2),
            'rate' => $total ? round($returning / $total, 4) : null,
            'frequency' => $freq,
        ];
    }

    /**
     * The items most often in the same order as each item, best first.
     *
     * @param  Collection  $lines  order_id, item_id
     * @return array<int, int[]> item id => up to [$limit] item ids
     */
    public static function orderedWith(Collection $lines, int $limit = 2): array
    {
        $pairs = [];
        foreach ($lines->groupBy('order_id') as $rows) {
            $ids = $rows->pluck('item_id')->unique()->values()->all();
            foreach ($ids as $a) {
                foreach ($ids as $b) {
                    if ($a !== $b) {
                        $pairs[$a][$b] = ($pairs[$a][$b] ?? 0) + 1;
                    }
                }
            }
        }
        $out = [];
        foreach ($pairs as $a => $counts) {
            arsort($counts);
            $out[$a] = array_slice(array_keys($counts), 0, $limit);
        }
        return $out;
    }

    public static function build(Store $store, string $period, ?Carbon $now = null): array
    {
        return Cache::remember("store_insights_{$store->id}_{$period}", now()->addMinutes(10),
            fn () => self::compute($store, $period, $now ?? now()));
    }

    private static function compute(Store $store, string $period, Carbon $now): array
    {
        [$from, $to, $prevFrom, $prevTo] = self::window($period, $now);
        $utc = fn (Carbon $c) => $c->copy()->timezone(AppClock::storageTimezone());

        $orders = Order::withoutGlobalScopes()->where('store_id', $store->id)->where('order_status', 'delivered')
            ->whereBetween('created_at', [$utc($prevFrom), $utc($to)])
            ->get(['id', 'user_id', 'is_guest', 'order_amount', 'created_at']);
        [$current, $previous] = StoreSalesReportService::split($orders, $from, $prevTo);

        // Items -------------------------------------------------------------
        $lines = OrderDetail::whereIn('order_id', $orders->pluck('id'))
            ->get(['order_id', 'item_id', 'quantity', 'price', 'discount_on_item', 'total_add_on_price'])
            ->filter(fn ($d) => $d->item_id);
        $currentIds = $current->pluck('id')->flip();
        [$curLines, $prevLines] = $lines->partition(fn ($d) => isset($currentIds[$d->order_id]));
        $sum = fn (Collection $rows) => $rows->groupBy('item_id')->map(fn ($r) => [
            'quantity' => (int) $r->sum('quantity'),
            'orders' => $r->pluck('order_id')->unique()->count(),
            'sales' => round((float) $r->sum(fn ($d) => ($d->price - $d->discount_on_item) * $d->quantity + $d->total_add_on_price), 2),
        ]);
        $cur = $sum($curLines);
        $prev = $sum($prevLines);
        $with = self::orderedWith($curLines);

        $storeItems = Item::withoutGlobalScopes()->where('store_id', $store->id)->get(['id', 'name', 'category_id', 'status', 'created_at']);
        $top = $cur->sortByDesc('sales')->take(self::TOP_ITEMS);
        $known = $storeItems->keyBy('id');
        $categories = Category::withoutGlobalScopes()->whereIn('id', $storeItems->pluck('category_id')->filter()->unique())->get(['id', 'name'])->keyBy('id');
        $name = fn ($id) => $known[$id]->name ?? null;

        $items = $top->map(fn ($c, $id) => [
            'item_id' => (int) $id,
            'name' => $name($id) ?? '',
            'category' => isset($known[$id]) ? ($categories[$known[$id]->category_id]->name ?? null) : null,
            'quantity' => $c['quantity'],
            'orders' => $c['orders'],
            'sales' => $c['sales'],
            'previous_quantity' => $prev[$id]['quantity'] ?? 0,
            'previous_sales' => $prev[$id]['sales'] ?? 0,
            'ordered_with' => collect($with[$id] ?? [])->map($name)->filter()->values(),
        ])->filter(fn ($i) => $i['name'] !== '')->values();

        // On the menu for the whole period, fewest orders first.
        $slow = $storeItems->filter(fn ($i) => (int) $i->status === 1 && $i->created_at && Carbon::parse($i->created_at)->lt($utc($from)))
            ->map(fn ($i) => [
                'item_id' => $i->id,
                'name' => $i->name,
                'orders' => $cur[$i->id]['orders'] ?? 0,
                'previous_orders' => $prev[$i->id]['orders'] ?? 0,
            ])
            ->sortBy([['orders', 'asc'], ['previous_orders', 'desc']])
            ->take(self::SLOW_ITEMS)->values();
        // A short menu where everything sells is not a slow-mover problem.
        if ($storeItems->count() <= self::SLOW_ITEMS * 2) {
            $slow = collect();
        }

        // Customers ---------------------------------------------------------
        $named = $orders->filter(fn ($o) => $o->user_id && !$o->is_guest);
        $first = $named->isEmpty() ? collect() : Order::withoutGlobalScopes()->where('store_id', $store->id)
            ->where('order_status', 'delivered')->where('is_guest', 0)
            ->whereIn('user_id', $named->pluck('user_id')->unique())
            ->where('created_at', '<', $utc($from))
            ->groupBy('user_id')->selectRaw('user_id, MIN(created_at) AS first_at')->toBase()->get();
        $before = fn (Carbon $at) => $first->filter(fn ($r) => Carbon::parse($r->first_at, AppClock::storageTimezone())->lt($utc($at)))
            ->pluck('user_id')->flip()->map(fn () => true)->all();
        $curNamed = $current->filter(fn ($o) => $o->user_id && !$o->is_guest);
        $prevNamed = $previous->filter(fn ($o) => $o->user_id && !$o->is_guest);

        $regularIds = $curNamed->groupBy('user_id')->sortByDesc(fn ($r) => $r->count())->take(self::REGULARS);
        $users = User::whereIn('id', $regularIds->keys())->get(['id', 'f_name', 'l_name'])->keyBy('id');
        $regulars = $regularIds->map(function ($rows, $userId) use ($users, $curLines, $name) {
            $ids = $rows->pluck('id')->flip();
            $fav = $curLines->filter(fn ($d) => isset($ids[$d->order_id]))->groupBy('item_id')
                ->map(fn ($r) => $r->sum('quantity'))->sortDesc()->keys()->first();
            $u = $users[$userId] ?? null;
            $last = Carbon::parse($rows->max('created_at'), AppClock::storageTimezone())->timezone(AppClock::timezone());
            return [
                // First name and initial: enough to recognise, nothing more.
                'name' => $u ? trim($u->f_name . ' ' . mb_substr((string) $u->l_name, 0, 1) . ($u->l_name ? '.' : '')) : '',
                'orders' => $rows->count(),
                'sales' => round((float) $rows->sum('order_amount'), 2),
                'favourite' => $fav ? $name($fav) : null,
                'last_order_at' => $last->format('Y-m-d H:i'),
            ];
        })->filter(fn ($r) => $r['name'] !== '')->values();

        $customers = self::customers($curNamed, $before($from));
        $customers['previous_rate'] = self::customers($prevNamed, $before($prevFrom))['rate'];

        return [
            'period' => $period,
            'from' => $from->format('Y-m-d H:i'),
            'to' => $to->format('Y-m-d H:i'),
            'orders' => $current->count(),
            'guest_orders' => $current->count() - $curNamed->count(),
            'sales' => round((float) $current->sum('order_amount'), 2),
            'items' => $items,
            'slow_items' => $slow,
            'hours' => [
                'by_day_hour' => self::byDayHour($current->pluck('created_at')),
                'day_count' => self::dayCounts($from, $to),
            ],
            'customers' => $customers,
            'regulars' => $regulars,
        ];
    }
}
