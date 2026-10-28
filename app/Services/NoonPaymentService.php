<?php

namespace App\Services;

use App\Models\PaymentRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * noon payments API client, and the one place a noon order's state is turned
 * into ours (docs/noon_payments_plan.md §4–5). Used by NoonController (return
 * URL, webhook), NoonRefundService, and the reconcile job.
 *
 * Credentials come from the `noon` row in addon_settings (admin card), picked
 * by its mode. Never log the Authorization header.
 */
class NoonPaymentService
{
    public const FINAL_FAILURES = ['FAILED', 'REJECTED', 'CANCELLED', 'EXPIRED', 'REVERSED', 'LOCKED'];

    private ?object $config = null;

    private string $mode = 'test';

    public function __construct()
    {
        $row = DB::table('addon_settings')->where('key_name', 'noon')->where('settings_type', 'payment_config')->first();
        if ($row) {
            $this->mode = $row->mode === 'live' ? 'live' : 'test';
            $this->config = json_decode($this->mode === 'live' ? $row->live_values : $row->test_values) ?: null;
        }
    }

    public function config(string $key, $default = null)
    {
        $value = $this->config->$key ?? null;
        return is_string($value) ? trim($value) : ($value ?? $default);
    }

    /** Card refunds are switched on in the noon admin card (NP-18). */
    public static function cardRefundEnabled(): bool
    {
        return (string) (new self())->config('card_refund_enabled', '0') === '1';
    }

    public function initiate(array $body): ?array
    {
        return $this->call('POST', '/payment/v1/order', $body);
    }

    /**
     * GET ORDER. Returns noon's `result` (order, transactions, …) or null when
     * the call failed or was refused.
     */
    public function getOrder(string $noonOrderId, int $timeout = 15): ?array
    {
        try {
            $response = $this->call('GET', '/payment/v1/order/' . rawurlencode($noonOrderId), null, $timeout);
        } catch (\Throwable $e) {
            Log::channel('payments')->error('noon get order threw', ['noon_order_id' => $noonOrderId, 'error' => $e->getMessage()]);
            return null;
        }

        if (($response['resultCode'] ?? null) !== 0 || !isset($response['result']['order'])) {
            Log::channel('payments')->error('noon get order refused', [
                'noon_order_id' => $noonOrderId,
                'resultCode' => $response['resultCode'] ?? null,
                'message' => $response['message'] ?? null,
                'requestReference' => $response['requestReference'] ?? null,
            ]);
            return null;
        }
        return $response['result'];
    }

    /**
     * REFUND. Returns the decoded response (check resultCode), or null on a
     * timeout / network error, when the outcome is unknown.
     */
    public function refund(string $noonOrderId, float $amount, string $currency, string $reference): ?array
    {
        try {
            return $this->call('POST', '/payment/v1/order', [
                'apiOperation' => 'REFUND',
                'order' => ['id' => $noonOrderId],
                'transaction' => [
                    'amount' => number_format($amount, 2, '.', ''),
                    'currency' => $currency,
                    'transactionReference' => $reference,
                ],
            ], 20);
        } catch (\Throwable $e) {
            Log::channel('payments')->error('noon refund threw', ['noon_order_id' => $noonOrderId, 'reference' => $reference, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Applies noon's view of an order to our payment request.
     *
     * @return array{0: string, 1: bool} [browser flag success|fail, whether the status is final]
     */
    public function settle(PaymentRequest $payment, array $order): array
    {
        $status = strtoupper((string) ($order['status'] ?? ''));
        $noonOrderId = (string) ($order['id'] ?? $payment->transaction_id);

        if ($status === 'CAPTURED') {
            $captured = (int) round(((float) ($order['totalCapturedAmount'] ?? 0)) * 100);
            $expected = (int) round(((float) $payment->payment_amount) * 100);
            $currency = strtoupper((string) ($order['currency'] ?? ''));
            $reference = (string) ($order['reference'] ?? '');

            if ($captured !== $expected || $currency !== strtoupper($payment->currency_code) || $reference !== (string) $payment->id) {
                Log::channel('payments')->critical('noon capture does not match the payment request, not marked paid', [
                    'payment_id' => $payment->id,
                    'noon_order_id' => $noonOrderId,
                    'captured' => $order['totalCapturedAmount'] ?? null,
                    'expected' => $payment->payment_amount,
                    'currency' => $currency,
                    'reference' => $reference,
                ]);
                return ['fail', true];
            }

            PaymentSettlement::markPaid($payment, 'noon', $noonOrderId);
            return ['success', true];
        }

        if (in_array($status, self::FINAL_FAILURES)) {
            PaymentSettlement::markFailed($payment, 'noon');
        }

        $paid = (bool) PaymentRequest::whereKey($payment->id)->value('is_paid');
        return [$paid ? 'success' : 'fail', $paid || in_array($status, self::FINAL_FAILURES)];
    }

    /**
     * NP-07: payments noon hasn't given us a final answer for (PENDING, a lost
     * webhook, a customer who closed the app). Each order is asked at most once
     * per 5 minutes, which also keeps us under noon's GET ORDER throttle.
     */
    public static function reconcilePayments(): int
    {
        $service = new self();
        if (!$service->config) {
            return 0;
        }

        $settled = 0;
        $pending = PaymentRequest::where('payment_method', 'noon')
            ->where('is_paid', 0)
            ->whereNull('failed_at')
            ->whereNotNull('transaction_id')
            ->where('created_at', '>=', now()->subHours(48))
            ->where('created_at', '<=', now()->subMinutes(3))
            ->orderBy('created_at')
            ->limit(50)
            ->get();

        foreach ($pending as $payment) {
            if (!Cache::add("noon:reconcile:{$payment->id}", 1, now()->addMinutes(5))) {
                continue;
            }
            $result = $service->getOrder($payment->transaction_id, 10);
            if ($result === null) {
                continue;
            }
            [, $final] = $service->settle($payment, $result['order']);
            $settled += $final ? 1 : 0;
        }
        return $settled;
    }

    private function call(string $method, string $path, ?array $body = null, int $timeout = 15): ?array
    {
        $request = Http::timeout($timeout)
            ->acceptJson()
            ->withHeaders(['Authorization' => $this->authHeader()]);

        $response = $method === 'GET'
            ? $request->get($this->baseUrl() . $path)
            : $request->post($this->baseUrl() . $path, $body);

        if ($response->status() === 429) {
            Log::channel('payments')->warning('noon throttled', ['path' => $path]);
        }

        // Order ids are 16-digit integers; keep them as strings.
        $decoded = json_decode($response->body(), true, 512, JSON_BIGINT_AS_STRING);
        return is_array($decoded) ? $decoded : null;
    }

    private function authHeader(): string
    {
        $business = $this->config('business_identifier', '');
        $app = $this->config('application_identifier', '');
        $key = $this->config('application_key', '');

        return 'Key ' . base64_encode("{$business}.{$app}:{$key}");
    }

    private function baseUrl(): string
    {
        $host = $this->mode === 'live' ? 'api' : 'api-test';
        $region = $this->config('region', 'eg');

        return $region === 'global'
            ? "https://{$host}.noonpayments.com"
            : "https://{$host}.{$region}.noonpayments.com";
    }
}
