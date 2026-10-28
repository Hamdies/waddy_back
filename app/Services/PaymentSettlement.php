<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\PaymentRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The only place a gateway flips a payment request to paid or failed (NP-06).
 *
 * The success/failure hooks in app/helpers.php are not idempotent
 * (wallet_success credits the wallet every time it runs), and a gateway can
 * report the same result more than once: return URL + webhook, a refreshed
 * callback, a webhook retry. A compare-and-set on the row decides which call
 * runs the hook; every other call is a no-op.
 */
class PaymentSettlement
{
    /**
     * @return bool true when this call won the flip and the hook ran (or was
     *              deliberately skipped for a late capture)
     */
    public static function markPaid(PaymentRequest $payment, string $method, ?string $transactionId = null): bool
    {
        return DB::transaction(function () use ($payment, $method, $transactionId) {
            $won = PaymentRequest::whereKey($payment->id)->where('is_paid', 0)->update([
                'is_paid' => 1,
                'payment_method' => $method,
                'transaction_id' => $transactionId ?? $payment->transaction_id ?? $payment->id,
            ]);
            if ($won !== 1) {
                return false;
            }

            $payment = $payment->fresh();

            // The customer saw "failed", switched to cash or cancelled, and the
            // money landed anyway. Running order_place now would flip the order
            // back to a paid card order, so leave it and flag it for a refund.
            if (! self::orderStillWaiting($payment)) {
                // noon refunds it to the card on its own (NP-07).
                if ($method === 'noon' && $payment->attribute === 'order') {
                    DB::afterCommit(fn () => NoonRefundService::openForLateCapture($payment));
                }
                Log::channel('payments')->critical('late capture on an order that no longer waits for this payment, refund needed', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->attribute_id,
                    'method' => $method,
                    'transaction_id' => $payment->transaction_id,
                    'amount' => $payment->payment_amount,
                ]);
                return true;
            }

            if (function_exists($payment->success_hook)) {
                call_user_func($payment->success_hook, $payment);
            }
            return true;
        });
    }

    /**
     * Runs the failure hook at most once, and never after success.
     */
    public static function markFailed(PaymentRequest $payment, string $method): bool
    {
        return DB::transaction(function () use ($payment, $method) {
            $won = PaymentRequest::whereKey($payment->id)
                ->where('is_paid', 0)
                ->whereNull('failed_at')
                ->update(['failed_at' => now(), 'payment_method' => $method]);
            if ($won !== 1) {
                return false;
            }

            $payment = $payment->fresh();

            // A late EXPIRED after the customer switched to cash must not
            // mark their cash order failed.
            if (self::orderStillWaiting($payment) && function_exists($payment->failure_hook)) {
                call_user_func($payment->failure_hook, $payment);
            }
            return true;
        });
    }

    /**
     * True unless the payment is for an order that has since moved on:
     * cancelled, switched to cash, or already paid another way.
     */
    private static function orderStillWaiting(PaymentRequest $payment): bool
    {
        if ($payment->attribute !== 'order') {
            return true;
        }

        $order = Order::find($payment->attribute_id);
        if (! $order) {
            return false;
        }
        if (! in_array($order->order_status, ['pending', 'failed', 'confirmed'])) {
            return false;
        }
        if (! in_array($order->payment_status, ['unpaid', 'partially_paid'])) {
            return false;
        }
        if ($order->payment_method === 'partial_payment') {
            return OrderPayment::where('order_id', $order->id)
                ->where('payment_status', 'unpaid')
                ->where('payment_method', '!=', 'cash_on_delivery')
                ->exists();
        }
        return ! in_array($order->payment_method, ['cash_on_delivery', 'offline_payment', 'wallet']);
    }
}
