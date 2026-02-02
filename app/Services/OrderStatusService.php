<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryMan;
use App\Models\OrderStatusLog;
use App\CentralLogics\Helpers;
use App\CentralLogics\OrderLogic;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Centralized Order Status Service
 * 
 * This service handles all order status transitions with proper validation,
 * atomic operations, notification handling, and audit logging to ensure 
 * consistency across Admin, Vendor, and Deliveryman controllers.
 */
class OrderStatusService
{
    /**
     * Get valid transitions from config or fallback
     */
    protected static function getValidTransitions(): array
    {
        return config('order.valid_transitions', [
            'pending' => ['confirmed', 'accepted', 'canceled'],
            'confirmed' => ['accepted', 'processing', 'canceled'],
            'accepted' => ['processing', 'handover', 'canceled'],
            'processing' => ['handover', 'canceled'],
            'handover' => ['picked_up', 'canceled'],
            'picked_up' => ['out_for_delivery', 'delivered', 'canceled'],
            'out_for_delivery' => ['delivered', 'canceled'],
            'delivered' => ['refund_requested'],
            'refund_requested' => ['refunded'],
            'canceled' => [],
            'refunded' => [],
            'failed' => [],
        ]);
    }

    /**
     * Validate if a status transition is allowed
     *
     * @param string $currentStatus
     * @param string $newStatus
     * @return bool
     */
    public static function validateTransition(string $currentStatus, string $newStatus): bool
    {
        $validTransitions = self::getValidTransitions();
        
        if (!isset($validTransitions[$currentStatus])) {
            return false;
        }

        return in_array($newStatus, $validTransitions[$currentStatus]);
    }

    /**
     * Get available next statuses for an order
     *
     * @param Order $order
     * @return array
     */
    public static function getAvailableTransitions(Order $order): array
    {
        $validTransitions = self::getValidTransitions();
        $currentStatus = $order->order_status;
        
        if (!isset($validTransitions[$currentStatus])) {
            return [];
        }

        return $validTransitions[$currentStatus];
    }

    /**
     * Update order status with all related operations
     *
     * @param Order $order
     * @param string $newStatus
     * @param string $updatedBy - 'admin', 'vendor', 'deliveryman', 'customer'
     * @param array $options - Additional options like 'reason', 'otp', 'processing_time', 'updated_by_id'
     * @return array ['success' => bool, 'message' => string]
     */
    public static function updateStatus(Order $order, string $newStatus, string $updatedBy, array $options = []): array
    {
        $previousStatus = $order->order_status;
        
        // Validate transition
        if (!self::validateTransition($previousStatus, $newStatus)) {
            return [
                'success' => false,
                'message' => translate('messages.invalid_status_transition')
            ];
        }

        try {
            return DB::transaction(function() use ($order, $newStatus, $updatedBy, $previousStatus, $options) {
                // Lock order for update
                $order = Order::where('id', $order->id)->lockForUpdate()->first();
                
                // Handle specific status transitions
                $result = match($newStatus) {
                    'delivered' => self::handleDelivered($order, $updatedBy, $options),
                    'canceled' => self::handleCanceled($order, $updatedBy, $options),
                    'refunded' => self::handleRefunded($order, $options),
                    default => ['success' => true, 'message' => '']
                };

                if (!$result['success']) {
                    return $result;
                }

                // Update order status
                $order->order_status = $newStatus;
                
                if ($newStatus == 'processing' && isset($options['processing_time'])) {
                    $order->processing_time = $options['processing_time'];
                }
                
                $order[$newStatus] = now();
                $order->save();

                // Log the status change (audit trail)
                self::logStatusChange($order, $previousStatus, $newStatus, $updatedBy, $options);

                // Send notifications
                try {
                    Helpers::send_order_notification($order);
                } catch (\Exception $e) {
                    info('Order status notification failed: ' . $e->getMessage());
                }

                return [
                    'success' => true,
                    'message' => translate('messages.order_status_updated')
                ];
            });
        } catch (\Exception $e) {
            info('Order status update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => translate('messages.order_status_update_failed')
            ];
        }
    }

