<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Store;
use Carbon\Carbon;

/**
 * Estimated Delivery Time Service
 *
 * Calculates and recalculates the estimated delivery time for orders
 * based on store processing time, distance, and order status.
 */
class EstimatedDeliveryService
{
    /**
     * Average travel time in minutes per kilometer.
     * Accounts for urban delivery conditions (traffic, stops, navigation).
     */
    const MINUTES_PER_KM = 3;

    /**
     * Buffer time in minutes added to account for order handoff,
     * delivery man assignment delay, and other operational overhead.
     */
    const BUFFER_MINUTES = 5;

    /**
     * Calculate the initial estimated delivery time when an order is placed.
     *
     * Formula: base_time + processing_minutes + travel_minutes + buffer
     *
     * @param Order $order
     * @param Store|null $store
     * @return Carbon
     */
    public static function calculateInitialEstimate(Order $order, ?Store $store): Carbon
    {
        $baseTime = $order->schedule_at ? Carbon::parse($order->schedule_at) : now();

        // Get processing time from store's delivery_time (e.g., "30-40 min")
        $processingMinutes = self::getProcessingMinutes($order, $store);

        // Calculate travel time from distance
        $travelMinutes = self::getTravelMinutes($order->distance);

        return $baseTime->copy()->addMinutes($processingMinutes + $travelMinutes + self::BUFFER_MINUTES);
    }

    /**
     * Recalculate the estimated delivery time when order status changes.
     *
     * Returns null if no recalculation is needed for this status.
     *
     * @param Order $order
     * @param string $newStatus
     * @return Carbon|null
     */
    public static function recalculateOnStatusChange(Order $order, string $newStatus): ?Carbon
    {
        return match ($newStatus) {
            'confirmed' => self::recalculateOnConfirmed($order),
            'processing' => self::recalculateOnProcessing($order),
            'handover' => self::recalculateOnHandover($order),
            'picked_up' => self::recalculateOnPickedUp($order),
            default => null, // No recalculation for other statuses
        };
    }

    /**
     * When confirmed: recalculate from now with full processing + travel time.
     */
    protected static function recalculateOnConfirmed(Order $order): Carbon
    {
        $processingMinutes = self::getProcessingMinutes($order, $order->store);
        $travelMinutes = self::getTravelMinutes($order->distance);

        return now()->addMinutes($processingMinutes + $travelMinutes + self::BUFFER_MINUTES);
    }

    /**
     * When processing: use actual processing_time + travel from now.
     * The store has committed to a preparation time at this point.
     */
    protected static function recalculateOnProcessing(Order $order): Carbon
    {
        $processingMinutes = $order->processing_time ?? self::getStoreMinDeliveryTime($order->store);
        $travelMinutes = self::getTravelMinutes($order->distance);

        return now()->addMinutes($processingMinutes + $travelMinutes);
    }

    /**
     * When handover: food is ready, just waiting for pickup + travel.
     */
    protected static function recalculateOnHandover(Order $order): Carbon
    {
        $travelMinutes = self::getTravelMinutes($order->distance);

        // Add a small buffer for delivery man to arrive at store and pick up
        return now()->addMinutes($travelMinutes + self::BUFFER_MINUTES);
    }

    /**
     * When picked up: only travel time remains.
     */
    protected static function recalculateOnPickedUp(Order $order): Carbon
    {
        $travelMinutes = self::getTravelMinutes($order->distance);

        return now()->addMinutes($travelMinutes);
    }

    /**
     * Get the processing time in minutes.
     *
     * Priority: order's processing_time > store's min delivery time > fallback 30 min
     */
    protected static function getProcessingMinutes(Order $order, ?Store $store): int
    {
        if ($order->processing_time) {
            return (int) $order->processing_time;
        }

        return self::getStoreMinDeliveryTime($store);
    }

    /**
     * Extract minimum delivery time from store's delivery_time field.
     *
     * Store delivery_time format: "30-40 min" or "1-2 hours"
     *
     * @param Store|null $store
     * @return int minutes
     */
    protected static function getStoreMinDeliveryTime(?Store $store): int
    {
        if (!$store || !$store->delivery_time) {
            return 30; // Default fallback
        }

        $parts = explode('-', $store->delivery_time);
        $minTime = (int) ($parts[0] ?? 30);

        // Check if the time is in hours or days and convert to minutes
        $deliveryTime = strtolower($store->delivery_time);
        if (str_contains($deliveryTime, 'hour')) {
            $minTime *= 60;
        } elseif (str_contains($deliveryTime, 'day')) {
            $minTime *= 1440;
        }

        return $minTime;
    }

    /**
     * Calculate travel time in minutes based on distance.
     *
     * @param float|null $distance in kilometers
     * @return int minutes
     */
    protected static function getTravelMinutes(?float $distance): int
    {
        if (!$distance || $distance <= 0) {
            return 10; // Default for unknown distance
        }

        return (int) ceil($distance * self::MINUTES_PER_KM);
    }
}
