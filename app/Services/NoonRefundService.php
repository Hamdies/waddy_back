<?php

namespace App\Services;

use App\CentralLogics\CustomerLogic;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\CardRefund;
use App\Models\Order;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Refunds to the card a noon payment came from (docs/noon_payments_plan.md §11,
 * NP-09/15/16).
 *
 * Lifecycle of a card_refunds row:
 *   awaiting_choice ─ customer picks wallet ─→ to_wallet (credited at once)
 *        │  customer picks card, or 48 h pass
 *        ▼
 *   requested ─ REFUND sent ─→ submitted ─ noon confirms ─→ succeeded
 *        └──────────── refused / gave up ──→ failed (admin: retry or wallet)
 *
 * Every transition is a compare-and-set on `status`, so a double tap, the push
 * and the sheet, or the scheduler racing a request can't move money twice.
 * A REFUND is never resent blindly: each attempt first looks for our
 * transactionReference in GET ORDER.
 */
class NoonRefundService
{
    public const CHOICE_HOURS = 48;

    private const RETRY_MINUTES = [1, 5, 15, 60, 180];

    // ─── Eligibility ────────────────────────────────────────────────────────

    /** The paid noon payment request behind an order, if the card part went through noon. */
    public static function cardPayment(Order $order): ?PaymentRequest
    {
        return PaymentRequest::where('attribute', 'order')
            ->where('attribute_id', $order->id)
            ->where('payment_method', 'noon')
            ->where('is_paid', 1)
            ->whereNotNull('transaction_id')
            ->latest()
            ->first();
    }

    /** Card money on this order not yet promised to a card or wallet refund. */
    public static function cardBalance(Order $order, ?PaymentRequest $payment = null): float
    {
        $payment ??= self::cardPayment($order);
        if (!$payment) {
            return 0.0;
        }
        $taken = CardRefund::where('order_id', $order->id)
            ->whereNotIn('status', [CardRefund::FAILED])
            ->sum('amount');
        return max(0.0, round((float) $payment->payment_amount - (float) $taken, 2));
    }

    /** Shown as a "Refund to: card" option on the refund request form. */
    public static function refundRequestEligible(Order $order): bool
    {
        return $order->is_guest == 0
            && NoonPaymentService::cardRefundEnabled()
            && self::cardBalance($order) > 0;
    }

    public static function walletAvailable(): bool
    {
        return BusinessSetting::where('key', 'wallet_status')->first()?->value == 1;
    }

    // ─── Opening a refund ───────────────────────────────────────────────────

    /**
     * A cancelled order that was paid by card through noon. Logged-in customers
     * are asked wallet or card; guests (no wallet, no in-app prompt) go straight
     * to the card.
     */
    public static function openForCancellation(Order $order, PaymentRequest $payment, float $amount): ?CardRefund
    {
        if ($amount <= 0) {
            return null;
        }
        $ask = $order->is_guest == 0 && self::walletAvailable();

        $row = CardRefund::firstOrCreate(
            ['order_id' => $order->id, 'source' => 'cancellation'],
            [
                'user_id' => $order->is_guest ? null : $order->user_id,
                'payment_request_id' => $payment->id,
                'noon_order_id' => (string) $payment->transaction_id,
                'amount' => $amount,
                'currency' => $payment->currency_code,
                'status' => $ask ? CardRefund::AWAITING_CHOICE : CardRefund::REQUESTED,
                'choice_deadline' => $ask ? now()->addHours(self::CHOICE_HOURS) : null,
                'chosen_by' => $ask ? null : ($order->is_guest ? 'guest' : 'auto'),
            ]
        );
        if (!$row->wasRecentlyCreated) {
            return $row;
        }

        Log::channel('payments')->info('card refund opened', ['card_refund' => $row->id, 'order_id' => $order->id, 'status' => $row->status, 'amount' => $amount]);
        if ($ask) {
            self::notifyCustomer($row, 'choose');
        } else {
            self::notifyCustomer($row, 'card_auto');
            self::queueSubmit($row->id);
        }
        return $row;
    }

