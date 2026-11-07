<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DeliveryHistory;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderDispatch;
use App\Models\OrderOffer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Smarter dispatch (XS-2). A store order used to be pushed to every rider in
 * the zone at once; whoever tapped first won, wherever they were. Now:
 *
 * - wave 1: the [wave size] riders who can reach the pickup first (live location from
 *   the rider app, under 5 min old) get the alert;
 * - next wave: the next [wave size + 2], when the wave's time is up or everyone
 *   in it has declined;
 * - then the old broadcast to the whole zone, so the worst case is today's
 *   behaviour, a minute or two later.
 * - If the store is already cooking with more than [lead minutes] to go,
 *   wave 1 waits until about [lead minutes] before the food is ready, so
 *   riders do not stand at the counter.
 *
 * Each zone runs one of two modes (zones.dispatch_mode):
 *
 * - waves: everyone in the zone can see and take the order; only who is
 *   alerted first changes.
 * - offers: the order is reserved for the riders in the current wave, who see
 *   a real countdown, until the waves run out and it opens to the zone.
 *
 * An order can never get stuck: a reservation only holds while its dispatch is
 * alive. If the scheduler stops, it lapses by itself after FAIL_OPEN_SECONDS.
 *
 * Business settings: smart_dispatch_status (1), dispatch_wave_size (2),
 * dispatch_wave_seconds (60), dispatch_offer_seconds (30),
 * dispatch_lead_minutes (10).
 */
class DispatchService
{
    /** A phone silent for longer than this would only use up a wave. Idle riders report every minute. */
    private const LOCATION_FRESH_MINUTES = 5;
    private const SAME_TIME_MINUTES = 2;
    /** Handing over at a stop, on top of the ride. */
    private const MINUTES_PER_STOP = 3;
    private const STACK_METERS = 2000;
    private const MAX_WAVES = 2;
    private const FAIL_OPEN_SECONDS = 60;
    /** A tap on the last second, on a slow network, still counts. */
    private const ACCEPT_GRACE_SECONDS = 3;
    private const ACTIVE_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover'];

    /** Tests replace this; everything else pushes through FCM. */
    public static ?\Closure $pusher = null;
    private const LATEST_ORDERS_VERSION = 'dm_latest_orders_version';

    public static function enabled(): bool
    {
        return (string) (Helpers::get_business_settings('smart_dispatch_status') ?? '1') === '1';
    }

    private static function setting(string $key, int $default): int
    {
        return (int) (Helpers::get_business_settings($key) ?? $default);
    }

