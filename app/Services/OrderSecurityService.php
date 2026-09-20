<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\CentralLogics\Helpers;

/**
 * Order-placement abuse controls.
 *
 * Idempotency and the cooldown are real controls: both return a response that
 * stops the order. The device fingerprint is audit telemetry.
 *
 * A client-generated HMAC `order_signature` used to be verified here. It was
 * removed because it could not work: the client needs the signing secret to
 * sign, so the secret shipped inside the APK and anyone could forge a valid
 * signature. It also only ever wrote a log line — a mismatch never blocked an
 * order — so it documented a protection that did not exist. Order amounts are
 * protected by the server recomputing `order_amount` in PlaceNewOrder rather
 * than trusting the client's. If tamper-evidence is wanted later it must be
 * server-issued: sign a short-lived quote token here, have the client echo it
 * back, verify it with a secret that never leaves the server. Play Integrity /
 * App Attest is the tool for proving the caller is a genuine app build.
 */
class OrderSecurityService
{
    const IDEMPOTENCY_TTL_SECONDS = 3600;
    const ORDER_COOLDOWN_SECONDS = 30;

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
     * Store security fields on the order for audit purposes.
     */
    public function storeSecurityFields(Order $order, Request $request): void
    {
        $order->idempotency_key = $request->input('idempotency_key');
        $order->device_fingerprint = $request->input('device_fingerprint');
    }
}
