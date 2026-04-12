<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\OrderTrackingService;

class OrderTrackingStreamController extends Controller
{
    /**
     * Return a lightweight order tracking snapshot.
     *
     * The old SSE implementation kept the request open for minutes and
     * delayed order detail loading, so this endpoint now returns one JSON
     * payload and lets the client refresh explicitly when needed.
     *
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function stream(Request $request, $orderId, OrderTrackingService $trackingService)
    {
        $order = Order::with([
            'delivery_man',
            'tracking_logs' => function ($query) {
                $query->latest()->limit(10);
            },
        ])->find($orderId);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        // Verify user can access this order
        if (!$this->canAccessOrder($request, $order)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $data = $trackingService->getCurrentTrackingData($order);
        $data['tracking_logs'] = $order->tracking_logs
            ->values()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'status' => $log->status,
                    'sub_status' => $log->sub_status,
                    'lat' => $log->lat,
                    'lng' => $log->lng,
                    'heading' => $log->heading,
                    'speed' => $log->speed,
                    'created_at' => $log->created_at?->toIso8601String(),
                ];
            })
            ->all();

        return response()->json($data, 200);
    }

    /**
     * Check if the request can access the order
     */
    private function canAccessOrder(Request $request, Order $order): bool
    {
        // Check if logged-in user owns the order
        if ($request->user() && $request->user()->id === $order->user_id) {
            return true;
        }
        
        // Check guest access with contact number
        if ($request->has('contact_number')) {
            $contactNumber = $request->contact_number;
            
            // Normalize phone number
            if (substr($contactNumber, 0, 1) !== '+') {
                $contactNumber = '+' . $contactNumber;
            }
            
            $deliveryAddress = json_decode($order->delivery_address, true);
            
            if ($deliveryAddress && isset($deliveryAddress['contact_person_number'])) {
                $orderPhone = $deliveryAddress['contact_person_number'];
                
                // Normalize order phone
                if (substr($orderPhone, 0, 1) !== '+') {
                    $orderPhone = '+' . $orderPhone;
                }
                
                return $contactNumber === $orderPhone;
            }
        }
        
        // For guest orders, check guest ID
        if ($order->is_guest && $request->has('guest_id')) {
            return $order->user_id == $request->guest_id;
        }
        
        return false;
    }
}
