<?php

namespace App\Http\Controllers\Api\V1;

ini_set('memory_limit', '-1');

use App\Models\Order;
use App\Library\Payer;
use App\Models\OrderTransaction;
use App\Traits\Payment;
use App\Library\Receiver;
use App\Models\DeliveryMan;
use App\Models\Notification;
use App\Models\OrderPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DeliveryHistory;
use App\Models\ProvideDMEarning;
use App\Models\UserNotification;
use App\Models\WithdrawalMethod;
use App\CentralLogics\OrderLogic;
use App\Models\DeliveryManWallet;
use App\Models\AccountTransaction;
use Illuminate\Support\Facades\DB;
use App\Models\DisbursementDetails;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use App\Library\Payment as PaymentInfo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use App\Models\DisbursementWithdrawalMethod;



class DeliverymanController extends Controller
{

    public function get_profile(Request $request)
    {
        $dm = $request['dm']->loadMissing('rating');
        // Cached settings instead of three uncached business_settings reads.
        $min_amount_to_pay_dm = Helpers::get_business_settings('min_amount_to_pay_dm') ?? 0;
        $dm['avg_rating'] = (double)(!empty($dm->rating[0])?$dm->rating[0]->average:0);
        $dm['rating_count'] = (double)(!empty($dm->rating[0])?$dm->rating[0]->rating_count:0);
        // One COUNT query instead of loading every order the rider ever had
        // (B1). Same windows as todaysorders / this_week_orders.
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        $orderCounts = $dm->orders()->withoutGlobalScope('storage')->selectRaw(
            'count(*) as total, coalesce(sum(date(accepted) = ?), 0) as today, coalesce(sum(accepted between ? and ?), 0) as week',
            [now()->toDateString(), $weekStart, $weekEnd]
        )->first();
        $dm['order_count'] = (integer) ($orderCounts?->total ?? 0);
        $dm['todays_order_count'] = (integer) ($orderCounts?->today ?? 0);
        $dm['this_week_order_count'] = (integer) ($orderCounts?->week ?? 0);
        $dm['member_since_days'] =(integer)$dm->created_at->diffInDays();

        // Delivery fee + tips for today / this week / this month, and today's
        // tips alone, in one query instead of seven SUMs. Same windows as the
        // todays_earning, this_week_earning and this_month_earning relations.
        $amount = 'coalesce(original_delivery_charge, 0) + coalesce(dm_tips, 0)';
        $earnings = $dm->order_transaction()->selectRaw(
            "coalesce(sum(case when date(created_at) = ? then $amount end), 0) as today,
             coalesce(sum(case when date(created_at) = ? then coalesce(dm_tips, 0) end), 0) as today_tips,
             coalesce(sum(case when created_at between ? and ? then $amount end), 0) as week,
             coalesce(sum(case when month(created_at) = ? and year(created_at) = ? then $amount end), 0) as month",
            [now()->toDateString(), now()->toDateString(), $weekStart, $weekEnd, now()->month, now()->year]
        )->first();
        $dm['todays_earning'] = (float) ($earnings?->today ?? 0);
        $dm['this_week_earning'] = (float) ($earnings?->week ?? 0);
        $dm['this_month_earning'] = (float) ($earnings?->month ?? 0);
        // The rider home splits today's total into fees and tips.
        $dm['todays_tips'] = (float) ($earnings?->today_tips ?? 0);
        $dm['zone_name'] = $dm->zone?->name;
        $dm['vehicle_type'] = $dm->vehicle?->type;

        $dm['cash_in_hands'] =$dm->wallet?$dm->wallet->collected_cash:0;
        $dm['balance'] = $dm->wallet?$dm->wallet->total_earning - ($dm->wallet->total_withdrawn +$dm?->wallet?->pending_withdraw) :0;
        $dm['total_withdrawn'] = (float) ($dm?->wallet?->total_withdrawn ?? 0);
        $dm['total_earning'] = (float) ($dm?->wallet?->total_earning ?? 0);
        $dm['withdraw_able_balance'] =(float)( $dm['balance'] - $dm?->wallet?->collected_cash > 0 ? abs($dm['balance'] - $dm?->wallet?->collected_cash ): 0 );
        $dm['Payable_Balance'] =(float)(  $dm?->wallet?->collected_cash ?? 0 );


        $over_flow_balance = $dm['balance'] - $dm?->wallet?->collected_cash ;

        $wallet_earning =  round($dm?->wallet?->total_earning - ($dm?->wallet?->total_withdrawn +$dm?->wallet?->pending_withdraw) ,8);
        if(isset($dm?->wallet) && (($over_flow_balance > 0 && $dm?->wallet?->collected_cash > 0 ) || ($dm?->wallet?->collected_cash != 0 && $dm['balance'] !=  0)) ){
            $dm['adjust_able'] = true;

        }  elseif( isset($dm?->wallet) &&  $over_flow_balance == $dm['balance']  ){
            $dm['adjust_able'] = false;
        }
        else{
            $dm['adjust_able'] = false;
        }

        if($dm?->wallet?->collected_cash == 0 ||  $wallet_earning == 0 ){
            $dm['adjust_able'] = false;
        }

        $dm['show_pay_now_button'] = false;

        $digital_payment = Helpers::get_business_settings('digital_payment');
        if ($min_amount_to_pay_dm <= $dm?->wallet?->collected_cash && $digital_payment['status'] == 1 ){
            $dm['show_pay_now_button'] = true;
        }


        $Payable_Balance =  $dm?->wallet?->collected_cash > 0 ? 1: 0;
        $cash_in_hand_overflow = Helpers::get_business_settings('cash_in_hand_overflow_delivery_man');
        $cash_in_hand_overflow_delivery_man = Helpers::get_business_settings('dm_max_cash_in_hand');
        $val=  $cash_in_hand_overflow_delivery_man - (($cash_in_hand_overflow_delivery_man * 10)/100);
        $dm['over_flow_warning'] = false;

        if($Payable_Balance == 1 &&  $cash_in_hand_overflow &&  $over_flow_balance < 0 &&  $val <=  abs($dm?->wallet?->collected_cash)){

            $dm['over_flow_warning'] = true;
        }

        // Cash at which new cash orders stop; null when the cap is off.
        $dm['cash_in_hand_limit'] = $cash_in_hand_overflow ? (float)$cash_in_hand_overflow_delivery_man : null;

        $dm['over_flow_block_warning'] = false;
        if ($Payable_Balance == 1 &&  $cash_in_hand_overflow &&  $over_flow_balance < 0 &&  $cash_in_hand_overflow_delivery_man < abs($dm?->wallet?->collected_cash)){
            $dm['over_flow_block_warning'] = true;
        }

        $zoneWasLoaded = $dm->relationLoaded('zone');
        $topics = $dm->pushTopics();
        if (!$zoneWasLoaded) {
            $dm->unsetRelation('zone'); // keep the payload as it was
        }
        $dm['topic'] = $topics['topic'];
        $dm['zone_topic'] = $topics['zone_topic'];

        unset($dm['orders']);
        unset($dm['rating']);
        unset($dm['todaysorders']);
        unset($dm['this_week_orders']);
        unset($dm['wallet']);
        unset($dm['zone']);
        unset($dm['vehicle']);
        return response()->json($dm, 200);
    }