    /** Admin approved a refund request the customer asked to go to their card. */
    public static function openForRefundRequest(Order $order, float $amount): ?CardRefund
    {
        $payment = self::cardPayment($order);
        $amount = min($amount, self::cardBalance($order, $payment));
        if (!$payment || $amount <= 0) {
            return null;
        }

        $row = CardRefund::firstOrCreate(
            ['order_id' => $order->id, 'source' => 'refund_request'],
            [
                'user_id' => $order->user_id,
                'payment_request_id' => $payment->id,
                'noon_order_id' => (string) $payment->transaction_id,
                'amount' => $amount,
                'currency' => $payment->currency_code,
                'status' => CardRefund::REQUESTED,
                'chosen_by' => 'customer',
            ]
        );
        if ($row->wasRecentlyCreated) {
            Log::channel('payments')->info('card refund opened', ['card_refund' => $row->id, 'order_id' => $order->id, 'status' => $row->status, 'amount' => $amount]);
            self::queueSubmit($row->id);
        }
        return $row;
    }

    /**
     * noon captured a payment for an order that had already moved on (the
     * customer saw "failed" and switched to cash, or it was cancelled). The
     * money goes straight back to the card; the order itself is untouched.
     */
    public static function openForLateCapture(PaymentRequest $payment): ?CardRefund
    {
        $order = Order::find($payment->attribute_id);
        if (!$order || !$payment->transaction_id) {
            return null;
        }
        $row = CardRefund::firstOrCreate(
            ['order_id' => $order->id, 'source' => 'admin'],
            [
                'user_id' => $order->is_guest ? null : $order->user_id,
                'payment_request_id' => $payment->id,
                'noon_order_id' => (string) $payment->transaction_id,
                'amount' => (float) $payment->payment_amount,
                'currency' => $payment->currency_code,
                'status' => CardRefund::REQUESTED,
                'chosen_by' => 'auto',
            ]
        );
        if ($row->wasRecentlyCreated) {
            self::notifyCustomer($row, 'card_auto');
            self::queueSubmit($row->id);
        }
        return $row;
    }

    // ─── Customer choice ────────────────────────────────────────────────────

    /** @return bool false when the choice was already made (or the window closed). */
    public static function choose(CardRefund $refund, string $method): bool
    {
        if ($method === 'wallet') {
            return self::toWallet($refund, 'customer', [CardRefund::AWAITING_CHOICE]);
        }

        $won = CardRefund::whereKey($refund->id)
            ->where('status', CardRefund::AWAITING_CHOICE)
            ->update(['status' => CardRefund::REQUESTED, 'chosen_by' => 'customer', 'next_attempt_at' => now()]);
        if ($won !== 1) {
            return false;
        }
        self::queueSubmit($refund->id);
        return true;
    }

    /**
     * Credit the wallet instead of the card: the customer's choice, or the
     * admin's fallback after a failed card refund.
     */
    public static function toWallet(CardRefund $refund, string $by, array $from): bool
    {
        if (!$refund->user_id || !self::walletAvailable()) {
            return false;
        }
        return DB::transaction(function () use ($refund, $by, $from) {
            $won = CardRefund::whereKey($refund->id)->whereIn('status', $from)->update([
                'status' => CardRefund::TO_WALLET,
                'chosen_by' => $by,
                'completed_at' => now(),
            ]);
            if ($won !== 1) {
                return false;
            }
            if (!CustomerLogic::create_wallet_transaction($refund->user_id, $refund->amount, 'order_refund', $refund->order_id)) {
                throw new \RuntimeException("wallet credit failed for card refund {$refund->id}");
            }
            Log::channel('payments')->info('card refund sent to wallet', ['card_refund' => $refund->id, 'by' => $by]);
            return true;
        });
    }

