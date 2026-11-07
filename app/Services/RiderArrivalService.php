<?php

namespace App\Services;

use App\Models\DeliveryMan;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SM-2: "your rider is arriving". The rider app sends its location about
 * every 10 s; when the assigned rider of an order the store has not handed
 * over yet comes within [RADIUS_METERS] of the store, the store's phones
 * get one push, so the food is packed when the rider walks in.
 *
 * It also records when the rider first reaches each stop, for the real wait at
 * the store. That only ever sets a time: GPS is not sure enough to change an
 * order's status.
 */
class RiderArrivalService
{
    public const RADIUS_METERS = 500;
    /** The rider app's "you're here" uses the same distance. */
    public const AT_STOP_METERS = 150;
    /** City riding speed used for the "about N min away" line. */
    public const METERS_PER_MINUTE = 333;

    public static function check(?DeliveryMan $dm, $latitude, $longitude): void
    {
        if (!$dm || !$dm->current_orders || !is_numeric($latitude) || !is_numeric($longitude)) {
            return;
        }
        try {
            $orders = Order::withoutGlobalScopes()
                ->where('delivery_man_id', $dm->id)
                ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover', 'picked_up'])
                ->whereIn('order_type', ['delivery', 'parcel'])
                ->with('store')
                ->get();
            foreach ($orders as $order) {
                $pickedUp = $order->order_status === 'picked_up';
                $stop = $pickedUp ? self::dropOffPoint($order) : DispatchService::pickupPoint($order);
                if (!$stop) {
                    continue;
                }
                $meters = self::distanceMeters((float) $latitude, (float) $longitude, $stop[0], $stop[1]);
                $arrival = $pickedUp ? 'rider_at_customer_at' : 'rider_at_store_at';
                if ($meters <= self::AT_STOP_METERS && !$order->{$arrival}) {
                    Order::withoutGlobalScopes()->where('id', $order->id)->whereNull($arrival)->update([$arrival => now()]);
                }
                // Cache::add is atomic: one push per order even with two
                // location pings in flight.
                if (!$pickedUp && $order->store && $meters <= self::RADIUS_METERS && Cache::add("rider_arriving_{$order->id}", 1, now()->addHours(6))) {
                    self::notify($order, $meters);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Rider arrival check failed: ' . $e->getMessage());
        }
    }

    /** Where the order is dropped: a parcel's receiver, or the customer. [lat, lng] or null. */
    public static function dropOffPoint(Order $order): ?array
    {
        $to = $order->order_type === 'parcel' ? $order->receiver_details : $order->delivery_address;
        $to = is_string($to) ? json_decode($to, true) : $to;
        $lat = $to['latitude'] ?? null;
        $lng = $to['longitude'] ?? null;
        return is_numeric($lat) && is_numeric($lng) ? [(float) $lat, (float) $lng] : null;
    }

    public static function minutesAway(float $meters): int
    {
        return max(1, (int) ceil($meters / self::METERS_PER_MINUTE));
    }

    private static function notify(Order $order, float $meters): void
    {
        $minutes = self::minutesAway($meters);
        StorePushService::sendLocalized($order->store, fn ($lang) => [
            'title' => $lang === 'ar' ? "المندوب في الطريق إليك · #{$order->id}" : "Rider arriving · #{$order->id}",
            'description' => $lang === 'ar' ? "يصل خلال $minutes دقيقة تقريبًا. جهّز الطلب للتسليم." : "About $minutes min away. Get the order ready for handover.",
            'order_id' => $order->id,
            'image' => '',
            'type' => 'order_status',
            'ring' => 0,
        ], 'rider');
    }

    /** Haversine distance in meters. */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
