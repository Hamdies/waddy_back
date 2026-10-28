<?php

namespace App\Services;

use App\Events\StoreOrderChanged;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Live updates for the store app (RT). One place decides what is sent and
 * when; the order observer calls it for every created / updated order.
 */
class StoreRealtime
{
    /** Orders already queued in this request, so several saves send one event. */
    private static array $pending = [];

    public static function enabled(): bool
    {
        return config('broadcasting.default') === 'reverb' && (bool) config('broadcasting.connections.reverb.key');
    }

    public static function orderChanged(Order $order): void
    {
        if (!self::enabled() || !$order->store_id || $order->order_type === 'pos') {
            return;
        }
        $first = !isset(self::$pending[$order->id]);
        self::$pending[$order->id] = new StoreOrderChanged(
            (int) $order->store_id,
            (int) $order->id,
            $order->order_status,
            $order->updated_at ? Carbon::parse($order->updated_at)->toIso8601String() : null,
        );
        if (!$first) {
            return;
        }
        // After the response in web requests; at once in console (scheduler,
        // tinker), where "terminating" can be far away.
        $send = function () use ($order) {
            $event = self::$pending[$order->id] ?? null;
            unset(self::$pending[$order->id]);
            if (!$event) {
                return;
            }
            try {
                broadcast($event);
            } catch (\Throwable $e) {
                Log::warning("Realtime order.changed for order {$order->id} not sent: " . $e->getMessage());
            }
        };
        app()->runningInConsole() ? $send() : app()->terminating($send);
    }

    /**
     * Pusher-protocol signature for a private channel, or null when this
     * store may not listen on it. Only `private-store.{its own store id}`.
     */
    public static function authorize(int $storeId, string $socketId, string $channel): ?string
    {
        if ($channel !== 'private-store.' . $storeId || !preg_match('/^\d+\.\d+$/', $socketId)) {
            return null;
        }
        $key = config('broadcasting.connections.reverb.key');
        $secret = config('broadcasting.connections.reverb.secret');
        return $key . ':' . hash_hmac('sha256', $socketId . ':' . $channel, $secret);
    }

    /** What the apps need to connect, in /config. Null when off. */
    public static function clientConfig(): ?array
    {
        if (!self::enabled()) {
            return null;
        }
        $o = config('broadcasting.connections.reverb.public');
        if (empty($o['host'])) {
            return null;
        }
        return [
            'key' => config('broadcasting.connections.reverb.key'),
            'host' => $o['host'] ?? null,
            'port' => (int) ($o['port'] ?? 443),
            'scheme' => $o['scheme'] ?? 'https',
        ];
    }
}
