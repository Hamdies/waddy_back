<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTrackingLog;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;

class OrderTrackingHistoryController extends Controller
{
    /**
     * Get tracking history for an order
     * 
     * @param Request $request
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request, $orderId)
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        }
        
        // Verify user can access this order
        if (!$this->canAccessOrder($request, $order)) {
            return response()->json([
                'errors' => [
                    ['code' => 'unauthorized', 'message' => translate('messages.unauthorized')]
                ]
            ], 403);
        }
        
        $perPage = $request->get('per_page', 50);
        $perPage = min($perPage, 100); // Max 100 per page
        
        $logs = OrderTrackingLog::where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json([
            'order_id' => $order->id,
            'current_status' => $order->order_status,
            'current_sub_status' => $order->sub_status,
            'tracking_logs' => $logs->items(),
            'pagination' => [
                'total' => $logs->total(),
                'per_page' => $logs->perPage(),
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
        ]);
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