    /** Admin: send a failed card refund again. */
    public static function retry(CardRefund $refund): bool
    {
        $won = CardRefund::whereKey($refund->id)->where('status', CardRefund::FAILED)->update([
            'status' => CardRefund::REQUESTED,
            'attempts' => 0,
            'next_attempt_at' => now(),
            'error_message' => null,
            'result_code' => null,
        ]);
        if ($won === 1) {
            self::queueSubmit($refund->id);
        }
        return $won === 1;
    }

    // ─── Talking to noon ────────────────────────────────────────────────────

    public static function queueSubmit(int $id): void
    {
        if (app()->runningInConsole()) {
            self::submit($id);
            return;
        }
        // After the response, so a store's cancel or an admin click isn't held
        // up by noon. The scheduler picks it up if this doesn't run.
        dispatch(fn () => self::submit($id))->afterResponse();
    }

    public static function submit(int $id): void
    {
        $lock = Cache::lock("card_refund:{$id}", 120);
        if (!$lock->get()) {
            return;
        }
        try {
            $refund = CardRefund::find($id);
            if (!$refund || $refund->status !== CardRefund::REQUESTED) {
                return;
            }
            $noon = new NoonPaymentService();

            $result = $noon->getOrder($refund->noon_order_id, 10);
            if ($result === null) {
                self::retryLater($refund, 'GET ORDER failed');
                return;
            }

            // An earlier attempt that timed out may have reached noon.
            $txn = self::findTransaction($result, $refund);
            if ($txn) {
                self::applyTransaction($refund, $txn);
                return;
            }

            $order = $result['order'];
            $refundable = round((float) ($order['totalCapturedAmount'] ?? 0) - (float) ($order['totalRefundedAmount'] ?? 0), 2);
            if ($refund->amount - $refundable > 0.004) {
                self::fail($refund, null, "noon can refund only {$refundable} on this order");
                return;
            }

            $refund->update(['attempts' => $refund->attempts + 1, 'submitted_at' => now()]);
            $response = $noon->refund($refund->noon_order_id, $refund->amount, $refund->currency, $refund->reference());

            if (!is_array($response) || !array_key_exists('resultCode', $response)) {
                // Unknown outcome: the next attempt checks GET ORDER first.
                self::retryLater($refund, 'REFUND call timed out or returned nothing');
                return;
            }
            if ($response['resultCode'] !== 0) {
                Log::channel('payments')->error('noon refund refused', [
                    'card_refund' => $refund->id,
                    'resultCode' => $response['resultCode'],
                    'message' => $response['message'] ?? null,
                    'requestReference' => $response['requestReference'] ?? null,
                ]);
                self::fail($refund, (int) $response['resultCode'], (string) ($response['message'] ?? 'refused'));
                return;
            }

            self::applyTransaction($refund, $response['result']['transaction'] ?? ['status' => 'PENDING']);
        } finally {
            $lock->release();
        }
    }

    /** Refund results found in a GET ORDER result (webhook or sweep). */
    public static function syncFromResult(string $noonOrderId, array $result): void
    {
        $open = CardRefund::where('noon_order_id', $noonOrderId)
            ->whereIn('status', [CardRefund::SUBMITTED, CardRefund::REQUESTED])
            ->get();
        foreach ($open as $refund) {
            // A requested row that was never sent has nothing to find.
            if ($refund->status === CardRefund::REQUESTED && $refund->attempts === 0) {
                continue;
            }
            $txn = self::findTransaction($result, $refund);
            if ($txn) {
                self::applyTransaction($refund, $txn);
            }
        }
    }