    /**
     * Handle delivered status transition
     */
    protected static function handleDelivered(Order $order, string $updatedBy, array $options): array
    {
        // Create transaction if not exists
        if ($order->transaction == null) {
            $receivedBy = match($updatedBy) {
                'store' => 'store',
                'deliveryman' => 'deliveryman',
                default => $order->payment_method == 'cash_on_delivery' ? 'deliveryman' : 'admin'
            };

            $result = OrderLogic::create_transaction($order, $receivedBy, null);
            if (!$result) {
                return [
                    'success' => false,
                    'message' => translate('messages.faield_to_create_order_transaction')
                ];
            }
        }

        $order->payment_status = 'paid';

        // Update delivery man
        if ($order->delivery_man) {
            $dm = DeliveryMan::where('id', $order->delivery_man_id)->lockForUpdate()->first();
            $dm->current_orders = max(0, $dm->current_orders - 1);
            $dm->order_count = $dm->order_count + 1;
            $dm->save();
        }

        // Increment order counts
        $order->details->each(function ($item) {
            if ($item->item) {
                $item->item->increment('order_count');
            }
        });
        
        $order?->customer?->increment('order_count');
        $order?->store?->increment('order_count');
        $order?->parcel_category?->increment('orders_count');

        OrderLogic::update_unpaid_order_payment(order_id: $order->id, payment_method: $order->payment_method);

        return ['success' => true, 'message' => ''];
    }

    /**
     * Handle canceled status transition
     */
    protected static function handleCanceled(Order $order, string $updatedBy, array $options): array
    {
        if (in_array($order->order_status, ['delivered', 'canceled', 'refund_requested', 'refunded', 'failed'])) {
            return [
                'success' => false,
                'message' => translate('messages.you_can_not_cancel_a_completed_order')
            ];
        }

        $order->cancellation_reason = $options['reason'] ?? null;
        $order->canceled_by = $updatedBy;

        // Release delivery man
        if ($order->delivery_man) {
            $dm = DeliveryMan::where('id', $order->delivery_man_id)->lockForUpdate()->first();
            $dm->current_orders = max(0, $dm->current_orders - 1);
            $dm->save();
        }

        // Refund if needed
        if ($order->is_guest == 0) {
            OrderLogic::refund_before_delivered($order);
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Handle refunded status transition
     */
    protected static function handleRefunded(Order $order, array $options): array
    {
        if ($order->payment_status == "unpaid") {
            return [
                'success' => false,
                'message' => translate('messages.you_can_not_refund_a_cod_order')
            ];
        }

        if (isset($order->delivered)) {
            $result = OrderLogic::refund_order($order);
            if (!$result) {
                return [
                    'success' => false,
                    'message' => translate('messages.faield_to_create_order_transaction')
                ];
            }
        }

        // Release delivery man
        if ($order->delivery_man) {
            $dm = DeliveryMan::where('id', $order->delivery_man_id)->lockForUpdate()->first();
            $dm->current_orders = max(0, $dm->current_orders - 1);
            $dm->save();
        }

        return ['success' => true, 'message' => ''];
    }

    /**
     * Verify OTP with rate limiting (uses config values)
     *
     * @param Order $order
     * @param string $otp
     * @return array ['success' => bool, 'message' => string]
     */
    public static function verifyOTP(Order $order, string $otp): array
    {
        $cacheKey = "otp_attempts_{$order->id}";
        $maxAttempts = config('order.otp_max_attempts', 5);
        $decayMinutes = config('order.otp_decay_minutes', 15);

        // Check rate limit
        $attempts = Cache::get($cacheKey, 0);
        if ($attempts >= $maxAttempts) {
            return [
                'success' => false,
                'message' => translate('messages.too_many_otp_attempts')
            ];
        }

        // Verify OTP
        if ($order->otp != $otp) {
            Cache::put($cacheKey, $attempts + 1, now()->addMinutes($decayMinutes));
            
            $remainingAttempts = $maxAttempts - $attempts - 1;
            return [
                'success' => false,
                'message' => translate('Otp Not matched') . ". {$remainingAttempts} " . translate('attempts remaining')
            ];
        }

        // Clear attempts on success
        Cache::forget($cacheKey);

        return [
            'success' => true,
            'message' => ''
        ];
    }

    /**
     * Log status change for audit trail using OrderStatusLog model
     */
    protected static function logStatusChange(
        Order $order, 
        string $previousStatus, 
        string $newStatus, 
        string $updatedBy,
        array $options = []
    ): void {
        try {
            OrderStatusLog::logStatusChange(
                order: $order,
                previousStatus: $previousStatus,
                newStatus: $newStatus,
                updatedByType: $updatedBy,
                updatedById: $options['updated_by_id'] ?? null,
                reason: $options['reason'] ?? null,
                metadata: [
                    'processing_time' => $options['processing_time'] ?? null,
                    'order_amount' => $order->order_amount,
                    'payment_method' => $order->payment_method,
                ]
            );
        } catch (\Exception $e) {
            // Log failure but don't break the main flow
            info("Failed to log order status change: " . $e->getMessage());
        }
    }

    /**
     * Get order status history timeline
     */
    public static function getOrderTimeline(int $orderId): array
    {
        return OrderStatusLog::getOrderTimeline($orderId);
    }
}
