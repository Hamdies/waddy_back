<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends Purchase events to the Meta Conversions API (app dataset).
 *
 * This is the server-side twin of the SDK purchase event the app already
 * logs: it recovers attribution for iOS users who deny App Tracking
 * Transparency, because matching falls back to hashed phone/email instead
 * of the IDFA. Both sides send event_id "purchase_{order_id}" so Meta
 * deduplicates when it receives the event twice.
 */
class MetaConversionsService
{
    public static function sendPurchase(Order $order, string $platform, bool $attEnabled): void
    {
        $config = self::resolveConfig();
        if (!$config['enabled'] || empty($config['access_token'])) {
            return;
        }

        $address = json_decode($order->delivery_address ?? '{}', true) ?: [];

        $userData = array_filter([
            'ph' => self::hashPhone($address['contact_person_number'] ?? $order->customer?->phone),
            'em' => self::hashEmail($address['contact_person_email'] ?? $order->customer?->email),
            'external_id' => $order->user_id ? [hash('sha256', (string) $order->user_id)] : null,
        ]);

        // Match rate depends on user_data; an event with nothing to match on
        // is noise, not signal.
        if (empty($userData['ph']) && empty($userData['em'])) {
            return;
        }

        $payload = [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => $order->created_at?->timestamp ?? time(),
                'event_id' => 'purchase_' . $order->id,
                'action_source' => 'app',
                'user_data' => $userData,
                'custom_data' => [
                    'currency' => $config['currency'],
                    'value' => (float) $order->order_amount,
                    'order_id' => (string) $order->id,
                ],
                'app_data' => [
                    'advertiser_tracking_enabled' => $attEnabled ? 1 : 0,
                    'application_tracking_enabled' => 1,
                    'extinfo' => self::extinfo($platform),
                ],
            ]],
        ];

        try {
            $response = Http::timeout(5)->post(
                sprintf(
                    'https://graph.facebook.com/%s/%s/events?access_token=%s',
                    $config['graph_version'],
                    $config['dataset_id'],
                    $config['access_token']
                ),
                $payload
            );

            if (!$response->successful()) {
                Log::warning('Meta CAPI purchase rejected', [
                    'order_id' => $order->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);
            }
        } catch (\Throwable $e) {
            // Ads signal must never surface as an order error.
            Log::warning('Meta CAPI purchase failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Admin-panel settings (Business Settings → 3rd Party → Meta Ads
     * Tracking) win over .env, so marketing can rotate the token without a
     * deploy. .env remains the fallback for headless setups.
     */
    private static function resolveConfig(): array
    {
        $env = config('services.meta_capi');
        $settings = [];
        try {
            $settings = Helpers::get_business_settings('meta_capi') ?: [];
        } catch (\Throwable $e) {
            // Settings table unavailable (fresh install, migration run) —
            // fall through to .env.
        }

        return [
            'enabled' => isset($settings['status'])
                ? (bool) $settings['status']
                : (bool) $env['enabled'],
            'dataset_id' => !empty($settings['dataset_id'])
                ? $settings['dataset_id']
                : $env['dataset_id'],
            'access_token' => !empty($settings['access_token'])
                ? $settings['access_token']
                : $env['access_token'],
            'graph_version' => $env['graph_version'],
            'currency' => $env['currency'],
        ];
    }

    /**
     * Meta requires phones hashed as digits only with country code.
     * Egyptian numbers arriving as 01xxxxxxxxx get the 20 prefix.
     */
    private static function hashPhone(?string $phone): ?array
    {
        $digits = preg_replace('/\D/', '', (string) $phone);
        if ($digits === '') {
            return null;
        }
        if (str_starts_with($digits, '0')) {
            $digits = '20' . substr($digits, 1);
        }
        return [hash('sha256', $digits)];
    }

    private static function hashEmail(?string $email): ?array
    {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        return [hash('sha256', $email)];
    }

    /**
     * Meta's extinfo must be exactly 16 elements, version first
     * (a2 = Android, i2 = iOS). Unknown fields stay empty strings.
     */
    private static function extinfo(string $platform): array
    {
        $info = array_fill(0, 16, '');
        $info[0] = $platform === 'ios' ? 'i2' : 'a2';
        $info[1] = 'com.hamdiesolutions.waddi';
        return $info;
    }
}
