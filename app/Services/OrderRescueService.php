<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Order rescue (XS-3). Runs every minute and catches orders that are going
 * nowhere, before the customer gives up and cancels:
 *
 * - store has not confirmed (only where the store is the one who confirms):
 *   5 min → ring the store's phones again; 10 min → admin alert + an honest
 *   message to the customer;
 * - no rider yet (delivery orders, store without its own riders): 8 min →
 *   offer it to the zone's riders again; 15 min → admin alert + customer.
 *
 * Each step happens once per order (atomic cache flag).
 */
class OrderRescueService
{
    public const STORE_RING_AGAIN = 5;
    public const STORE_ESCALATE = 10;
    public const RIDER_OFFER_AGAIN = 8;
    public const RIDER_ESCALATE = 15;

    /** Orders older than this are someone else's problem (admin queue). */
    private const LOOKBACK_HOURS = 3;

    /**
     * A step fires only within this many minutes after its threshold: ringing
     * a store or telling a customer "we're on it" hours later (or on the
     * first run after a deploy) helps nobody.
     */
    public const STEP_WINDOW = 30;

    public static function due(int $minutes, int $threshold): bool
    {
        return $minutes >= $threshold && $minutes < $threshold + self::STEP_WINDOW;
    }

    /**
     * What is wrong with [$order] at [$now] and for how long, or null.
     * Pure apart from config, so it is tested.
     *
     * @return array{problem:string, minutes:int}|null  problem: 'store' or 'rider'
     */
    public static function diagnose(Order $order, Carbon $now, bool $selfDelivery): ?array
    {
        if ($order->scheduled && $order->schedule_at && Carbon::parse($order->schedule_at)->isAfter($now)) {
            return null;
        }
        $cod = $order->payment_method === 'cash_on_delivery';
        $storeModel = config('order_confirmation_model') === 'store';

        if ($order->order_status === 'pending' && $cod && ($storeModel || $order->order_type === 'take_away')) {
            $since = Carbon::parse($order->pending ?? $order->created_at);
            return ['problem' => 'store', 'minutes' => (int) $since->diffInMinutes($now)];
        }

        if ($order->order_type === 'delivery' && !$order->delivery_man_id && !$selfDelivery) {
            // Under the deliveryman model a pending cash order is waiting for a
            // rider to accept it; otherwise riders are needed once confirmed.
            $waitingForRider = ($order->order_status === 'pending' && $cod && !$storeModel)
                || in_array($order->order_status, ['confirmed', 'processing', 'handover'], true);
            if ($waitingForRider) {
                $since = Carbon::parse($order->confirmed ?? $order->pending ?? $order->created_at);
                return ['problem' => 'rider', 'minutes' => (int) $since->diffInMinutes($now)];
            }
        }
        return null;
    }

    public static function run(): int
    {
        $acted = 0;
        $orders = Order::withoutGlobalScopes()
            ->whereIn('order_status', ['pending', 'confirmed', 'processing', 'handover'])
            ->where('created_at', '>=', now()->subHours(self::LOOKBACK_HOURS))
            ->with(['store', 'zone', 'customer'])
            ->get();
        foreach ($orders as $order) {
            try {
                $acted += self::handle($order);
            } catch (\Throwable $e) {
                Log::warning("Order rescue failed for order {$order->id}: " . $e->getMessage());
            }
        }
        return $acted;
    }

    private static function handle(Order $order): int
    {
        $diagnosis = self::diagnose($order, now(), (bool) $order->store?->sub_self_delivery);
        if (!$diagnosis) {
            return 0;
        }
        ['problem' => $problem, 'minutes' => $minutes] = $diagnosis;
        $acted = 0;

        if ($problem === 'store') {
            if (self::due($minutes, self::STORE_RING_AGAIN) && self::once($order, 'store_ring')) {
                self::ringStoreAgain($order, $minutes);
                $acted++;
            }
            if (self::due($minutes, self::STORE_ESCALATE) && self::once($order, 'store_escalate')) {
                self::alertAdmin($order, "Store has not confirmed order #{$order->id} for {$minutes} min");
                self::tellCustomer($order, 'store');
                $acted++;
            }
        } else {
            if (self::due($minutes, self::RIDER_OFFER_AGAIN) && self::once($order, 'rider_offer')) {
                self::offerToRidersAgain($order);
                $acted++;
            }
            if (self::due($minutes, self::RIDER_ESCALATE) && self::once($order, 'rider_escalate')) {
                self::alertAdmin($order, "No rider for order #{$order->id} after {$minutes} min");
                self::tellCustomer($order, 'rider');
                $acted++;
            }
        }
        return $acted;
    }

    private static function once(Order $order, string $step): bool
    {
        return Cache::add("order_rescue_{$order->id}_{$step}", 1, now()->addHours(self::LOOKBACK_HOURS + 1));
    }

    private static function ringStoreAgain(Order $order, int $minutes): void
    {
        if (!$order->store) {
            return;
        }
        foreach (StorePushService::tokensByLanguage($order->store) as $lang => $tokens) {
            $ar = $lang === 'ar';
            $data = [
                'title' => $ar ? "الطلب #{$order->id} ما زال ينتظر" : "Order #{$order->id} is still waiting",
                'description' => $ar ? "لم يُؤكَّد منذ $minutes دقيقة. أكّده أو ألغِه حتى لا ينتظر العميل." : "Not confirmed for $minutes min. Confirm or cancel it so the customer is not left waiting.",
                'order_id' => $order->id,
                'image' => '',
                'type' => 'new_order',
                'ring' => 1,
            ];
            foreach ($tokens as $token) {
                Helpers::send_push_notif_to_device($token, $data);
            }
        }
    }

    /** By now the dispatch waves are over: the whole zone, again. */
    private static function offerToRidersAgain(Order $order): void
    {
        DispatchService::broadcast($order, [
            'title' => translate('Order_Notification'),
            'description' => translate('messages.new_order_push_description'),
            'order_id' => $order->id,
            'module_id' => $order->module_id,
            'order_type' => $order->order_type,
            'image' => '',
        ]);
    }

    private static function alertAdmin(Order $order, string $text): void
    {
        $data = [
            'title' => 'Order needs attention',
            'description' => $text,
            'order_id' => $order->id,
            'image' => '',
            'module_id' => $order->module_id,
            'order_type' => $order->order_type,
            'zone_id' => $order->zone_id,
        ];
        Helpers::send_push_notif_to_topic($data, 'admin_message', 'order_request', url('/') . '/admin/order/details/' . $order->id);
    }

    /** One honest line instead of silence. */
    private static function tellCustomer(Order $order, string $problem): void
    {
        $token = $order->is_guest ? $order->guest?->fcm_token : $order->customer?->cm_firebase_token;
        if (!$token) {
            return;
        }
        $ar = ($order->customer?->current_language_key ?? 'en') === 'ar';
        $text = $problem === 'store'
            ? ($ar ? 'المتجر يتأخر في تأكيد طلبك. نتابع معه الآن.' : 'The store is taking longer than usual to confirm your order. We are on it.')
            : ($ar ? 'البحث عن مندوب يستغرق وقتًا أطول من المعتاد. نتابع الآن.' : 'Finding a rider is taking longer than usual. We are on it.');
        Helpers::send_push_notif_to_device($token, [
            'title' => $ar ? "طلبك #{$order->id}" : "Your order #{$order->id}",
            'description' => $text,
            'order_id' => $order->id,
            'image' => '',
            'type' => 'order_status',
        ]);
    }
}
