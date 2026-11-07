<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\CentralLogics\AkedlyGateway;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DeliveryMan;
use App\Traits\PhoneOtp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class DeliveryManLoginController extends Controller
{
    use PhoneOtp;

    /** Seconds a verified-phone token stays good for finishing sign up. */
    const PHONE_TOKEN_TTL = 1800;

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $data = [
            'phone' => $request->phone,
            'password' => $request->password
        ];

        if (auth('delivery_men')->attempt($data)) {
            $token = Str::random(120);

            if(auth('delivery_men')->user()->application_status != 'approved')
            {
                return response()->json([
                    'errors' => [
                        ['code' => 'auth-003', 'message' => translate('messages.Your_account_is_not_approved_yet.')]
                    ]
                ], 401);
            }
            else if(!auth('delivery_men')->user()->status)
            {
                $errors = [];
                array_push($errors, ['code' => 'auth-003', 'message' => translate('messages.your_account_has_been_suspended')]);
                return response()->json([
                    'errors' => $errors
                ], 401);
            }

            $delivery_man =  DeliveryMan::where(['phone' => $request['phone']])->first();
            $delivery_man->auth_token = $token;
            $delivery_man->save();

            return response()->json(['token' => $token] + $delivery_man->pushTopics(), 200);
        } else {
            $errors = [];
            array_push($errors, ['code' => 'auth-001', 'message' => translate('Incorrect_credential,_please_try_again')]);
            return response()->json([
                'errors' => $errors
            ], 401);
        }
    }

    /**
     * Sign up, step 1: text a code to a phone that is not a rider yet. A
     * registered number gets `phone_taken` so the app can send them to sign in.
     */
    public function send_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|max:20',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = AkedlyGateway::normalize_phone($request->phone);
        if (DeliveryMan::where('phone', $phone)->exists()) {
            return response()->json(['errors' => [
                ['code' => 'phone_taken', 'message' => translate('messages.phone_already_registered_please_sign_in')]
            ]], 403);
        }

        return $this->send_phone_otp($phone, $request->otp_channel);
    }

    /**
     * Sign up, step 2: trade the code for a `phone_token` that store() requires,
     * so a rider can only register a number they received the code on.
     */
    public function verify_otp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|max:20',
            'otp' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $phone = AkedlyGateway::normalize_phone($request->phone);
        $invalid = $this->check_phone_otp($phone, $request->otp);
        if ($invalid) {
            return $invalid;
        }

        DB::table('phone_verifications')->where('phone', $phone)->delete();

        $phone_token = Crypt::encryptString(json_encode(['phone' => $phone, 'exp' => now()->timestamp + self::PHONE_TOKEN_TTL]));
        return response()->json(['phone_token' => $phone_token], 200);
    }

    /** True when $token came from verify_otp() for $phone and has not expired. */
    private function phone_token_valid($token, string $phone): bool
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $token), true);
        } catch (\Exception $e) {
            return false;
        }
        return ($data['phone'] ?? null) === $phone && ($data['exp'] ?? 0) >= now()->timestamp;
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'f_name' => 'required',
            'identity_type' => 'required|in:passport,driving_license,nid',
            'identity_number' => 'required',
            'email' => 'nullable|email|unique:delivery_men',
            'phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|unique:delivery_men',
            'phone_token' => 'required',
            'password' => ['required', Password::min(8)->mixedCase()->letters()->numbers()->symbols()->uncompromised()],
            'zone_id' => 'required',
            'vehicle_id' => 'required',
            'earning' => 'nullable'
        ], [
            'f_name.required' => translate('messages.first_name_is_required'),
            'zone_id.required' => translate('messages.select_a_zone'),
            'earning.required' => translate('messages.select_dm_type'),
            'vehicle_id.required' => translate('messages.select_a_vehicle'),
            'password.required' => translate('The password is required'),
            'password.min_length' => translate('The password must be at least :min characters long'),
            'password.mixed' => translate('The password must contain both uppercase and lowercase letters'),
            'password.letters' => translate('The password must contain letters'),
            'password.numbers' => translate('The password must contain numbers'),
            'password.symbols' => translate('The password must contain symbols'),
            'password.uncompromised' => translate('The password is compromised. Please choose a different one'),

        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)],403);
        }

        if (!$this->phone_token_valid($request->phone_token, AkedlyGateway::normalize_phone($request->phone))) {
            return response()->json(['errors' => [
                ['code' => 'phone_token', 'message' => translate('messages.verify_your_phone_again')]
            ]], 403);
        }

        if ($request->has('image')) {
            $image_name = Helpers::upload('delivery-man/', 'png', $request->file('image'));
        } else {
            $image_name = 'def.png';
        }

        $id_img_names = [];
        if (!empty($request->file('identity_image'))) {
            foreach ($request->identity_image as $img) {
                $identity_image = Helpers::upload('delivery-man/', 'png', $img);
                array_push($id_img_names, ['img'=>$identity_image, 'storage'=> Helpers::getDisk()]);
            }
            $identity_image = json_encode($id_img_names);
        } else {
            $identity_image = json_encode([]);
        }

        $dm = New DeliveryMan();
        $dm->f_name = $request->f_name;
        $dm->l_name = $request->l_name;
        $dm->email = $request->email;
        $dm->phone = $request->phone;
        $dm->identity_number = $request->identity_number;
        $dm->identity_type = $request->identity_type;
        $dm->identity_image = $identity_image;
        $dm->vehicle_id = $request->vehicle_id;
        $dm->image = $image_name;
        $dm->status = 0;
        $dm->active = 0;
        $dm->application_status = 'pending';
        $dm->zone_id = $request->zone_id;
        $dm->earning = config('order.dm_freelance_enabled') ? (int) $request->earning : 0;
        $dm->password = bcrypt($request->password);

        $dm->save();
        try{
            $admin= Admin::where('role_id', 1)->first();
            $mail_status = Helpers::get_mail_status('registration_mail_status_dm');
            if($request->email && config('mail.status') && $mail_status == '1' && Helpers::getNotificationStatusData('deliveryman','deliveryman_registration','mail_status')){
                Mail::to($request->email)->send(new \App\Mail\DmSelfRegistration('pending', $dm->f_name.' '.$dm->l_name));
            }
            $mail_status = Helpers::get_mail_status('dm_registration_mail_status_admin');
            if(config('mail.status') && $mail_status == '1' && Helpers::getNotificationStatusData('admin','deliveryman_self_registration','mail_status')){
                Mail::to($admin['email'])->send(new \App\Mail\DmRegistration('pending', $dm->f_name.' '.$dm->l_name));
            }
        }catch(\Exception $ex){
            info($ex->getMessage());
        }

        return response()->json(['message' => translate('messages.deliveryman_added_successfully')], 200);
    }
}
