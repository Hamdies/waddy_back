<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Models\Zone;
use App\Models\Admin;
use App\Models\Store;
use App\Models\Module;
use App\Models\Vendor;
use App\Models\Translation;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\VendorEmployee;
use App\Mail\StoreRegistration;
use App\Models\BusinessSetting;
use App\CentralLogics\StoreLogic;
use App\CentralLogics\SMS_module;
use App\CentralLogics\AkedlyGateway;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Modules\Gateways\Traits\SmsGateway;
use App\Http\Controllers\Controller;
use App\Mail\VendorSelfRegistration;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\Rental\Emails\ProviderRegistration;
use Modules\Rental\Emails\ProviderSelfRegistration;

class VendorLoginController extends Controller
{
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $vendor_type= $request->vendor_type;

        $data = [
            'email' => $request->email,
            'password' => $request->password
        ];

        if($vendor_type == 'owner' && auth('vendor')->attempt($data)){
            return $this->owner_session(Vendor::where(['email' => $request['email']])->first());
        }
        if($vendor_type == 'employee' && auth('vendor_employee')->attempt($data)){
            return $this->employee_session(VendorEmployee::where(['email' => $request['email']])->first());
        }

        $errors = [];
        array_push($errors, ['code' => 'auth-001', 'message' => translate('Credential_do_not_match,_please_try_again')]);
        return response()->json([
            'errors' => $errors
        ], 401);
    }

    /** Seconds between login OTP sends; the app runs its resend countdown from the value we return. */
    const OTP_RESEND_INTERVAL = 30;

    /** A resend inside this window re-sends the code on file, so a late WhatsApp and the SMS fallback match. */
    const OTP_REUSE_WINDOW = 300;

    /** A login OTP older than this is refused. */
    const OTP_EXPIRY = 600;

    /**
     * Phone sign-in, step 1: text a code to an owner or employee already on
     * file. An unknown number gets `account_not_found` so the app can offer
     * store registration instead; nobody is created here.
     */
    public function send_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|max:20',
            'vendor_type' => 'required|in:owner,employee',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = AkedlyGateway::normalize_phone($request->phone);
        if (!$this->account_by_phone($request->vendor_type, $phone)) {
            return response()->json(['errors' => [
                ['code' => 'account_not_found', 'message' => translate('messages.no_account_on_this_phone')]
            ]], 404);
        }

        $blocked = $this->otp_block_response($phone);
        if ($blocked) {
            return $blocked;
        }

        $verification_data = DB::table('phone_verifications')->where('phone', $phone)->first();
        if (isset($verification_data) && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < self::OTP_RESEND_INTERVAL) {
            $time = self::OTP_RESEND_INTERVAL - Carbon::parse($verification_data->updated_at)->DiffInSeconds();
            return response()->json(['errors' => [
                ['code' => 'otp', 'message' => translate('messages.please_try_again_after_').$time.' '.translate('messages.seconds')]
            ]], 405);
        }

        $reuse = isset($verification_data) && !empty($verification_data->token)
            && Carbon::parse($verification_data->created_at)->DiffInSeconds() < self::OTP_REUSE_WINDOW;
        $otp = $reuse ? $verification_data->token : rand(100000, 999999);

        $published_status = config('get_payment_publish_status')[0]['is_published'] ?? 0;
        AkedlyGateway::request_channel($request->otp_channel);
        $response = $published_status == 1 ? SmsGateway::send($phone, $otp) : SMS_module::send($phone, $otp);

        if ($response !== 'success') {
            return response()->json(['errors' => [
                ['code' => 'otp', 'message' => translate('messages.failed_to_send_sms')]
            ]], 405);
        }

        // A reused code keeps its created_at and wrong-attempt count, so
        // resending can't reset the expiry or the attempt limit.
        DB::table('phone_verifications')->updateOrInsert(['phone' => $phone],
            $reuse ? ['updated_at' => now()] : [
                'token' => $otp,
                'otp_hit_count' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        return response()->json(['otp_channel' => AkedlyGateway::last_channel() ?? 'sms', 'resend_in' => self::OTP_RESEND_INTERVAL], 200);
    }

    /** Phone sign-in, step 2: the same token response as login(). */
    public function verify_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|max:20',
            'otp' => 'required',
            'vendor_type' => 'required|in:owner,employee',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = AkedlyGateway::normalize_phone($request->phone);

        // A blocked number stays blocked even for the right code.
        $blocked = $this->otp_block_response($phone);
        if ($blocked) {
            return $blocked;
        }

        $data = DB::table('phone_verifications')->where(['phone' => $phone, 'token' => $request->otp])->first();
        if (!$data) {
            return $this->record_wrong_otp($phone) ?? response()->json(['errors' => [
                ['code' => 'otp', 'message' => translate('messages.otp_does_not_match')]
            ]], 404);
        }
        if (Carbon::parse($data->created_at)->DiffInSeconds() > self::OTP_EXPIRY) {
            return response()->json(['errors' => [
                ['code' => 'otp_expired', 'message' => translate('messages.otp_expired_request_a_new_code')]
            ]], 405);
        }

        $account = $this->account_by_phone($request->vendor_type, $phone);
        if (!$account) {
            return response()->json(['errors' => [
                ['code' => 'account_not_found', 'message' => translate('messages.no_account_on_this_phone')]
            ]], 404);
        }

        DB::table('phone_verifications')->where('phone', $phone)->delete();

        return $request->vendor_type == 'owner' ? $this->owner_session($account) : $this->employee_session($account);
    }

    /**
     * Phones are stored however they were typed at registration or under
     * Staff, so an Egyptian number is looked up as +20…, 20… and 0….
     */
    private function account_by_phone(string $type, string $phone)
    {
        $digits = ltrim($phone, '+');
        $candidates = [$phone, $digits];
        if (str_starts_with($digits, '20')) {
            $candidates[] = '0'.substr($digits, 2);
        }

        return ($type == 'owner' ? Vendor::query() : VendorEmployee::query())->whereIn('phone', $candidates)->first();
    }

    private function owner_session(Vendor $vendor)
    {
        // Reuse the token: a new one per login logged the owner out on every
        // other phone (SB-4). Blocking the store or resetting the password clears it.
        $token = $vendor->auth_token ?: $this->genarate_token($vendor->email);

        $storeSubscriptionCheck = $this->storeSubscriptionCheck($vendor?->stores[0], $vendor, $token);
        if(data_get($storeSubscriptionCheck,'type') != null){
            return response()->json(data_get($storeSubscriptionCheck,'data'), data_get($storeSubscriptionCheck,'code'));
        }

        if($vendor?->stores[0]?->module?->module_type == 'rental' && !addon_published_status('Rental')){
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('rental_module_is_not_available')]]
            ], 401);
        }

        $vendor->auth_token = $token;
        $vendor->save();
        return response()->json(['token' => $token, 'zone_wise_topic'=> $vendor->stores[0]->zone->store_wise_topic, 'module_type' => $vendor?->stores[0]?->module?->module_type], 200);
    }

    private function employee_session(VendorEmployee $vendor)
    {
        $token = $vendor->auth_token ?: $this->genarate_token($vendor->email);
        $storeSubscriptionCheck = $this->storeSubscriptionCheck($vendor?->store, $vendor, $token);
        if(data_get($storeSubscriptionCheck,'type') != null){
            return response()->json(data_get($storeSubscriptionCheck,'data'), data_get($storeSubscriptionCheck,'code'));
        }

        if($vendor?->store?->module_type == 'rental' && !addon_published_status('Rental')){
            return response()->json([
                'errors' => [['code' => 'auth-001', 'message' => translate('rental_module_is_not_available')]]
            ], 401);
        }

        $vendor->auth_token = $token;
        $vendor->save();
        $role = $vendor->role ? json_decode($vendor->role->modules):[];
        return response()->json(['token' => $token, 'zone_wise_topic'=> $vendor->store->zone->store_wise_topic, 'role'=>$role,
            'module_type' => $vendor?->store?->module_type], 200);
    }

    /** The error response while this phone sits in its 10-minute wrong-OTP block, else null. */
    private function otp_block_response($phone)
    {
        $temp_block_time = 600; // seconds
        $verification_data = DB::table('phone_verifications')->where('phone', $phone)->first();

        if (isset($verification_data->temp_block_time) && Carbon::parse($verification_data->temp_block_time)->DiffInSeconds() <= $temp_block_time) {
            $time = $temp_block_time - Carbon::parse($verification_data->temp_block_time)->DiffInSeconds();
            return response()->json(['errors' => [
                ['code' => 'otp_block_time', 'message' => translate('messages.please_try_again_after_') . CarbonInterval::seconds($time)->cascade()->forHumans()]
            ]], 405);
        }
        return null;
    }

    /**
     * Count a wrong OTP; after 5 inside a minute the number is blocked for 10
     * minutes. Same rules as customer login (CustomerAuthController).
     */
    private function record_wrong_otp($phone)
    {
        $max_otp_hit = 5;
        $max_otp_hit_time = 60; // seconds

        $verification_data = DB::table('phone_verifications')->where('phone', $phone)->first();

        if (isset($verification_data)) {
            if ($verification_data->is_temp_blocked == 1 && Carbon::parse($verification_data->updated_at)->DiffInSeconds() >= $max_otp_hit_time) {
                DB::table('phone_verifications')->updateOrInsert(['phone' => $phone], [
                    'otp_hit_count' => 0,
                    'is_temp_blocked' => 0,
                    'temp_block_time' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($verification_data->otp_hit_count >= $max_otp_hit && Carbon::parse($verification_data->updated_at)->DiffInSeconds() < $max_otp_hit_time && $verification_data->is_temp_blocked == 0) {
                DB::table('phone_verifications')->updateOrInsert(['phone' => $phone], [
                    'is_temp_blocked' => 1,
                    'temp_block_time' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                return response()->json(['errors' => [
                    ['code' => 'otp_temp_blocked', 'message' => translate('messages.Too_many_attemps')]
                ]], 405);
            }
        }

        DB::table('phone_verifications')->updateOrInsert(['phone' => $phone], [
            'otp_hit_count' => DB::raw('otp_hit_count + 1'),
            'updated_at' => now(),
            'temp_block_time' => null,
        ]);
        return null;
    }

    private function genarate_token($email)
    {
        $token = Str::random(120);
        // The middleware looks a token up in both tables. The retry's result
        // used to be thrown away (SB-9).
        $is_taken = Vendor::where('auth_token', $token)->exists() || VendorEmployee::where('auth_token', $token)->exists();
        if($is_taken)
        {
            return $this->genarate_token($email);
        }
        return $token;
    }

    public function register(Request $request)
    {
        $status = BusinessSetting::where('key', 'toggle_store_registration')->first();
        if(!isset($status) || $status->value == '0')
        {
            return response()->json(['errors' => Helpers::error_processor('self-registration', translate('messages.store_self_registration_disabled'))]);
        }

        $validator = Validator::make($request->all(), [
            'f_name' => 'required|max:100',
            'l_name' => 'nullable|max:100',
            'latitude' => 'required',
            'longitude' => 'required',
            'email' => 'required|unique:vendors',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:20|unique:vendors',
            'minimum_delivery_time' => 'required',
            'maximum_delivery_time' => 'required',
            'delivery_time_type'=>'required',
            'password' => ['required', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
            'zone_id' => 'required',
            'module_id' => 'required',
            'logo' => 'required',
            'tin' => 'required',
            'tin_expire_date' => 'required',
            'tin_certificate_image' => 'required',
        ],[
            'password.required' => translate('The password is required'),
            'password.min_length' => translate('The password must be at least :min characters long'),
            'password.mixed' => translate('The password must contain both uppercase and lowercase letters'),
            'password.letters' => translate('The password must contain letters'),
            'password.numbers' => translate('The password must contain numbers'),
            'password.symbols' => translate('The password must contain symbols'),
            'password.uncompromised' => translate('The password is compromised. Please choose a different one'),
        ]);

        if($request->zone_id)
        {
            $zone = Zone::query()
            ->whereContains('coordinates', new Point($request->latitude, $request->longitude, POINT_SRID))
            ->where('id',$request->zone_id)
            ->first();
            if(!$zone){
                $validator->getMessageBag()->add('latitude', translate('messages.coordinates_out_of_zone'));
                return response()->json(['errors' => Helpers::error_processor($validator)], 403);
            }
        }
        $module = Module::find($request['module_id']);
        if ($module?->module_type == 'rental' && addon_published_status('Rental') && empty($request['pickup_zone_id'])){
            $validator->getMessageBag()->add('pickup_zone_id', translate('messages.You_must_select_a_pickup_zone'));
            return back()->withErrors($validator)
                ->withInput();
        }

        $data = json_decode($request->translations, true);

        if (count($data) < 1) {
            $validator->getMessageBag()->add('translations', translate('messages.Name and description in english is required'));
        }

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $vendor = new Vendor();
        $vendor->f_name = $request->f_name;
        $vendor->l_name = $request->l_name;
        $vendor->email = $request->email;
        $vendor->phone = $request->phone;
        $vendor->password = bcrypt($request->password);
        $vendor->status = null;
        $vendor->save();

        $store = new Store;
        $store->name = $data[0]['value'];
        $store->phone = $request->phone;
        $store->email = $request->email;
        $store->logo = Helpers::upload('store/', 'png', $request->file('logo'));
        $store->cover_photo = Helpers::upload('store/cover/', 'png', $request->file('cover_photo'));
        $store->address = $data[1]['value'];
        $store->latitude = $request->latitude;
        $store->longitude = $request->longitude;
        $store->vendor_id = $vendor->id;
        $store->zone_id = $request->zone_id;
        $store->tin = $request->tin;
        $store->tin_expire_date = $request->tin_expire_date;
        $extension = $request->has('tin_certificate_image') ? $request->file('tin_certificate_image')->getClientOriginalExtension() : 'png';
        $store->tin_certificate_image = Helpers::upload('store/', $extension, $request->file('tin_certificate_image'));
        $store->delivery_time = $request->minimum_delivery_time .'-'. $request->maximum_delivery_time.' '.$request->delivery_time_type;
        $store->module_id = $request->module_id;
        $store->status = 0;
        $store->store_business_model = 'none';
        $store->pickup_zone_id = $request['pickup_zone_id'] ?? json_encode([]);
        $store->save();
        // $store->module->increment('stores_count');
        if(config('module.'.$store->module->module_type)['always_open'])
        {
            StoreLogic::insert_schedule($store->id);
        }

        foreach ($data as $key=>$i) {
            $data[$key]['translationable_type'] = 'App\Models\Store';
            $data[$key]['translationable_id'] = $store->id;
        }
        Translation::insert($data);


        try{
            $admin= Admin::where('role_id', 1)->first();
            $mail_status = Helpers::get_mail_status('registration_mail_status_store');
            if($module?->module_type != 'rental' && config('mail.status') && $mail_status == '1' &&  Helpers::getNotificationStatusData('store','store_registration','mail_status')){
                Mail::to($request['email'])->send(new VendorSelfRegistration('pending', $vendor->f_name.' '.$vendor->l_name));
            }
            elseif($module?->module_type == 'rental' && addon_published_status('Rental')&& config('mail.status') && Helpers::get_mail_status('rental_registration_mail_status_provider') == '1' &&  Helpers::getRentalNotificationStatusData('provider','provider_registration','mail_status') ){
                Mail::to($request['email'])->send(new ProviderSelfRegistration('pending', $vendor->f_name.' '.$vendor->l_name));
            }

            $mail_status = Helpers::get_mail_status('store_registration_mail_status_admin');
            if($module?->module_type != 'rental' && config('mail.status') && $mail_status == '1' &&  Helpers::getNotificationStatusData('admin','store_self_registration','mail_status')){
                Mail::to($admin['email'])->send(new StoreRegistration('pending', $vendor->f_name.' '.$vendor->l_name));
            }
            elseif($module?->module_type == 'rental' && addon_published_status('Rental') && config('mail.status') && Helpers::get_mail_status('rental_provider_registration_mail_status_admin') == '1' &&  Helpers::getRentalNotificationStatusData('admin','provider_self_registration','mail_status') ){
                Mail::to($admin['email'])->send(new ProviderRegistration('pending', $vendor->f_name.' '.$vendor->l_name));
            }
        }catch(\Exception $ex){
            info($ex->getMessage());
        }


        if (Helpers::subscription_check()) {
                if ($request->business_plan == 'subscription' && $request->package_id != null ) {
                    $store->package_id = $request->package_id;
                    $store->save();

                    return response()->json([
                        'store_id'=> $store->id,
                        'package_id'=> $store->package_id,
                        'type'=> 'subscription',
                        'message'=>translate('messages.application_placed_successfully')],200);
                }
                elseif($request->business_plan == 'commission' ){
                    $store->store_business_model = 'commission';
                    $store->save();
                    return response()->json([
                        'store_id'=> $store->id,
                        'type'=> 'commission',
                        'message'=>translate('messages.application_placed_successfully')],200);
                }
                else{
                    return response()->json([
                        'store_id'=> $store->id,
                        'type'=> 'business_model_fail',
                        'message'=>translate('messages.application_placed_successfully')],200);
                }
            } else{
                $store->store_business_model = 'commission';
                $store->save();
                return response()->json([
                    'store_id'=> $store->id,
                    'type'=> 'commission',
                    'message'=>translate('messages.application_placed_successfully')],200);
            }

        return response()->json([
            'store_id'=> $store->id,
            'message'=>translate('messages.application_placed_successfully')],200);
    }





    private function storeSubscriptionCheck($store, $vendor,$token){
        if ($store?->store_business_model == 'none') {
            $vendor->auth_token = $token;
            $vendor?->save();
            return [
                'type' => 'subscribed',
                'code' => 200,
                'data' => [
                    'subscribed' => [
                        'store_id' => $store?->id,
                        'token' => $token,
                        'package_id' => $store?->package_id,
                        'zone_wise_topic' => $store?->zone?->store_wise_topic,
                        'type' => 'new_join',
                        'module_type' => $store?->module?->module_type
                    ]
                ]
            ];
        }

        if ($store->status == 0 && $vendor->status == 0) {
            return [
                'type' => 'errors',
                'code' => 403,
                'data' => [
                    'errors' => [
                        ['code' => 'auth-002', 'message' => translate('messages.Your_registration_is_not_approved_yet._You_can_login_once_admin_approved_the_request')]
                    ]
                ]
            ];
        } elseif ($store->status == 0 && $vendor->status == 1 && in_array($store?->store_business_model ,['subscription' ,'commission']) ) {
            return [
                'type' => 'errors',
                'code' => 403,
                'data' => [
                    'errors' => [
                        ['code' => 'auth-002', 'message' => translate('messages.Your_account_is_suspended')]
                    ]
                ]
            ];
        }

        if ($store?->store_business_model == 'subscription') {
            $store_sub = $store?->store_sub;
            if (isset($store_sub)) {
                if ($store_sub?->mobile_app == 0) {
                    return [
                        'type' => 'errors',
                        'code' => 401,
                        'data' => [
                            'errors' => [
                                ['code' => 'no_mobile_app', 'message' => translate('messages.Your Subscription Plan is not Active for Mobile App')]
                            ]
                        ]
                    ];
                }
            }
        }

        if ($store?->store_business_model == 'unsubscribed' && isset($store?->store_sub_update_application)) {
            return null;
        }

        if ($store?->store_business_model == 'unsubscribed' && !isset($store?->store_sub_update_application)) {
            $vendor->auth_token = $token;
            $vendor?->save();
            return [
                'type' => 'subscribed',
                'code' => 200,
                'data' => [
                    'subscribed' => [
                        'store_id' => $store?->id,
                        'token' => $token,
                        'zone_wise_topic' => $store?->zone?->store_wise_topic,
                        'type' => 'new_join',
                        'module_type' => $store?->module?->module_type
                    ]
                ]
            ];
        }
        return null ;
    }

}