    public function update_profile(Request $request)
    {
        $dm = $request['dm']->loadMissing('rating');
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'l_name' => 'required',
            'email' => 'required|unique:delivery_men,email,'.$dm->id,
            'password' => ['nullable', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
        ], [
            'f_name.required' => 'First name is required!',
            'l_name.required' => 'Last name is required!',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $image = $request->file('image');

        if ($request->has('image')) {
            $imageName = Helpers::update('delivery-man/', $dm->image, 'png', $request->file('image'));
        } else {
            $imageName = $dm->image;
        }

        if ($request['password'] != null && strlen($request['password']) > 5) {
            $pass = bcrypt($request['password']);
        } else {
            $pass = $dm->password;
        }

        $dm->vehicle_id = $request->vehicle_id ??  $dm->vehicle_id ?? null;

        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->image = $imageName;
        $dm->password = $pass;
        $dm->updated_at = now();
        $dm->save();

        if($dm->userinfo) {
            $userinfo = $dm->userinfo;
            $userinfo->f_name = $request->f_name;
            $userinfo->l_name = $request->l_name;
            $userinfo->email = $request->email;
            $userinfo->image = $imageName;
            $userinfo->save();
        }

        return response()->json(['message' => translate('successfully updated!')], 200);
    }

    public function activeStatus(Request $request)
    {
        $dm = $request['dm']->loadMissing('rating');
        // An explicit `active` (0/1) makes a retried or double-tapped request
        // harmless. Without it (older rider builds) this stays a toggle.
        if ($request->has('active') && in_array((string) $request['active'], ['0', '1'], true)) {
            $dm->active = (int) $request['active'];
        } else {
            $dm->active = $dm->active ? 0 : 1;
        }
        $dm->save();
        return response()->json(['message' => translate('messages.active_status_updated'), 'active' => (int) $dm->active], 200);
    }

    public function get_current_orders(Request $request)
    {
        $dm = $request['dm'];
        $orders = Order::with(['customer', 'store','parcel_category'])
        ->whereIn('order_status', ['accepted','confirmed','pending', 'processing', 'picked_up', 'handover'])
        ->where(['delivery_man_id' => $dm['id']])
        ->orderBy('accepted')
        ->orderBy('schedule_at', 'desc')
        ->dmOrder()
        ->get();
        $orders= Helpers::order_data_formatting($orders, true);
        return response()->json($orders, 200);
    }

    public function get_latest_orders(Request $request)
    {
        $dm = $request['dm'];
        
        // Support optional pagination parameters
        $limit = (int) $request->input('limit', 50);
        $offset = (int) $request->input('offset', 1);

        // Every rider with the same zone/store/vehicle filter gets the same
        // list, and each polls every 10 s, so share it for 5 s (B5). A card
        // that is already taken just gets "can not accept": accept_order
        // locks the row.
        $cacheKey = 'dm_latest_orders:' . implode(':', [
            $dm->type, $dm->zone_id, $dm->store_id, $dm->vehicle_id ?? 0,
            config('order_confirmation_model'), $limit, $offset,
        ]);
        return response()->json(\Illuminate\Support\Facades\Cache::remember($cacheKey, 5,
            // Plain arrays, not models: same JSON out, and safe in any cache store.
            fn () => json_decode(json_encode($this->latest_orders_payload($dm, $limit, $offset)), true)
        ), 200);
    }

    private function latest_orders_payload(DeliveryMan $dm, int $limit, int $offset): array
    {
        $orders = Order::with(['customer', 'store','parcel_category']);

        if($dm->type == 'zone_wise')
        {
            $orders = $orders->where('zone_id', $dm->zone_id)
            ->where(function($query){
                $query->whereNull('store_id')

                    ->orWhere(function($query){
                        $query->whereHas('store', function($q){
                            $q->where('store_business_model','subscription')->whereHas('store_sub', function($q1){
                                $q1->where('self_delivery', 0);
                            });
                        })
                        ->orWhereHas('store', function($qu) {
                            $qu->where('store_business_model','commission')->where('self_delivery_system', 0);
                        });
                    });
            });
        }
        else
        {
            $orders = $orders->where('store_id', $dm->store_id);
        }

        if(config('order_confirmation_model') == 'deliveryman' && $dm->type == 'zone_wise')
        {
            $orders = $orders->whereIn('order_status', ['pending', 'confirmed','processing','handover']);
        }
        else
        {
            $orders = $orders->where(function ($query) {
                return $query->whereIn('order_status', ['confirmed', 'processing', 'handover'])
                    ->orWhere(function ($subQuery) {
                        return  $subQuery->where('order_type', 'parcel')->whereIn('order_status', ['pending','confirmed', 'processing', 'handover']);
                    });
            });
        }
        if(isset($dm->vehicle_id )){
            $orders = $orders->where('dm_vehicle_id',$dm->vehicle_id);
        }
        
        $paginator = $orders->dmOrder()
            ->Notpos()
            ->NotDigitalOrder()
            ->OrderScheduledIn(30)
            ->whereNull('delivery_man_id')
            ->orderBy('schedule_at', 'desc')
            ->paginate($limit, ['*'], 'page', $offset);
            
        $formattedOrders = Helpers::order_data_formatting($paginator->items(), true);
        
        return [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'orders' => $formattedOrders
        ];
    }

    /**
     * What the active-order screen polls every 10 s (B4): one row, no eager
     * loads. The app fetches the full order only when this changes.
     */
    public function get_order_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $order = Order::withoutGlobalScope('storage')
            ->select(['id', 'order_status', 'payment_status', 'updated_at'])
            ->where(['id' => $request['order_id'], 'delivery_man_id' => $request['dm']['id']])
            ->first();
        if (!$order) {
            return response()->json([
                'errors' => [['code' => 'order', 'message' => translate('messages.not_found')]]
            ], 404);
        }
        return response()->json([
            'order_id' => $order->id,
            'order_status' => $order->order_status,
            'payment_status' => $order->payment_status,
            'updated_at' => $order->updated_at,
        ], 200);
    }

