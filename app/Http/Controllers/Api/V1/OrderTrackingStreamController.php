<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Order;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;

class OrderTrackingStreamController extends Controller
{
    /**
     * Stream real-time order tracking updates via SSE
     * 
     * @param Request $request
     * @param int $orderId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function stream(Request $request, $orderId)
    {
        $order = Order::with('delivery_man')->find($orderId);
        
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }
        
        // Verify user can access this order
        if (!$this->canAccessOrder($request, $order)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->stream(function () use ($order, $orderId) {
            $lastDataHash = null;
            $startTime = time();
            $maxDuration = 300; // 5 minutes max connection time
            
            // Disable output buffering
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            while (true) {
                // Prevent infinite connections - max 5 minutes
                if ((time() - $startTime) > $maxDuration) {
                    echo "event: timeout\n";
                    echo "data: {\"message\": \"Connection timeout, please reconnect\"}\n\n";
                    break;
                }
                
                // Refresh order data from database
                $order = Order::with('delivery_man')->find($orderId);
                
                if (!$order) {
                    echo "event: error\n";
                    echo "data: {\"error\": \"Order not found\"}\n\n";
                    break;
                }
                
                $data = $this->buildTrackingData($order);
                $dataJson = json_encode($data);
                $dataHash = md5($dataJson);
                
                // Only send if data changed
                if ($dataHash !== $lastDataHash) {
                    echo "event: tracking_update\n";
                    echo "data: {$dataJson}\n\n";
                    $lastDataHash = $dataHash;
                }
                
                // Flush output
                if (ob_get_level()) {
                    ob_flush();
                }
                flush();
                
                // Send heartbeat to keep connection alive and detect disconnections
                echo ": heartbeat\n\n";
                
                // Stop streaming for terminal statuses
                if (in_array($order->order_status, ['delivered', 'canceled', 'failed', 'refunded'])) {
                    echo "event: completed\n";
                    echo "data: {\"message\": \"Order completed\", \"status\": \"{$order->order_status}\"}\n\n";
                    break;
                }
                
                // Check if client disconnected
                if (connection_aborted()) {
                    break;
                }
                
                // Wait 3 seconds before next update
                sleep(3);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no', // For nginx
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
    
    /**
     * Build tracking data array for SSE response
     */
    private function buildTrackingData(Order $order): array
    {
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
                'lat' => $dm->lat ? (float) $dm->lat : null,
                'lng' => $dm->lng ? (float) $dm->lng : null,
                'heading' => $dm->heading ?? 0,
                'speed' => $dm->speed ?? 0,
                'image' => $dm->image_full_url ?? null,
            ];
        }
        
        return $data;
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
