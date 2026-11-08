<?php

namespace App\Services;

/**
 * How good a rider is for an order, as one number in minutes (lower is
 * better). Pure: DispatchService gathers the facts, this weighs them, so every
 * rule here is unit-tested without a database.
 *
 * cost = when the order can leave the store            (the customer's wait)
 *      + W_WORK × rider minutes the order uses up      (keep free riders free)
 *      + W_IDLE × minutes standing at the counter      (don't send them too early)
 *      + fairness, idle-time bonus, reliability, GPS doubt
 *
 * A rider already on a route can take the order on the way when it adds at
 * most [stack detour] minutes and delays none of their customers by more than
 * [STACK_DELAY_MAX]. Then the order only costs the detour, which is why a
 * rider passing the store beats a free one standing slightly closer.
 */
class DispatchScorer
{
    /** Cost per minute of rider time the order takes (ride there, wait, ride, hand over). */
    public const W_WORK = 0.5;
    /** Cost per minute a rider waits at the counter for the food. */
    public const W_IDLE = 0.3;
    /** Cost per order a rider has had today above the median of the riders compared. */
    public const FAIR_PER_ORDER = 0.75;
    public const FAIR_CAP = 4.0;
    /** Minutes off for each 10 minutes since a rider's last order, up to the cap. */
    public const IDLE_BONUS_PER_10_MIN = 0.5;
    public const IDLE_BONUS_CAP = 1.5;
    /** A fix this old may be this much off: one minute of doubt per minute of age. */
    public const STALE_PER_MINUTE = 1.0;
    public const POOR_ACCURACY_METERS = 150;
    public const POOR_ACCURACY_COST = 1.0;
    /** Handing over or collecting at a stop. Stops this close count as one. */
    public const MINUTES_PER_STOP = 3;
    public const SAME_STOP_METERS = 60;
    public const STACK_DETOUR_MAX = 8;
    public const STACK_DELAY_MAX = 10;
    /** Faster than this, a fix is pushed along the heading for the time since it was taken. */
    public const MOVING_MPS = 2.0;
    public const PROJECT_MAX_SECONDS = 60;
    public const DEFAULT_METERS_PER_MINUTE = 333;

    /**
     * @param array $rider at [lat, lng]; optional: heading (deg), speed (m/s),
     *   age_seconds, accuracy (m), stops (held route: list of
     *   ['point' => [lat, lng], 'drop' => bool]), today (orders today),
     *   today_median, idle_minutes (null when unknown), accept_rate (0..1),
     *   max_km (vehicle range, 0 = none).
     * @param array $order pickup [lat, lng]; optional: drop [lat, lng],
     *   ready_in (minutes until the food is ready).
     * @param array $opts meters_per_minute, stack_detour_max, offer_minutes
     *   (time a refused alert wastes).
     * @return array|null cost, pickup_minutes, stack, detour, work; null when
     *   the rider cannot take the order at all.
     */
    public static function score(array $rider, array $order, array $opts = []): ?array
    {
        $mpm = max(50.0, (float) ($opts['meters_per_minute'] ?? self::DEFAULT_METERS_PER_MINUTE));
        $pickup = $order['pickup'];
        $drop = $order['drop'] ?? null;
        $readyIn = max(0.0, (float) ($order['ready_in'] ?? 0));

        $tripMinutes = $drop ? self::minutes($pickup, $drop, $mpm) : 0.0;
        $maxKm = (float) ($rider['max_km'] ?? 0);
        if ($maxKm > 0 && $drop && RiderArrivalService::distanceMeters($pickup[0], $pickup[1], $drop[0], $drop[1]) / 1000 > $maxKm) {
            return null; // beyond what this vehicle covers
        }

        $at = self::projected($rider);
        $held = array_values($rider['stops'] ?? []);

        $stack = false;
        $detour = 0.0;
        if ($held) {
            $plan = self::bestInsertion($at, $held, $pickup, $drop, $mpm);
            $detourMax = (float) ($opts['stack_detour_max'] ?? self::STACK_DETOUR_MAX);
            if ($plan && $plan['detour'] <= $detourMax && $plan['delay'] <= self::STACK_DELAY_MAX) {
                $stack = true;
                $detour = $plan['detour'];
                $arrive = $plan['pickup_at'];
            } else {
                // Finish what they hold, then come.
                $route = self::route($at, array_column($held, 'point'), $mpm);
                $arrive = $route + self::minutes(end($held)['point'], $pickup, $mpm);
            }
        } else {
            $arrive = self::minutes($at, $pickup, $mpm);
        }

        $pickupAt = max($arrive, $readyIn);
        $idle = max(0.0, $readyIn - $arrive);
        // Rider time the order uses: only the detour when it rides along.
        $work = $stack ? $detour : $arrive + $idle + $tripMinutes + ($drop ? self::MINUTES_PER_STOP : 0);

        $cost = $pickupAt + self::W_WORK * $work + self::W_IDLE * $idle;

        if (isset($rider['today'], $rider['today_median'])) {
            $cost += max(-self::FAIR_CAP, min(self::FAIR_CAP, ($rider['today'] - $rider['today_median']) * self::FAIR_PER_ORDER));
        }
        if (isset($rider['idle_minutes']) && !$held) {
            $cost -= min(self::IDLE_BONUS_CAP, $rider['idle_minutes'] / 10 * self::IDLE_BONUS_PER_10_MIN);
        }
        if (isset($rider['accept_rate'])) {
            $cost += (1 - max(0.0, min(1.0, (float) $rider['accept_rate']))) * (float) ($opts['offer_minutes'] ?? 0.5);
        }
        $cost += self::STALE_PER_MINUTE * max(0.0, (float) ($rider['age_seconds'] ?? 0)) / 60;
        if (($rider['accuracy'] ?? 0) > self::POOR_ACCURACY_METERS) {
            $cost += self::POOR_ACCURACY_COST;
        }

        return [
            'cost' => round($cost, 2),
            'pickup_minutes' => round($arrive, 1),
            'stack' => $stack,
            'detour' => round($detour, 1),
            'work' => round($work, 1),
        ];
    }

