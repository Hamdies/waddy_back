<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use App\Models\Store;
use App\Models\StoreDevice;
use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Support\Facades\Log;

/**
 * Pushes to the store app, on every phone the store is logged in on.
 *
 * - SB-4: a store owner (and each employee) can be logged in on several
 *   phones. vendors.firebase_token holds one token, so only the last phone
 *   to log in rang. Devices are kept in store_devices; the old columns are
 *   still written and still receive, for the web panel and older builds.
 * - SB-8: employees used to get only some of the store's pushes. Everything
 *   sent through here reaches the owner and every employee.
 * - SB-1/SB-2: silent `order_handled` / `store_status_changed` pushes tell
 *   the other phones to stop ringing or refresh the open/closed switch.
 * - SB-3: visible order pushes carry `ring` (1 for a new order, 0 for
 *   anything else), so the app does not have to guess from the type.
 * - MA-3: the store turns kinds of pushes off in the app (Account,
 *   Notifications & sound). New orders always go through.
 */
class StorePushService
{
    /** Keep at most this many phones per login; the oldest are dropped. */
    private const MAX_DEVICES_PER_LOGIN = 10;

    /**
     * MA-3: the push switches in the store app and their defaults. "tips"
     * is the admin's broadcasts to all stores; those go by FCM topic, so the
     * app unsubscribes itself and the switch is stored here for its other
     * phones.
     */
    public const PUSH_DEFAULTS = ['cancel' => true, 'rider' => true, 'reviews' => true, 'payouts' => true, 'tips' => true];

    /**
     * Statuses after which the store no longer needs to be alerted: someone
     * has started on the order, or it is over.
     */
    private const HANDLED_STATUSES = [
        'processing', 'handover', 'picked_up', 'delivered',
        'canceled', 'failed', 'refund_requested', 'refunded',
    ];

    public static function register(Vendor|VendorEmployee $login, string $fcmToken, ?string $platform = null, ?string $language = null): void
    {
        $storeId = $login instanceof VendorEmployee ? $login->store_id : $login->stores()->value('id');
        if (!$storeId || !self::isRealToken($fcmToken)) {
            return;
        }

        // A token belongs to one phone; if another login used it, it moves.
        StoreDevice::updateOrCreate(['fcm_token' => $fcmToken], [
            'store_id' => $storeId,
            'vendor_id' => $login instanceof Vendor ? $login->id : null,
            'vendor_employee_id' => $login instanceof VendorEmployee ? $login->id : null,
            'platform' => $platform ? mb_substr($platform, 0, 16) : null,
            'language' => in_array($language, ['en', 'ar'], true) ? $language : null,
            'last_seen_at' => now(),
        ]);

        $column = $login instanceof Vendor ? 'vendor_id' : 'vendor_employee_id';
        $keep = StoreDevice::where($column, $login->id)->orderByDesc('last_seen_at')
            ->limit(self::MAX_DEVICES_PER_LOGIN)->pluck('id');
        StoreDevice::where($column, $login->id)->whereNotIn('id', $keep)->delete();
    }

    /**
     * Logout. With the phone's own token only that phone is removed; older
     * builds send none, and then every phone of this login is removed (what
     * clearing the single firebase_token used to mean).
     */
    public static function forget(Vendor|VendorEmployee $login, ?string $fcmToken = null): void
    {
        $column = $login instanceof Vendor ? 'vendor_id' : 'vendor_employee_id';
        $query = StoreDevice::where($column, $login->id);
        if (self::isRealToken($fcmToken)) {
            $query->where('fcm_token', $fcmToken);
        }
        $query->delete();
    }

    /** Every token that should hear about this store, without duplicates. */
    public static function tokens(Store $store, ?string $except = null): array
    {
        $tokens = StoreDevice::where('store_id', $store->id)->pluck('fcm_token')->all();
        $tokens[] = $store->vendor?->firebase_token;
        foreach (VendorEmployee::where('store_id', $store->id)->pluck('firebase_token') as $token) {
            $tokens[] = $token;
        }

        return array_values(array_unique(array_filter(
            $tokens,
            fn ($token) => self::isRealToken($token) && $token !== $except
        )));
    }

    /**
     * Tokens grouped by the phone's app language. Phones registered before
     * the language was stored (and the legacy single-token columns) get
     * [$fallback].
     *
     * @return array<string, string[]>
     */
    public static function tokensByLanguage(Store $store, string $fallback = 'en'): array
    {
        $known = StoreDevice::where('store_id', $store->id)->pluck('language', 'fcm_token')->all();
        $groups = [];
        foreach (self::tokens($store) as $token) {
            $groups[$known[$token] ?? $fallback][] = $token;
        }
        return $groups;
    }

    /** The store's push switches, defaults filled in. */
    public static function prefs(Store $store): array
    {
        $saved = is_array($store->push_prefs) ? $store->push_prefs : [];
        $out = self::PUSH_DEFAULTS;
        foreach ($out as $key => $_) {
            if (array_key_exists($key, $saved)) {
                $out[$key] = (bool) $saved[$key];
            }
        }
        return $out;
    }

    /** Whether the store wants pushes of [$category]; null (new orders, account notices) always goes. */
    public static function wants(Store $store, ?string $category): bool
    {
        return $category === null || (self::prefs($store)[$category] ?? true);
    }

    /** A visible push (tray notification), e.g. a new order. */
    public static function send(Store $store, array $data, ?string $category = null): void
    {
        if (!self::wants($store, $category)) {
            return;
        }
        foreach (self::tokens($store) as $token) {
            Helpers::send_push_notif_to_device($token, $data);
        }
    }

