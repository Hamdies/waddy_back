<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\DeliveryHistory;
use App\Models\DeliveryMan;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Smarter dispatch (XS-2). A store order used to be pushed to every rider in
 * the zone at once; whoever tapped first won, wherever they were. Now:
 *
 * - wave 1: the [wave size] nearest available riders (live location from
 *   the rider app, under 10 min old) get the alert;
 * - each [wave seconds] after: the next [wave size + 2];
 * - then the old broadcast to the whole zone, so the worst case is today's
 *   behaviour, a minute or two later.
 * - If the store is already cooking with more than [lead minutes] to go,
 *   wave 1 waits until about [lead minutes] before the food is ready, so
 *   riders do not stand at the counter.
 *
 * The rider app's list of available orders is unchanged (everyone in the
 * zone still sees the order); only who is alerted first changes.
 *
 * Business settings: smart_dispatch_status (1), dispatch_wave_size (3),
 * dispatch_wave_seconds (60), dispatch_lead_minutes (15).
 */
class DispatchService
{
    private const LOCATION_FRESH_MINUTES = 10;
    private const STATE_HOURS = 3;

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
        if (!self::enabled() || !$order->store || !is_numeric($order->store->latitude)) {
            self::broadcast($order, $data);
            return;
        }
        $key = self::key($order);
        if (Cache::has($key)) {
            return; // already dispatching (status pushes can repeat)
        }
        $state = ['wave' => 0, 'offered' => [], 'data' => $data, 'next_at' => self::firstWaveAt($order)->timestamp];
        Cache::put($key, $state, now()->addHours(self::STATE_HOURS));
        if ($state['next_at'] <= now()->timestamp) {
            self::advance($order, $state);
        }
    }

    /**
     * When wave 1 should go. Now, unless the store is cooking with more than
     * the lead time left. Pure apart from settings.
     */
    public static function firstWaveAt(Order $order, ?Carbon $now = null): Carbon
    {
        $now ??= now();
        $lead = self::setting('dispatch_lead_minutes', 15);
        if ($order->order_status === 'processing' && $order->processing && $order->processing_time) {
            $ready = Carbon::parse($order->processing)->addMinutes((int) $order->processing_time);
            $at = $ready->copy()->subMinutes($lead);
            return $at->isAfter($now) ? $at : $now;
        }
        return $now;
    }

    /** Scheduler (every minute): next wave for orders still without a rider. */
    public static function tick(): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $sent = 0;
        $orders = Order::withoutGlobalScopes()
            ->whereNull('delivery_man_id')
            ->where('order_type', 'delivery')
            ->whereIn('order_status', ['pending', 'accepted', 'confirmed', 'processing', 'handover'])
            ->where('created_at', '>=', now()->subHours(self::STATE_HOURS))
            ->with(['store', 'zone'])
            ->get();
        foreach ($orders as $order) {
            $state = Cache::get(self::key($order));
            if (!$state || ($state['done'] ?? false) || $state['next_at'] > now()->timestamp) {
                continue;
            }
            try {
                self::advance($order, $state);
                $sent++;
            } catch (\Throwable $e) {
                Log::warning("Dispatch wave failed for order {$order->id}: " . $e->getMessage());
            }
        }
        return $sent;
    }

    private static function advance(Order $order, array $state): void
    {
        $size = self::setting('dispatch_wave_size', 3);
        $state['wave']++;
        $want = $state['wave'] === 1 ? $size : $size + 2;
        $riders = $state['wave'] <= 2 ? self::candidates($order, $state['offered'])->take($want) : collect();

        if ($riders->isEmpty()) {
            // No one nearby (or waves used up): the whole zone, as before.
            self::broadcast($order, $state['data']);
            $state['done'] = true;
        } else {
            foreach ($riders as $rider) {
                Helpers::send_push_notif_to_device($rider['fcm_token'], $state['data'] + ['type' => 'order_request']);
                $state['offered'][] = $rider['id'];
            }
            $state['next_at'] = now()->addSeconds(self::setting('dispatch_wave_seconds', 60))->timestamp;
        }
        Cache::put(self::key($order), $state, now()->addHours(self::STATE_HOURS));
    }

    /**
     * Online, approved riders of the order's zone (and vehicle), not full,
     * with a fresh location, nearest to the store first.
     */
    public static function candidates(Order $order, array $exclude = [])
    {
        $store = $order->store;
        $riders = DeliveryMan::query()
            ->where(['active' => 1, 'status' => 1, 'application_status' => 'approved'])
            ->where('zone_id', $order->zone_id)
            ->where('current_orders', '<', (int) (config('dm_maximum_orders') ?: 1))
            ->whereNotNull('fcm_token')->where('fcm_token', '!=', '@')
            ->when($order->dm_vehicle_id, fn ($q) => $q->where('vehicle_id', $order->dm_vehicle_id))
            ->whereNotIn('id', $exclude)
            ->get(['id', 'fcm_token', 'current_orders']);
        if ($riders->isEmpty()) {
            return collect();
        }
        $locations = DeliveryHistory::whereIn('delivery_man_id', $riders->pluck('id'))
            ->where('time', '>=', now()->subMinutes(self::LOCATION_FRESH_MINUTES))
            ->get(['delivery_man_id', 'latitude', 'longitude'])
            ->keyBy('delivery_man_id');

        return self::rank($riders->map(function ($r) use ($locations, $store) {
            $loc = $locations[$r->id] ?? null;
            if (!$loc || !is_numeric($loc->latitude) || !is_numeric($loc->longitude)) {
                return null;
            }
            return [
                'id' => $r->id,
                'fcm_token' => $r->fcm_token,
                'current_orders' => (int) $r->current_orders,
                'meters' => RiderArrivalService::distanceMeters((float) $loc->latitude, (float) $loc->longitude, (float) $store->latitude, (float) $store->longitude),
            ];
        })->filter()->values());
    }

    /** Nearest first; a free rider beats a busy one at the same distance (500 m). Pure. */
    public static function rank($riders)
    {
        return collect($riders)->sortBy(fn ($r) => [intdiv((int) $r['meters'], 500), $r['current_orders'], $r['meters']])->values();
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

    private static function key(Order $order): string
    {
        return "dispatch_{$order->id}";
    }
}