    /**
     * Scheduler (every 5 min): close expired choices to the card, send what's
     * due, and ask noon about refunds it hasn't confirmed yet.
     */
    public static function processDue(): int
    {
        $acted = 0;

        $expired = CardRefund::where('status', CardRefund::AWAITING_CHOICE)
            ->where('choice_deadline', '<=', now())
            ->limit(50)
            ->get();
        foreach ($expired as $refund) {
            $won = CardRefund::whereKey($refund->id)->where('status', CardRefund::AWAITING_CHOICE)
                ->update(['status' => CardRefund::REQUESTED, 'chosen_by' => 'deadline', 'next_attempt_at' => now()]);
            if ($won === 1) {
                self::notifyCustomer($refund->fresh(), 'card_auto');
                $acted++;
            }
        }

        $due = CardRefund::where('status', CardRefund::REQUESTED)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->limit(20)
            ->pluck('id');
        foreach ($due as $id) {
            self::submit($id);
            $acted++;
        }

        $noon = null;
        $waiting = CardRefund::where('status', CardRefund::SUBMITTED)
            ->where('submitted_at', '<=', now()->subMinutes(10))
            ->limit(20)
            ->pluck('noon_order_id')
            ->unique();
        foreach ($waiting as $noonOrderId) {
            if (!Cache::add("noon:refund-sync:{$noonOrderId}", 1, now()->addMinutes(15))) {
                continue;
            }
            $noon ??= new NoonPaymentService();
            $result = $noon->getOrder($noonOrderId, 10);
            if ($result !== null) {
                self::syncFromResult($noonOrderId, $result);
                $acted++;
            }
        }
        return $acted;
    }

    private static function findTransaction(array $result, CardRefund $refund): ?array
    {
        foreach ($result['transactions'] ?? [] as $txn) {
            if (is_array($txn) && (string) ($txn['transactionReference'] ?? '') === $refund->reference()) {
                return $txn;
            }
        }
        return null;
    }

    private static function applyTransaction(CardRefund $refund, array $txn): void
    {
        $status = strtoupper((string) ($txn['status'] ?? 'PENDING'));
        $fields = [
            'noon_txn_id' => isset($txn['id']) ? (string) $txn['id'] : $refund->noon_txn_id,
            'noon_txn_status' => $status,
            'rrn' => $txn['rrn'] ?? $txn['retrievalReferenceNumber'] ?? $txn['arn'] ?? $refund->rrn,
        ];

        if ($status === 'FAILED') {
            CardRefund::whereKey($refund->id)->update($fields);
            self::fail($refund->fresh(), null, 'noon reported the refund as FAILED');
            return;
        }

        $target = $status === 'SUCCESS' ? CardRefund::SUCCEEDED : CardRefund::SUBMITTED;
        $won = CardRefund::whereKey($refund->id)
            ->whereIn('status', [CardRefund::REQUESTED, CardRefund::SUBMITTED])
            ->update($fields + [
                'status' => $target,
                'submitted_at' => $refund->submitted_at ?? now(),
                'completed_at' => $target === CardRefund::SUCCEEDED ? now() : null,
            ]);

        if ($won === 1 && $target === CardRefund::SUCCEEDED && $refund->status !== CardRefund::SUCCEEDED) {
            Log::channel('payments')->info('card refund succeeded', ['card_refund' => $refund->id, 'noon_txn_id' => $fields['noon_txn_id']]);
            self::notifyCustomer($refund->fresh(), 'card_sent');
        }
    }

    private static function retryLater(CardRefund $refund, string $why): void
    {
        $attempts = max($refund->attempts, 1);
        if ($attempts >= count(self::RETRY_MINUTES)) {
            self::fail($refund, null, $why . ' (gave up after ' . $attempts . ' attempts)');
            return;
        }
        CardRefund::whereKey($refund->id)->where('status', CardRefund::REQUESTED)->update([
            'attempts' => $attempts,
            'error_message' => mb_substr($why, 0, 500),
            'next_attempt_at' => now()->addMinutes(self::RETRY_MINUTES[$attempts - 1]),
        ]);
    }