    /** Called where the order used to be broadcast to the zone. */
    public static function start(Order $order, array $data): void
    {
        // Before the push: the alert looks the order up in this list.
        self::refreshRiderLists();
        if (!self::enabled() || !self::pickupPoint($order)) {
            self::broadcast($order, $data);
            return;
        }
        $nextAt = self::firstWaveAt($order);
        // The unique order_id makes this once per order (status pushes can repeat).
        $created = OrderDispatch::insertOrIgnore([
            'order_id' => $order->id,
            'mode' => $order->zone?->dispatch_mode === 'offers' ? 'offers' : 'waves',
            'status' => 'waiting',
            'next_at' => $nextAt,
            'data' => json_encode($data),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($created && !$nextAt->isFuture()) {
            self::advance($order, OrderDispatch::where('order_id', $order->id)->first());
        }
    }

    /**
     * When wave 1 should go. Now, unless the store is cooking with more than
     * the lead time left, or the order is scheduled for later and not yet in
     * the riders' list (Order::scopeOrderScheduledIn). Pure apart from settings.
     */
    public static function firstWaveAt(Order $order, ?Carbon $now = null): Carbon
    {
        $now ??= now();
        if ($order->scheduled && $order->schedule_at) {
            $listed = Carbon::parse($order->schedule_at)->subMinutes(30);
            if ($listed->isAfter($now)) {
                return $listed;
            }
        }
        $lead = self::setting('dispatch_lead_minutes', 10);
        if ($order->order_status === 'processing' && $order->processing && $order->processing_time) {
            $ready = Carbon::parse($order->processing)->addMinutes((int) $order->processing_time);
            $at = $ready->copy()->subMinutes($lead);
            return $at->isAfter($now) ? $at : $now;
        }
        return $now;
    }

    /** Scheduler (every 15 s): next wave for orders still without a rider. */
    public static function tick(): int
    {
        $sent = 0;
        $due = OrderDispatch::whereIn('status', ['waiting', 'offering'])->where('next_at', '<=', now())->get();
        $orders = Order::withoutGlobalScopes()->whereIn('id', $due->pluck('order_id'))->with(['store', 'zone'])->get()->keyBy('id');
        foreach ($due as $dispatch) {
            $order = $orders[$dispatch->order_id] ?? null;
            if (!self::needsRider($order)) {
                self::close($dispatch);
                continue;
            }
            try {
                self::advance($order, $dispatch);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Dispatch wave failed for order {$dispatch->order_id}: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private static function needsRider(?Order $order): bool
    {
        return $order && !$order->delivery_man_id && in_array($order->order_status, self::ACTIVE_STATUSES, true);
    }

    /** The next wave, or the whole zone when there is no one left to offer it to. */
    private static function advance(Order $order, OrderDispatch $dispatch): void
    {
        $wave = $dispatch->wave + 1;
        $offers = $dispatch->mode === 'offers';
        $seconds = $offers
            ? max(10, min(120, self::setting('dispatch_offer_seconds', 30)))
            : self::setting('dispatch_wave_seconds', 60);
        // Only one caller moves a wave on: a decline can race the scheduler.
        $claimed = OrderDispatch::where('id', $dispatch->id)
            ->where('wave', $dispatch->wave)
            ->whereIn('status', ['waiting', 'offering'])
            ->update(['wave' => $wave, 'next_at' => now()->addSeconds($seconds), 'updated_at' => now()]);
        if (!$claimed) {
            return;
        }
        OrderOffer::where('order_id', $order->id)->where('status', 'offered')->update(['status' => 'expired', 'updated_at' => now()]);

        $size = self::setting('dispatch_wave_size', 2);
        $already = OrderOffer::where('order_id', $order->id)->pluck('delivery_man_id')->all();
        $riders = $wave <= self::MAX_WAVES ? self::candidates($order, $already)->take($wave === 1 ? $size : $size + 2) : collect();
        if ($riders->isEmpty()) {
            OrderDispatch::where('id', $dispatch->id)->update(['status' => 'open', 'opened_at' => now(), 'updated_at' => now()]);
            self::broadcast($order, $dispatch->data);
            return;
        }

        OrderDispatch::where('id', $dispatch->id)->update(['status' => 'offering']);
        // Saved before the push: the alert reads the list, which needs the offer.
        foreach ($riders as $rider) {
            OrderOffer::create([
                'order_id' => $order->id, 'delivery_man_id' => $rider['id'], 'wave' => $wave, 'status' => 'offered',
                'distance_m' => (int) $rider['meters'], 'offered_at' => now(), 'expires_at' => now()->addSeconds($seconds),
            ]);
        }
        $data = $dispatch->data + ['type' => 'order_request'] + ($offers ? ['offer_seconds' => $seconds] : []);
        foreach ($riders as $rider) {
            try {
                self::push($rider['fcm_token'], $data);
            } catch (\Throwable $e) {
                Log::warning("Dispatch push failed for order {$order->id}: " . $e->getMessage());
            }
        }
    }

    private static function push(string $token, array $data): void
    {
        self::$pusher ? (self::$pusher)($token, $data) : Helpers::send_push_notif_to_device($token, $data);
    }

    private static function close(OrderDispatch $dispatch): void
    {
        OrderDispatch::where('id', $dispatch->id)->update(['status' => 'closed', 'updated_at' => now()]);
        OrderOffer::where('order_id', $dispatch->order_id)->where('status', 'offered')->update(['status' => 'expired', 'updated_at' => now()]);
    }

    /**
     * Whether this dispatch keeps its order from the rest of the zone right now.
     * Lapses by itself if nothing moves it on. Pure.
     */
    public static function isReserved(OrderDispatch $dispatch, ?Carbon $now = null): bool
    {
        return $dispatch->mode === 'offers'
            && in_array($dispatch->status, ['waiting', 'offering'], true)
            && $dispatch->next_at->copy()->addSeconds(self::FAIL_OPEN_SECONDS)->isAfter($now ?? now());
    }

    /** accept_order's check. A store's own riders never go through dispatch. */
    public static function mayTake(Order $order, DeliveryMan $dm): bool
    {
        if ($dm->type !== 'zone_wise') {
            return true;
        }
        $dispatch = OrderDispatch::where('order_id', $order->id)->first();
        if (!$dispatch || !self::isReserved($dispatch)) {
            return true;
        }
        return OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => $dm->id, 'status' => 'offered'])
            ->where('expires_at', '>', now()->subSeconds(self::ACCEPT_GRACE_SECONDS))
            ->exists();
    }

    /** The order has a rider, by any route: dispatch for it is over. */
    public static function assigned(Order $order, int $deliveryManId): void
    {
        OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => $deliveryManId, 'status' => 'offered'])
            ->update(['status' => 'accepted', 'responded_at' => now(), 'updated_at' => now()]);
        OrderOffer::where('order_id', $order->id)->where('status', 'offered')->update(['status' => 'taken', 'updated_at' => now()]);
        OrderDispatch::where('order_id', $order->id)->update(['status' => 'closed', 'updated_at' => now()]);
    }

    /** The rider said no: when the whole wave has, the next one goes now. */
    public static function decline(int $orderId, DeliveryMan $dm): void
    {
        $declined = OrderOffer::where(['order_id' => $orderId, 'delivery_man_id' => $dm->id, 'status' => 'offered'])
            ->update(['status' => 'declined', 'responded_at' => now(), 'updated_at' => now()]);
        if (!$declined || OrderOffer::where(['order_id' => $orderId, 'status' => 'offered'])->exists()) {
            return;
        }
        $dispatch = OrderDispatch::where(['order_id' => $orderId, 'status' => 'offering'])->first();
        $order = $dispatch ? Order::withoutGlobalScopes()->with(['store', 'zone'])->find($orderId) : null;
        if (self::needsRider($order)) {
            self::advance($order, $dispatch);
        }
    }

    /**
     * Rescue found an order still without a rider: the whole zone, reservation
     * or not.
     */
    public static function openToZone(Order $order, array $data): void
    {
        $dispatch = OrderDispatch::where('order_id', $order->id)->first();
        if ($dispatch && in_array($dispatch->status, ['waiting', 'offering'], true)) {
            OrderOffer::where('order_id', $order->id)->where('status', 'offered')->update(['status' => 'expired', 'updated_at' => now()]);
            OrderDispatch::where('id', $dispatch->id)->update(['status' => 'open', 'opened_at' => now(), 'updated_at' => now()]);
        }
        self::broadcast($order, $data);
    }

    /** Offers mode only: the first wave is still timed for the food. */
    public static function isWaitingForFood(Order $order): bool
    {
        return OrderDispatch::where(['order_id' => $order->id, 'mode' => 'offers', 'status' => 'waiting'])
            ->where('next_at', '>', now())
            ->exists();
    }

    /**
     * Share of offers this rider took in the last 30 days, or null with too few
     * to mean anything. For their own information: nothing acts on it. An offer
     * another rider took first is not counted against them.
     */
    public static function acceptanceRate(int $deliveryManId): ?int
    {
        $counts = OrderOffer::where('delivery_man_id', $deliveryManId)
            ->whereIn('status', ['accepted', 'declined', 'expired'])
            ->where('offered_at', '>=', now()->subDays(30))
            ->selectRaw("count(*) as total, sum(case when status = 'accepted' then 1 else 0 end) as accepted")
            ->first();
        return (int) $counts->total < 10 ? null : (int) round($counts->accepted / $counts->total * 100);
    }

    /** The store with the most orders in the zone in the last hour, if it is really busy. */
    public static function busyHint(int $zoneId): ?array
    {
        return Cache::remember("dm_busy_hint:$zoneId", 300, function () use ($zoneId) {
            $top = DB::table('orders as o')->join('stores as s', 's.id', '=', 'o.store_id')
                ->where('o.zone_id', $zoneId)
                ->where('o.created_at', '>=', now()->subHour())
                ->groupBy('s.id', 's.name')
                ->selectRaw('s.name, count(*) as n')
                ->orderByDesc('n')
                ->first();
            return $top && $top->n >= 3 ? ['place' => $top->name, 'orders' => (int) $top->n] : null;
        });
    }

    /**
     * One rider's view of the zone's shared latest-orders list: reserved orders
     * they hold no offer for are gone, theirs come first with the time left.
     * Seconds, not a timestamp, so a wrong phone clock does not matter.
     */
    public static function forRider(DeliveryMan $dm, array $payload): array
    {
        if ($dm->type !== 'zone_wise' || empty($payload['orders'])) {
            return $payload;
        }
        $ids = array_column($payload['orders'], 'id');
        $dispatches = OrderDispatch::whereIn('order_id', $ids)->whereIn('status', ['waiting', 'offering'])->get()->keyBy('order_id');
        $offers = OrderOffer::where(['delivery_man_id' => $dm->id, 'status' => 'offered'])
            ->whereIn('order_id', $ids)
            ->where('expires_at', '>', now())
            ->get()->keyBy('order_id');

        $mine = [];
        $others = [];
        foreach ($payload['orders'] as $order) {
            $dispatch = $dispatches[$order['id']] ?? null;
            $offer = $offers[$order['id']] ?? null;
            if ($dispatch && $offer && $dispatch->mode === 'offers') {
                $order['offer_seconds_left'] = max(1, (int) now()->diffInSeconds($offer->expires_at));
                $order['offer_total_seconds'] = (int) $offer->offered_at->diffInSeconds($offer->expires_at);
                $mine[] = $order;
            } elseif (!$dispatch || !self::isReserved($dispatch)) {
                $others[] = $order;
            }
        }
        $hidden = count($payload['orders']) - count($mine) - count($others);
        $payload['orders'] = array_merge($mine, $others);
        $payload['total_size'] = max(0, ($payload['total_size'] ?? 0) - $hidden);
        return $payload;
    }

    /**
     * Online, approved riders of the order's zone (and vehicle), not full,
     * able to take its cash, with a fresh location, soonest at the pickup first.
     */
    public static function candidates(Order $order, array $exclude = [])
    {
        $pickup = self::pickupPoint($order);
        if (!$pickup) {
            return collect();
        }
        $cashCeiling = self::cashCeiling($order);
        $riders = DeliveryMan::query()
            ->where(['active' => 1, 'status' => 1, 'application_status' => 'approved'])
            ->where('zone_id', $order->zone_id)
            ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?: 1))
            ->whereNotNull('fcm_token')->where('fcm_token', '!=', '@')
            ->when($order->dm_vehicle_id, fn ($q) => $q->where('vehicle_id', $order->dm_vehicle_id))
            ->whereNotIn('id', $exclude)
            ->with('wallet')
            ->get(['id', 'fcm_token', 'current_orders'])
            ->filter(fn ($r) => $cashCeiling === null || (float) ($r->wallet?->collected_cash ?? 0) < $cashCeiling);
        if ($riders->isEmpty()) {
            return collect();
        }
        $locations = DeliveryHistory::whereIn('delivery_man_id', $riders->pluck('id'))
            ->where('time', '>=', now()->subMinutes(self::LOCATION_FRESH_MINUTES))
            ->get(['delivery_man_id', 'latitude', 'longitude'])
            ->keyBy('delivery_man_id');
        $jobs = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $riders->where('current_orders', '>', 0)->pluck('id'))
            ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
            ->with('store')
            ->get()->groupBy('delivery_man_id');
        $today = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $riders->pluck('id'))
            ->where('accepted', '>=', now()->startOfDay())
            ->groupBy('delivery_man_id')->selectRaw('delivery_man_id, count(*) as n')->pluck('n', 'delivery_man_id');
        $dropOff = RiderArrivalService::dropOffPoint($order);

        return self::rank($riders->map(function ($r) use ($locations, $pickup, $jobs, $today, $order, $dropOff) {
            $loc = $locations[$r->id] ?? null;
            if (!$loc || !is_numeric($loc->latitude) || !is_numeric($loc->longitude)) {
                return null;
            }
            $at = [(float) $loc->latitude, (float) $loc->longitude];
            $held = $jobs[$r->id] ?? collect();
            $stack = self::canStack($order, $dropOff, $held);
            return [
                'id' => $r->id,
                'fcm_token' => $r->fcm_token,
                'current_orders' => (int) $r->current_orders,
                'meters' => RiderArrivalService::distanceMeters($at[0], $at[1], $pickup[0], $pickup[1]),
                'minutes' => self::minutesToPickup($at, $pickup, $stack ? [] : self::stopsLeft($held)),
                'stack' => $stack,
                'today' => (int) ($today[$r->id] ?? 0),
            ];
        })->filter()->values());
    }

    /**
     * Whether the rider can collect [$order] together with one they already
     * hold: same store, not picked up yet, and the two customers close together.
     */
    private static function canStack(Order $order, ?array $dropOff, $held): bool
    {
        if (!$order->store_id || !$dropOff || $held->isEmpty()) {
            return false;
        }
        foreach ($held as $job) {
            $theirs = RiderArrivalService::dropOffPoint($job);
            if ($job->store_id !== $order->store_id || $job->order_status === 'picked_up' || !$theirs
                || RiderArrivalService::distanceMeters($dropOff[0], $dropOff[1], $theirs[0], $theirs[1]) > self::STACK_METERS) {
                return false;
            }
        }
        return true;
    }

    /** What the rider must still do first: pickups, then drop-offs. */
    private static function stopsLeft($held): array
    {
        $pickups = $held->where('order_status', '!=', 'picked_up')->map(fn ($job) => self::pickupPoint($job));
        $dropOffs = $held->map(fn ($job) => RiderArrivalService::dropOffPoint($job));
        return $pickups->concat($dropOffs)->filter()->values()->all();
    }

    /**
     * How long until the rider could be at the pickup, after the stops they must
     * make first. Pure.
     */
    public static function minutesToPickup(array $at, array $pickup, array $stops = []): float
    {
        $meters = 0.0;
        foreach (array_merge($stops, [$pickup]) as $next) {
            $meters += RiderArrivalService::distanceMeters($at[0], $at[1], $next[0], $next[1]);
            $at = $next;
        }
        return $meters / RiderArrivalService::METERS_PER_MINUTE + count($stops) * self::MINUTES_PER_STOP;
    }

    /**
     * Who can be at the pickup first. Within a couple of minutes of each other:
     * a rider collecting from that store already saves a trip, then whoever has
     * had the fewest orders today, so the shift's work is shared. Pure.
     */
    public static function rank($riders)
    {
        return collect($riders)->sortBy(fn ($r) => [
            (int) floor($r['minutes'] / self::SAME_TIME_MINUTES),
            empty($r['stack']) ? 1 : 0,
            $r['today'] ?? 0,
            $r['meters'],
        ])->values();
    }

    /**
     * Where the rider collects: the store, or a parcel's sender. [lat, lng], or
     * null when unknown.
     */
    public static function pickupPoint(Order $order): ?array
    {
        if ($order->order_type === 'parcel') {
            $sender = $order->delivery_address;
            $sender = is_string($sender) ? json_decode($sender, true) : $sender;
            $lat = $sender['latitude'] ?? null;
            $lng = $sender['longitude'] ?? null;
        } else {
            $lat = $order->store?->latitude;
            $lng = $order->store?->longitude;
        }
        return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
    }

    /**
     * A rider holding this much cash or more cannot take [$order]; null when it
     * involves no cash or the admin has the cap off. The one rule for accepting,
     * assigning and who gets alerted; the rider app reads the same switch through
     * the profile's cash_in_hand_limit.
     */
    public static function cashCeiling(Order $order): ?float
    {
        if (!BusinessSetting::where('key', 'cash_in_hand_overflow_delivery_man')->value('value')) {
            return null;
        }
        $cash = $order->payment_method === 'cash_on_delivery'
            || $order->payments()->where('payment_method', 'cash_on_delivery')->exists();
        if (!$cash) {
            return null;
        }
        return (float) (BusinessSetting::where('key', 'dm_max_cash_in_hand')->value('value') ?? 0) - (float) $order->order_amount;
    }

    /**
     * Riders poll a list shared per zone for 5 s (get_latest_orders). Without
     * this, an alert could ring, read a list cached just before the order
     * existed, and tell the rider it is no longer available.
     */
    public static function refreshRiderLists(): void
    {
        Cache::forever(self::LATEST_ORDERS_VERSION, (string) microtime(true));
    }

    public static function latestOrdersVersion(): string
    {
        return (string) Cache::get(self::LATEST_ORDERS_VERSION, '0');
    }

    /** Today's behaviour: every rider subscribed to the zone (and vehicle) topic. */
    public static function broadcast(Order $order, array $data): void
    {
        if (!$order->zone) {
            return;
        }
        if ($order->dm_vehicle_id) {
            Helpers::send_push_notif_to_topic($data, 'delivery_man_' . $order->zone_id . '_' . $order->dm_vehicle_id, 'order_request');
        }
        if ($order->zone->deliveryman_wise_topic) {
            Helpers::send_push_notif_to_topic($data, $order->zone->deliveryman_wise_topic, 'order_request');
        }
    }
}
