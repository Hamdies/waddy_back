<?php

namespace App\Services;

use App\Models\Order;
use App\Models\LiveActivityToken;
use App\Models\User;
use App\Traits\NotificationTrait;
use Carbon\Carbon;

/**
 * Service for handling order-related push notifications
 */
class OrderNotificationService
{
    use NotificationTrait;

    /**
     * Step mapping for order progress tracking
     */
    private const STATUS_STEPS = [
        'pending'    => ['step' => 0, 'progress' => 0.0],
        'confirmed'  => ['step' => 1, 'progress' => 0.2],
        'processing' => ['step' => 2, 'progress' => 0.4],
        'handover'   => ['step' => 3, 'progress' => 0.6],
        'picked_up'  => ['step' => 4, 'progress' => 0.8],
        'delivered'  => ['step' => 5, 'progress' => 1.0],
    ];

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
        
        // Build base data
        $data = [
            'title' => $message['title'],
            'description' => $message['body'],
            'order_id' => (string) $order->id,
            'type' => 'order_status',
            'image' => '',
        ];
        
        // Merge extended payload fields for rich client-side updates
        $data = array_merge($data, self::buildExtendedPayload($order));
        
        $fcmResult = (bool) self::sendPushNotificationToDevice(
            $user->cm_firebase_token,
            $data
        );

        // Also push to iOS Live Activity if token exists
        $this->sendLiveActivityUpdate($order);

        return $fcmResult;
    }

    /**
     * Build extended payload fields for order status notifications.
     *
     * These fields are added to FCM data so clients (especially Android
     * in background) can update UI without an API call.
     *
     * @param Order $order
     * @return array All values cast to strings (FCM data requirement)
     */
    public static function buildExtendedPayload(Order $order): array
    {
        // Eager-load relationships if not already loaded
        if (!$order->relationLoaded('store')) {
            $order->load('store');
        }
        if (!$order->relationLoaded('delivery_man')) {
            $order->load('delivery_man');
        }

        $statusKey = $order->order_status ?? 'pending';
        $stepInfo = self::STATUS_STEPS[$statusKey] ?? self::STATUS_STEPS['pending'];

        // Calculate ETA from estimated_delivery_at
        $etaMinutes = null;
        $etaText = '';
        if ($order->estimated_delivery_at && !in_array($statusKey, ['delivered', 'canceled', 'refunded'])) {
            $now = now();
            $eta = Carbon::parse($order->estimated_delivery_at);
            $etaMinutes = max(0, (int) $now->diffInMinutes($eta, false));
            $etaText = $etaMinutes <= 1 ? 'Arriving now' : "Arriving in {$etaMinutes} mins";
        }

        // Friendly title/subtitle for Live Activity display
        $displayTitle = self::getStatusDisplayTitle($statusKey);
        $displaySubtitle = self::getStatusDisplaySubtitle($statusKey, $order->sub_status);

        return [
            'status'            => (string) $statusKey,
            'sub_status'        => (string) ($order->sub_status ?? ''),
            'eta_minutes'       => (string) ($etaMinutes ?? ''),
            'eta_text'          => (string) $etaText,
            'store_name'        => (string) ($order->store->name ?? ''),
            'delivery_man_name' => (string) ($order->delivery_man ? $order->delivery_man->f_name : ''),
            'progress'          => (string) $stepInfo['progress'],
            'step'              => (string) $stepInfo['step'],
            'display_title'     => (string) $displayTitle,
            'display_subtitle'  => (string) $displaySubtitle,
        ];
    }

    /**
     * Send APNs push to iOS Live Activity token if one exists for this order.
     */
    public function sendLiveActivityUpdate(Order $order): bool
    {
        try {
            $liveActivityToken = LiveActivityToken::where('order_id', $order->id)->first();
            if (!$liveActivityToken) {
                return false;
            }

            $isEndEvent = in_array($order->order_status, ['delivered', 'canceled', 'refunded', 'failed']);

            return app(LiveActivityService::class)->pushUpdate(
                pushToken: $liveActivityToken->push_token,
                order: $order,
                event: $isEndEvent ? 'end' : 'update'
            );
        } catch (\Exception $e) {
            \Log::warning('Live Activity push failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get display title for a status (used in Live Activity / notification)
     */
    private static function getStatusDisplayTitle(string $status): string
    {
        return match ($status) {
            'pending'    => 'Order Placed',
            'confirmed'  => 'Order Confirmed',
            'processing' => 'Preparing',
            'handover'   => 'Ready for Pickup',
            'picked_up'  => 'On The Way',
            'delivered'  => 'Delivered',
            'canceled'   => 'Canceled',
            default      => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Get display subtitle based on status and sub-status
     */
    private static function getStatusDisplaySubtitle(string $status, ?string $subStatus): string
    {
        if ($subStatus) {
            return match ($subStatus) {
                'preparing' => 'Kitchen is working on your order',
                'packaging' => 'Your order is being packaged',
                'ready'     => 'Waiting for driver pickup',
                'en_route'  => 'Driver is on the way',
                'nearby'    => 'Driver is almost there',
                'arrived'   => 'Driver has arrived',
                default     => ucfirst(str_replace('_', ' ', $subStatus)),
            };
        }

        return match ($status) {
            'pending'    => 'Waiting for confirmation',
            'confirmed'  => 'Restaurant is preparing your food',
            'processing' => 'Your order is being prepared',
            'handover'   => 'Ready and waiting for driver',
            'picked_up'  => 'Driver is on the way',
            'delivered'  => 'Enjoy your meal!',
            'canceled'   => 'Your order was canceled',
            default      => '',
        };
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
