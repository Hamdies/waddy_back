<?php

namespace App\Services;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * XS-6: keep the promise or say sorry. When an order is delivered more than
 * [lateAfter] minutes after the time the customer was first promised, the
 * customer gets wallet credit and a push, once, without asking.
 *
 * Business settings: late_apology_status (default on), late_apology_amount
 * (default 15), late_apology_after_minutes (default 10). Needs the customer
 * wallet to be on; guests are skipped (no wallet).
 */
class LateApologyService
{
    public static function enabled(): bool
    {
        return (string) (Helpers::get_business_settings('late_apology_status') ?? '1') === '1'
            && (string) (Helpers::get_business_settings('wallet_status') ?? '0') === '1';
    }

    public static function amount(): float
    {
        return (float) (Helpers::get_business_settings('late_apology_amount') ?? 15);
    }

    public static function lateAfter(): int
    {
        return (int) (Helpers::get_business_settings('late_apology_after_minutes') ?? 10);
    }

    /** Minutes late against the first promise, or null if it cannot be told. Pure. */
    public static function minutesLate(?Carbon $promised, ?Carbon $delivered): ?int
    {
        if (!$promised || !$delivered) {
            return null;
        }
        return (int) floor($promised->diffInSeconds($delivered, false) / 60);
    }

    /** Called by OrderObserver when an order becomes delivered. */
    public static function handleDelivered(Order $order): void
    {
        try {
            if ($order->late_apology_amount !== null || $order->is_guest || !$order->user_id || !self::enabled()) {
                return;
            }
            $late = self::minutesLate(
                $order->promised_delivery_at ? Carbon::parse($order->promised_delivery_at) : null,
                Carbon::parse($order->delivered ?? now())
            );
            if ($late === null || $late <= self::lateAfter()) {
                return;
            }
            $amount = self::amount();
            if ($amount <= 0) {
                return;
            }
            // Claim the order first, so two saves can't both pay.
            $claimed = DB::table('orders')->where('id', $order->id)->whereNull('late_apology_amount')
                ->update(['late_apology_amount' => $amount]);
            if (!$claimed) {
                return;
            }
            CustomerLogic::create_wallet_transaction($order->user_id, $amount, 'add_fund_by_admin', 'late_order_' . $order->id);
            self::tellCustomer($order, $amount, $late);
        } catch (\Throwable $e) {
            Log::warning("Late apology failed for order {$order->id}: " . $e->getMessage());
        }
    }

    private static function tellCustomer(Order $order, float $amount, int $late): void
    {
        $token = $order->customer?->cm_firebase_token;
        if (!$token) {
            return;
        }
        $ar = ($order->customer?->current_language_key ?? 'en') === 'ar';
        $money = Helpers::format_currency($amount);
        Helpers::send_push_notif_to_device($token, [
            'title' => $ar ? 'آسفين على التأخير' : 'Sorry we were late',
            'description' => $ar
                ? "وصل طلبك #{$order->id} متأخرًا $late دقيقة عن الموعد. أضفنا $money إلى محفظتك."
                : "Order #{$order->id} arrived $late min after the time we promised. We added $money to your wallet.",
            'order_id' => $order->id,
            'image' => '',
            'type' => 'order_status',
        ]);
    }
}