    public function accept_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        
        $dm = $request['dm'];
        
        // Pre-validation checks (before transaction)
        if($dm->active != 1) {
            return response()->json([
                'errors' => [
                    ['code' => 'active_status', 'message' => translate('messages.You_can_not_accept_order_on_offline')]
                ]
            ], 404);
        }
        
        if($dm->current_orders >= config('dm_maximum_orders')) {
            return response()->json([
                'errors'=>[
                    ['code' => 'dm_maximum_order_exceed', 'message'=> translate('messages.dm_maximum_order_exceed_warning')]
                ]
            ], 405);
        }

        try {
            return DB::transaction(function() use ($request, $dm) {
                // Use pessimistic locking to prevent race conditions
                $order = Order::where('id', $request['order_id'])
                    ->whereNull('delivery_man_id')
                    ->dmOrder()
                    ->lockForUpdate()
                    ->first();
                    
                if(!$order) {
                    return response()->json([
                        'errors' => [
                            ['code' => 'order', 'message' => translate('messages.can_not_accept')]
                        ]
                    ], 404);
                }

                // Cash in hand validation
                $payments = $order->payments()->where('payment_method','cash_on_delivery')->exists();
                $cash_in_hand = $dm?->wallet?->collected_cash ?? 0;
                $dm_max_cash = BusinessSetting::where('key','dm_max_cash_in_hand')->first();
                $maxCashValue = $dm_max_cash?->value ?? 0;

                if(($order->payment_method == "cash_on_delivery" || $payments) && (($cash_in_hand + $order->order_amount) >= $maxCashValue)) {
                    return response()->json([
                        'errors'=>[
                            ['code' => 'dm_maximum_hand_in_cash', 'message'=> \App\CentralLogics\Helpers::format_currency($maxCashValue) ." ".translate('max_cash_in_hand_exceeds')]
                        ]
                    ], 405);
                }

                // Update order status
                if($order->order_type == 'parcel' && $order->order_status == 'confirmed') {
                    $order->order_status = 'handover';
                    $order->handover = now();
                    $order->processing = now();
                } else {
                    $order->order_status = in_array($order->order_status, ['pending', 'confirmed']) ? 'accepted' : $order->order_status;
                }

                $order->delivery_man_id = $dm->id;
                $order->accepted = now();
                $order->save();

                // Update delivery man with lock
                $dm = DeliveryMan::where('id', $dm->id)->lockForUpdate()->first();
                $dm->current_orders = $dm->current_orders + 1;
                $dm->assigned_order_count = $dm->assigned_order_count + 1;
                $dm->save();

                // Send notification (outside critical section)
                $fcm_token = $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;

                $notificationValue = Helpers::order_status_update_message('accepted', $order->module->module_type);
                $notificationValue = Helpers::text_variable_data_format(
                    value: $notificationValue,
                    store_name: $order->store?->name,
                    order_id: $order->id,
                    user_name: "{$order?->customer?->f_name} {$order?->customer?->l_name}",
                    delivery_man_name: "{$dm->f_name} {$dm->l_name}"
                );
                
                try {
                    if($notificationValue && $fcm_token && Helpers::getNotificationStatusData('customer','customer_order_notification','push_notification_status')) {
                        $data = [
                            'title' => translate('Order_Notification'),
                            'description' => $notificationValue,
                            'order_id' => $order['id'],
                            'image' => '',
                            'type' => 'order_status'
                        ];
                        Helpers::send_push_notif_to_device($fcm_token, $data);
                    }
                } catch (\Exception $e) {
                    // Log notification failure but don't fail the order acceptance
                    info('Order acceptance notification failed: ' . $e->getMessage());
                }

                return response()->json(['message' => 'Order accepted successfully'], 200);
            });
        } catch (\Exception $e) {
            info('Order acceptance failed: ' . $e->getMessage());
            return response()->json([
                'errors' => [
                    ['code' => 'error', 'message' => translate('messages.order_acceptance_failed')]
                ]
            ], 500);
        }
    }

    public function record_location_data(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location' => 'nullable|string|max:255',
            'heading' => 'nullable|numeric|between:0,360',
            'speed' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        DeliveryHistory::recordFix($request['dm']['id'], $request->only(['latitude', 'longitude', 'location', 'heading', 'speed', 'accuracy']));
        \App\Services\RiderArrivalService::check($request['dm'], $request['latitude'], $request['longitude']);

        return response()->json(['message' => translate('location recorded')], 200);
    }

    public function get_order_history(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        $history = DeliveryHistory::where(['order_id' => $request['order_id'], 'delivery_man_id' => $dm['id']])->get();
        return response()->json($history, 200);
    }

    public function send_order_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];
        $order = Order::where(['id' => $request['order_id'], 'delivery_man_id' => $dm['id']]);
        if(config('order_confirmation_model') == 'deliveryman' && $dm->type == 'zone_wise')
        {
            $order = $order->whereIn('order_status', ['pending', 'confirmed','processing','handover','picked_up']);
        }
        else
        {
            $order = $order->where(function($query){
                $query->whereIn('order_status', ['confirmed','processing','handover','picked_up'])->orWhere('order_type','parcel');
            });
        }
        $order = $order->dmOrder()->first();
        if(!$order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        }
        $value = translate('your_order_is_ready_to_be_delivered,_plesae_share_your_otp_with_delivery_man.').' '.translate('otp:').$order->otp.', '.translate('order_id:').$order->id;
        try {

            $fcm_token= $order->is_guest == 0 ? $order?->customer?->cm_firebase_token : $order?->guest?->fcm_token;
            if ($value && $fcm_token && Helpers::getNotificationStatusData('customer','customer_delivery_verification' ,'push_notification_status')) {
                $data = [
                    'title' => translate('messages.order_ready_to_be_delivered'),
                    'description' => $value,
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'otp',
                ];

                Helpers::send_push_notif_to_device($fcm_token , $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        } catch (\Exception $e) {
            info($e->getMessage());
            return response()->json(['message' => translate('messages.push_notification_faild')], 403);
        }
        return response()->json([], 200);
    }

    public function update_order_status(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:confirmed,canceled,picked_up,delivered,handover',
            'reason' =>'required_if:status,canceled',
            'order_proof' =>'array|max:5',
        ]);

        $validator->sometimes('otp', 'required', function ($request) {
            return (Config::get('order_delivery_verification')==1 && $request['status']=='delivered');
        });

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        $order = Order::where(['id' => $request['order_id'], 'delivery_man_id' => $dm['id']])->dmOrder()->first();

        if(!$order || (!$order->store && $order->order_type !='parcel') ){
            return response()->json([
                'errors' => [
                    ['code' => 'not_found', 'message' => translate('messages.you_can_not_change_the_status_of_this_order')]
                ]
            ], 403);
        }

        if($request['status'] =="confirmed" && config('order_confirmation_model') == 'store')
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order-confirmation-model', 'message' => translate('messages.order_confirmation_warning')]
                ]
            ], 403);
        }

        if($request['status'] == 'canceled' && !config('canceled_by_deliveryman'))
        {
            return response()->json([
                'errors' => [
                    ['code' => 'status', 'message' => translate('messages.you_can_not_cancel_a_order')]
                ]
            ], 403);
        }

        if($order->confirmed && $request['status'] == 'canceled')
        {
            return response()->json([
                'errors' => [
                    ['code' => 'delivery-man', 'message' => translate('messages.order_can_not_cancle_after_confirm')]
                ]
            ], 403);
        }

        // XS-5: pickup needs the store's handover code (when switched on).
        if ($request['status'] == 'picked_up') {
            $handoverError = \App\Services\HandoverCodeService::check($order, $request['handover_code']);
            if ($handoverError) {
                return response()->json(['errors' => [['code' => $handoverError[0], 'message' => $handoverError[1]]]], $handoverError[2]);
            }
        }

    /*     if(Config::get('order_delivery_verification')==1  && $order->charge_payer=='sender' && $request['status']=='picked_up' && $order->otp != $request['otp'])
        {
            return response()->json([
                'errors' => [
                    ['code' => 'otp', 'message' => translate('Not matched')]
                ]
            ], 406);
        } */

        // Rate-limited OTP verification
        if(Config::get('order_delivery_verification')==1 && $request['status']=='delivered')
        {
            $cacheKey = "otp_attempts_order_{$order->id}";
            $maxAttempts = 5;
            $decayMinutes = 15;
            
            // Check rate limit
            $attempts = \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
            if ($attempts >= $maxAttempts) {
                return response()->json([
                    'errors' => [
                        ['code' => 'otp_rate_limit', 'message' => translate('messages.too_many_otp_attempts')]
                    ]
                ], 429);
            }
            
            if ($order->otp != $request['otp']) {
                \Illuminate\Support\Facades\Cache::put($cacheKey, $attempts + 1, now()->addMinutes($decayMinutes));
                $remainingAttempts = $maxAttempts - $attempts - 1;
                return response()->json([
                    'errors' => [
                        ['code' => 'otp', 'message' => translate('Otp Not matched') . ". {$remainingAttempts} " . translate('attempts remaining')]
                    ]
                ], 406);
            }
            
            // Clear attempts on success
            \Illuminate\Support\Facades\Cache::forget($cacheKey);
        }
        if ($request->status == 'delivered')
        {
            if($order->transaction == null)
            {
                $unpaid_payment = OrderPayment::where('payment_status','unpaid')->where('order_id',$order->id)->first();
                $pay_method = 'digital_payment';
                if($unpaid_payment && $unpaid_payment->payment_method == 'cash_on_delivery'){
                    $pay_method = 'cash_on_delivery';
                }
                $reveived_by = ($order->payment_method == 'cash_on_delivery' || $pay_method == 'cash_on_delivery')?($dm->type != 'zone_wise'?'store':'deliveryman'):'admin';

                if(OrderLogic::create_transaction($order,$reveived_by, null))
                {
                    $order->payment_status = 'paid';
                }
                else
                {
                    return response()->json([
                        'errors' => [
                            ['code' => 'error', 'message' => translate('messages.faield_to_create_order_transaction')]
                        ]
                    ], 406);
                }
            }
            if($order->transaction)
            {
                $order->transaction->update(['delivery_man_id'=>$dm->id]);
            }

            $order->details->each(function($item, $key){
                if($item->food)
                {
                    $item->food->increment('order_count');
                }
            });
            $order?->customer?->increment('order_count');

            $dm->current_orders = $dm->current_orders>1?$dm->current_orders-1:0;
            $dm->save();

            $dm->increment('order_count');
            if($order->store)
            {
                $order->store->increment('order_count');
            }
            if($order->parcel_category)
            {
                $order->parcel_category->increment('orders_count');
            }

            $img_names = [];
            $images = [];
            if (!empty($request->file('order_proof'))) {
                foreach ($request->order_proof as $img) {
                    $image_name = Helpers::upload('order/', 'png', $img);
                    array_push($img_names, ['img'=>$image_name, 'storage'=> Helpers::getDisk()]);
                }
                $images = $img_names;
            }
            if(count($images)>0){
                $order->order_proof = json_encode($images);
            }

            OrderLogic::update_unpaid_order_payment(order_id:$order->id, payment_method:$order->payment_method);

        }
        else if($request->status == 'canceled')
        {
            if($order->delivery_man)
            {
                $dm = $order->delivery_man;
                $dm->current_orders = $dm->current_orders>1?$dm->current_orders-1:0;
                $dm->save();
            }
            $order->cancellation_reason = $request->reason;
            $order->canceled_by = 'deliveryman';
        }
        else if($order->order_type == 'parcel' && $request->status == 'handover')
        {
            $order->confirmed = now();
            $order->processing = now();
        }

        $order->order_status = $request['status'];
        $order[$request['status']] = now();
        $order->save();

        Helpers::send_order_notification($order);

        return response()->json(['message' =>  translate('Status updated')], 200);
    }

    public function get_order_details(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        $dm = $request['dm'];
        $order = Order::with(['details'])->where('id',$request['order_id'])->where(function($query) use($dm){
            $query->WhereNull('delivery_man_id')
                ->orWhere('delivery_man_id', $dm['id']);
        })->Notpos()->first();
        if(!$order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404);
        }
        $details = isset($order->details)?$order->details:null;
        if ($details != null && $details->count() > 0) {
            $details[0]['vendor_id'] = $order?->store?->vendor_id;
            $details = $details = Helpers::order_details_data_formatting($details);
            $details[0]['is_guest'] = (int)$order->is_guest;
            return response()->json($details, 200);
        }
        else if ($order->order_type == 'parcel' ) {
            $order->delivery_address = $order->delivery_address?json_decode($order->delivery_address, true):[];
            return response()->json(($order), 200);
        }
        elseif($order->prescription_order == 1){
            return response()->json([], 200);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('messages.not_found')]
            ]
        ], 404);
    }

    public function get_order(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        $order = Order::with(['customer', 'store','details','parcel_category','payments'])->where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->Notpos()->first();
        if(!$order)
        {
            return response()->json([
                'errors' => [
                    ['code' => 'order', 'message' => translate('messages.not_found')]
                ]
            ], 404); // was 204 (no body), which clients could not tell from an empty reply
        }
        return response()->json(Helpers::order_data_formatting($order), 200);
    }

    public function get_all_orders(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $dm = $request['dm'];

        $paginator = Order::with(['customer', 'store','parcel_category'])
        ->where(['delivery_man_id' => $dm['id']])
        ->whereIn('order_status', ['delivered','canceled','refund_requested','refunded','failed'])
        ->orderBy('schedule_at', 'desc')
        ->dmOrder()
        ->paginate($request['limit'], ['*'], 'page', $request['offset']);
        $orders= Helpers::order_data_formatting($paginator->items(), true);
        $data = [
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'orders' => $orders
        ];
        return response()->json($data, 200);
    }

    public function get_last_location(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $last_data = DeliveryHistory::whereHas('delivery_man.orders', function($query) use($request){
            return $query->where('id',$request->order_id);
        })->latest()->first();
        return response()->json($last_data, 200);
    }

    public function order_payment_status_update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'status' => 'required|in:paid'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $dm = $request['dm'];

        if (Order::where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->dmOrder()->first()) {
            Order::where(['delivery_man_id' => $dm['id'], 'id' => $request['order_id']])->update([
                'payment_status' => $request['status']
            ]);
            return response()->json(['message' => translate('Payment status updated') ], 200);
        }
        return response()->json([
            'errors' => [
                ['code' => 'order', 'message' => translate('not found!')]
            ]
        ], 404);
    }

    public function update_fcm_token(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fcm_token' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        DeliveryMan::where(['id' => $dm['id']])->update([
            'fcm_token' => $request['fcm_token']
        ]);

        return response()->json(['message'=> translate('successfully updated!')], 200);
    }

    public function get_notifications(Request $request){

        $dm = $request['dm'];

        $notifications = Notification::active()->where(function($q) use($dm){
                $q->whereNull('zone_id')->orWhere('zone_id', $dm->zone_id);
            })->where('tergat', 'deliveryman')->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();

        $user_notifications = UserNotification::where('delivery_man_id', $dm->id)->where('created_at', '>=', \Carbon\Carbon::today()->subDays(7))->get();

        $notifications->append('data');

        $notifications =  $notifications->merge($user_notifications);
        try {
            return response()->json($notifications, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function remove_account(Request $request)
    {
        $dm = $request['dm'];

        if(Order::where('delivery_man_id', $dm->id)->whereIn('order_status', ['pending','accepted','confirmed','processing','handover','picked_up'])->count())
        {
            return response()->json(['errors'=>[['code'=>'on-going', 'message'=>translate('messages.Please_complete_your_ongoing_and_accepted_orders')]]],203);
        }

        if($dm->wallet && $dm->wallet->collected_cash > 0)
        {
            return response()->json(['errors'=>[['code'=>'on-going', 'message'=>translate('messages.You_have_cash_in_hand,_you_have_to_pay_the_due_to_delete_your_account.')]]],203);
        }


        Helpers::check_and_delete('delivery-man/' , $dm['image']);


        foreach (json_decode($dm['identity_image'], true) as $img) {
            Helpers::check_and_delete('delivery-man/' , $img);

        }
        if($dm->userinfo){

            $dm->userinfo->delete();
        }
        $dm->delete();
        return response()->json([]);
    }
    Public function make_payment(Request $request){
        $validator = Validator::make($request->all(), [
            'payment_gateway' => 'required',
            'amount' => 'required|numeric|min:.001',
            'callback' => 'required',
            'token' => 'required'
        ]);


        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];

        $payer = new Payer(
            $dm->f_name ,
            $dm->email,
            $dm->phone,
            ''
        );

        $store_logo= BusinessSetting::where(['key' => 'logo'])->first();
        $additional_data = [
            'business_name' => BusinessSetting::where(['key'=>'business_name'])->first()?->value,
            'business_logo' => \App\CentralLogics\Helpers::get_full_url('business',$store_logo?->value,$store_logo?->storage[0]?->value ?? 'public' )
        ];
        $payment_info = new PaymentInfo(
            success_hook: 'collect_cash_success',
            failure_hook: 'collect_cash_fail',
            currency_code: Helpers::currency_code(),
            payment_method: $request->payment_gateway,
            payment_platform: 'app',
            payer_id: $dm->id,
            receiver_id: '100',
            additional_data:  $additional_data,
            payment_amount: $request->amount ,
            external_redirect_link: $request->has('callback')?$request['callback']:session('callback'),
            attribute: 'deliveryman_collect_cash_payments',
            attribute_id: $dm->id,
        );

        $receiver_info = new Receiver('Admin','example.png');
        $redirect_link = Payment::generate_link($payer, $payment_info, $receiver_info);

        $data = [
            'redirect_link' => $redirect_link,
        ];
        return response()->json($data, 200);

    }


    public function make_wallet_adjustment(Request $request){

        $dm = $request['dm'];
        $wallet = DeliveryManWallet::firstOrNew(
            ['delivery_man_id' =>$dm->id]
        );
        $wallet_earning =  round($wallet->total_earning -($wallet->total_withdrawn + $wallet->pending_withdraw) ,8);
        $adj_amount =  $wallet->collected_cash - $wallet_earning;

        if($wallet->collected_cash == 0 || $wallet_earning == 0 ){
            return response()->json(['message' => translate('messages.Already_Adjusted')], 201);
        }

        if($adj_amount > 0 ){
            $wallet->total_withdrawn =  $wallet->total_withdrawn + $wallet_earning ;
            $wallet->collected_cash =   $wallet->collected_cash - $wallet_earning ;

            $data = [
                'delivery_man_id' => $dm->id,
                'amount' => $wallet_earning,
                'ref' => "delivery_man_wallet_adjustment_partial",
                'method' => "adjustment",
                // 'approved' => 1,
                // 'type' => 'adjustment',
                'created_at' => now(),
                'updated_at' => now()
            ];

        } else{
            $data = [
                'delivery_man_id' => $dm->id,
                'amount' => $wallet->collected_cash ,
                'ref' => "delivery_man_wallet_adjustment_full",
                'method' => "adjustment",
                // 'approved' => 1,
                // 'type' => 'adjustment',
                'created_at' => now(),
                'updated_at' => now()
            ];
            $wallet->total_withdrawn =  $wallet->total_withdrawn + $wallet->collected_cash ;
            $wallet->collected_cash =   0;

        }

        $wallet->save();
        DB::table('provide_d_m_earnings')->insert($data);

        return response()->json(['message' => translate('messages.Delivery_man_wallet_adjustment_successfull')], 200);
    }
    public function wallet_payment_list(Request $request)
    {
        $limit= $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $dm = $request['dm'];

        $key = isset($request['search']) ? explode(' ', $request['search']) : [];
        $paginator = AccountTransaction::
        when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('ref', 'like', "%{$value}%");
                }
            });
        })
            ->where('type', 'collected')
            ->where('created_by' , 'deliveryman')
            ->where('from_id',$dm->id)
            ->where('from_type', 'deliveryman')
            ->latest()

            ->paginate($limit, ['*'], 'page', $offset);

        $temp= [];

        foreach( $paginator->items() as $item)
        {
            $item['status'] = 'approved';
            $item['payment_time'] = \App\CentralLogics\Helpers::time_date_format($item->created_at);

            $temp[] = $item;
        }
        $data = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'transactions' => $temp,
        ];

        return response()->json($data, 200);
    }
    public function wallet_provided_earning_list(Request $request)
    {
        $limit= $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $dm = $request['dm'];

        $key = isset($request['search']) ? explode(' ', $request['search']) : [];
        $paginator = ProvideDMEarning::
        when(isset($key), function ($query) use ($key) {
            return $query->where(function ($q) use ($key) {
                foreach ($key as $value) {
                    $q->orWhere('ref', 'like', "%{$value}%");
                }
            });
        })
            ->where('delivery_man_id',$dm->id)
            ->where('method', 'adjustment')
            ->whereIn('ref', ['delivery_man_wallet_adjustment_partial' , 'delivery_man_wallet_adjustment_full' ])
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        $temp= [];

        foreach( $paginator->items() as $item)
        {
            $item['amount'] = (float) $item['amount'];
            $item['status'] = 'Approved';
            $item['payment_time'] = \App\CentralLogics\Helpers::time_date_format($item->created_at);

            $temp[] = $item;
        }
        $data = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'transactions' => $temp,
        ];

        return response()->json($data, 200);
    }

    public function get_disbursement_withdrawal_methods(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $dm = $request['dm'];

        $key = explode(' ', $request['search']);
        $paginator = DisbursementWithdrawalMethod::where('delivery_man_id', $dm['id'])
            ->when( isset($key) , function($query) use($key){
                $query->where(function ($q) use ($key) {
                    foreach ($key as $value) {
                        $q->orWhere('method_name', 'like', "%{$value}%");
                    }
                });
            }
            )
            ->latest()
            ->paginate($request['limit'], ['*'], 'page', $request['offset']);

        $datas =[];
        foreach ($paginator->items() as $k => $v) {
            $userInputs=[];
            foreach(json_decode($v->method_fields,true)as $key => $value){
                $userInput = [
                    'user_input' => $key,
                    'user_data' => $value,
                ];
                $userInputs[] = $userInput;
            }
            $v['method_fields'] = $userInputs;
            $datas[] = $v;
        }

        $data = [
            'total_size' => $paginator->total(),
            'limit' => $request['limit'],
            'offset' => $request['offset'],
            'methods' => $datas
        ];
        return response()->json($data, 200);
    }

    public function withdraw_method_list(){
        $wi=WithdrawalMethod::where('is_active',1)->get();
        return response()->json($wi,200);
    }

    public function disbursement_withdrawal_method_store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'withdraw_method_id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $dm = $request['dm'];

        $method = WithdrawalMethod::find($request['withdraw_method_id']);
        $fields = array_column($method->method_fields, 'input_name');
        $values = $request->all();

        $method_data = [];
        foreach ($fields as $field) {
            if(key_exists($field, $values)) {
                $method_data[$field] = $values[$field];
            }
        }

        $data = [
            'delivery_man_id' => $dm['id'],
            'withdrawal_method_id' => $method['id'],
            'method_name' => $method['method_name'],
            'method_fields' => json_encode($method_data),
            'is_default' => 0,
            'created_at' => now(),
            'updated_at' => now()
        ];

        DB::table('disbursement_withdrawal_methods')->insert($data);

        return response()->json(['message'=>translate('successfully added!')], 200);
    }

    public function disbursement_withdrawal_method_default(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
            'is_default' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $dm = $request['dm'];
        $method = DisbursementWithdrawalMethod::find($request->id);
        $method->is_default = $request->is_default;
        $method->save();
        DisbursementWithdrawalMethod::whereNot('id', $request->id)->where('delivery_man_id',$dm['id'])->update(['is_default' => 0]);
        return response()->json(['message'=>translate('messages.method_updated_successfully')], 200);
    }

    public function disbursement_withdrawal_method_delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $method = DisbursementWithdrawalMethod::find($request->id);
        $method->delete();
        return response()->json(['message'=>translate('messages.method_deleted_successfully')], 200);
    }

    public function disbursement_report(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required',
            'offset' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $limit = $request['limit']??25;
        $offset = $request['offset']??1;

        $dm = $request['dm'];

        $total_disbursements=DisbursementDetails::where('delivery_man_id',$dm['id'])->latest()->get();
        $paginator=DisbursementDetails::where('delivery_man_id',$dm['id'])->latest()->paginate($limit, ['*'], 'page', $offset);

        $paginator->each(function ($data) {
            $data->withdraw_method?->method_fields ?  $data->withdraw_method->method_fields = json_decode($data->withdraw_method?->method_fields, true) : '';
        });

        $data = [
            'total_size' => $paginator->total(),
            'limit' => $limit,
            'offset' => $offset,
            'pending' =>(float) $total_disbursements->where('status','pending')->sum('disbursement_amount'),
            'completed' =>(float) $total_disbursements->where('status','completed')->sum('disbursement_amount'),
            'canceled' =>(float) $total_disbursements->where('status','canceled')->sum('disbursement_amount'),
            'complete_day' =>(int) BusinessSetting::where(['key'=>'dm_disbursement_waiting_time'])->first()?->value,
            'disbursements' => $paginator->items()
        ];
        return response()->json($data,200);

    }
    public function earningReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer',
            'offset' => 'required|integer',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'type' => 'nullable|in:all,delivery_fee,delivery_tips',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $type = $request['type'] ?? 'all';

        $dm = $request['dm'];

        $baseQuery = OrderTransaction::with(['order:id,payment_method'])
            ->where('delivery_man_id', $dm['id'])
            ->where(function ($query) {
                $query->where('original_delivery_charge', '>', 0)
                    ->orWhere('dm_tips', '>', 0);
            });

        if ($request->start_date && $request->end_date) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $end = Carbon::parse($request->end_date)->endOfDay();
            $baseQuery->whereBetween('created_at', [$start, $end]);
        } elseif ($request->start_date) {
            $start = Carbon::parse($request->start_date)->startOfDay();
            $baseQuery->where('created_at', '>=', $start);
        } elseif ($request->end_date) {
            $end = Carbon::parse($request->end_date)->endOfDay();
            $baseQuery->where('created_at', '<=', $end);
        }

        if ($type === 'delivery_fee') {
            $baseQuery->where('original_delivery_charge', '>', 0);
        } elseif ($type === 'delivery_tips') {
            $baseQuery->where('dm_tips', '>', 0);
        }

        $total_dm_tips = (clone $baseQuery)->sum('dm_tips');
        $total_delivery_charge = (clone $baseQuery)->sum('original_delivery_charge');
        $total_admin_commission = (clone $baseQuery)->sum('delivery_fee_comission');

        $paginated_orders = (clone $baseQuery)
            ->select(['id','order_id', 'delivery_man_id', 'dm_tips', 'original_delivery_charge','delivery_fee_comission','created_at'])
            ->latest()
            ->paginate($limit, ['*'], 'page', $offset);

        $paginated_orders->getCollection()->transform(function ($item) {
            $item->dm_tips = (float) $item->dm_tips;
            $item->original_delivery_charge = (float) $item->original_delivery_charge;
            $item->delivery_fee_comission = (float) $item->delivery_fee_comission;
            return $item;
        });


        $data = [
            'earning' => $paginated_orders,
            'total_dm_tips' => (float)$total_dm_tips,
            'total_delivery_charge' => (float)$total_delivery_charge,
            'total_admin_commission' => (float)$total_admin_commission,
            'type' => $type,
            'limit' => $limit,
            'offset' => $offset,
        ];

        return response()->json($data, 200);
    }

}