    /**
     * Where a moving rider is now: the fix pushed along the heading for the
     * seconds since it was taken (plus a little for the alert), at most a
     * minute's worth. A standing rider stays where the fix says.
     */
    public static function projected(array $rider): array
    {
        $at = $rider['at'];
        $speed = (float) ($rider['speed'] ?? 0);
        if ($speed < self::MOVING_MPS || !isset($rider['heading']) || !is_numeric($rider['heading'])) {
            return $at;
        }
        $seconds = min(self::PROJECT_MAX_SECONDS, max(0.0, (float) ($rider['age_seconds'] ?? 0)) + 15);
        $meters = $speed * $seconds;
        $rad = deg2rad((float) $rider['heading']);
        $dLat = $meters * cos($rad) / 111320;
        $dLng = $meters * sin($rad) / (111320 * max(0.01, cos(deg2rad($at[0]))));
        return [$at[0] + $dLat, $at[1] + $dLng];
    }

    /**
     * The cheapest place in the held route for the new pickup and its drop-off
     * (pickup first). Brute force: routes are a handful of stops.
     *
     * @return array|null detour (extra minutes), delay (most a held drop-off
     *   gets later), pickup_at (minutes until the new pickup).
     */
    public static function bestInsertion(array $at, array $held, array $pickup, ?array $drop, float $mpm): ?array
    {
        $points = array_column($held, 'point');
        $base = self::arrivals($at, $points, $mpm);
        $baseTotal = $base ? end($base) : 0.0;
        $n = count($points);
        $best = null;
        for ($i = 0; $i <= $n; $i++) {
            for ($j = $i; $j <= $n; $j++) {
                if (!$drop && $j !== $i) {
                    continue;
                }
                $stops = $points;
                array_splice($stops, $j, 0, $drop ? [$drop] : []);
                array_splice($stops, $i, 0, [$pickup]);
                $times = self::arrivals($at, $stops, $mpm);
                $detour = end($times) - $baseTotal;
                // Held stops, in order, in the new route: skip the inserted ones.
                $delay = 0.0;
                $k = 0;
                foreach ($stops as $idx => $_) {
                    if ($idx === $i || ($drop && $idx === $j + 1)) {
                        continue;
                    }
                    if (!empty($held[$k]['drop'])) {
                        $delay = max($delay, $times[$idx] - $base[$k]);
                    }
                    $k++;
                }
                if (!$best || $detour < $best['detour']) {
                    // Getting there, not done there: take off the pickup's own handling.
                    $prev = $i === 0 ? $at : $stops[$i - 1];
                    $handling = $i === 0 || RiderArrivalService::distanceMeters($prev[0], $prev[1], $pickup[0], $pickup[1]) > self::SAME_STOP_METERS
                        ? self::MINUTES_PER_STOP : 0;
                    $best = ['detour' => max(0.0, $detour), 'delay' => max(0.0, $delay), 'pickup_at' => $times[$i] - $handling];
                }
            }
        }
        return $best;
    }

    /** Minutes until each stop is done (ridden to and handled), in order. */
    public static function arrivals(array $at, array $stops, float $mpm): array
    {
        $t = 0.0;
        $out = [];
        $prev = $at;
        $first = true;
        foreach ($stops as $stop) {
            $meters = RiderArrivalService::distanceMeters($prev[0], $prev[1], $stop[0], $stop[1]);
            $t += $meters / $mpm;
            // A second stop at the same place (same store, same building) is one stop.
            if ($first || $meters > self::SAME_STOP_METERS) {
                $t += self::MINUTES_PER_STOP;
            }
            $out[] = $t;
            $prev = $stop;
            $first = false;
        }
        return $out;
    }

    public static function route(array $at, array $stops, float $mpm): float
    {
        $times = self::arrivals($at, $stops, $mpm);
        return $times ? end($times) : 0.0;
    }

    public static function minutes(array $from, array $to, float $mpm): float
    {
        return RiderArrivalService::distanceMeters($from[0], $from[1], $to[0], $to[1]) / $mpm;
    }

    /**
     * Which order goes to which rider when several are due at once. Greedy on
     * regret: the order that loses most if it does not get its best rider goes
     * first, and each rider takes one new order per round. Pure.
     *
     * @param array<int, array<int, float>> $costs order id => [rider id => cost]
     * @return array<int, int> order id => rider id
     */
    public static function assign(array $costs): array
    {
        $taken = [];
        $result = [];
        while ($costs) {
            $pick = null;
            foreach ($costs as $orderId => $riders) {
                $free = array_diff_key($riders, $taken);
                if (!$free) {
                    unset($costs[$orderId]);
                    continue;
                }
                asort($free);
                $values = array_values($free);
                $regret = ($values[1] ?? $values[0] + 30) - $values[0];
                if (!$pick || $regret > $pick[2] || ($regret == $pick[2] && $values[0] < $pick[3])) {
                    $pick = [$orderId, array_key_first($free), $regret, $values[0]];
                }
            }
            if (!$pick) {
                break;
            }
            $result[$pick[0]] = $pick[1];
            $taken[$pick[1]] = true;
            unset($costs[$pick[0]]);
        }
        return $result;
    }
}
