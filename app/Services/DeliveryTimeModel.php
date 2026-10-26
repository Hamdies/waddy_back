<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * What delivery really takes in a zone, learned from the last 30 days of
 * delivered orders (XS-1). Feeds EstimatedDeliveryService, which used fixed
 * constants (3 min/km, 5 min buffer) for every zone and hour.
 *
 * Each number falls back to the old constant when there are fewer than
 * [MIN_SAMPLES] usable orders, so a new zone behaves exactly as before.
 */
class DeliveryTimeModel
{
    public const DEFAULT_MINUTES_PER_KM = 3;
    public const DEFAULT_PICKUP_GAP = 5;
    public const DEFAULT_RIDE_MINUTES = 10;
    private const HISTORY_DAYS = 30;
    private const MIN_SAMPLES = 20;
    /** Beyond this a distance is bad data (PRODUCT.md truth contract). */
    public const MAX_PLAUSIBLE_KM = 60;

    /** Cairo lunch and dinner rush. */
    public static function isPeak(Carbon $at): bool
    {
        $h = (int) $at->copy()->setTimezone(config('placestovisit.timezone', 'Africa/Cairo'))->format('G');
        return ($h >= 12 && $h < 15) || ($h >= 19 && $h < 23);
    }

    /** Ride minutes for [$order] from pickup to the door. */
    public static function rideMinutes(Order $order, ?Carbon $at = null): int
    {
        $stats = self::zoneStats($order->zone_id);
        $peak = self::isPeak($at ?? now()) ? 'peak' : 'off';
        $km = (float) $order->distance;
        if ($km > 0 && $km <= self::MAX_PLAUSIBLE_KM) {
            $perKm = $stats["per_km_$peak"] ?? $stats['per_km'] ?? self::DEFAULT_MINUTES_PER_KM;
            return max(1, (int) ceil($km * $perKm));
        }
        // Unknown or impossible distance: the zone's typical ride.
        return $stats["ride_$peak"] ?? $stats['ride'] ?? self::DEFAULT_RIDE_MINUTES;
    }

    /** Minutes between "ready for handover" and the rider picking it up. */
    public static function pickupGap(?int $zoneId): int
    {
        return self::zoneStats($zoneId)['pickup_gap'] ?? self::DEFAULT_PICKUP_GAP;
    }

    /**
     * Medians for a zone, cached an hour. Keys present only with enough
     * samples: per_km, per_km_peak, per_km_off, ride, ride_peak, ride_off,
     * pickup_gap.
     */
    public static function zoneStats(?int $zoneId): array
    {
        return Cache::remember('delivery_time_model_' . ($zoneId ?? 'all'), now()->addHour(), function () use ($zoneId) {
            $rows = Order::withoutGlobalScopes()
                ->where('order_status', 'delivered')
                ->whereNotNull('picked_up')->whereNotNull('delivered')
                ->where('created_at', '>=', now()->subDays(self::HISTORY_DAYS))
                ->when($zoneId, fn ($q) => $q->where('zone_id', $zoneId))
                ->get(['distance', 'handover', 'picked_up', 'delivered']);

            $perKm = ['peak' => [], 'off' => []];
            $ride = ['peak' => [], 'off' => []];
            $gap = [];
            foreach ($rows as $row) {
                $pickedUp = Carbon::parse($row->picked_up);
                $minutes = $pickedUp->diffInSeconds(Carbon::parse($row->delivered), false) / 60;
                if ($minutes < 1 || $minutes > 120) {
                    continue; // tapped late or early, not a real ride
                }
                $bucket = self::isPeak($pickedUp) ? 'peak' : 'off';
                $ride[$bucket][] = $minutes;
                $km = (float) $row->distance;
                if ($km >= 0.3 && $km <= self::MAX_PLAUSIBLE_KM) {
                    $perKm[$bucket][] = $minutes / $km;
                }
                if ($row->handover) {
                    $g = Carbon::parse($row->handover)->diffInSeconds($pickedUp, false) / 60;
                    if ($g >= 0 && $g <= 60) {
                        $gap[] = $g;
                    }
                }
            }

            $stats = [];
            $put = function (string $key, array $values, float $min, float $max, bool $round = true) use (&$stats) {
                if (count($values) >= self::MIN_SAMPLES) {
                    $m = min($max, max($min, self::median($values)));
                    $stats[$key] = $round ? (int) round($m) : round($m, 2);
                }
            };
            $put('per_km', array_merge($perKm['peak'], $perKm['off']), 1.5, 8, false);
            $put('per_km_peak', $perKm['peak'], 1.5, 8, false);
            $put('per_km_off', $perKm['off'], 1.5, 8, false);
            $put('ride', array_merge($ride['peak'], $ride['off']), 3, 90);
            $put('ride_peak', $ride['peak'], 3, 90);
            $put('ride_off', $ride['off'], 3, 90);
            $put('pickup_gap', $gap, 0, 20);
            return $stats;
        });
    }

    public static function median(array $values): float
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);
        return $n % 2 ? (float) $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
