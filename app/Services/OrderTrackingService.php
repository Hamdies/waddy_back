<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryMan;
use App\Models\OrderTrackingLog;

/**
 * Service for handling order tracking operations
 */
class OrderTrackingService
{
    protected OrderNotificationService $notificationService;

    public function __construct(OrderNotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Log a location update for an order
     * 
     * @param Order $order
     * @param DeliveryMan $deliveryMan
     * @return OrderTrackingLog
     */
    public function logLocationUpdate(Order $order, DeliveryMan $deliveryMan): OrderTrackingLog
    {
        $log = OrderTrackingLog::create([
            'order_id' => $order->id,
            'status' => $order->order_status,
            'sub_status' => $order->sub_status,
            'lat' => $deliveryMan->lat,
            'lng' => $deliveryMan->lng,
            'heading' => $deliveryMan->heading ?? 0,
            'speed' => $deliveryMan->speed ?? 0,
        ]);

        // Check if we should send a proximity notification
        if ($deliveryMan->lat && $deliveryMan->lng) {
            $this->notificationService->checkProximityNotification(
                $order,
                (float) $deliveryMan->lat,
                (float) $deliveryMan->lng
            );
        }

        return $log;
    }

    /**
     * Get tracking history for an order
     * 
     * @param int $orderId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getTrackingHistory(int $orderId, int $limit = 100)
    {
        return OrderTrackingLog::forOrder($orderId)
            ->latestFirst()
            ->limit($limit)
            ->get();
    }

    /**
     * Get the current tracking data for an order
     * 
     * @param Order $order
     * @return array
     */
    public function getCurrentTrackingData(Order $order): array
    {
        $order->load('delivery_man');

        $data = [
            'order_id' => $order->id,
            'status' => $order->order_status,
            'sub_status' => $order->sub_status,
            'delivery_man' => null,
            'timestamp' => now()->toIso8601String(),
        ];

        if ($order->delivery_man) {
            $dm = $order->delivery_man;
            $data['delivery_man'] = [
                'id' => $dm->id,
                'name' => $dm->f_name . ' ' . $dm->l_name,
                'phone' => $dm->phone,
                'lat' => $dm->lat,
                'lng' => $dm->lng,
                'heading' => $dm->heading ?? 0,
                'speed' => $dm->speed ?? 0,
                'image' => $dm->image_full_url,
            ];
        }

        return $data;
    }

    /**
     * Update order sub-status
     * 
     * @param Order $order
     * @param string $subStatus
     * @param bool $notify Whether to send push notification
     * @return bool
     */
    public function updateSubStatus(Order $order, string $subStatus, bool $notify = true): bool
    {
        $order->update([
            'sub_status' => $subStatus,
            'sub_status_updated_at' => now(),
        ]);

        if ($notify) {
            $this->notificationService->notifyStatusChange($order);
        }

        return true;
    }
}
