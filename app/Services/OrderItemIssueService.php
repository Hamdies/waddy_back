<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\Order;
use App\Models\OrderDetail;

/**
 * XS-4: an item ran out after the order was placed.
 *
 * Store reports it -> customer is asked "continue without it, or cancel?"
 * -> the store sees the answer and acts with its existing tools (edit the
 * bill, or cancel with refund). No money changes here: the order-amount
 * recalculation the store app uses already handles coupons, tax and
 * delivery, and a second copy of that arithmetic would drift.
 */
class OrderItemIssueService
{
    public const OPEN_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover'];

    /** @return string|null error message key, null on success */
    public static function report(Order $order, OrderDetail $detail): ?string
    {
        if (!in_array($order->order_status, self::OPEN_STATUSES, true)) {
            return 'messages.order_can_not_be_changed_now';
        }
        if ($detail->unavailable_status !== null) {
            return null; // already reported; asking twice helps nobody
        }
        $detail->unavailable_status = 'waiting';
        $detail->unavailable_at = now();
        $detail->save();
        $order->touch(); // the store app's status poll reloads on updated_at
        self::askCustomer($order, $detail);
        return null;
    }

    /** Customer's answer: 'remove' (continue without it) or 'cancel'. */
    public static function respond(Order $order, OrderDetail $detail, string $choice): ?string
    {
        if ($detail->unavailable_status !== 'waiting') {
            return 'messages.already_answered';
        }
        $detail->unavailable_status = $choice;
        $detail->save();
        $order->touch(); // both apps' order polls pick the answer up
        self::tellStore($order, $detail, $choice);
        return null;
    }

    private static function itemName(OrderDetail $detail): string
    {
        $item = is_array($detail->item_details) ? $detail->item_details : json_decode($detail->item_details ?? '', true);
        return (string) ($item['name'] ?? '');
    }

    private static function askCustomer(Order $order, OrderDetail $detail): void
    {
        $token = $order->is_guest ? $order->guest?->fcm_token : $order->customer?->cm_firebase_token;
        if (!$token) {
            return;
        }
        $ar = ($order->customer?->current_language_key ?? 'en') === 'ar';
        $name = self::itemName($detail);
        Helpers::send_push_notif_to_device($token, [
            'title' => $ar ? "نفد $name" : "$name is out of stock",
            'description' => $ar
                ? "المتجر لم يعد لديه $name. افتح الطلب #{$order->id} لتكمل بدونه أو تلغي الطلب."
                : "{$order->store?->name} ran out of $name. Open order #{$order->id} to continue without it or cancel.",
            'order_id' => $order->id,
            'image' => '',
            'type' => 'order_status',
        ]);
    }

    private static function tellStore(Order $order, OrderDetail $detail, string $choice): void
    {
        if (!$order->store) {
            return;
        }
        $name = self::itemName($detail);
        foreach (StorePushService::tokensByLanguage($order->store) as $lang => $tokens) {
            $ar = $lang === 'ar';
            $text = $choice === 'remove'
                ? ($ar ? "العميل يريد المتابعة بدون $name. عدّل الفاتورة." : "Customer wants to continue without $name. Update the bill.")
                : ($ar ? "العميل يريد إلغاء الطلب لأن $name نفد." : "Customer wants to cancel because $name ran out.");
            $data = [
                'title' => $ar ? "رد العميل · #{$order->id}" : "Customer answered · #{$order->id}",
                'description' => $text,
                'order_id' => $order->id,
                'image' => '',
                'type' => 'order_status',
                'ring' => 0,
            ];
            foreach ($tokens as $token) {
                Helpers::send_push_notif_to_device($token, $data);
            }
        }
    }
}
