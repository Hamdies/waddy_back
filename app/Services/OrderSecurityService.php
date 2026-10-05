<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\CentralLogics\Helpers;

/**
 * Order-placement abuse controls.
 *
 * Idempotency and the cooldown are real controls: both return a response that
 * stops the order. The device fingerprint is audit telemetry.
 *
 * ## How a placement is guarded ({@see guard()})
 *
 * 1. One request at a time per (owner, key). The key is locked while a
 *    request is in flight; a second request meanwhile gets 409
 *    `order_in_progress` and should retry.
 * 2. A replay returns the original order. If an order already exists for this
 *    owner and key, the response is the original 200 with that `order_id`, so
 *    a client whose first response was lost lands on the success screen.
 *    The source of truth is the `orders` table (unique on user_id +
 *    idempotency_key), not the cache, so it survives cache eviction.
 * 3. The cooldown starts only after a successful order, and is never applied
 *    to a replay. A rejected attempt (bad coupon, closed store, zone, …) used
 *    to burn both the key and the cooldown, so the retry the customer was
 *    told to make was refused.
 *
 * Nothing is written to the cache to "claim" a key any more. The in-flight
 * lock is released in a `finally`, so every rollback and rejection path
 * releases it without having to remember to.
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
    const IN_FLIGHT_LOCK_SECONDS = 120;
    const ORDER_COOLDOWN_SECONDS = 30;

    /**
     * Run [$place] under the order-placement guards and return its response.
     *
     * @param  \Closure(): \Illuminate\Http\JsonResponse  $place
     */
    public function guard(Request $request, \Closure $place): JsonResponse
    {
        $owner = $this->owner($request);
        $key = $this->key($request);

        if (!$key) {
            Log::info('Order placed without idempotency key', [
                'user_id' => $request->user?->id,
                'ip' => $request->ip(),
            ]);
        }

        // Keyless requests (older apps) share one lock per owner, which keeps
        // the double-tap protection the old cooldown gave them.
        $lock = Cache::lock(
            'order_inflight:' . $owner . ':' . ($key ?? 'nokey'),
            self::IN_FLIGHT_LOCK_SECONDS
        );

        if (!$lock->get()) {
            return response()->json([
                'errors' => [
                    ['code' => 'order_in_progress', 'message' => translate('messages.order_in_progress')]
                ]
            ], 409);
        }

        try {
            // Checked after taking the lock: a request that waited on the
            // lock must see the order the winner just committed.
            if ($order = $this->findPlacedOrder($request, $key)) {
                return $this->placedResponse($order, true);
            }

            if ($this->isCoolingDown($owner)) {
                return response()->json([
                    'errors' => [
                        ['code' => 'order_cooldown', 'message' => translate('messages.please_wait_before_placing_another_order')]
                    ]
                ], 429);
            }

            $response = $place();

            if ($response->getStatusCode() === 200) {
                $this->startCooldown($owner);
                return $response;
            }

            // The order can be committed and the request still end in an
            // error (a notification throws after DB::commit, or the unique
            // index rejected a racing duplicate). The order exists, so tell
            // the client that rather than inviting a retry.
            if ($order = $this->findPlacedOrder($request, $key)) {
                $this->startCooldown($owner);
                return $this->placedResponse($order, true);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    /**
     * The success body of a placed order. Shared by the first response and by
     * replays so the two cannot drift apart.
     */
    public function placedResponse(Order $order, bool $replayed = false): JsonResponse
    {
        $body = [
            'message' => translate('messages.order_placed_successfully'),
            'order_id' => $order->id,
            'total_ammount' => $order->order_amount,
            'status' => $order->order_status,
            'created_at' => $order->created_at,
            'user_id' => (int) $order->user_id,
        ];

        if ($replayed) {
            $body['replayed'] = true;
        }

        return response()->json($body, 200);
    }

    /**
     * The order this owner already placed with this key, if any.
     */
    private function findPlacedOrder(Request $request, ?string $key): ?Order
    {
        if (!$key) {
            return null;
        }

        $ownerId = $request->user ? $request->user->id : $request->input('guest_id');
        if (!$ownerId) {
            return null;
        }

        return Order::where('user_id', $ownerId)
            ->where('idempotency_key', $key)
            ->orderBy('id')
            ->first();
    }

    /**
     * The client's idempotency key, or null when absent or not a UUID (the
     * request validator rejects the latter; it must not reach a lock name).
     */
    private function key(Request $request): ?string
    {
        $key = $request->input('idempotency_key');

        return is_string($key) && Str::isUuid($key) ? $key : null;
    }

    private function owner(Request $request): string
    {
        return $request->user?->id
            ? 'user_' . $request->user->id
            : 'guest_' . $request->input('guest_id');
    }

    private function isCoolingDown(string $owner): bool
    {
        return Cache::has('order_cooldown:' . $owner);
    }

    private function startCooldown(string $owner): void
    {
        Cache::put('order_cooldown:' . $owner, true, self::ORDER_COOLDOWN_SECONDS);
    }

    /**
     * Store security fields on the order for audit purposes. The idempotency
     * key is also what makes a replay findable, so it is only stored when it
     * is a valid UUID.
     */
    public function storeSecurityFields(Order $order, Request $request): void
    {
        $order->idempotency_key = $this->key($request);
        $order->device_fingerprint = $request->input('device_fingerprint');
    }
}
