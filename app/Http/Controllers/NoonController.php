<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentRequest;
use App\Models\User;
use App\Services\NoonPaymentService;
use App\Services\NoonRefundService;
use App\Services\PaymentSettlement;
use App\Traits\Processor;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

/**
 * noon payments, hosted checkout (docs/noon_payments_plan.md, NP-03..05).
 *
 * pay     INITIATE → redirect to noon's page. No session: the payment id rides
 *         in the return URL, so it survives the bank's 3DS redirects.
 * return  noon sends the browser back here. The query string is never trusted;
 *         the order is re-read with GET ORDER.
 * webhook Server-to-server (v2, JWS HS256). Covers a customer who closes the
 *         app before the return URL loads, and refund results.
 *
 * Both return and webhook settle through NoonPaymentService::settle, which
 * goes through PaymentSettlement so the hooks run once.
 */
class NoonController extends Controller
{
    use Processor;

    private NoonPaymentService $noon;

    public function __construct()
    {
        $this->noon = new NoonPaymentService();
    }

    public function pay(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = PaymentRequest::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        $business = $data['additional_data'] != null ? json_decode($data['additional_data']) : null;
        $business_name = $business->business_name ?? 'Waddy';

        $body = [
            'apiOperation' => 'INITIATE',
            'order' => [
                'amount' => number_format((float) $data->payment_amount, 2, '.', ''),
                'currency' => $data->currency_code,
                'channel' => 'web',
                'category' => $this->noon->config('order_category', ''),
                'reference' => $data->id,
                'name' => mb_substr($business_name . ' #' . $data->attribute_id, 0, 50),
            ],
            'configuration' => [
                'paymentAction' => 'SALE',
                'returnUrl' => route('noon.return', ['payment_id' => $data->id]),
                'locale' => $this->locale($data),
            ],
        ];

        try {
            $result = $this->noon->initiate($body);
        } catch (\Throwable $e) {
            Log::channel('payments')->error('noon initiate threw', ['payment_id' => $data->id, 'error' => $e->getMessage()]);
            $result = null;
        }

        $noonOrderId = $result['result']['order']['id'] ?? null;
        $postUrl = $result['result']['checkoutData']['postUrl'] ?? null;

        if (($result['resultCode'] ?? null) !== 0 || !$noonOrderId || !$postUrl) {
            Log::channel('payments')->error('noon initiate refused', [
                'payment_id' => $data->id,
                'resultCode' => $result['resultCode'] ?? null,
                'message' => $result['message'] ?? null,
                'requestReference' => $result['requestReference'] ?? null,
            ]);
            PaymentSettlement::markFailed($data, 'noon');
            return $this->payment_response($data, 'fail');
        }

        PaymentRequest::whereKey($data->id)->where('is_paid', 0)->update(['transaction_id' => (string) $noonOrderId]);

        Log::channel('payments')->info('noon initiate', [
            'payment_id' => $data->id,
            'noon_order_id' => (string) $noonOrderId,
            'requestReference' => $result['requestReference'] ?? null,
        ]);

        return Redirect::away($postUrl);
    }

    public function return(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $payment = PaymentRequest::find($request['payment_id']);
        if (!$payment) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        if ($payment->is_paid) {
            return $this->payment_response($payment, 'success');
        }

        // The stored id came from our own INITIATE; one in the query string
        // that differs is someone else's order.
        if (!$payment->transaction_id || ($request->filled('orderId') && (string) $request['orderId'] !== (string) $payment->transaction_id)) {
            Log::channel('payments')->warning('noon return with an unknown order id', [
                'payment_id' => $payment->id,
                'stored' => $payment->transaction_id,
                'query' => $request['orderId'],
            ]);
            return $this->payment_response($payment, 'fail');
        }

        // noon redirects as soon as the bank answers; a status can trail it
        // by a moment, so give a non-final one two short retries.
        $flag = 'fail';
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if ($attempt > 0) {
                usleep(1500000);
            }
            $result = $this->noon->getOrder($payment->transaction_id);
            if ($result === null) {
                break;
            }
            [$flag, $final] = $this->noon->settle($payment, $result['order']);
            if ($final) {
                break;
            }
        }

        return $this->payment_response($payment, $flag);
    }

    public function webhook(Request $request)
    {
        $jws = $request->input('data');
        if (!is_string($jws) || substr_count($jws, '.') !== 2 || $request->header('np-webhook-version') != '2') {
            Log::channel('payments')->warning('noon webhook: not a v2 payload');
            return response()->json(['message' => 'bad payload'], 400);
        }

        $secret = $this->noon->config('webhook_secret', '');
        $kid = $this->noon->config('webhook_key_id', '');
        if ($secret === '') {
            Log::channel('payments')->error('noon webhook: no secret configured');
            return response()->json(['message' => 'not configured'], 503);
        }

        try {
            $header = json_decode(JWT::urlsafeB64Decode(explode('.', $jws)[0]), true);
            if (($header['alg'] ?? null) !== 'HS256' || ($kid !== '' && ($header['kid'] ?? null) !== $kid)) {
                throw new \UnexpectedValueException('alg/kid mismatch');
            }
            JWT::$leeway = 300;
            $payload = (array) JWT::decode($jws, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            Log::channel('payments')->warning('noon webhook: signature rejected', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'invalid signature'], 401);
        }

        $eventId = (string) ($payload['eventId'] ?? '');
        $noonOrderId = (string) ($payload['orderId'] ?? '');
        $reference = (string) ($payload['merchantOrderRef'] ?? '');

        if ($eventId !== '' && Cache::has("noon:evt:{$eventId}")) {
            return response()->json(['message' => 'duplicate'], 200);
        }

        $payment = $noonOrderId !== '' ? PaymentRequest::where('transaction_id', $noonOrderId)->first() : null;
        if (!$payment && $reference !== '') {
            $payment = PaymentRequest::find($reference);
        }

        Log::channel('payments')->info('noon webhook', [
            'eventId' => $eventId,
            'eventType' => $payload['eventType'] ?? null,
            'orderStatus' => $payload['orderStatus'] ?? null,
            'noon_order_id' => $noonOrderId,
            'payment_id' => $payment?->id,
        ]);

        if (!$payment) {
            // Not ours (or a payment row that was purged). Ack so noon stops retrying.
            return response()->json(['message' => 'unknown order'], 200);
        }

        // Settle from GET ORDER, not from the event, so the amount, currency
        // and reference checks run on noon's own record. Short timeout: noon
        // wants the ack within 10 s.
        $result = $this->noon->getOrder($noonOrderId !== '' ? $noonOrderId : $payment->transaction_id, 6);
        if ($result === null) {
            return response()->json(['message' => 'retry'], 503);
        }
        $this->noon->settle($payment, $result['order']);
        NoonRefundService::syncFromResult((string) $result['order']['id'], $result);

        if ($eventId !== '') {
            Cache::put("noon:evt:{$eventId}", 1, now()->addDays(3));
        }
        return response()->json(['message' => 'ok'], 200);
    }

    private function locale(PaymentRequest $payment): string
    {
        // A guest's payer_id is a guest id, not a users.id.
        $isCustomer = $payment->attribute === 'wallet_payments'
            || ($payment->attribute === 'order' && Order::whereKey($payment->attribute_id)->value('is_guest') == 0);
        $language = $isCustomer
            ? User::whereKey($payment->payer_id)->value('current_language_key')
            : null;

        return ($language ?? app()->getLocale()) === 'ar' ? 'ar' : 'en';
    }
}
