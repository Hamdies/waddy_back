<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DeliveryHistory;
use App\Models\DeliveryMan;
use App\Models\DMVehicle;
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
 * Who is "best" is DispatchScorer's one number in minutes: when the food can
 * leave the store, how much rider time the order uses (a rider passing the
 * store only pays the detour), standing at the counter, fair shares, GPS
 * doubt. Speeds are the zone's own, learned by DeliveryTimeModel.
 *
 * Each zone runs one of three modes (zones.dispatch_mode):
 *
 * - waves: everyone in the zone can see and take the order; only who is
 *   alerted first changes.
 * - offers: the order is reserved for the riders in the current wave, who see
 *   a real countdown, until the waves run out and it opens to the zone.
 * - auto: the best rider gets the order straight away, with [decline seconds]
 *   to hand it back; then the next best, up to AUTO_MAX_TRIES riders, then the
 *   zone. Orders due together are matched together (DispatchScorer::assign),
 *   so two orders never fight over one rider while another stands free.
 *
 * An order can never get stuck: a reservation only holds while its dispatch is
 * alive. If the scheduler stops, it lapses by itself after FAIL_OPEN_SECONDS.
 *
 * Business settings: smart_dispatch_status (1), dispatch_wave_size (2),
 * dispatch_wave_seconds (60), dispatch_offer_seconds (30),
 * dispatch_lead_minutes (10), dispatch_auto_decline_seconds (30),
 * dispatch_stack_detour_minutes (8).
 */
