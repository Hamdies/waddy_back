<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\DeliveryMan;
use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * SM-2: "your rider is arriving". The rider app sends its location about
 * every 10 s; when the assigned rider of an order the store has not handed
 * over yet comes within [RADIUS_METERS] of the store, the store's phones
 * get one push, so the food is packed when the rider walks in.
 */
class RiderArrivalService
{
    public const RADIUS_METERS = 500;
    /** City riding speed used for the "about N min away" line. */
    private const METERS_PER_MINUTE = 333;

    public static function check(?DeliveryMan $dm, $latitude, $longitude): void
    {
        if (!$dm || !$dm->current_orders || !is_numeric($latitude) || !is_numeric($longitude)) {
            return;
        }
        try {
            $orders = Order::withoutGlobalScopes()
                ->where('delivery_man_id', $dm->id)
                ->whereIn('order_status', ['accepted', 'confirmed', 'processing', 'handover'])
                ->where('order_type', 'delivery')
                ->with('store')
                ->get();
            foreach ($orders as $order) {
                $store = $order->store;
                if (!$store || !is_numeric($store->latitude) || !is_numeric($store->longitude)) {
                    continue;
                }
                $meters = self::distanceMeters((float) $latitude, (float) $longitude, (float) $store->latitude, (float) $store->longitude);
                // Cache::add is atomic: one push per order even with two
                // location pings in flight.
                if ($meters <= self::RADIUS_METERS && Cache::add("rider_arriving_{$order->id}", 1, now()->addHours(6))) {
                    self::notify($order, $meters);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Rider arrival check failed: ' . $e->getMessage());
        }
    }

    public static function minutesAway(float $meters): int
    {
        return max(1, (int) ceil($meters / self::METERS_PER_MINUTE));
    }

    private static function notify(Order $order, float $meters): void
    {
        $minutes = self::minutesAway($meters);
        foreach (StorePushService::tokensByLanguage($order->store) as $lang => $tokens) {
            $ar = $lang === 'ar';
            $data = [
                'title' => $ar ? "المندوب في الطريق إليك · #{$order->id}" : "Rider arriving · #{$order->id}",
                'description' => $ar ? "يصل خلال $minutes دقيقة تقريبًا. جهّز الطلب للتسليم." : "About $minutes min away. Get the order ready for handover.",
                'order_id' => $order->id,
                'image' => '',
                'type' => 'order_status',
                'ring' => 0,
            ];
            foreach ($tokens as $token) {
                Helpers::send_push_notif_to_device($token, $data);
            }
        }
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
