<?php

namespace App\Services\AdminPanel;

use App\CentralLogics\Helpers;
use App\Services\AdminPanel\Resources\Spots;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;
use Modules\PlacesToVisit\Services\LeaderboardService;
use Modules\PlacesToVisit\Services\RaceClock;

/** Page registry, nav badges, the two home dashboards, ⌘K search and the Spots jobs. */
class Panel
{
    public const RESOURCES = [
        'orders' => Resources\OrdersResource::class,
        'couriers' => Resources\CouriersResource::class,
        'zones' => Resources\ZonesResource::class,
        'tickets' => Resources\TicketsResource::class,
        'merchants' => Resources\MerchantsResource::class,
        'products' => Resources\ProductsResource::class,
        'reviews' => Resources\ReviewsResource::class,
        'promotions' => Resources\PromotionsResource::class,
        'customers' => Resources\CustomersResource::class,
        'admins' => Resources\AdminsResource::class,
        'payouts' => Resources\PayoutsResource::class,
        'spotPlaces' => Spots\PlacesResource::class,
        'spotVotes' => Spots\VotesResource::class,
        'spotReviews' => Spots\ReviewsResource::class,
        'spotSubmissions' => Spots\SubmissionsResource::class,
        'spotWinners' => Spots\WinnersResource::class,
        'spotPrizes' => Spots\PrizesResource::class,
        'spotVoters' => Spots\VotersResource::class,
    ];

    public static function resource(string $key): ?Resource
    {
        $class = self::RESOURCES[$key] ?? null;
        return $class ? app($class) : null;
    }

    public static function allowed(string $key): bool
    {
        if ($key === 'settings') {
            return Helpers::module_permission_check('settings') || Helpers::module_permission_check('business_settings');
        }
        $resource = self::resource($key);
        return $resource && ($resource->permission === null || Helpers::module_permission_check($resource->permission));
    }

    /** Config for every page this admin may open. */
    public static function config(): array
    {
        $out = [];
        foreach (array_keys(self::RESOURCES) as $key) {
            if (self::allowed($key)) {
                $out[$key] = self::resource($key)->config();
            }
        }
        return $out;
    }

    // ------------------------------------------------------------- badges

    public static function counts(string $mod): array
    {
        $c = [];
        $count = fn ($key, $code, $m = null) => self::allowed($key) ? self::resource($key)->statusCount($m ?? $mod, $code) : 0;

        if ($mod !== 'spots') {
            $c['orders'] = $count('orders', 'preparing') + $count('orders', 'onway');
            $c['merchants'] = $count('merchants', 'pending');
            $c['products'] = $count('products', 'out');
            $c['payouts'] = $count('payouts', 'pending');
            $c['refunds'] = $count('orders', 'refundreq');
            $c['latestOrder'] = (int) self::orders($mod)->max('o.id');
        }
        $c['tickets'] = $count('tickets', 'open', $mod === 'spots' ? 'all' : $mod);
        $c['spotVotes'] = (int) DB::table('place_votes')->where('period', RaceClock::period())->where('is_flagged', 0)->count();
        $c['spotReviews'] = $count('spotReviews', 'flagged', 'all');
        $c['spotSubmissions'] = $count('spotSubmissions', 'pending', 'all');
        $c['spotPrizes'] = $count('spotPrizes', 'active', 'all');
        return $c;
    }

    // --------------------------------------------------------------- home

    /** Orders in a module, within the admin's zone. */
    private static function orders(string $mod): Builder
    {
        $q = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id');
        if ($zone = Scope::adminZone()) {
            $q->where('s.zone_id', $zone);
        }
        $ids = Scope::ids($mod);
        return $ids === null ? $q : $q->whereIn('o.module_id', $ids ?: [0]);
    }

