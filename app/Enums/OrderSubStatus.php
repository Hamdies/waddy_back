<?php

namespace App\Enums;

/**
 * Order Sub-Status Constants
 * 
 * These provide granular tracking statuses within main order statuses.
 */
class OrderSubStatus
{
    // Processing sub-statuses (when order_status = 'processing' or 'confirmed')
    const PREPARING = 'preparing';
    const PACKAGING = 'packaging';
    const READY = 'ready';
    
    // Delivery sub-statuses (when order_status = 'picked_up' or 'handover')
    const EN_ROUTE = 'en_route';
    const NEARBY = 'nearby';      // Driver is < 500m from delivery address
    const ARRIVED = 'arrived';    // Driver has arrived at delivery location
    
    /**
     * Get all processing sub-statuses
     */
    public static function processingStatuses(): array
    {
        return [
            self::PREPARING,
            self::PACKAGING,
            self::READY,
        ];
    }
    
    /**
     * Get all delivery sub-statuses
     */
    public static function deliveryStatuses(): array
    {
        return [
            self::EN_ROUTE,
            self::NEARBY,
            self::ARRIVED,
        ];
    }
    
    /**
     * Get all sub-statuses
     */
    public static function all(): array
    {
        return array_merge(self::processingStatuses(), self::deliveryStatuses());
    }
    
    /**
     * Check if a sub-status is valid
     */
    public static function isValid(?string $status): bool
    {
        return $status === null || in_array($status, self::all());
    }
    
    /**
     * Get display label for a sub-status
     */
    public static function label(string $status): string
    {
        return match($status) {
            self::PREPARING => 'Preparing your order',
            self::PACKAGING => 'Packaging your order',
            self::READY => 'Ready for pickup',
            self::EN_ROUTE => 'On the way',
            self::NEARBY => 'Almost there',
            self::ARRIVED => 'Driver has arrived',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
