<?php

namespace App\Console\Commands;

use App\Models\DeliveryHistory;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Drives an order's rider toward the customer, for checking the live map
 * without a rider on the road (LT-*, docs/live_tracking_plan.md in the app).
 *
 * Writes the same delivery_histories row the rider app's 10 s
 * record-location-data call writes, so the customer app can't tell the
 * difference. The path is a straight line from a point --from km out (toward
 * the store) to the delivery address. Close the rider app first, or its own
 * GPS reports will fight these.
 *
 *   php artisan rider:simulate 100123
 *   php artisan rider:simulate 100123 --from=3 --speed=40
 *   php artisan rider:simulate 100123 --stale      # fix 5 min old, then exit
 */
class SimulateRiderRoute extends Command
{
    protected $signature = 'rider:simulate
        {order : Order id (must have a rider assigned)}
        {--from=2.5 : Start this many km from the delivery address}
        {--speed=25 : Rider speed in km/h}
        {--every=10 : Seconds between fixes (the rider app uses 10)}
        {--stale : Write one fix dated 5 minutes ago and stop}';

    protected $description = 'Simulate a rider riding to an order\'s delivery address (testing the live map)';

    public function handle(): int
    {
        $order = Order::find($this->argument('order'));
        if (!$order) {
            $this->error('Order not found.');
            return self::FAILURE;
        }
        if (!$order->delivery_man_id) {
            $this->error('Order has no rider assigned. Assign one first.');
            return self::FAILURE;
        }

        $address = is_string($order->delivery_address)
            ? json_decode($order->delivery_address, true)
            : (array) $order->delivery_address;
        $homeLat = (float) ($address['latitude'] ?? 0);
        $homeLng = (float) ($address['longitude'] ?? 0);
        if (!$homeLat || !$homeLng) {
            $this->error('Order has no delivery coordinates.');
            return self::FAILURE;
        }

        if ($order->order_status !== 'picked_up') {
            $this->warn("Order status is '{$order->order_status}'. The map only shows once it is 'picked_up'.");
        }

        $riderId = $order->delivery_man_id;

        if ($this->option('stale')) {
            $this->write($riderId, $homeLat + 0.005, $homeLng, now()->subMinutes(5));
            $this->info('Wrote a 5-minute-old fix. The app should drop the map on its next poll.');
            return self::SUCCESS;
        }

        // Head in from the store's side when we know where the store is.
        $store = $order->store;
        $bearing = ($store && $store->latitude && $store->longitude)
            ? $this->bearing($homeLat, $homeLng, (float) $store->latitude, (float) $store->longitude)
            : 45.0;
        $fromKm = max(0.05, (float) $this->option('from'));
        [$lat, $lng] = $this->destination($homeLat, $homeLng, $bearing, $fromKm);

        $every = max(1, (int) $this->option('every'));
        $stepKm = max(0.001, (float) $this->option('speed') * $every / 3600);
        $steps = (int) ceil($fromKm / $stepKm);

        $this->info("Rider #{$riderId}: {$fromKm} km out, {$steps} fixes every {$every}s (~" . round($steps * $every / 60, 1) . ' min). Ctrl+C to stop.');

        for ($i = 0; $i <= $steps; $i++) {
            $t = $i / $steps;
            $curLat = $lat + ($homeLat - $lat) * $t;
            $curLng = $lng + ($homeLng - $lng) * $t;
            $this->write($riderId, $curLat, $curLng, now());
            $left = round($fromKm * (1 - $t), 2);
            $this->line(sprintf('  %3d/%d  %.6f, %.6f  %s km to go', $i, $steps, $curLat, $curLng, $left));
            if ($i < $steps) {
                sleep($every);
            }
        }

        $this->info('Arrived at the delivery address. The fix stays there until the rider app reports again.');
        return self::SUCCESS;
    }

    private function write(int $riderId, float $lat, float $lng, $at): void
    {
        DeliveryHistory::updateOrCreate(['delivery_man_id' => $riderId], [
            'latitude' => (string) $lat,
            'longitude' => (string) $lng,
            'time' => $at,
            'location' => 'simulated',
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        // rider-location caches each rider's fix for 5 s; show this one now.
        Cache::forget('rider_loc:' . $riderId);
    }

    private function bearing(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $p1 = deg2rad($lat1);
        $p2 = deg2rad($lat2);
        $dl = deg2rad($lng2 - $lng1);
        $y = sin($dl) * cos($p2);
        $x = cos($p1) * sin($p2) - sin($p1) * cos($p2) * cos($dl);
        return fmod(rad2deg(atan2($y, $x)) + 360, 360);
    }

    /** The point $km away from ($lat, $lng) along $bearing degrees. */
    private function destination(float $lat, float $lng, float $bearing, float $km): array
    {
        $d = $km / 6371;
        $b = deg2rad($bearing);
        $p1 = deg2rad($lat);
        $l1 = deg2rad($lng);
        $p2 = asin(sin($p1) * cos($d) + cos($p1) * sin($d) * cos($b));
        $l2 = $l1 + atan2(sin($b) * sin($d) * cos($p1), cos($d) - sin($p1) * sin($p2));
        return [rad2deg($p2), rad2deg($l2)];
    }
}
