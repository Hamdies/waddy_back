<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use App\Traits\NotificationTrait;

/**
 * Service for handling order-related push notifications
 */
class OrderNotificationService
{
    use NotificationTrait;

    /**
     * Notification messages for each status/sub-status
     */
    private array $messages = [
        // Main statuses
        'confirmed' => [
            'title' => 'Order Confirmed! ✓',
            'body' => 'Your order has been confirmed and is being prepared'
        ],
        'processing' => [
            'title' => 'Preparing Your Order 👨‍🍳',
            'body' => 'The kitchen has started working on your order'
        ],
        'handover' => [
            'title' => 'Order Ready! 📦',
            'body' => 'Your order is ready and waiting for driver pickup'
        ],
        'picked_up' => [
            'title' => 'On The Way! 🚗',
            'body' => 'Driver has picked up your order and is heading your way'
        ],
        'delivered' => [
            'title' => 'Order Delivered! 🎉',
            'body' => 'Enjoy your order! Thank you for ordering with us'
        ],
        
        // Sub-statuses
        'preparing' => [
            'title' => 'Preparing Your Order 👨‍🍳',
            'body' => 'Your order is being prepared'
        ],
        'packaging' => [
            'title' => 'Almost Ready! 📦',
            'body' => 'Your order is being packaged'
        ],
        'ready' => [
            'title' => 'Order Ready! ✅',
            'body' => 'Your order is ready and waiting for pickup'
        ],
        'en_route' => [
            'title' => 'On The Way! 🚗',
            'body' => 'Driver is on the way to you'
        ],
        'nearby' => [
            'title' => 'Almost There! 📍',
            'body' => 'Driver is less than 500m away from you'
        ],
        'arrived' => [
            'title' => 'Driver Arrived! 🎉',
            'body' => 'Please collect your order from the driver'
        ],
    ];

    /**
     * Send notification when order status changes
     */
    public function notifyStatusChange(Order $order): bool
    {
        $status = $order->sub_status ?? $order->order_status;
        
        if (!isset($this->messages[$status])) {
            return false;
        }
        
        $message = $this->messages[$status];
        $user = $order->customer;
        
        if (!$user || !$user->cm_firebase_token) {
            return false;
        }
        
        $data = [
            'title' => $message['title'],
            'description' => $message['body'],
            'order_id' => (string) $order->id,
            'type' => 'order_status',
            'image' => '',
        ];
        
        return (bool) self::sendPushNotificationToDevice(
            $user->cm_firebase_token,
            $data
        );
    }

    /**
     * Check proximity and trigger "nearby" notification if driver is within 500m
     * 
     * @param Order $order
     * @param float $driverLat Driver's current latitude
     * @param float $driverLng Driver's current longitude
     * @return bool True if notification was sent
     */
    public function checkProximityNotification(Order $order, float $driverLat, float $driverLng): bool
    {
        // Only check for picked_up orders that haven't been marked as nearby
        if ($order->order_status !== 'picked_up' || $order->sub_status === 'nearby' || $order->sub_status === 'arrived') {
            return false;
        }
        
        $deliveryAddress = json_decode($order->delivery_address, true);
        
        if (!$deliveryAddress || !isset($deliveryAddress['latitude'], $deliveryAddress['longitude'])) {
            return false;
        }
        
        $distance = $this->calculateDistance(
            $driverLat,
            $driverLng,
            (float) $deliveryAddress['latitude'],
            (float) $deliveryAddress['longitude']
        );
        
        // If driver is within 500 meters
        if ($distance < 0.5) {
            $order->update([
                'sub_status' => 'nearby',
                'sub_status_updated_at' => now(),
            ]);
            
            return $this->notifyStatusChange($order);
        }
        
        return false;
    }

    /**
     * Calculate distance between two coordinates in kilometers (Haversine formula)
     */
    private function calculateDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }

    /**
     * Get notification message for a specific status
     */
    public function getMessage(string $status): ?array
    {
        return $this->messages[$status] ?? null;
    }
}
