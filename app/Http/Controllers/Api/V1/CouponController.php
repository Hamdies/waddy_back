<?php

namespace App\Http\Controllers\Api\V1;

use App\CentralLogics\Helpers;
use App\CentralLogics\CouponLogic;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Store;
use App\Services\ScratchCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CouponController extends Controller
{
    public function list(Request $request)
    {
        if (!$request->hasHeader('zoneId')) {
            $errors = [];
            array_push($errors, ['code' => 'zoneId', 'message' => translate('messages.zone_id_required')]);
            return response()->json([
                'errors' => $errors
            ], 403);
        }
        $customer_id=Auth::user()?->id ?? $request->customer_id ?? null;
        $zone_id= $request->header('zoneId');
        $data = [];
        // try {
            $coupons = Coupon::with('store:id,name')->active()
            ->when(config('module.current_module_data'), function($query){
                // Null module = XP reward coupon, valid in every module.
                $query->where(function ($q) {
                    $q->module(config('module.current_module_data')['id'])->orWhereNull('module_id');
                });
            })
            ->whereDate('expire_date', '>=', date('Y-m-d'))->whereDate('start_date', '<=', date('Y-m-d'))->get();
            foreach($coupons as $key=>$coupon)
            {
                if($coupon->coupon_type == 'store_wise')
                {
                    $temp = Store::active()
                    ->when(config('module.current_module_data'), function($query)use($zone_id){
                        if(!config('module.current_module_data')['all_zone_service']) {
                            $query->whereIn('zone_id', json_decode($zone_id, true));
                        }
                    })
                    ->whereIn('id', json_decode($coupon->data, true))->first();
                    if($temp && (in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id,json_decode($coupon->customer_id, true))))
                    {
                        $coupon->data = $temp->name;
                        $coupon['store_id'] = (int)$temp->id;
                        $data[] = $coupon;
                    }
                }
                else if($coupon->coupon_type == 'zone_wise')
                {
                    if(count(array_intersect(json_decode($zone_id, true), json_decode($coupon->data,true))))
                    {
                        $data[] = $coupon;
                    }
                }
                else if(isset($coupon->store_id) )
                {
                    $temp = Store::active()->when(config('module.current_module_data'), function($query)use($zone_id){
                        if(!config('module.current_module_data')['all_zone_service']) {
                            $query->whereIn('zone_id', json_decode($zone_id, true));
                        }
                    })->where('id', $coupon->store_id)->exists();

                    if($temp){
                        $data[] = $coupon;
                    }

                }
                else{
                    if((in_array("all", json_decode($coupon->customer_id, true)) || in_array($customer_id,json_decode($coupon->customer_id, true))) ){
                        $data[] = $coupon;
                    }
                }
            }

            return response()->json($data, 200);
        // } catch (\Exception $e) {
        //     return response()->json(['errors' => $e], 403);
        // }
    }

    public function apply(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'code' => 'required',
            'store_id' => 'required',
        ]);

        if ($validator->errors()->count()>0) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        try {
            $user = $request->user();
            // Checked before any lookup, so a locked-out guesser learns nothing (SC-04).
            if (ScratchCardService::tooManyMisses($user->id, $request->ip())) {
                return self::cardError('too_many_attempts', 429);
            }

            $coupon = Coupon::active()->where(['code' => $request['code']])->first();
            if (!isset($coupon)) {
                // Not a coupon: maybe a printed scratch card code (SC-03).
                $bound = ScratchCardService::bind((string) $request['code'], $user);
                if ($bound === null) {
                    ScratchCardService::recordMiss($user->id, $request->ip());
                    return response()->json([
                        'errors' => [
                            ['code' => 'coupon', 'message' => translate('messages.not_found')]
                        ]
                    ], 404);
                }
                if (isset($bound['error'])) {
                    return self::cardError($bound['error'], 403, $bound['available_on'] ?? null);
                }
                $coupon = $bound['coupon'];
            }

            $staus = CouponLogic::is_valide($coupon, $user->id ,$request['store_id']);

            if (ScratchCardService::cardFor($coupon)) {
                // A card coupon only ever fails as "used" (spent, or someone
                // else's) or "expired"; the generic texts would make a real
                // card feel fake.
                if (in_array($staus, [406, 408])) {
                    return self::cardError('card_already_used', 403);
                }
                if ($staus === 407) {
                    return self::cardError('card_expired', 403);
                }
                $coupon->setAttribute('scratch_card', true);
            }

            switch ($staus) {
            case 200:
                return response()->json($coupon, 200);
            case 406:
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.coupon_usage_limit_over')]
                    ]
                ], 406);
            case 407:
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.coupon_expire')]
                    ]
                ], 407);
            case 408:
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.You_are_not_eligible_for_this_coupon')]
                    ]
                ], 403);
            default:
                return response()->json([
                    'errors' => [
                        ['code' => 'coupon', 'message' => translate('messages.not_found')]
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['errors' => $e], 403);
        }
    }

    /**
     * A scratch-card refusal. The app picks its own wording from `code`
     * (card_already_used, card_expired, card_not_active, card_limit,
     * too_many_attempts); the message is the English fallback.
     */
    private static function cardError(string $code, int $status, ?string $availableOn = null)
    {
        $messages = [
            'card_already_used' => 'This card was already used.',
            'card_expired' => "This card's offer has ended.",
            'card_not_active' => "This card isn't active yet.",
            'card_limit' => "You've used the maximum number of Waddy cards for now.",
            'too_many_attempts' => 'Too many tries. Please wait a bit and try again.',
        ];
        $error = ['code' => $code, 'message' => $messages[$code] ?? $code];
        if ($availableOn !== null) {
            $error['available_on'] = $availableOn;
        }
        return response()->json(['errors' => [$error]], $status);
    }
}
