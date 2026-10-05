<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Services\OrderSecurityService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The placement guards in OrderSecurityService::guard, exercised with a fake
 * "place the order" closure so they need no zones, stores or carts.
 *
 * Runs on an in-memory SQLite database holding only the columns the guard
 * touches. The project's migrations cannot build the full schema from empty,
 * and the sibling feature tests use RefreshDatabase, which this deliberately
 * does not.
 */
class OrderIdempotencyTest extends TestCase
{
    private OrderSecurityService $guard;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('orders', function ($t) {
            $t->id();
            $t->string('user_id')->nullable();
            $t->boolean('is_guest')->default(false);
            $t->decimal('order_amount', 10, 2)->default(0);
            $t->string('order_status')->default('pending');
            $t->string('idempotency_key', 36)->nullable();
            $t->string('device_fingerprint', 64)->nullable();
            $t->timestamps();
            // The migration's backstop.
            $t->unique(['user_id', 'idempotency_key']);
        });
        Schema::create('storages', function ($t) {
            $t->id();
            $t->string('data_type')->nullable();
            $t->unsignedBigInteger('data_id')->nullable();
        });

        Cache::flush();
        $this->guard = new OrderSecurityService();
    }

    // -- helpers ----------------------------------------------------------

    private function request(int $userId = 7, ?string $key = null): Request
    {
        $request = Request::create('/api/v1/customer/order/place', 'POST', [
            'idempotency_key' => $key,
        ]);
        $request->user = (object) ['id' => $userId];

        return $request;
    }

    private function makeOrder(array $attributes): Order
    {
        return Order::withoutEvents(fn () => Order::unguarded(fn () => Order::create($attributes)));
    }

    /** A closure that "places" an order the way the trait does. */
    private function placing(Request $request, int &$calls): \Closure
    {
        return function () use ($request, &$calls): JsonResponse {
            $calls++;
            $order = $this->makeOrder([
                'user_id' => $request->user->id,
                'order_amount' => 120,
                'idempotency_key' => $request->input('idempotency_key'),
            ]);

            return $this->guard->placedResponse($order);
        };
    }

    private function rejecting(int &$calls, int $status = 203): \Closure
    {
        return function () use (&$calls, $status): JsonResponse {
            $calls++;

            return response()->json(
                ['errors' => [['code' => 'coupon', 'message' => 'invalid']]],
                $status
            );
        };
    }

    private function code(JsonResponse $r): ?string
    {
        return $r->getData(true)['errors'][0]['code'] ?? null;
    }

    // -- replay -----------------------------------------------------------

    public function test_a_first_placement_runs_and_returns_the_order(): void
    {
        $key = (string) Str::uuid();
        $request = $this->request(7, $key);
        $calls = 0;

        $response = $this->guard->guard($request, $this->placing($request, $calls));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $calls);
        $this->assertArrayNotHasKey('replayed', $response->getData(true));
    }

    public function test_a_replay_returns_the_original_order_without_placing_again(): void
    {
        $key = (string) Str::uuid();
        $first = $this->request(7, $key);
        $calls = 0;
        $original = $this->guard->guard($first, $this->placing($first, $calls));

        Cache::flush(); // not even the cooldown may be what stops it

        $retry = $this->request(7, $key);
        $response = $this->guard->guard($retry, $this->placing($retry, $calls));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, $calls, 'the order must not be placed twice');
        $this->assertSame(
            $original->getData(true)['order_id'],
            $response->getData(true)['order_id']
        );
        $this->assertTrue($response->getData(true)['replayed']);
        $this->assertSame(1, Order::count());
    }

    public function test_a_replay_does_not_start_a_cooldown(): void
    {
        $key = (string) Str::uuid();
        $first = $this->request(7, $key);
        $calls = 0;
        $this->guard->guard($first, $this->placing($first, $calls));
        Cache::flush();

        $replay = $this->request(7, $key);
        $this->guard->guard($replay, $this->placing($replay, $calls));

        // A genuinely new order straight after the replay is allowed.
        $next = $this->request(7, (string) Str::uuid());
        $response = $this->guard->guard($next, $this->placing($next, $calls));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, Order::count());
    }

    public function test_a_replay_is_scoped_to_its_owner(): void
    {
        $key = (string) Str::uuid();
        $mine = $this->request(7, $key);
        $calls = 0;
        $this->guard->guard($mine, $this->placing($mine, $calls));

        $theirs = $this->request(8, $key);
        $response = $this->guard->guard($theirs, $this->placing($theirs, $calls));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertArrayNotHasKey('replayed', $response->getData(true));
        $this->assertSame(2, $calls);
    }

    // -- rejection never burns the key ------------------------------------

    public function test_a_rejected_attempt_can_be_retried_with_the_same_key(): void
    {
        $key = (string) Str::uuid();
        $calls = 0;

        $rejected = $this->guard->guard(
            $this->request(7, $key),
            $this->rejecting($calls)
        );
        $this->assertSame(203, $rejected->getStatusCode());

        $retry = $this->request(7, $key);
        $response = $this->guard->guard($retry, $this->placing($retry, $calls));

        $this->assertSame(200, $response->getStatusCode(), 'the key was burned');
        $this->assertSame(2, $calls);
    }

    public function test_a_rejection_does_not_start_the_cooldown(): void
    {
        $calls = 0;
        $this->guard->guard($this->request(7, (string) Str::uuid()), $this->rejecting($calls, 403));

        $next = $this->request(7, (string) Str::uuid());
        $response = $this->guard->guard($next, $this->placing($next, $calls));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_the_cooldown_applies_after_a_success(): void
    {
        $calls = 0;
        $first = $this->request(7, (string) Str::uuid());
        $this->guard->guard($first, $this->placing($first, $calls));

        $second = $this->request(7, (string) Str::uuid());
        $response = $this->guard->guard($second, $this->placing($second, $calls));

        $this->assertSame(429, $response->getStatusCode());
        $this->assertSame('order_cooldown', $this->code($response));
        $this->assertSame(1, Order::count());
    }

    public function test_a_throwing_placement_releases_the_key(): void
    {
        $key = (string) Str::uuid();
        $request = $this->request(7, $key);

        try {
            $this->guard->guard($request, function () {
                throw new \RuntimeException('boom');
            });
            $this->fail('the exception should propagate');
        } catch (\RuntimeException) {
        }

        $calls = 0;
        $retry = $this->request(7, $key);
        $response = $this->guard->guard($retry, $this->placing($retry, $calls));

        $this->assertSame(200, $response->getStatusCode());
    }

    // -- simultaneous requests --------------------------------------------

    public function test_a_simultaneous_identical_request_is_told_to_wait(): void
    {
        $key = (string) Str::uuid();
        $outer = $this->request(7, $key);
        $calls = 0;
        $inner = null;

        $response = $this->guard->guard($outer, function () use ($key, &$inner, &$calls) {
            // While the first request is still placing, the same key arrives.
            $second = $this->request(7, $key);
            $inner = $this->guard->guard($second, $this->placing($second, $calls));

            return $this->guard->placedResponse($this->makeOrder([
                'user_id' => 7,
                'idempotency_key' => $key,
            ]));
        });

        $this->assertSame(409, $inner->getStatusCode());
        $this->assertSame('order_in_progress', $this->code($inner));
        $this->assertSame(0, $calls, 'the second request must not place anything');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, Order::count());
    }

    public function test_the_lock_is_released_so_a_later_request_replays(): void
    {
        $key = (string) Str::uuid();
        $first = $this->request(7, $key);
        $calls = 0;
        $this->guard->guard($first, $this->placing($first, $calls));

        $later = $this->request(7, $key);
        $response = $this->guard->guard($later, $this->placing($later, $calls));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['replayed']);
    }

    public function test_keyless_requests_are_serialised_per_owner(): void
    {
        $outer = $this->request(7, null);
        $inner = null;
        $calls = 0;

        $this->guard->guard($outer, function () use (&$inner, &$calls) {
            $second = $this->request(7, null);
            $inner = $this->guard->guard($second, $this->placing($second, $calls));

            return response()->json(['ok' => true], 200);
        });

        $this->assertSame(409, $inner->getStatusCode());
    }

    public function test_a_different_key_is_not_blocked_by_an_in_flight_one(): void
    {
        $outer = $this->request(7, (string) Str::uuid());
        $inner = null;
        $calls = 0;

        $this->guard->guard($outer, function () use (&$inner, &$calls) {
            $other = $this->request(8, (string) Str::uuid());
            $inner = $this->guard->guard($other, $this->placing($other, $calls));

            return response()->json(['ok' => true], 200);
        });

        $this->assertSame(200, $inner->getStatusCode());
    }

    // -- the backstop -----------------------------------------------------

    public function test_the_database_refuses_a_second_order_for_the_same_key(): void
    {
        $key = (string) Str::uuid();
        $this->makeOrder(['user_id' => 7, 'idempotency_key' => $key]);

        $this->expectException(QueryException::class);
        $this->makeOrder(['user_id' => 7, 'idempotency_key' => $key]);
    }

    public function test_two_keyless_orders_are_allowed_by_the_index(): void
    {
        $this->makeOrder(['user_id' => 7]);
        $this->makeOrder(['user_id' => 7]);

        $this->assertSame(2, Order::count());
    }

    public function test_a_committed_order_that_errors_afterwards_is_reported_as_placed(): void
    {
        // e.g. the confirmation push throws after DB::commit(), so the trait
        // answers 403 for an order that exists.
        $key = (string) Str::uuid();
        $request = $this->request(7, $key);

        $response = $this->guard->guard($request, function () use ($key) {
            $this->makeOrder(['user_id' => 7, 'idempotency_key' => $key]);

            return response()->json([new \Exception('push failed')], 403);
        });

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->getData(true)['replayed']);
    }

    public function test_a_malformed_key_is_not_used_to_find_or_store_anything(): void
    {
        $request = $this->request(7, 'not-a-uuid');
        $order = new Order();
        $this->guard->storeSecurityFields($order, $request);

        $this->assertNull($order->idempotency_key);
    }
}
