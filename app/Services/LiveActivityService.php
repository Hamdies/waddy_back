<?php

namespace App\Services;

use App\Models\Order;
use App\Models\LiveActivityToken;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending APNs push notifications to iOS Live Activity tokens.
 *
 * Uses HTTP/2 APNs provider API with JWT-based authentication.
 * Requires the following config values in config/services.php under 'apns':
 *   - team_id:     Apple Developer Team ID
 *   - key_id:      APNs Auth Key ID (.p8 key identifier)
 *   - private_key: Contents of the .p8 private key file
 *   - bundle_id:   App bundle identifier (e.g. 'com.waddy.app')
 *   - environment: 'production' or 'sandbox'
 */
class LiveActivityService
{
    private const APNS_PRODUCTION_URL = 'https://api.push.apple.com';
    private const APNS_SANDBOX_URL = 'https://api.sandbox.push.apple.com';

    /**
     * Push an update (or end) to a Live Activity via APNs.
     *
     * @param string $pushToken  The Live Activity push token
     * @param Order  $order      The order to build content-state from
     * @param string $event      'update' or 'end'
     * @return bool
     */
    public function pushUpdate(string $pushToken, Order $order, string $event = 'update'): bool
    {
        $config = config('services.apns');

        if (!$config || !($config['team_id'] ?? null) || !($config['key_id'] ?? null) || !($config['private_key'] ?? null)) {
            Log::info('APNs not configured — skipping Live Activity push');
            return false;
        }

        $payload = $this->buildPayload($order, $event);

        $url = $this->getBaseUrl($config) . '/3/device/' . $pushToken;

        try {
            $jwt = $this->generateJwt($config);

            $headers = [
                'authorization'  => 'bearer ' . $jwt,
                'apns-topic'     => ($config['bundle_id'] ?? 'com.waddy.app') . '.push-type.liveactivity',
                'apns-push-type' => 'liveactivity',
                'apns-priority'  => '10',
            ];

            $response = Http::withHeaders($headers)
                ->withOptions(['version' => 2.0])
                ->post($url, $payload);

            if ($response->successful()) {
                return true;
            }

            // 410 Gone means the token is no longer valid — clean up
            if ($response->status() === 410) {
                LiveActivityToken::where('push_token', $pushToken)->delete();
                Log::info("APNs: Deleted expired Live Activity token for order {$order->id}");
                return false;
            }

            Log::warning('APNs Live Activity push failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'order'  => $order->id,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('APNs Live Activity push exception: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Build the APNs payload for a Live Activity push.
     */
    private function buildPayload(Order $order, string $event): array
    {
        $extendedData = OrderNotificationService::buildExtendedPayload($order);

        $contentState = [
            'status'          => $extendedData['status'],
            'subStatus'       => $extendedData['sub_status'] ?: null,
            'etaMinutes'      => $extendedData['eta_minutes'] ? (int) $extendedData['eta_minutes'] : null,
            'etaText'         => $extendedData['eta_text'] ?: null,
            'progress'        => (float) $extendedData['progress'],
            'deliveryManName' => $extendedData['delivery_man_name'] ?: null,
            'storeName'       => $extendedData['store_name'] ?: null,
            'title'           => $extendedData['display_title'],
            'subtitle'        => $extendedData['display_subtitle'],
            'step'            => (int) $extendedData['step'],
        ];

        $aps = [
            'timestamp'     => time(),
            'event'         => $event,
            'content-state' => $contentState,
        ];

        // For end events, dismiss after 4 hours
        if ($event === 'end') {
            $aps['dismissal-date'] = time() + (4 * 3600);
        }

        return ['aps' => $aps];
    }

    /**
     * Generate a JWT for APNs authentication.
     */
    private function generateJwt(array $config): string
    {
        $header = [
            'alg' => 'ES256',
            'kid' => $config['key_id'],
        ];

        $claims = [
            'iss' => $config['team_id'],
            'iat' => time(),
        ];

        $headerEncoded = rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '=');
        $claimsEncoded = rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

        $signingInput = $headerEncoded . '.' . $claimsEncoded;

        $privateKey = openssl_pkey_get_private($config['private_key']);
        if (!$privateKey) {
            throw new \RuntimeException('Invalid APNs private key');
        }

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        // ES256 signature needs to be converted from DER to raw (r || s)
        $signature = $this->derToRaw($signature);

        $signatureEncoded = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $signingInput . '.' . $signatureEncoded;
    }

    /**
     * Convert DER-encoded ECDSA signature to raw (r || s) format.
     */
    private function derToRaw(string $der): string
    {
        $pos = 0;
        $pos++; // skip sequence tag (0x30)
        $pos++; // skip sequence length

        // Read r
        $pos++; // skip integer tag (0x02)
        $rLen = ord($der[$pos++]);
        $r = substr($der, $pos, $rLen);
        $pos += $rLen;

        // Read s
        $pos++; // skip integer tag (0x02)
        $sLen = ord($der[$pos++]);
        $s = substr($der, $pos, $sLen);

        // Pad r and s to 32 bytes each
        $r = str_pad(ltrim($r, "\x00"), 32, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), 32, "\x00", STR_PAD_LEFT);

        return $r . $s;
    }

    /**
     * Get the APNs base URL based on environment config.
     */
    private function getBaseUrl(array $config): string
    {
        $env = $config['environment'] ?? 'sandbox';
        return $env === 'production' ? self::APNS_PRODUCTION_URL : self::APNS_SANDBOX_URL;
    }
}
