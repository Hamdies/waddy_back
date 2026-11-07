<?php

namespace App\Traits;

use App\CentralLogics\AkedlyGateway;
use App\CentralLogics\SMS_module;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Gateways\Traits\SmsGateway;

/**
 * The phone_verifications flow shared by the vendor and rider apps: resend
 * interval, code reuse, expiry and the wrong-code block.
 */
trait PhoneOtp
{
    /** Seconds between OTP sends; the app runs its resend countdown from the value we return. */
    const OTP_RESEND_INTERVAL = 30;

    /** A resend inside this window re-sends the code on file, so a late WhatsApp and the SMS fallback match. */
    const OTP_REUSE_WINDOW = 300;

    /** An OTP older than this is refused. */
    const OTP_EXPIRY = 600;

    /** Text a code to an already-normalized phone. */
    protected function send_phone_otp(string $phone, $channel = null)
    {
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
        AkedlyGateway::request_channel($channel);
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

    /**
     * The error response when $otp is not a live code for $phone, else null.
     * The caller deletes the phone_verifications row once it acts on the code.
     */
    protected function check_phone_otp(string $phone, $otp)
    {
        // A blocked number stays blocked even for the right code.
        $blocked = $this->otp_block_response($phone);
        if ($blocked) {
            return $blocked;
        }

        $data = DB::table('phone_verifications')->where(['phone' => $phone, 'token' => $otp])->first();
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

        return null;
    }

    /** The error response while this phone sits in its 10-minute wrong-OTP block, else null. */
    protected function otp_block_response($phone)
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
    protected function record_wrong_otp($phone)
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
}