class DispatchService
{
    /** A phone silent for longer than this would only use up a wave. Idle riders report every minute. */
    private const LOCATION_FRESH_MINUTES = 5;
    /** Auto-assign gives the order to someone, so their phone must be alive now (idle riders report every minute). */
    private const AUTO_FRESH_MINUTES = 2;
    /** Riders auto-assign tries before the order opens to the zone. */
    private const AUTO_MAX_TRIES = 3;
    /** Riders scored per order: enough to match several orders at once. */
    private const SHORTLIST = 8;
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
            'mode' => in_array($order->zone?->dispatch_mode, ['offers', 'auto'], true) ? $order->zone->dispatch_mode : 'waves',
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
        self::settleAssignments();
        $due = OrderDispatch::whereIn('status', ['waiting', 'offering'])->where('next_at', '<=', now())->get();
        $orders = Order::withoutGlobalScopes()->whereIn('id', $due->pluck('order_id'))->with(['store', 'zone'])->get()->keyBy('id');
        $auto = [];
        foreach ($due as $dispatch) {
            $order = $orders[$dispatch->order_id] ?? null;
            if (!self::needsRider($order)) {
                self::close($dispatch);
                continue;
            }
            if ($dispatch->mode === 'auto') {
                $auto[$order->zone_id][] = [$order, $dispatch];
                continue;
            }
            try {
                self::advance($order, $dispatch);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Dispatch wave failed for order {$dispatch->order_id}: " . $e->getMessage());
            }
        }
        foreach ($auto as $items) {
            try {
                $sent += self::autoAssign($items);
            } catch (\Throwable $e) {
                Log::warning('Auto-assign failed: ' . $e->getMessage());
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
        if ($dispatch->mode === 'auto') {
            self::autoAssign([[$order, $dispatch]]);
            return;
        }
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
            self::open($order, $dispatch);
            return;
        }

        OrderDispatch::where('id', $dispatch->id)->update(['status' => 'offering']);
        // Saved before the push: the alert reads the list, which needs the offer.
        foreach ($riders as $rider) {
            OrderOffer::create([
                'order_id' => $order->id, 'delivery_man_id' => $rider['id'], 'wave' => $wave, 'status' => 'offered',
                'distance_m' => (int) $rider['meters'], 'score' => $rider['cost'], 'pickup_minutes' => $rider['minutes'],
                'offered_at' => now(), 'expires_at' => now()->addSeconds($seconds),
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

    /** No one left to offer it to: the whole zone, as before smart dispatch. */
    private static function open(Order $order, OrderDispatch $dispatch): void
    {
        OrderDispatch::where('id', $dispatch->id)->update(['status' => 'open', 'opened_at' => now(), 'updated_at' => now()]);
        self::broadcast($order, $dispatch->data);
    }

    /**
     * Auto mode: score each order's riders, match orders to riders together,
     * and give each order to its rider. An order whose riders all went to
     * other orders this round waits for the next tick (15 s).
     *
     * @param array<int, array{0: Order, 1: OrderDispatch}> $items
     */
    private static function autoAssign(array $items): int
    {
        $costs = [];
        $picked = [];
        $byOrder = [];
        foreach ($items as [$order, $dispatch]) {
            $byOrder[$order->id] = [$order, $dispatch];
            $tried = OrderOffer::where('order_id', $order->id)->pluck('delivery_man_id')->all();
            $ranked = $dispatch->wave < self::AUTO_MAX_TRIES
                ? self::candidates($order, $tried, self::AUTO_FRESH_MINUTES)->take(self::SHORTLIST)
                : collect();
            if ($ranked->isEmpty()) {
                self::open($order, $dispatch);
                continue;
            }
            foreach ($ranked as $rider) {
                $costs[$order->id][$rider['id']] = $rider['cost'];
                $picked[$order->id][$rider['id']] = $rider;
            }
        }
        $assigned = 0;
        foreach (DispatchScorer::assign($costs) as $orderId => $riderId) {
            [$order, $dispatch] = $byOrder[$orderId];
            if (self::assignTo($order, $dispatch, $picked[$orderId][$riderId])) {
                $assigned++;
            }
        }
        return $assigned;
    }

    /** Gives the order to the rider, under the same rules as accept_order. False when they can no longer take it. */
    private static function assignTo(Order $order, OrderDispatch $dispatch, array $rider): bool
    {
        $seconds = max(10, min(120, self::setting('dispatch_auto_decline_seconds', 30)));
        try {
            [$order, $dm] = DB::transaction(function () use ($order, $dispatch, $rider, $seconds) {
                // Only one caller moves a dispatch on: a decline can race the scheduler.
                $claimed = OrderDispatch::where('id', $dispatch->id)->where('wave', $dispatch->wave)
                    ->whereIn('status', ['waiting', 'offering'])
                    ->update(['status' => 'assigned', 'wave' => $dispatch->wave + 1, 'next_at' => now()->addSeconds($seconds), 'updated_at' => now()]);
                $locked = $claimed ? Order::withoutGlobalScopes()->whereKey($order->id)->whereNull('delivery_man_id')
                    ->whereIn('order_status', self::ACTIVE_STATUSES)->lockForUpdate()->first() : null;
                $dm = $locked ? DeliveryMan::withoutGlobalScopes()->whereKey($rider['id'])
                    ->where(['active' => 1, 'status' => 1])->with('wallet')->lockForUpdate()->first() : null;
                $ceiling = $locked ? self::cashCeiling($locked) : null;
                if (!$dm || $dm->current_orders >= (int) (config('dm_maximum_orders') ?: 1)
                    || ($ceiling !== null && (float) ($dm->wallet?->collected_cash ?? 0) >= $ceiling)) {
                    throw new \RuntimeException('skip'); // rolls the claim back
                }

                $prev = $locked->order_status;
                if ($locked->order_type === 'parcel' && $prev === 'confirmed') {
                    $locked->order_status = 'handover';
                    $locked->handover = now();
                    $locked->processing = now();
                } elseif (in_array($prev, ['pending', 'confirmed'], true)) {
                    $locked->order_status = 'accepted';
                }
                $locked->delivery_man_id = $dm->id;
                $locked->accepted = now();
                $locked->save();
                $dm->current_orders = $dm->current_orders + 1;
                $dm->assigned_order_count = $dm->assigned_order_count + 1;
                $dm->save();

                OrderOffer::where('order_id', $locked->id)->where('status', 'offered')->update(['status' => 'expired', 'updated_at' => now()]);
                OrderOffer::create([
                    'order_id' => $locked->id, 'delivery_man_id' => $dm->id, 'wave' => $dispatch->wave + 1, 'status' => 'assigned',
                    'distance_m' => (int) $rider['meters'], 'score' => $rider['cost'], 'pickup_minutes' => $rider['minutes'],
                    'prev_status' => $prev, 'offered_at' => now(), 'expires_at' => now()->addSeconds($seconds),
                ]);
                return [$locked, $dm];
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() !== 'skip') {
                throw $e;
            }
            return false;
        }

        self::refreshRiderLists();
        $data = [
            'title' => translate('Order_Notification'),
            'description' => translate('messages.you_are_assigned_to_a_order'),
            'order_id' => $order->id,
            'order_type' => $order->order_type === 'parcel' ? 'parcel_order' : $order->order_type,
            'image' => '',
            'type' => 'assign',
            // The rider app shows a countdown with "Decline" while this runs.
            'decline_seconds' => $seconds,
        ];
        try {
            self::push((string) $dm->fcm_token, $data);
            DB::table('user_notifications')->insert([
                'data' => json_encode($data), 'delivery_man_id' => $dm->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning("Assign push failed for order {$order->id}: " . $e->getMessage());
        }
        self::notifyCustomerAccepted($order, $dm);
        return true;
    }

    /** The decline window is over: the rider keeps the order. */
    private static function settleAssignments(): void
    {
        $over = OrderDispatch::where('status', 'assigned')
            ->where('next_at', '<=', now()->subSeconds(self::ACCEPT_GRACE_SECONDS))->pluck('order_id');
        if ($over->isEmpty()) {
            return;
        }
        OrderOffer::whereIn('order_id', $over)->where('status', 'assigned')->update(['status' => 'accepted', 'updated_at' => now()]);
        OrderDispatch::whereIn('order_id', $over)->where('status', 'assigned')->update(['status' => 'closed', 'updated_at' => now()]);
    }

    /** An auto-assigned rider handed the order back in time: undo, then the next best. */
    private static function handBack(OrderOffer $offer, DeliveryMan $dm): void
    {
        $undone = DB::transaction(function () use ($offer, $dm) {
            $order = Order::withoutGlobalScopes()->whereKey($offer->order_id)->where('delivery_man_id', $dm->id)
                ->whereIn('order_status', self::ACTIVE_STATUSES)->lockForUpdate()->first();
            if (!$order) {
                return false;
            }
            if ($order->order_status === 'accepted' || ($order->order_type === 'parcel' && $offer->prev_status === 'confirmed')) {
                $order->order_status = $offer->prev_status ?: $order->order_status;
                if ($order->order_type === 'parcel' && $offer->prev_status === 'confirmed') {
                    $order->handover = null;
                    $order->processing = null;
                }
            }
            $order->delivery_man_id = null;
            $order->accepted = null;
            $order->save();
            $rider = DeliveryMan::withoutGlobalScopes()->whereKey($dm->id)->lockForUpdate()->first();
            $rider->current_orders = max(0, $rider->current_orders - 1);
            $rider->save();
            OrderOffer::whereKey($offer->id)->update(['status' => 'declined', 'responded_at' => now(), 'updated_at' => now()]);
            OrderDispatch::where(['order_id' => $order->id, 'status' => 'assigned'])->update(['status' => 'offering', 'next_at' => now(), 'updated_at' => now()]);
            return true;
        });
        if (!$undone) {
            return;
        }
        self::refreshRiderLists();
        $dispatch = OrderDispatch::where(['order_id' => $offer->order_id, 'status' => 'offering'])->first();
        $order = $dispatch ? Order::withoutGlobalScopes()->with(['store', 'zone'])->find($offer->order_id) : null;
        if (self::needsRider($order)) {
            self::advance($order, $dispatch);
        }
    }

    /**
     * Auto-assigned orders this rider may still hand back: order id =>
     * offer_seconds_left, offer_total_seconds (the fields the rider app's
     * offer countdown already reads).
     */
    public static function handBackWindows(int $deliveryManId, array $orderIds): array
    {
        if (!$orderIds) {
            return [];
        }
        return OrderOffer::where(['delivery_man_id' => $deliveryManId, 'status' => 'assigned'])
            ->whereIn('order_id', $orderIds)->where('expires_at', '>', now())
            ->get()
            ->mapWithKeys(fn ($o) => [$o->order_id => [
                'offer_seconds_left' => max(1, (int) now()->diffInSeconds($o->expires_at)),
                'offer_total_seconds' => (int) $o->offered_at->diffInSeconds($o->expires_at),
            ]])->all();
    }

    /** "A rider took your order", to the customer. Shared by accept_order and auto-assign. */
    public static function notifyCustomerAccepted(Order $order, DeliveryMan $dm): void
    {
        try {
            $fcm_token = $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;
            $value = Helpers::order_status_update_message('accepted', $order->module?->module_type);
            $value = Helpers::text_variable_data_format(
                value: $value,
                store_name: $order->store?->name,
                order_id: $order->id,
                user_name: "{$order?->customer?->f_name} {$order?->customer?->l_name}",
                delivery_man_name: "{$dm->f_name} {$dm->l_name}"
            );
            if ($value && $fcm_token && Helpers::getNotificationStatusData('customer', 'customer_order_notification', 'push_notification_status')) {
                Helpers::send_push_notif_to_device($fcm_token, [
                    'title' => translate('Order_Notification'),
                    'description' => $value,
                    'order_id' => $order['id'],
                    'image' => '',
                    'type' => 'order_status',
                ]);
            }
        } catch (\Throwable $e) {
            // The order is taken either way.
            info('Order acceptance notification failed: ' . $e->getMessage());
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
        return in_array($dispatch->mode, ['offers', 'auto'], true)
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
        OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => $deliveryManId])->whereIn('status', ['offered', 'assigned'])
            ->update(['status' => 'accepted', 'responded_at' => now(), 'updated_at' => now()]);
        OrderOffer::where('order_id', $order->id)->whereIn('status', ['offered', 'assigned'])->update(['status' => 'taken', 'updated_at' => now()]);
        OrderDispatch::where('order_id', $order->id)->update(['status' => 'closed', 'updated_at' => now()]);
    }

    /** The rider said no: when the whole wave has, the next one goes now. */
    public static function decline(int $orderId, DeliveryMan $dm): void
    {
        $assigned = OrderOffer::where(['order_id' => $orderId, 'delivery_man_id' => $dm->id, 'status' => 'assigned'])
            ->where('expires_at', '>', now()->subSeconds(self::ACCEPT_GRACE_SECONDS))->first();
        if ($assigned) {
            self::handBack($assigned, $dm);
            return;
        }
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
        return OrderDispatch::where(['order_id' => $order->id, 'status' => 'waiting'])->whereIn('mode', ['offers', 'auto'])
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
        return self::acceptanceRates([$deliveryManId])[$deliveryManId] ?? null;
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
     * able to take its cash, with a fresh location, best first by
     * DispatchScorer. Each: id, fcm_token, current_orders, meters (straight
     * line to the pickup), minutes (until they can be there), stack, today,
     * cost.
     */
    public static function candidates(Order $order, array $exclude = [], ?int $freshMinutes = null)
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
            ->get(['id', 'fcm_token', 'current_orders', 'vehicle_id'])
            ->filter(fn ($r) => $cashCeiling === null || (float) ($r->wallet?->collected_cash ?? 0) < $cashCeiling);
        if ($riders->isEmpty()) {
            return collect();
        }
        $ids = $riders->pluck('id');
        $locations = DeliveryHistory::whereIn('delivery_man_id', $ids)
            ->where('time', '>=', now()->subMinutes($freshMinutes ?? self::LOCATION_FRESH_MINUTES))
            ->get()
            ->keyBy('delivery_man_id');
        $jobs = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $riders->where('current_orders', '>', 0)->pluck('id'))
            ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
            ->with('store')
            ->orderBy('accepted')
            ->get()->groupBy('delivery_man_id');
        $today = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $ids)
            ->where('accepted', '>=', now()->startOfDay())
            ->groupBy('delivery_man_id')->selectRaw('delivery_man_id, count(*) as n')->pluck('n', 'delivery_man_id');
        $lastDrop = Order::withoutGlobalScopes()
            ->whereIn('delivery_man_id', $ids)
            ->where('delivered', '>=', now()->subHours(3))
            ->groupBy('delivery_man_id')->selectRaw('delivery_man_id, max(delivered) as at')->pluck('at', 'delivery_man_id');
        $rates = self::acceptanceRates($ids->all());
        // Only applies when checkout picked no vehicle; otherwise dm_vehicle_id already matched the distance.
        $vehicleIds = $order->dm_vehicle_id ? collect() : $riders->pluck('vehicle_id')->filter()->unique();
        $ranges = $vehicleIds->isEmpty() ? collect() : DMVehicle::whereIn('id', $vehicleIds)->pluck('maximum_coverage_area', 'id');

        $dropOff = RiderArrivalService::dropOffPoint($order);
        $facts = ['pickup' => $pickup, 'drop' => $dropOff, 'ready_in' => self::readyIn($order)];
        $opts = [
            'meters_per_minute' => self::metersPerMinute($order->zone_id),
            'stack_detour_max' => self::setting('dispatch_stack_detour_minutes', DispatchScorer::STACK_DETOUR_MAX),
            'offer_minutes' => self::setting('dispatch_offer_seconds', 30) / 60,
        ];
        $counts = $ids->map(fn ($id) => (int) ($today[$id] ?? 0))->all();
        $median = $counts ? DeliveryTimeModel::median($counts) : 0;

        $scored = $riders->map(function ($r) use ($locations, $jobs, $today, $median, $lastDrop, $rates, $ranges, $pickup, $facts, $opts) {
            $loc = $locations[$r->id] ?? null;
            if (!$loc || !is_numeric($loc->latitude) || !is_numeric($loc->longitude)) {
                return null;
            }
            $at = [(float) $loc->latitude, (float) $loc->longitude];
            $last = $lastDrop[$r->id] ?? null;
            $score = DispatchScorer::score([
                'at' => $at,
                'heading' => $loc->heading ?? null,
                'speed' => $loc->speed ?? null,
                'accuracy' => $loc->accuracy ?? null,
                'age_seconds' => $loc->time ? max(0, Carbon::parse($loc->time)->diffInSeconds(now(), false)) : 0,
                'stops' => self::heldStops($jobs[$r->id] ?? collect()),
                'today' => (int) ($today[$r->id] ?? 0),
                'today_median' => $median,
                // Nothing delivered in 3 h: long idle (the bonus caps at 30 min anyway).
                'idle_minutes' => $last ? Carbon::parse($last)->diffInMinutes(now()) : 30,
                'accept_rate' => isset($rates[$r->id]) ? $rates[$r->id] / 100 : null,
                'max_km' => (float) ($ranges[$r->vehicle_id] ?? 0),
            ], $facts, $opts);
            if (!$score) {
                return null;
            }
            return [
                'id' => $r->id,
                'fcm_token' => $r->fcm_token,
                'current_orders' => (int) $r->current_orders,
                'meters' => RiderArrivalService::distanceMeters($at[0], $at[1], $pickup[0], $pickup[1]),
                'minutes' => $score['pickup_minutes'],
                'stack' => $score['stack'],
                'today' => (int) ($today[$r->id] ?? 0),
                'cost' => $score['cost'],
            ];
        })->filter();

        return $scored->sortBy([['cost', 'asc'], ['meters', 'asc']])->values();
    }

    /** What a rider still has to do, in order: pickups not made yet, then drop-offs. */
    private static function heldStops($held): array
    {
        $pickups = $held->where('order_status', '!=', 'picked_up')
            ->map(fn ($job) => ($p = self::pickupPoint($job)) ? ['point' => $p, 'drop' => false] : null);
        $drops = $held->map(fn ($job) => ($d = RiderArrivalService::dropOffPoint($job)) ? ['point' => $d, 'drop' => true] : null);
        return $pickups->concat($drops)->filter()->values()->all();
    }

    /**
     * Minutes until the food is ready: the store's own estimate once it is
     * cooking, else its quoted prep time. 0 for parcels and unknown.
     */
    public static function readyIn(Order $order, ?Carbon $now = null): float
    {
        $now ??= now();
        if ($order->order_type === 'parcel' || !$order->processing_time) {
            return 0.0;
        }
        if ($order->order_status === 'processing' && $order->processing) {
            $ready = Carbon::parse($order->processing)->addMinutes((int) $order->processing_time);
            return max(0.0, $now->diffInSeconds($ready, false) / 60);
        }
        return in_array($order->order_status, ['pending', 'confirmed', 'accepted'], true) ? (float) $order->processing_time : 0.0;
    }

    /** The zone's real riding speed (DeliveryTimeModel), peak or not; city default without enough history. */
    public static function metersPerMinute(?int $zoneId): float
    {
        $stats = DeliveryTimeModel::zoneStats($zoneId);
        $peak = DeliveryTimeModel::isPeak(now()) ? 'per_km_peak' : 'per_km_off';
        $perKm = $stats[$peak] ?? $stats['per_km'] ?? null;
        return $perKm ? 1000 / $perKm : DispatchScorer::DEFAULT_METERS_PER_MINUTE;
    }

    /** acceptanceRate for many riders in one query: id => percent, only riders with enough offers. */
    private static function acceptanceRates(array $ids): array
    {
        return OrderOffer::whereIn('delivery_man_id', $ids)
            ->whereIn('status', ['accepted', 'declined', 'expired'])
            ->where('offered_at', '>=', now()->subDays(30))
            ->groupBy('delivery_man_id')
            ->selectRaw("delivery_man_id, count(*) as total, sum(case when status = 'accepted' then 1 else 0 end) as accepted")
            ->get()
            ->filter(fn ($r) => (int) $r->total >= 10)
            ->mapWithKeys(fn ($r) => [$r->delivery_man_id => (int) round($r->accepted / $r->total * 100)])
            ->all();
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