    /**
     * A visible push written per phone language: [$build] gets 'en' or 'ar'
     * and returns the push data.
     */
    public static function sendLocalized(Store $store, callable $build, ?string $category = null): void
    {
        if (!self::wants($store, $category)) {
            return;
        }
        foreach (self::tokensByLanguage($store) as $lang => $tokens) {
            $data = $build($lang);
            foreach ($tokens as $token) {
                Helpers::send_push_notif_to_device($token, $data);
            }
        }
    }

    /**
     * A data-only push: no tray entry, no sound. Wakes the app's background
     * handler on Android; iOS delivers it when it can.
     */
    public static function sendSilent(Store $store, array $data, ?string $except = null): void
    {
        $data = array_map(fn ($value) => (string) $value, $data);
        foreach (self::tokens($store, $except) as $token) {
            Helpers::sendNotificationToHttp([
                'message' => [
                    'token' => $token,
                    'data' => $data,
                    'android' => ['priority' => 'high'],
                    'apns' => [
                        'headers' => ['apns-priority' => '5', 'apns-push-type' => 'background'],
                        'payload' => ['aps' => ['content-available' => 1]],
                    ],
                ],
            ]);
        }
    }

    /**
     * Whether moving an order from [$from] to its current status means the
     * store has dealt with the new-order alert. Pure, so it is tested.
     *
     * Confirming counts only where the store is the one who confirms (cash,
     * and the store confirms under the store model or for take-away). For a
     * paid order, pending -> confirmed is the payment arriving, which is
     * when the store's alert *starts*; under the deliveryman model it is the
     * rider accepting, and the store still has to start on it.
     */
    public static function isHandled(Order $order, ?string $from): bool
    {
        $to = $order->order_status;
        if ($from === $to) {
            return false;
        }
        if (in_array($to, self::HANDLED_STATUSES, true)) {
            return true;
        }

        return $from === 'pending' && $to === 'confirmed'
            && $order->payment_method === 'cash_on_delivery'
            && (config('order_confirmation_model') === 'store' || $order->order_type === 'take_away');
    }

    /** Called by OrderObserver on every status change (SB-1). */
    public static function orderStatusChanged(Order $order, ?string $from): void
    {
        if (!$order->store_id || !self::isHandled($order, $from)) {
            return;
        }
        $store = $order->store;
        if (!$store) {
            return;
        }

        try {
            self::sendSilent($store, [
                'type' => 'order_handled',
                'order_id' => $order->id,
                'status' => $order->order_status,
            ]);
        } catch (\Throwable $e) {
            Log::warning("order_handled push failed for order {$order->id}: " . $e->getMessage());
        }

        if (self::tellsStoreOfCancel($order, $from)) {
            $byCustomer = $order->canceled_by === 'customer';
            try {
                self::sendLocalized($store, fn ($lang) => [
                    'title' => $lang === 'ar' ? "تم إلغاء الطلب #{$order->id}" : "Order #{$order->id} cancelled",
                    'description' => $lang === 'ar'
                        ? ($byCustomer ? 'ألغى العميل الطلب. أوقف تجهيزه.' : 'تم إلغاء الطلب. أوقف تجهيزه.')
                        : ($byCustomer ? 'The customer cancelled it. Stop preparing it.' : 'It was cancelled. Stop preparing it.'),
                    'order_id' => $order->id,
                    'image' => '',
                    'type' => 'order_status',
                    'ring' => 0,
                ], 'cancel');
            } catch (\Throwable $e) {
                Log::warning("cancel push failed for order {$order->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * MA-3: a visible "order cancelled" push, for a cancel the store did not
     * make itself, once the store already knew about the order. Pure, so it
     * is tested.
     */
    public static function tellsStoreOfCancel(Order $order, ?string $from): bool
    {
        return $order->order_status === 'canceled' && $from !== null && $from !== 'canceled'
            && $order->canceled_by !== 'store'
            && in_array($from, ['pending', 'accepted', 'confirmed', 'processing', 'handover'], true);
    }

    /** MA-3: a customer rated items of an order; one push per order. */
    public static function newReview(Order $order, int $rating): void
    {
        $store = $order->store;
        if (!$store || !\Illuminate\Support\Facades\Cache::add("store_review_push_{$order->id}", 1, now()->addDay())) {
            return;
        }
        try {
            self::sendLocalized($store, fn ($lang) => [
                'title' => $lang === 'ar' ? "تقييم جديد · {$rating} ★" : "New review · {$rating} ★",
                'description' => $lang === 'ar' ? "قيّم عميل الطلب #{$order->id}." : "A customer rated order #{$order->id}.",
                'order_id' => '',
                'image' => '',
                'type' => 'review',
            ], 'reviews');
        } catch (\Throwable $e) {
            Log::warning("review push failed for order {$order->id}: " . $e->getMessage());
        }
    }

    /** SB-2: the store was opened or closed; the other phones refresh. */
    public static function storeStatusChanged(Store $store, ?string $exceptToken = null): void
    {
        try {
            self::sendSilent($store, [
                'type' => 'store_status_changed',
                'status' => (int) $store->active,
            ], $exceptToken);
        } catch (\Throwable $e) {
            Log::warning("store_status_changed push failed for store {$store->id}: " . $e->getMessage());
        }
    }

    /**
     * D4 / SB-7: under the deliveryman confirmation model a cash order used
     * to reach the store only once a rider accepted it. When this is on (the
     * default) the store hears at placement and can start cooking while a
     * rider is found. Business setting `store_new_order_at_placement`.
     */
    public static function newOrderAtPlacement(): bool
    {
        return (string) (Helpers::get_business_settings('store_new_order_at_placement') ?? '1') === '1';
    }

    private static function isRealToken(?string $token): bool
    {
        return $token !== null && $token !== '' && $token !== '@';
    }
}
