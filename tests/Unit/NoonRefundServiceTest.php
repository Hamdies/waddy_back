<?php

namespace Tests\Unit;

use App\Models\CardRefund;
use App\Models\Order;
use App\Models\PaymentRequest;
use App\Services\NoonPaymentService;
use App\Services\NoonRefundService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Money paths of the noon integration (docs/noon_payments_plan.md): settlement
 * runs a hook once, a refund choice is accepted once, and a REFUND is never
 * sent twice. In-memory SQLite with only the columns these paths touch; noon
 * is faked with Http::fake.
 */
class NoonRefundServiceTest extends TestCase
{
    private const NOON_ORDER = '1234567890123456';

    /** What the fake noon answers to GET ORDER / POST (REFUND). */
    private $getResponse;

    private $postResponse;

    /** @var array<int, array> bodies of every POST sent to noon */
    private array $posts = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array',
            'logging.channels.payments' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
        ]);
        DB::purge('sqlite');

        Schema::create('addon_settings', function (Blueprint $t) {
            $t->char('id', 36);
            $t->string('key_name');
            $t->longText('live_values')->nullable();
            $t->longText('test_values')->nullable();
            $t->string('settings_type');
            $t->string('mode')->default('test');
            $t->boolean('is_active')->default(1);
            $t->longText('additional_data')->nullable();
            $t->timestamps();
        });
        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key');
            $t->longText('value')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_requests', function (Blueprint $t) {
            $t->char('id', 36)->primary();
            $t->string('payer_id')->nullable();
            $t->decimal('payment_amount', 24, 2);
            $t->string('success_hook')->nullable();
            $t->string('failure_hook')->nullable();
            $t->string('transaction_id')->nullable();
            $t->string('currency_code')->default('EGP');
            $t->string('payment_method')->nullable();
            $t->boolean('is_paid')->default(0);
            $t->timestamp('failed_at')->nullable();
            $t->string('attribute_id')->nullable();
            $t->string('attribute')->nullable();
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->boolean('is_guest')->default(0);
            $t->string('order_status')->default('pending');
            $t->string('payment_status')->default('unpaid');
            $t->string('payment_method')->default('digital_payment');
            $t->decimal('order_amount', 24, 2)->default(0);
            $t->unsignedBigInteger('module_id')->nullable();
            $t->unsignedBigInteger('zone_id')->nullable();
            $t->string('order_type')->default('delivery');
            $t->timestamps();
        });
        Schema::create('storages', function (Blueprint $t) {
            $t->id();
            $t->string('data_type');
            $t->string('data_id');
            $t->string('key')->nullable();
            $t->string('value')->nullable();
            $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('cm_firebase_token')->nullable();
            $t->string('current_language_key')->nullable();
            $t->timestamps();
        });
        Schema::create('guests', function (Blueprint $t) {
            $t->id();
            $t->string('fcm_token')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_28_000002_create_card_refunds_table.php'))->up();

        $values = json_encode([
            'gateway' => 'noon', 'mode' => 'test', 'status' => '1', 'region' => 'eg',
            'business_identifier' => 'waddy', 'application_identifier' => 'app', 'application_key' => 'key',
            'order_category' => 'pay', 'webhook_key_id' => 'key1', 'webhook_secret' => 'secret',
            'card_refund_enabled' => '1',
        ]);
        DB::table('addon_settings')->insert([
            'id' => (string) Str::uuid(), 'key_name' => 'noon', 'settings_type' => 'payment_config',
            'mode' => 'test', 'live_values' => $values, 'test_values' => $values,
        ]);
        DB::table('business_settings')->insert(['key' => 'wallet_status', 'value' => '0']);
        DB::table('users')->insert(['id' => 7]);

        $this->getResponse = Http::response($this->noonOrder(250), 200);
        $this->postResponse = Http::response(['resultCode' => 0, 'result' => ['transaction' => ['id' => 't1', 'status' => 'PENDING']]], 200);
        Http::fake(function ($request) {
            if ($request->method() === 'POST') {
                $this->posts[] = $request->data();
                return $this->postResponse;
            }
            return $this->getResponse;
        });
    }

    private function paidOrder(float $amount = 250.0): array
    {
        // Straight to the table: Order's observers fill columns this schema doesn't have.
        DB::table('orders')->insert([
            'id' => 100001, 'user_id' => 7, 'is_guest' => 0, 'order_status' => 'confirmed',
            'payment_status' => 'paid', 'payment_method' => 'noon', 'order_amount' => $amount,
        ]);
        $order = Order::find(100001);

        $payment = new PaymentRequest();
        $payment->forceFill([
            'payment_amount' => $amount, 'currency_code' => 'EGP', 'payment_method' => 'noon',
            'is_paid' => 1, 'transaction_id' => self::NOON_ORDER, 'attribute' => 'order', 'attribute_id' => 100001,
        ])->save();

        return [$order->fresh(), $payment->fresh()];
    }

    /** GET ORDER with whatever refund transactions noon already holds. */
    private function noonOrder(float $captured, float $refunded = 0, array $transactions = []): array
    {
        return [
            'resultCode' => 0,
            'result' => [
                'order' => [
                    'id' => (int) self::NOON_ORDER, 'status' => 'CAPTURED', 'currency' => 'EGP',
                    'totalCapturedAmount' => $captured, 'totalRefundedAmount' => $refunded,
                ],
                'transactions' => $transactions,
            ],
        ];
    }

    public function test_card_refund_enabled_reads_the_admin_card(): void
    {
        $this->assertTrue(NoonPaymentService::cardRefundEnabled());
    }

    public function test_guest_cancellation_goes_straight_to_the_card(): void
    {
        [$order, $payment] = $this->paidOrder();
        DB::table('orders')->where('id', $order->id)->update(['is_guest' => 1]);

        $row = NoonRefundService::openForCancellation(Order::find($order->id), $payment, 250);

        $this->assertSame(CardRefund::REQUESTED, $row->status);
        $this->assertSame('guest', $row->chosen_by);
        $this->assertNull($row->user_id);
    }

    public function test_customer_without_wallet_is_not_asked(): void
    {
        [$order, $payment] = $this->paidOrder();

        $row = NoonRefundService::openForCancellation($order, $payment, 250);

        $this->assertSame(CardRefund::REQUESTED, $row->status);
        $this->assertSame('auto', $row->chosen_by);
    }

    public function test_customer_with_wallet_is_asked_and_can_choose_only_once(): void
    {
        DB::table('business_settings')->where('key', 'wallet_status')->update(['value' => '1']);
        [$order, $payment] = $this->paidOrder();
        $row = NoonRefundService::openForCancellation($order, $payment, 250);
        $this->assertSame(CardRefund::AWAITING_CHOICE, $row->status);
        $this->assertNotNull($row->choice_deadline);

        // Opening it again (a second cancel path) changes nothing.
        $again = NoonRefundService::openForCancellation($order, $payment, 250);
        $this->assertSame($row->id, $again->id);
        $this->assertSame(1, CardRefund::count());

        $this->assertTrue(NoonRefundService::choose($row, 'card'));
        $this->assertFalse(NoonRefundService::choose($row, 'card'));
        $this->assertFalse(NoonRefundService::choose($row, 'wallet'));
        $this->assertSame('customer', $row->fresh()->chosen_by);
    }

    public function test_expired_choice_goes_to_the_card(): void
    {
        DB::table('business_settings')->where('key', 'wallet_status')->update(['value' => '1']);
        [$order, $payment] = $this->paidOrder();
        $row = NoonRefundService::openForCancellation($order, $payment, 250);
        $row->forceFill(['choice_deadline' => now()->subMinute()])->save();

        NoonRefundService::processDue();
        NoonRefundService::processDue();

        $row->refresh();
        $this->assertSame('deadline', $row->chosen_by);
        $this->assertSame(CardRefund::SUBMITTED, $row->status);
    }

    public function test_submit_success_sends_one_refund_with_our_reference(): void
    {
        [$order, $payment] = $this->paidOrder();
        $this->postResponse = Http::response(['resultCode' => 0, 'result' => ['transaction' => ['id' => 'txn-9', 'status' => 'SUCCESS']]], 200);

        // Opening sends it (no choice to wait for); a second submit is a no-op.
        $row = NoonRefundService::openForCancellation($order, $payment, 250);
        NoonRefundService::submit($row->id);

        $row->refresh();
        $this->assertSame(CardRefund::SUCCEEDED, $row->status);
        $this->assertSame('txn-9', $row->noon_txn_id);
        $this->assertCount(1, $this->posts);
        $body = $this->posts[0];
        $this->assertSame('REFUND', $body['apiOperation']);
        $this->assertSame(self::NOON_ORDER, $body['order']['id']);
        $this->assertSame('250.00', $body['transaction']['amount']);
        $this->assertSame($row->reference(), $body['transaction']['transactionReference']);
    }

    public function test_a_timed_out_refund_is_found_by_reference_not_resent(): void
    {
        [$order, $payment] = $this->paidOrder();
        // First attempt (sent on open): GET ORDER fine, REFUND returns nothing usable.
        $this->postResponse = Http::response('', 504);
        $row = NoonRefundService::openForCancellation($order, $payment, 250);
        $row->refresh();
        $this->assertSame(CardRefund::REQUESTED, $row->status);
        $this->assertSame(1, $row->attempts);

        // Second attempt: noon did get it; GET ORDER now lists our reference.
        $this->getResponse = Http::response($this->noonOrder(250, 250, [
            ['id' => 'txn-late', 'type' => 'REFUND', 'status' => 'SUCCESS', 'transactionReference' => $row->reference()],
        ]), 200);
        $this->postResponse = Http::response(['resultCode' => 0, 'result' => ['transaction' => ['id' => 'dup', 'status' => 'SUCCESS']]], 200);
        // Retries wait for their backoff; the scheduler would pick it up then.
        $row->forceFill(['next_attempt_at' => now()->subSecond()])->save();
        NoonRefundService::submit($row->id);

        $row->refresh();
        $this->assertSame(CardRefund::SUCCEEDED, $row->status);
        $this->assertSame('txn-late', $row->noon_txn_id);
        $this->assertCount(1, $this->posts, 'only the first, timed-out REFUND was sent');
    }

    public function test_refund_above_what_noon_can_refund_fails_without_calling_refund(): void
    {
        [$order, $payment] = $this->paidOrder();
        $this->getResponse = Http::response($this->noonOrder(250, 100), 200);

        $row = NoonRefundService::openForCancellation($order, $payment, 250);

        $row->refresh();
        $this->assertSame(CardRefund::FAILED, $row->status);
        $this->assertCount(0, $this->posts);

        // Admin retry sends it again; it goes through once noon has the room.
        $this->getResponse = Http::response($this->noonOrder(250), 200);
        $this->assertTrue(NoonRefundService::retry($row));
        $this->assertSame(CardRefund::SUBMITTED, $row->fresh()->status);
        $this->assertCount(1, $this->posts);
        $this->assertFalse(NoonRefundService::retry($row->fresh()), 'only a failed refund can be retried');
    }

    public function test_webhook_sync_completes_a_submitted_refund(): void
    {
        [$order, $payment] = $this->paidOrder();
        $row = NoonRefundService::openForCancellation($order, $payment, 250);
        $row->forceFill(['status' => CardRefund::SUBMITTED, 'attempts' => 1, 'submitted_at' => now()])->save();

        NoonRefundService::syncFromResult(self::NOON_ORDER, $this->noonOrder(250, 250, [
            ['id' => 'txn-1', 'status' => 'SUCCESS', 'transactionReference' => $row->reference(), 'rrn' => '123456789012'],
        ])['result']);

        $row->refresh();
        $this->assertSame(CardRefund::SUCCEEDED, $row->status);
        $this->assertSame('123456789012', $row->rrn);
    }

    public function test_card_balance_counts_refunds_already_promised(): void
    {
        [$order, $payment] = $this->paidOrder(300);
        NoonRefundService::openForCancellation($order, $payment, 120);

        $this->assertEqualsWithDelta(180.0, NoonRefundService::cardBalance($order), 0.001);
    }

    public function test_settle_runs_the_success_hook_once(): void
    {
        $payment = new PaymentRequest();
        $payment->forceFill([
            'payment_amount' => 99.5, 'currency_code' => 'EGP', 'payment_method' => 'noon', 'is_paid' => 0,
            'transaction_id' => self::NOON_ORDER, 'attribute' => 'wallet_payments', 'attribute_id' => 1,
            'success_hook' => 'Tests\\Unit\\noon_test_success_hook',
        ])->save();
        $payment = $payment->fresh();
        $GLOBALS['noon_test_hook_calls'] = 0;

        $captured = ['id' => self::NOON_ORDER, 'status' => 'CAPTURED', 'currency' => 'EGP', 'totalCapturedAmount' => 99.5, 'reference' => $payment->id];
        $noon = new NoonPaymentService();
        $this->assertSame(['success', true], $noon->settle($payment, $captured));
        $this->assertSame(['success', true], $noon->settle($payment, $captured));

        $this->assertSame(1, $GLOBALS['noon_test_hook_calls']);
    }

    public function test_settle_refuses_a_capture_for_a_different_amount(): void
    {
        $payment = new PaymentRequest();
        $payment->forceFill([
            'payment_amount' => 99.5, 'currency_code' => 'EGP', 'payment_method' => 'noon', 'is_paid' => 0,
            'transaction_id' => self::NOON_ORDER, 'attribute' => 'wallet_payments', 'attribute_id' => 1,
        ])->save();
        $payment = $payment->fresh();

        $captured = ['id' => self::NOON_ORDER, 'status' => 'CAPTURED', 'currency' => 'EGP', 'totalCapturedAmount' => 9.95, 'reference' => $payment->id];
        $this->assertSame(['fail', true], (new NoonPaymentService())->settle($payment, $captured));
        $this->assertSame(0, (int) $payment->fresh()->is_paid);
    }
}

if (!function_exists('noon_test_success_hook')) {
    function noon_test_success_hook($payment): void
    {
        $GLOBALS['noon_test_hook_calls']++;
    }
}
