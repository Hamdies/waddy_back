<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\CentralLogics\Helpers;

class OrderSecurityService
{
    const IDEMPOTENCY_TTL_SECONDS = 3600;
    const ORDER_COOLDOWN_SECONDS = 30;
    const SIGNATURE_WINDOW_SECONDS = 300;

    /**
     * Check if the idempotency key has already been used.
     * Returns a 409 response if duplicate, null if OK or absent.
     */
    public function checkIdempotency(Request $request): ?JsonResponse
    {
        $key = $request->input('idempotency_key');

        if (!$key) {
            Log::info('Order placed without idempotency key', [
                'user_id' => $request->user?->id,
                'ip' => $request->ip(),
            ]);
            return null;
        }

        $cacheKey = 'order_idempotency:' . $key;

        if (!Cache::add($cacheKey, true, self::IDEMPOTENCY_TTL_SECONDS)) {
            return response()->json([
                'errors' => [
                    ['code' => 'duplicate_order', 'message' => translate('messages.this_order_has_already_been_submitted')]
                ]
            ], 409);
        }

        return null;
    }

    /**
     * Enforce a 30-second cooldown between orders per user.
     * Returns a 429 response if too soon, null if OK.
     */
    public function checkOrderCooldown(Request $request): ?JsonResponse
    {
        $userId = $request->user?->id
            ? 'user_' . $request->user->id
            : 'guest_' . $request->input('guest_id');

        $cacheKey = 'order_cooldown:' . $userId;

        if (Cache::has($cacheKey)) {
            return response()->json([
                'errors' => [
                    ['code' => 'order_cooldown', 'message' => translate('messages.please_wait_before_placing_another_order')]
                ]
            ], 429);
        }

        Cache::put($cacheKey, true, self::ORDER_COOLDOWN_SECONDS);

        return null;
    }

    /**
     * Verify the HMAC-SHA256 order signature.
     * Logs warnings only — never blocks the order (rollout phase).
     */
    public function verifySignature(Request $request): void
    {
        $signature = $request->input('order_signature');
        $timestamp = $request->input('order_timestamp');
        $amount = $request->input('order_amount');
        $zoneId = $request->input('zone_id');

        if (!$signature || !$timestamp) {
            Log::info('Order placed without security signature', [
                'user_id' => $request->user?->id,
                'has_signature' => (bool) $signature,
                'has_timestamp' => (bool) $timestamp,
            ]);
            return;
        }

        $serverTimeMs = (int) (microtime(true) * 1000);
        $clientTimeMs = (int) $timestamp;
        $diffSeconds = abs($serverTimeMs - $clientTimeMs) / 1000;

        if ($diffSeconds > self::SIGNATURE_WINDOW_SECONDS) {
            Log::warning('Order signature timestamp expired', [
                'user_id' => $request->user?->id,
                'diff_seconds' => $diffSeconds,
                'client_timestamp' => $clientTimeMs,
            ]);
        }

        $data = [
            'amount' => (string) $amount,
            'timestamp' => (string) $timestamp,
            'zone_id' => (string) $zoneId,
        ];

        ksort($data);
        $filtered = array_filter($data, fn($v) => $v !== null && $v !== '');
        $payload = http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);

        $secret = config('services.order_security.hmac_secret', 'waddi_order_sec_2026');
        $expected = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expected, $signature)) {
            Log::warning('Order signature verification failed', [
                'user_id' => $request->user?->id,
                'expected' => $expected,
                'received' => $signature,
                'payload' => $payload,
            ]);
        }
    }

    /**
     * Store security fields on the order for audit purposes.
     */
    public function storeSecurityFields(Order $order, Request $request): void
    {
        $order->idempotency_key = $request->input('idempotency_key');
        $order->device_fingerprint = $request->input('device_fingerprint');
        $order->order_timestamp = $request->input('order_timestamp');
    }
}