    private static function fail(CardRefund $refund, ?int $code, string $message): void
    {
        $won = CardRefund::whereKey($refund->id)
            ->whereIn('status', [CardRefund::REQUESTED, CardRefund::SUBMITTED])
            ->update(['status' => CardRefund::FAILED, 'result_code' => $code, 'error_message' => mb_substr($message, 0, 500)]);
        if ($won !== 1) {
            return;
        }
        Log::channel('payments')->critical('card refund failed, admin action needed', [
            'card_refund' => $refund->id,
            'order_id' => $refund->order_id,
            'amount' => $refund->amount,
            'message' => $message,
        ]);
        try {
            $order = Order::find($refund->order_id);
            Helpers::send_push_notif_to_topic([
                'title' => 'Card refund failed',
                'description' => "Order #{$refund->order_id}: " . $message . '. Retry it or credit the wallet from the order page.',
                'order_id' => $refund->order_id,
                'image' => '',
                'module_id' => $order?->module_id,
                'order_type' => $order?->order_type,
                'zone_id' => $order?->zone_id,
            ], 'admin_message', 'order_request', url('/') . '/admin/order/details/' . $refund->order_id);
        } catch (\Throwable $e) {
            Log::warning('card refund admin alert failed: ' . $e->getMessage());
        }
    }

    // ─── Presentation ───────────────────────────────────────────────────────

    /** The `card_refund` object on order details (app NP-17). */
    public static function present(?CardRefund $refund): ?array
    {
        if (!$refund) {
            return null;
        }
        return [
            'id' => $refund->id,
            'status' => $refund->status,
            'amount' => (float) $refund->amount,
            'currency' => $refund->currency,
            'source' => $refund->source,
            'choice_deadline' => $refund->choice_deadline?->toIso8601String(),
            'chosen_by' => $refund->chosen_by,
            'rrn' => $refund->rrn,
            'submitted_at' => $refund->submitted_at?->toIso8601String(),
            'completed_at' => $refund->completed_at?->toIso8601String(),
            'wallet_available' => self::walletAvailable() && $refund->user_id !== null,
        ];
    }

    public static function latestFor(Order $order): ?CardRefund
    {
        return CardRefund::where('order_id', $order->id)->latest('id')->first();
    }

    private static function notifyCustomer(CardRefund $refund, string $kind): void
    {
        try {
            $order = Order::with(['customer', 'guest'])->find($refund->order_id);
            if (!$order) {
                return;
            }
            $token = $order->is_guest ? $order->guest?->fcm_token : $order->customer?->cm_firebase_token;
            if (!$token) {
                return;
            }
            $ar = ($order->customer?->current_language_key ?? 'en') === 'ar';
            $amount = Helpers::format_currency($refund->amount);

            [$title, $body] = match ($kind) {
                'choose' => $ar
                    ? ["تم إلغاء طلبك #{$order->id}", "أين نرسل {$amount}؟ اختر محفظة وددى (فورًا) أو بطاقتك (من 7 إلى 10 أيام عمل)."]
                    : ["Your order #{$order->id} was cancelled", "Where should we send your {$amount}? Pick your Waddy wallet (instant) or your card (7–10 business days)."],
                'card_auto' => $ar
                    ? ["استرداد طلبك #{$order->id}", "نعيد {$amount} إلى بطاقتك. يظهر المبلغ عادةً خلال 7 إلى 10 أيام عمل."]
                    : ["Refund for order #{$order->id}", "We're sending your {$amount} back to your card. Banks usually take 7–10 business days to show it."],
                default => $ar
                    ? ["استرداد طلبك #{$order->id}", "أرسلنا {$amount} إلى بطاقتك. يظهر المبلغ عادةً خلال 7 إلى 10 أيام عمل."]
                    : ["Refund for order #{$order->id}", "Your {$amount} has been sent to your card. Banks usually take 7–10 business days to show it."],
            };

            $data = [
                'title' => $title,
                'description' => $body,
                'order_id' => $order->id,
                'image' => '',
                'type' => 'order_status',
                'order_status' => $order->order_status,
            ];
            Helpers::send_push_notif_to_device($token, $data);
            if (!$order->is_guest) {
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $order->user_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning("card refund push ({$kind}) failed for {$refund->id}: " . $e->getMessage());
        }
    }
}