    /** Per-day values for the last $days days, oldest first, zero-filled. */
    private static function daily(Builder $q, string $dateCol, string $agg, int $days, Carbon $from): array
    {
        $rows = $q->where($dateCol, '>=', $from)
            ->selectRaw("date($dateCol) as d, $agg as v")->groupByRaw("date($dateCol)")->pluck('v', 'd');
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'v' => (float) ($rows[$d] ?? 0)];
        }
        return $out;
    }

    private static function pct(float $now, float $before): ?float
    {
        return $before > 0 ? round(($now - $before) / $before * 100) : null;
    }

    public static function home(string $mod): array
    {
        $today = now()->startOfDay();
        $from30 = now()->subDays(29)->startOfDay();
        $from60 = now()->subDays(59)->startOfDay();
        $live = ['canceled', 'failed', 'refunded'];
        $o = fn () => self::orders($mod);

        $daily = self::daily($o(), 'o.created_at', 'count(*)', 30, $from30);
        $revDaily = self::daily($o()->whereNotIn('o.order_status', $live), 'o.created_at', 'sum(o.order_amount)', 30, $from30);
        $orders30 = array_sum(array_column($daily, 'v'));
        $ordersPrev = $o()->whereBetween('o.created_at', [$from60, $from30])->count();
        $rev30 = array_sum(array_column($revDaily, 'v'));
        $revPrev = (float) $o()->whereNotIn('o.order_status', $live)->whereBetween('o.created_at', [$from60, $from30])->sum('o.order_amount');

        $users = function () use ($mod) {
            $q = DB::table('users as u');
            $ids = Scope::ids($mod);
            if ($ids !== null) {
                $q->whereExists(fn ($e) => $e->selectRaw(1)->from('orders')->whereColumn('orders.user_id', 'u.id')->whereIn('orders.module_id', $ids ?: [0]));
            }
            return $q;
        };
        $custDaily = self::daily($users(), 'u.created_at', 'count(*)', 30, $from30);
        $cust30 = array_sum(array_column($custDaily, 'v'));
        $custPrev = $users()->whereBetween('u.created_at', [$from60, $from30])->count();

        $stores = function () use ($mod) {
            $q = DB::table('stores as s')->join('vendors as v', 'v.id', '=', 's.vendor_id');
            if ($zone = Scope::adminZone()) {
                $q->where('s.zone_id', $zone);
            }
            $ids = Scope::ids($mod);
            return $ids === null ? $q : $q->whereIn('s.module_id', $ids ?: [0]);
        };
        $activeStores = $stores()->where('s.status', 1)->where('v.status', 1)->count();
        $storeDaily = self::daily($stores(), 's.created_at', 'count(*)', 30, $from30);
        $newStores = $stores()->where('s.created_at', '>=', now()->startOfMonth())->count();
        $runTotal = $activeStores - array_sum(array_column($storeDaily, 'v'));
        $storeSpark = array_map(function ($d) use (&$runTotal) {
            $runTotal += $d['v'];
            return ['date' => $d['date'], 'v' => $runTotal];
        }, $storeDaily);

        $couriers = DB::table('delivery_men')->whereNull('store_id')->where('application_status', 'approved')->where('status', 1)
            ->when(Scope::adminZone(), fn ($q, $z) => $q->where('zone_id', $z));
        $avgDelivery = $o()->whereNotNull('o.delivered')->where('o.delivered', '>=', now()->subDays(7))
            ->selectRaw('avg(timestampdiff(minute, o.created_at, o.delivered)) as m')->value('m');

        $byModule = [];
        foreach (['food', 'grocery', 'pets'] as $m) {
            $byModule[$m] = [
                'orders30' => self::orders($m)->where('o.created_at', '>=', $from30)->count(),
                'active' => self::orders($m)->whereNotIn('o.order_status', ['delivered', 'canceled', 'failed', 'refunded', 'refund_requested', 'refund_request_canceled'])->count(),
            ];
        }

        $categories = $mod === 'all' ? [] : DB::table('order_details as od')
            ->join('orders as o', 'o.id', '=', 'od.order_id')
            ->join('stores as s', 's.id', '=', 'o.store_id')
            ->leftJoin('categories as c', 'c.id', '=', 'od.category_id')
            ->when(Scope::adminZone(), fn ($q, $z) => $q->where('s.zone_id', $z))
            ->whereIn('o.module_id', Scope::ids($mod) ?: [0])
            ->where('o.created_at', '>=', $from30)
            ->selectRaw("coalesce(c.name, 'Other') as name, count(distinct od.order_id) as v")
            ->groupBy('name')->orderByDesc('v')->get()->map(fn ($r) => ['name' => $r->name, 'v' => (int) $r->v])->all();
        if (count($categories) > 5) {
            $rest = array_sum(array_column(array_slice($categories, 4), 'v'));
            $categories = array_merge(array_slice($categories, 0, 4), [['name' => 'Other', 'v' => $rest]]);
        }

        $orders = Panel::resource('orders');
        $counts = self::counts($mod);
        $week = RaceClock::lockTime();

        return [
            'hero' => [
                'ordersToday' => $o()->where('o.created_at', '>=', $today)->count(),
                'revenueToday' => (float) $o()->whereNotIn('o.order_status', $live)->where('o.created_at', '>=', $today)->sum('o.order_amount'),
                'avgDelivery' => $avgDelivery === null ? null : (int) round($avgDelivery),
                'couriersOnline' => (clone $couriers)->where('active', 1)->count(),
                'couriersTotal' => $couriers->count(),
            ],
            'kpis' => [
                'revenue' => ['value' => $rev30, 'change' => self::pct($rev30, $revPrev), 'spark' => array_column($revDaily, 'v')],
                'orders' => ['value' => $orders30, 'change' => self::pct($orders30, $ordersPrev), 'spark' => array_column($daily, 'v')],
                'customers' => ['value' => $cust30, 'change' => self::pct($cust30, $custPrev), 'spark' => array_column($custDaily, 'v')],
                'merchants' => ['value' => $activeStores, 'new' => $newStores, 'spark' => array_column($storeSpark, 'v')],
            ],
            'daily' => $daily,
            'dailyChange' => self::pct($orders30, $ordersPrev),
            'byModule' => $byModule,
            'categories' => $categories,
            'spots' => [
                'votes' => $counts['spotVotes'],
                'places' => self::spotsPlaces()->where('p.is_active', 1)->count(),
                'weekDone' => round(1 - min(1, max(0, now()->diffInSeconds($week, false)) / (7 * 86400)), 3),
            ],
            'attention' => [
                ['key' => 'pending', 'value' => $counts['merchants'] ?? 0],
                ['key' => 'tickets', 'value' => $counts['tickets'] ?? 0],
                ['key' => 'refunds', 'value' => $counts['refunds'] ?? 0],
                ['key' => 'payouts', 'value' => $counts['payouts'] ?? 0],
                ['key' => 'stock', 'value' => $counts['products'] ?? 0],
            ],
            'latest' => self::allowed('orders') ? $orders->recent($mod, 6) : [],
        ];
    }

    // --------------------------------------------------------- spots home

    private static function spotsPlaces(): Builder
    {
        return DB::table('places as p')->join('place_categories as pc', 'pc.id', '=', 'p.category_id')->where('pc.surface', SurfaceScope::SPOTS);
    }

    /** Active places with this week's (unflagged) votes, ranked competition style. */
    private static function board(string $period): array
    {
        $rows = self::spotsPlaces()
            ->leftJoin('place_translations as pt', fn ($j) => $j->on('pt.place_id', '=', 'p.id')->where('pt.locale', 'en'))
            ->leftJoin('place_zones as pz', 'pz.id', '=', 'p.zone_id')
            ->where('p.is_active', 1)
            ->select('p.id', 'pt.title', 'pz.name as zone', 'pc.name as category')
            ->selectRaw('(select count(*) from place_votes v where v.place_id = p.id and v.period = ? and v.is_flagged = 0) as votes', [$period])
            ->orderByDesc('votes')->orderBy('p.id')->get();
        $votes = $rows->pluck('votes')->all();
        return $rows->map(function ($r) use ($votes) {
            $rank = 1 + count(array_filter($votes, fn ($v) => $v > $r->votes));
            $tied = count(array_filter($votes, fn ($v) => $v == $r->votes)) > 1;
            return ['id' => $r->id, 'name' => $r->title ?: '#' . $r->id, 'zone' => $r->zone, 'category' => $r->category, 'votes' => (int) $r->votes, 'rank' => $rank, 'tied' => $tied];
        })->all();
    }

    public static function spotsHome(): array
    {
        $period = RaceClock::period();
        $lock = RaceClock::lockTime();
        $board = self::board($period);
        $prev = collect(self::board(RaceClock::lastClosedPeriod()))->filter(fn ($p) => $p['votes'] > 0)->keyBy('id');
        $top = array_values(array_filter($board, fn ($p) => $p['votes'] > 0));

        $winner = DB::table('place_winners as w')
            ->leftJoin('place_translations as pt', fn ($j) => $j->on('pt.place_id', '=', 'w.place_id')->where('pt.locale', 'en'))
            ->leftJoin('places as p', 'p.id', '=', 'w.place_id')
            ->leftJoin('place_zones as pz', 'pz.id', '=', 'p.zone_id')
            ->whereNull('w.zone_id')->whereRaw("w.period regexp '^[0-9]{4}-W[0-9]{2}$'")
            ->orderByDesc('w.period')->first(['w.id', 'w.period', 'w.place_id', 'pt.title', 'pz.name as zone']);
        $winners = $winner ? DB::table('place_prizes as z')->leftJoin('users as u', 'u.id', '=', 'z.user_id')
            ->where('z.place_winner_id', $winner->id)->orderBy('z.id')
            ->get(['z.id', 'z.code', 'z.status', 'z.expires_at', 'u.f_name', 'u.l_name'])
            ->map(fn ($z) => ['id' => $z->id, 'name' => trim($z->f_name . ' ' . $z->l_name) ?: '—', 'code' => $z->code,
                'status' => $z->status === 'active' && $z->expires_at && Carbon::parse($z->expires_at)->isPast() ? 'expired' : $z->status])->all() : [];

        $prizes = DB::table('place_prizes')->selectRaw("sum(status = 'active' and (expires_at is null or expires_at >= now())) as a, sum(status = 'redeemed') as r, sum(status = 'expired' or (status = 'active' and expires_at < now())) as e")->first();

        $ratedVotes = DB::table('place_votes')->where('period', $period)->whereNotNull('rating')->count();
        $malformed = DB::table('place_winners')->whereRaw("period not regexp '^[0-9]{4}-W[0-9]{2}$'")->pluck('period')->unique()->values()->all();

        return [
            'period' => $period,
            'lock' => $lock->toIso8601String(),
            'tz' => RaceClock::timezone(),
            'rules' => [
                'winners' => (int) config('placestovisit.prize.winners_per_week'),
                'cooldown' => (int) config('placestovisit.prize.winner_cooldown_days'),
                'validity' => (int) config('placestovisit.prize.validity_days'),
                'enabled' => (bool) config('placestovisit.prize.enabled'),
            ],
            'finalHoursGap' => (int) config('placestovisit.race.final_hours_gap', 3),
            'totalVotes' => array_sum(array_column($board, 'votes')),
            'places' => count($board),
            'gap' => count($top) > 1 ? $top[0]['votes'] - $top[1]['votes'] : ($top[0]['votes'] ?? 0),
            'board' => array_map(fn ($p) => $p + ['prevRank' => $prev[$p['id']]['rank'] ?? null], array_slice($top ?: $board, 0, 8)),
            'tieTop' => collect(array_slice($top, 0, 3))->contains('tied', true),
            'draw' => $winner ? [
                'id' => $winner->id, 'period' => $winner->period, 'place' => $winner->title, 'zone' => $winner->zone,
                'pool' => (int) DB::table('place_draw_entrants')->where('place_winner_id', $winner->id)->max('total_entrants'),
                'winners' => $winners,
            ] : null,
            'vouchers' => ['active' => (int) $prizes->a, 'redeemed' => (int) $prizes->r, 'expired' => (int) $prizes->e],
            'queue' => [
                'reviews' => Panel::resource('spotReviews')->statusCount('all', 'flagged'),
                'submissions' => Panel::resource('spotSubmissions')->statusCount('all', 'pending'),
                'votes' => DB::table('place_votes')->where('is_flagged', 1)->count(),
            ],
            'jobs' => self::jobs(),
            'health' => [
                'tieBreakEmpty' => $ratedVotes === 0,
                'malformed' => $malformed,
            ],
        ];
    }

    // --------------------------------------------------------------- jobs

    public const JOBS = [
        'placestovisit:close-week' => ['when' => 'Fri 00:10', 'desc' => 'jobClose'],
        'placestovisit:final-hours-push' => ['when' => 'Thu 21:00', 'desc' => 'jobFinal'],
        'placestovisit:expire-prizes' => ['when' => 'hourly', 'desc' => 'jobExpire'],
        'leaderboard:clear-cache' => ['when' => 'onDemand', 'desc' => 'jobCache'],
    ];

    public static function jobs(): array
    {
        $lock = RaceClock::lockTime();
        $next = [
            'placestovisit:close-week' => $lock->copy()->addMinutes(10),
            'placestovisit:final-hours-push' => $lock->copy()->subHours(3),
            'placestovisit:expire-prizes' => now()->addHour()->startOfHour(),
            'leaderboard:clear-cache' => null,
        ];
        return collect(self::JOBS)->map(fn ($job, $name) => $job + ['name' => $name, 'next' => $next[$name]?->toIso8601String()])->values()->all();
    }

    /** @return array{ok: bool, message: string} */
    public static function runJob(string $name): array
    {
        if (!isset(self::JOBS[$name])) {
            return ['ok' => false, 'message' => translate('messages.not_found')];
        }
        if ($name === 'leaderboard:clear-cache') {
            app(LeaderboardService::class)->clearCache();
            app(LeaderboardService::class)->clearRecentWinnersCache();
            return ['ok' => true, 'message' => 'Leaderboard caches cleared'];
        }
        if ($name === 'placestovisit:close-week') {
            $last = RaceClock::lastClosedPeriod();
            if (DB::table('place_winners')->where('period', $last)->exists()) {
                return ['ok' => false, 'message' => "Week {$last} is already closed and week " . RaceClock::period() . ' is still running. close-week only closes finished weeks.'];
            }
        }
        Artisan::call($name);
        $output = trim(Artisan::output());
        return ['ok' => true, 'message' => Str::limit($output ?: 'Done', 200)];
    }

    // ------------------------------------------------------------- search

    public static function search(string $term, string $mod): array
    {
        $out = [];
        foreach (array_keys(self::RESOURCES) as $key) {
            if (!self::allowed($key)) {
                continue;
            }
            $spot = str_starts_with($key, 'spot');
            if ($mod === 'spots' && !$spot && !in_array($key, ['customers', 'tickets'], true)) {
                continue;
            }
            $resource = self::resource($key);
            $config = $resource->config();
            $modKey = $config['modKey'] ?? null;
            foreach ($resource->lookup($term, $spot || $mod === 'spots' ? 'all' : $mod) as $row) {
                $out[] = [
                    'sec' => $key, 'id' => $row['id'],
                    'title' => (string) ($row[$config['titleKey']] ?? $row['id']),
                    'sub' => (string) ($row[$config['subKey']] ?? ''),
                    'module' => $modKey ? ($row[$modKey] ?? null) : null,
                ];
            }
        }
        return $out;
    }
}
