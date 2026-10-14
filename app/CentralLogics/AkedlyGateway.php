<?php

namespace App\CentralLogics;

use Illuminate\Support\Facades\Http;

/**
 * Akedly OTP delivery over the V1.2 REST API.
 *
 * Waddy generates and verifies its own OTPs (phone_verifications / password_resets),
 * so we hand Akedly our code through the `otp` field and only use it for delivery.
 * That keeps every login, signup, social and reset flow untouched and bills per
 * message, exactly like the legacy V1 create + activate calls this replaces.
 *
 * V1.2 gates /send behind a Proof-of-Work challenge. PoW is just a hash puzzle, so
 * we solve it here on the server. Cloudflare Turnstile genuinely needs a browser,
 * so the pipeline must have Turnstile DISABLED for this server-to-server flow.
 *
 * Flow: GET /challenge -> solve nonce -> POST /send
 *
 * @see https://docs.akedly.io/authentication/v1-2
 */
class AkedlyGateway
{
    const BASE_URL = 'https://api.akedly.io/api/v1.2/transactions';
    const TIMEOUT = 10;

    /** Safety cap so a misconfigured difficulty can never hang a login request. */
    const POW_MAX_ITERATIONS = 5000000;

    /** PoW rejections that a fresh challenge fixes; anything else is not worth a retry. */
    const RETRYABLE_POW_CODES = ['POW_DIFFICULTY_STALE', 'POW_CHALLENGE_EXPIRED', 'POW_CHALLENGE_REPLAYED'];

    const CHANNELS = ['whatsapp', 'sms'];

    /**
     * Channel the next send() must use, or null to let the pipeline's fallback
     * chain decide (WhatsApp first, then SMS). Set by the caller because the
     * SMS_module::send($receiver, $otp) signature it travels through has no room
     * for it; send() clears it so it never leaks into an unrelated send.
     */
    protected static ?string $requested_channel = null;

    /** Channel Akedly reported for the most recent successful send in this request. */
    protected static ?string $last_channel = null;

    public static function request_channel(?string $channel): void
    {
        self::$requested_channel = in_array($channel, self::CHANNELS, true) ? $channel : null;
    }

    public static function last_channel(): ?string
    {
        return self::$last_channel;
    }

    /**
     * Deliver `$otp` to `$receiver`.
     *
     * @param  array  $config  the akedly sms_config values (status, api_key, pipeline_id)
     * @return string 'success' | 'error'
     */
    public static function send($receiver, $otp, ?array $config): string
    {
        $channel = self::$requested_channel;
        self::$requested_channel = null;
        self::$last_channel = null;

        if (!isset($config) || $config['status'] != 1
            || empty($config['api_key']) || empty($config['pipeline_id'])) {
            return 'error';
        }

        try {
            // The server adapts PoW difficulty per request, so a stale or expired
            // challenge gets one fresh attempt before we give up.
            for ($attempt = 0; $attempt < 2; $attempt++) {
                $result = self::attempt_send($receiver, $otp, $config, $channel);

                // A pipeline without per-request channel choice rejects `channel`
                // outright. Sending through its own chain beats sending nothing;
                // last_channel() still reports where the code actually went.
                if ($result === 'channel_rejected') {
                    $channel = null;
                    $result = self::attempt_send($receiver, $otp, $config, null);
                }

                if ($result === 'success') {
                    return 'success';
                }
                if ($result !== 'retry') {
                    return 'error';
                }
            }
        } catch (\Exception $exception) {
            info('Akedly send exception: ' . $exception->getMessage());
        }

        return 'error';
    }

    /** @return string 'success' | 'retry' | 'channel_rejected' | 'error' */
    protected static function attempt_send($receiver, $otp, array $config, ?string $channel): string
    {
        $challenge = Http::timeout(self::TIMEOUT)
            ->get(self::BASE_URL . '/challenge', [
                'APIKey' => $config['api_key'],
                'pipelineID' => $config['pipeline_id'],
            ]);

        if (!$challenge->successful()) {
            info('Akedly challenge failed: ' . $challenge->body());
            return 'error';
        }

        $data = $challenge->json('data') ?? [];

        // Turnstile needs a real browser. If the pipeline still has it on we cannot
        // complete the send from the server, so fail loudly instead of sending a
        // request we know Cloudflare will reject.
        if (data_get($data, 'turnstile.required')) {
            info('Akedly: Turnstile is enabled on this pipeline; disable it for server-to-server OTP.');
            return 'error';
        }

        $otp = (string) $otp;
        $payload = [
            'APIKey' => $config['api_key'],
            'pipelineID' => $config['pipeline_id'],
            'verificationAddress' => [
                'phoneNumber' => self::normalize_phone($receiver),
            ],
            'otp' => $otp,
            'digits' => strlen($otp),
            // The app's X-localization header has already set the locale; Akedly
            // only knows en/ar, so anything else falls back to English.
            'otpLanguage' => app()->getLocale() === 'ar' ? 'ar' : 'en',
        ];

        if ($channel !== null) {
            $payload['channel'] = $channel;
        }

        if (data_get($data, 'challengeRequired')) {
            $nonce = self::solve_pow(data_get($data, 'challenge'), (int) data_get($data, 'difficulty', 3));

            if ($nonce === null) {
                info('Akedly: could not solve PoW challenge within the iteration cap.');
                return 'error';
            }

            $payload['powSolution'] = [
                'challengeToken' => data_get($data, 'challengeToken'),
                'nonce' => $nonce,
            ];
        }

        $send = Http::timeout(self::TIMEOUT)
            ->post(self::BASE_URL . '/send', $payload);

        if ($send->successful() && data_get($send->json(), 'status') === 'success') {
            // `channels` lists where the code went, primary first. Anything we
            // can't name (telegram, email) is reported as SMS, the generic case.
            $used = data_get($send->json(), 'data.channels.0', $channel);
            self::$last_channel = $used === 'whatsapp' ? 'whatsapp' : 'sms';
            return 'success';
        }

        info('Akedly send failed: ' . $send->body());

        $code = data_get($send->json(), 'code');

        if ($channel !== null && in_array($code, ['channel_choice_not_enabled', 'channel_not_eligible', 'invalid_channel'], true)) {
            return 'channel_rejected';
        }

        return in_array($code, self::RETRYABLE_POW_CODES, true) ? 'retry' : 'error';
    }

    /**
     * Solve the Proof-of-Work challenge: find a nonce where the hex digest of
     * sha256("<challenge>:<nonce>") begins with `difficulty` zeros.
     */
    public static function solve_pow($challenge, int $difficulty): ?int
    {
        if (empty($challenge) || $difficulty < 1) {
            return null;
        }

        $prefix = str_repeat('0', $difficulty);

        for ($nonce = 0; $nonce < self::POW_MAX_ITERATIONS; $nonce++) {
            if (strncmp(hash('sha256', $challenge . ':' . $nonce), $prefix, $difficulty) === 0) {
                return $nonce;
            }
        }

        return null;
    }

    /**
     * Akedly requires E.164. Only formatting noise and an international "00"
     * prefix are handled; a bare local number carries no country code, so it is
     * passed through for Akedly to reject rather than guessing a country.
     */
    public static function normalize_phone($phone): string
    {
        $phone = preg_replace('/[^0-9+]/', '', (string) $phone);

        if (substr($phone, 0, 2) === '00') {
            return '+' . substr($phone, 2);
        }

        return $phone;
    }
}
