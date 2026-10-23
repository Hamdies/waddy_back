<?php

namespace App\Services\AdminPanel;

use App\Models\DeliveryHistory;
use App\Services\AdminPanel\Resources\OrdersResource;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The live-ops screen: courier map, live order feed, zone load and the
 * dispatch board. Stages fold the order lifecycle into the five columns
 * dispatch thinks in.
 */
class LiveOps
{
    public const STAGES = ['placed', 'preparing', 'pickup', 'onway', 'delivered'];

    private const STAGE_OF = [
        'pending' => 'placed',
        'accepted' => 'preparing', 'confirmed' => 'preparing', 'processing' => 'preparing',
        'handover' => 'pickup',
        'picked_up' => 'onway',
        'delivered' => 'delivered',
    ];

    /** Classic status that moves an order into the next column. */
    private const ADVANCE = ['placed' => 'confirmed', 'preparing' => 'handover', 'pickup' => 'picked_up', 'onway' => 'delivered'];

    private static function orders(?int $zone): Builder
    {
        $q = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id');
        if ($admin = Scope::adminZone()) {
            $q->where('s.zone_id', $admin);
        }
        return $zone ? $q->where('s.zone_id', $zone) : $q;
    }

    public static function data(?int $zone, string $range): array
    {
        $now = now();
        $active = self::orders($zone)
            ->leftJoin('users as u', 'u.id', '=', 'o.user_id')
            ->leftJoin('zones as z', 'z.id', '=', 's.zone_id')
            ->where(fn ($w) => $w->whereIn('o.order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
                ->where('o.created_at', '>=', $now->copy()->subDays(2))
                ->orWhere(fn ($d) => $d->where('o.order_status', 'delivered')->where('o.delivered', '>=', $now->copy()->subHours(3))))
            ->orderByDesc('o.id')->limit(80)
            ->get(['o.id', 'o.order_status', 'o.order_amount', 'o.created_at', 'o.estimated_delivery_at', 'o.delivery_man_id', 'o.delivery_address',
                'o.pending', 'o.accepted', 'o.confirmed', 'o.processing', 'o.handover', 'o.picked_up', 'o.delivered', 's.name as store', 's.zone_id',
                's.latitude as s_lat', 's.longitude as s_lng', 'z.name as zone', 'u.f_name', 'u.l_name'])
            ->map(fn ($o) => self::order($o));
        $items = DB::table('order_details')->whereIn('order_id', $active->pluck('id'))->groupBy('order_id')
            ->selectRaw('order_id, sum(quantity) as n')->pluck('n', 'order_id');
        $active = $active->map(fn ($o) => $o + ['items' => (int) ($items[$o['id']] ?? 0)]);
        $delivered = $active->where('stage', 'delivered')->take(6);
        $orders = $active->where('stage', '!=', 'delivered')->concat($delivered)->values();

        $couriers = self::couriers($zone, $orders);

        return [
            'orders' => $orders->all(),
            'couriers' => $couriers,
            'zones' => self::zones($range),
            'kpis' => self::kpis($zone, $range),
            'merchants' => self::merchants($zone, $range),
            'bars' => self::bars($zone, $range),
        ];
    }

    private static function order(object $o): array
    {
        $address = json_decode($o->delivery_address ?? '', true) ?: [];
        $first = $o->f_name ?: strtok((string) ($address['contact_person_name'] ?? ''), ' ');
        $last = $o->l_name ?: (explode(' ', (string) ($address['contact_person_name'] ?? ''), 2)[1] ?? '');
        $stage = self::STAGE_OF[$o->order_status] ?? 'preparing';
        $times = [
            'placed' => $o->pending ?? $o->created_at,
            'preparing' => $o->confirmed ?? $o->accepted ?? $o->processing,
            'pickup' => $o->handover,
            'onway' => $o->picked_up,
            'delivered' => $o->delivered,
        ];
        return [
            'id' => $o->id,
            'merchant' => $o->store,
            'customer' => trim($first . ' ' . ($last ? mb_substr($last, 0, 1) . '.' : '')) ?: '—',
            'zone' => $o->zone,
            'zone_id' => $o->zone_id,
            'stage' => $stage,
            'raw' => $o->order_status,
            'total' => (float) $o->order_amount,
            'placedAt' => Carbon::parse($o->created_at)->toIso8601String(),
            'eta' => $o->estimated_delivery_at && $stage !== 'delivered' ? max(0, (int) round(now()->diffInMinutes(Carbon::parse($o->estimated_delivery_at), false))) : null,
            'courier' => $o->delivery_man_id,
            'times' => array_map(fn ($t) => $t ? Carbon::parse($t)->toIso8601String() : null, $times),
            'storeLat' => (float) $o->s_lat,
            'storeLng' => (float) $o->s_lng,
        ];
    }

    private static function couriers(?int $zone, $orders): array
    {
        $busy = $orders->whereIn('stage', ['placed', 'preparing', 'pickup', 'onway'])->pluck('courier')->filter()->flip();
        return DB::table('delivery_men')
            ->whereNull('store_id')->where('application_status', 'approved')->where('status', 1)
            ->when(Scope::adminZone() ?? $zone, fn ($q, $z) => $q->where('zone_id', $z))
            ->orderBy('f_name')->get(['id', 'f_name', 'l_name', 'active', 'current_orders', 'zone_id'])
            ->map(function ($d) use ($busy) {
                $fix = Cache::remember('rider_loc:' . $d->id, 5, fn () => DeliveryHistory::where('delivery_man_id', $d->id)
                    ->latest('id')->first(['latitude', 'longitude', 'time', 'updated_at'])?->toArray());
                $at = $fix ? ($fix['time'] ?? $fix['updated_at'] ?? null) : null;
                return [
                    'id' => $d->id,
                    'name' => trim($d->f_name . ' ' . $d->l_name),
                    'first' => $d->f_name,
                    'online' => (bool) $d->active,
                    'busy' => $busy->has($d->id) || $d->current_orders > 0,
                    'lat' => isset($fix['latitude']) ? (float) $fix['latitude'] : null,
                    'lng' => isset($fix['longitude']) ? (float) $fix['longitude'] : null,
                    'fixAge' => $at ? max(0, now()->getTimestamp() - Carbon::parse($at)->getTimestamp()) : null,
                ];
            })->all();
    }

    /** Zone polygons (lat/lng rings) with live load: active orders per online courier. */
    private static function zones(string $range): array
    {
        $since = self::since($range);
        $active = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')
            ->whereIn('o.order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
            ->where('o.created_at', '>=', now()->subDays(2))
            ->groupBy('s.zone_id')->selectRaw('s.zone_id, count(*) as n')->pluck('n', 'zone_id');
        $period = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')->where('o.created_at', '>=', $since)
            ->groupBy('s.zone_id')->selectRaw('s.zone_id, count(*) as n, avg(case when o.delivered is not null then timestampdiff(minute, o.created_at, o.delivered) end) as eta')
            ->get()->keyBy('zone_id');
        $couriers = DB::table('delivery_men')->whereNull('store_id')->where('status', 1)->where('active', 1)
            ->groupBy('zone_id')->selectRaw('zone_id, count(*) as n')->pluck('n', 'zone_id');

        return DB::table('zones')->where('status', 1)
            ->when(Scope::adminZone(), fn ($q, $z) => $q->where('id', $z))
            ->get(['id', 'name', 'display_name', DB::raw('ST_AsText(coordinates) as wkt')])
            ->map(function ($z) use ($active, $period, $couriers) {
                $a = (int) ($active[$z->id] ?? 0);
                $c = (int) ($couriers[$z->id] ?? 0);
                $load = $c ? min(1, $a / ($c * 3)) : ($a ? 1 : 0);
                return [
                    'id' => $z->id,
                    'name' => $z->display_name ?: $z->name,
                    'ring' => self::ring($z->wkt),
                    'active' => $a,
                    'orders' => (int) ($period[$z->id]->n ?? 0),
                    'eta' => isset($period[$z->id]) && $period[$z->id]->eta !== null ? (int) round($period[$z->id]->eta) : null,
                    'load' => round($load, 2),
                ];
            })->all();
    }

    /** POLYGON((lng lat, …)) → [[lat, lng], …] */
    private static function ring(?string $wkt): array
    {
        if (!$wkt || !preg_match('/\(\(([^)]*)\)/', $wkt, $m)) {
            return [];
        }
        return array_map(function ($pair) {
            [$lng, $lat] = array_map('floatval', preg_split('/\s+/', trim($pair)));
            return [$lat, $lng];
        }, explode(',', $m[1]));
    }

    private static function since(string $range): Carbon
    {
        return match ($range) {
            '7d' => now()->subDays(6)->startOfDay(),
            '30d' => now()->subDays(29)->startOfDay(),
            default => now()->startOfDay(),
        };
    }

    private static function kpis(?int $zone, string $range): array
    {
        $since = self::since($range);
        // "vs last week": the same window, seven days earlier.
        $prevFrom = $since->copy()->subDays(7);
        $prevTo = now()->subDays(7);
        $live = ['canceled', 'failed', 'refunded'];

        $stats = function (Carbon $from, Carbon $to) use ($zone, $live) {
            $row = self::orders($zone)->whereBetween('o.created_at', [$from, $to])
                ->selectRaw('count(*) as n')
                ->selectRaw('sum(case when o.order_status not in (?, ?, ?) then o.order_amount else 0 end) as gmv', $live)
                ->selectRaw('avg(case when o.delivered is not null then timestampdiff(minute, o.created_at, o.delivered) end) as eta')
                ->selectRaw('sum(o.delivered is not null and o.estimated_delivery_at is not null and o.delivered <= o.estimated_delivery_at) as ontime')
                ->selectRaw('sum(o.delivered is not null and o.estimated_delivery_at is not null) as timed')
                ->first();
            return [
                'orders' => (int) $row->n,
                'gmv' => (float) $row->gmv,
                'eta' => $row->eta === null ? null : (int) round($row->eta),
                'onTime' => $row->timed ? (int) round($row->ontime / $row->timed * 100) : null,
            ];
        };

        return ['now' => $stats($since, now()), 'prev' => $stats($prevFrom, $prevTo)];
    }

    private static function merchants(?int $zone, string $range): array
    {
        $rows = self::orders($zone)->where('o.created_at', '>=', self::since($range))
            ->groupBy('s.id', 's.name', 's.rating', 's.module_id')
            ->selectRaw('s.id, s.name, s.rating, s.module_id, count(*) as n')
            ->orderByDesc('n')->limit(6)->get();
        $modules = DB::table('modules')->pluck('module_name', 'id');
        return $rows->map(fn ($m) => [
            'id' => $m->id, 'name' => $m->name, 'orders' => (int) $m->n,
            'category' => $modules[$m->module_id] ?? '', 'rating' => Resources\MerchantsResource::rating($m->rating),
        ])->all();
    }

    private static function bars(?int $zone, string $range): array
    {
        if ($range === 'today') {
            $rows = self::orders($zone)->where('o.created_at', '>=', now()->startOfDay())
                ->groupByRaw('hour(o.created_at)')->selectRaw('hour(o.created_at) as h, count(*) as n')->pluck('n', 'h');
            $from = min(8, $rows->keys()->min() ?? 8);
            return collect(range($from, (int) now()->format('G')))->map(fn ($h) => ['label' => $h . ':00', 'v' => (int) ($rows[$h] ?? 0)])->all();
        }
        if ($range === '7d') {
            $from = now()->subDays(6)->startOfDay();
            $rows = self::orders($zone)->where('o.created_at', '>=', $from)
                ->groupByRaw('date(o.created_at)')->selectRaw('date(o.created_at) as d, count(*) as n')->pluck('n', 'd');
            return collect(range(0, 6))->map(fn ($i) => $from->copy()->addDays($i))
                ->map(fn ($d) => ['label' => $d->format('D'), 'day' => $d->dayOfWeek, 'v' => (int) ($rows[$d->toDateString()] ?? 0)])->all();
        }
        return collect(range(3, 0))->map(function ($w) use ($zone) {
            $to = now()->subWeeks($w);
            return ['label' => 'Week ' . (4 - $w), 'v' => self::orders($zone)->whereBetween('o.created_at', [$to->copy()->subWeek(), $to])->count()];
        })->all();
    }

    // ------------------------------------------------------------ actions

    public static function advance(int $orderId): array
    {
        $raw = DB::table('orders')->where('id', $orderId)->value('order_status');
        $next = self::ADVANCE[self::STAGE_OF[$raw] ?? ''] ?? null;
        if (!$next) {
            return ['ok' => false, 'message' => translate('messages.you_can_not_change_the_status_of_a_completed_order')];
        }
        return app(OrdersResource::class)->setStatus($orderId, $next);
    }

    /** Assigns the nearest idle courier (by last fix) to the order's store. */
    public static function assign(int $orderId, bool $reassign): array
    {
        $order = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')->where('o.id', $orderId)
            ->first(['o.id', 'o.delivery_man_id', 's.zone_id', 's.latitude', 's.longitude']);
        if (!$order) {
            return ['ok' => false, 'message' => translate('messages.order_not_found')];
        }
        $data = self::data($order->zone_id, 'today');
        $idle = collect($data['couriers'])->where('busy', false)->where('online', true)
            ->when($reassign, fn ($c) => $c->where('id', '!=', $order->delivery_man_id))
            ->sortBy(fn ($c) => $c['lat'] === null ? INF : hypot($c['lat'] - $order->latitude, $c['lng'] - $order->longitude))
            ->first();
        if (!$idle) {
            return ['ok' => false, 'message' => 'No idle couriers nearby'];
        }
        $result = app(OrdersResource::class)->assign($orderId, $idle['id']);
        return $result['ok'] ? ['ok' => true, 'message' => 'Assigned to ' . $idle['name']] : $result;
    }
}
