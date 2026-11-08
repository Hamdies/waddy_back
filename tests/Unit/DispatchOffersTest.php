<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\DeliverymanController;
use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\OrderDispatch;
use App\Models\OrderOffer;
use App\Services\AdminPanel\LiveOps;
use App\Services\DispatchService;
use App\Services\OrderRescueService;
use App\Services\RiderArrivalService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Timed offers against a real (in-memory) database and the real migration:
 * who can see an order, who can take it, and that it can never get stuck.
 */
class DispatchOffersTest extends TestCase
{
    /** @var array<int, array{0:string,1:array}> */
    private array $pushed = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(now()->startOfSecond());
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'cache.default' => 'array',
            'dm_maximum_orders' => 5,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('zones', function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('deliveryman_wise_topic')->nullable(); $t->integer('status')->default(1); $t->timestamps(); });
        Schema::create('translations', function ($t) { $t->id(); $t->string('translationable_type'); $t->integer('translationable_id'); $t->string('locale'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('notification_messages', function ($t) { $t->id(); $t->string('module_type')->nullable(); $t->string('key')->nullable(); $t->text('message')->nullable(); $t->integer('status')->default(0); $t->timestamps(); });
        Schema::create('stores', function ($t) { $t->id(); $t->string('name')->nullable(); $t->string('latitude')->nullable(); $t->string('longitude')->nullable(); $t->timestamps(); });
        Schema::create('cache', function ($t) { $t->string('key')->primary(); $t->mediumText('value'); $t->integer('expiration'); });
        Schema::create('modules', function ($t) { $t->id(); $t->string('module_type')->nullable(); $t->timestamps(); });
        Schema::create('storages', function ($t) { $t->id(); $t->string('data_type'); $t->string('data_id'); $t->string('key')->nullable(); $t->string('value')->nullable(); $t->timestamps(); });
        Schema::create('business_settings', function ($t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('order_payments', function ($t) { $t->id(); $t->integer('order_id'); $t->string('payment_method'); $t->timestamps(); });
        Schema::create('delivery_men', function ($t) {
            $t->id(); $t->string('type')->default('zone_wise'); $t->integer('active')->default(1); $t->integer('status')->default(1);
            $t->string('application_status')->default('approved'); $t->integer('zone_id')->default(1); $t->integer('current_orders')->default(0);
            $t->integer('assigned_order_count')->default(0); $t->string('fcm_token')->default('t'); $t->integer('vehicle_id')->nullable();
            $t->string('f_name')->nullable(); $t->string('l_name')->nullable(); $t->timestamps();
        });
        Schema::create('delivery_man_wallets', function ($t) { $t->id(); $t->integer('delivery_man_id'); $t->decimal('collected_cash', 10, 2)->default(0); $t->timestamps(); });
        Schema::create('delivery_histories', function ($t) { $t->id(); $t->integer('delivery_man_id'); $t->string('latitude'); $t->string('longitude'); $t->timestamp('time')->nullable(); $t->timestamps(); });
        Schema::create('orders', function ($t) {
            $t->id(); $t->string('order_type')->default('parcel'); $t->string('order_status')->default('pending'); $t->integer('zone_id')->default(1);
            $t->integer('module_id')->default(1); $t->integer('store_id')->nullable(); $t->integer('delivery_man_id')->nullable(); $t->integer('user_id')->nullable();
            $t->string('payment_method')->default('digital_payment'); $t->decimal('order_amount', 10, 2)->default(100); $t->text('delivery_address')->nullable();
            $t->integer('scheduled')->default(0); $t->dateTime('schedule_at')->nullable(); $t->integer('is_guest')->default(0); $t->dateTime('accepted')->nullable();
            $t->integer('dm_vehicle_id')->nullable(); $t->text('receiver_details')->nullable(); $t->dateTime('delivered')->nullable(); $t->timestamps();
        });
        (require database_path('migrations/2026_11_06_000001_create_order_dispatches.php'))->up();
        (require database_path('migrations/2026_11_06_000002_add_rider_arrival_times_to_orders.php'))->up();
        (require database_path('migrations/2026_11_08_000001_add_dispatch_scoring_to_order_offers.php'))->up();
        Schema::create('user_notifications', function ($t) { $t->id(); $t->text('data')->nullable(); $t->integer('delivery_man_id')->nullable(); $t->integer('user_id')->nullable(); $t->timestamps(); });

        DB::table('zones')->insert(['id' => 1, 'dispatch_mode' => 'offers', 'deliveryman_wise_topic' => 'zone_1']);
        DB::table('modules')->insert(['id' => 1, 'module_type' => 'food']);
        config(['dispatch_wave_size_conf' => ['value' => 3]]); // room to tell the waves apart
        // Offers, accept and decline exist only for freelancers; employee tests switch this.
        config(['rider_business_model_conf' => ['value' => 'freelance']]);

        $this->pushed = [];
        DispatchService::$pusher = function (string $token, array $data) { $this->pushed[] = [$token, $data]; };
    }

    protected function tearDown(): void
    {
        DispatchService::$pusher = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Riders at increasing distance from the pickup, so ids are their rank. */
    private function riders(int $count): void
    {
        for ($id = 1; $id <= $count; $id++) {
            DB::table('delivery_men')->insert(['id' => $id, 'fcm_token' => "t$id"]);
            DB::table('delivery_man_wallets')->insert(['delivery_man_id' => $id, 'collected_cash' => 0]);
            DB::table('delivery_histories')->insert(['delivery_man_id' => $id, 'latitude' => (string) (30 + $id * 0.02), 'longitude' => '31', 'time' => now()]);
        }
    }

    private function order(array $attrs = []): Order
    {
        $id = DB::table('orders')->insertGetId(array_merge([
            'delivery_address' => json_encode(['latitude' => '30', 'longitude' => '31']),
            'created_at' => now(), 'updated_at' => now(),
        ], $attrs));
        return Order::withoutGlobalScopes()->with(['store', 'zone'])->find($id);
    }

    private function start(Order $order): void
    {
        DispatchService::start($order, ['title' => 't', 'description' => 'd', 'image' => '', 'order_id' => $order->id]);
    }

    private function offered(Order $order): array
    {
        return OrderOffer::where(['order_id' => $order->id, 'status' => 'offered'])->orderBy('delivery_man_id')->pluck('delivery_man_id')->all();
    }

    private function listFor(int $dmId, Order $order): array
    {
        return DispatchService::forRider(DeliveryMan::find($dmId), ['total_size' => 1, 'orders' => [['id' => $order->id]]]);
    }

    private function accept(int $dmId, Order $order)
    {
        $request = Request::create('/', 'PUT', ['order_id' => $order->id]);
        $request->merge(['dm' => DeliveryMan::find($dmId)]);
        return app(DeliverymanController::class)->accept_order($request);
    }

    public function test_the_nearest_riders_get_a_timed_offer_and_the_alert(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        $this->assertSame([1, 2, 3], $this->offered($order));
        $this->assertSame(['t1', 't2', 't3'], array_column($this->pushed, 0));
        $this->assertSame(30, $this->pushed[0][1]['offer_seconds']);
        $this->assertSame('order_request', $this->pushed[0][1]['type']);
    }

    public function test_by_default_two_riders_hear_first_then_the_next_four(): void
    {
        config()->offsetUnset('dispatch_wave_size_conf');
        $this->riders(5);
        $order = $this->order();
        $this->start($order);
        $this->assertSame([1, 2], $this->offered($order));

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick();
        $this->assertSame([3, 4, 5], $this->offered($order)); // all that are left
    }

    public function test_a_rider_at_the_same_store_gets_the_second_order_first(): void
    {
        $this->riders(2);
        config(['dispatch_wave_size_conf' => ['value' => 1]]);
        DB::table('stores')->insert(['id' => 1, 'name' => 'Zaatar', 'latitude' => '30', 'longitude' => '31']);
        DB::table('delivery_histories')->where('delivery_man_id', 1)->update(['latitude' => '30.002']); // free, 220 m away
        DB::table('delivery_histories')->where('delivery_man_id', 2)->update(['latitude' => '30.003']); // holds an order there, 330 m away
        DB::table('delivery_men')->where('id', 2)->update(['current_orders' => 1]);
        $near = json_encode(['latitude' => '30.01', 'longitude' => '31']);
        $this->order(['order_type' => 'delivery', 'store_id' => 1, 'delivery_man_id' => 2, 'order_status' => 'processing', 'delivery_address' => $near]);

        $order = $this->order(['order_type' => 'delivery', 'store_id' => 1, 'delivery_address' => $near]);
        $this->assertSame(2, DispatchService::candidates($order)->first()['id']);

        // Their other customer is the opposite way: two trips, not one.
        DB::table('orders')->where('delivery_man_id', 2)->update(['delivery_address' => json_encode(['latitude' => '29.95', 'longitude' => '31'])]);
        $this->assertSame(1, DispatchService::candidates($order)->first()['id']);
    }

    public function test_an_order_on_the_way_to_another_customer_rides_along(): void
    {
        $this->riders(2);
        config(['dispatch_wave_size_conf' => ['value' => 1]]);
        DB::table('stores')->insert(['id' => 1, 'name' => 'Zaatar', 'latitude' => '30', 'longitude' => '31']);
        DB::table('delivery_histories')->where('delivery_man_id', 1)->update(['latitude' => '30.002']);
        DB::table('delivery_histories')->where('delivery_man_id', 2)->update(['latitude' => '30.003']);
        DB::table('delivery_men')->where('id', 2)->update(['current_orders' => 1]);
        // Rider 2's customer is 5.5 km north; the new one lives on that road, 1 km in.
        $this->order(['order_type' => 'delivery', 'store_id' => 1, 'delivery_man_id' => 2, 'order_status' => 'processing',
            'delivery_address' => json_encode(['latitude' => '30.05', 'longitude' => '31'])]);

        $order = $this->order(['order_type' => 'delivery', 'store_id' => 1, 'delivery_address' => json_encode(['latitude' => '30.01', 'longitude' => '31'])]);
        $best = DispatchService::candidates($order)->first();
        $this->assertSame(2, $best['id']);
        $this->assertTrue($best['stack']);
    }

    public function test_the_work_is_shared_between_riders_equally_close(): void
    {
        $this->riders(2);
        DB::table('delivery_histories')->update(['latitude' => '30.001']);
        DB::table('orders')->insert(['delivery_man_id' => 1, 'order_status' => 'delivered', 'accepted' => now()]);
        $this->assertSame(2, DispatchService::candidates($this->order())->first()['id']);
    }

    public function test_only_offered_riders_see_the_order_with_the_time_left(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        $mine = $this->listFor(1, $order);
        $this->assertSame(30, $mine['orders'][0]['offer_seconds_left']);
        $this->assertSame(30, $mine['orders'][0]['offer_total_seconds']);

        $other = $this->listFor(5, $order);
        $this->assertSame([], $other['orders']);
        $this->assertSame(0, $other['total_size']);
    }

    public function test_when_the_time_is_up_the_next_wave_goes_then_the_whole_zone(): void
    {
        $this->riders(9);
        $order = $this->order();
        $this->start($order);

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick();
        $this->assertSame([4, 5, 6, 7, 8], $this->offered($order)); // wave 2 is 2 bigger
        $this->assertSame('expired', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        $this->assertSame([], $this->listFor(1, $order)['orders']); // their chance has gone for now

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick();
        $this->assertSame([], $this->offered($order));
        $this->assertSame('open', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertCount(1, $this->listFor(9, $order)['orders']);
        $this->assertArrayNotHasKey('offer_seconds_left', $this->listFor(9, $order)['orders'][0]);
    }

    public function test_with_no_rider_nearby_it_opens_to_the_zone_at_once(): void
    {
        $order = $this->order();
        $this->start($order);
        $this->assertSame('open', OrderDispatch::where('order_id', $order->id)->value('status'));
    }

    public function test_when_the_whole_wave_declines_the_next_one_goes_at_once(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        DispatchService::decline($order->id, DeliveryMan::find(1));
        DispatchService::decline($order->id, DeliveryMan::find(2));
        $this->assertSame([3], $this->offered($order)); // still waiting on one

        DispatchService::decline($order->id, DeliveryMan::find(3));
        $this->assertSame([4, 5], $this->offered($order));
        $this->assertSame('declined', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
    }

    public function test_a_stale_caller_cannot_send_a_wave_twice(): void
    {
        $this->riders(9);
        $order = $this->order();
        $this->start($order);
        $stale = OrderDispatch::where('order_id', $order->id)->first();

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick();
        $pushes = count($this->pushed);

        $advance = new \ReflectionMethod(DispatchService::class, 'advance');
        $advance->invoke(null, $order, $stale);
        $this->assertCount($pushes, $this->pushed);
        $this->assertSame([4, 5, 6, 7, 8], $this->offered($order));
    }

    public function test_if_the_scheduler_stops_the_reservation_lapses_by_itself(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        // 30 s offer + 60 s grace, with no tick ever running.
        Carbon::setTestNow(now()->addSeconds(91));
        $this->assertCount(1, $this->listFor(5, $order)['orders']);
        $this->assertTrue(DispatchService::mayTake($order, DeliveryMan::find(5)));
    }

    public function test_a_store_rider_is_not_affected_by_zone_offers(): void
    {
        $this->riders(1);
        DB::table('delivery_men')->insert(['id' => 2, 'type' => 'restaurant_wise']);
        $order = $this->order();
        $this->start($order);
        $this->assertCount(1, $this->listFor(2, $order)['orders']);
        $this->assertTrue(DispatchService::mayTake($order, DeliveryMan::find(2)));
    }

    public function test_waves_mode_keeps_the_order_visible_but_still_logs_offers(): void
    {
        DB::table('zones')->update(['dispatch_mode' => 'waves']);
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        $this->assertSame([1, 2, 3], $this->offered($order));
        $this->assertArrayNotHasKey('offer_seconds', $this->pushed[0][1]);
        $list = $this->listFor(5, $order);
        $this->assertCount(1, $list['orders']);
        $this->assertArrayNotHasKey('offer_seconds_left', $this->listFor(1, $order)['orders'][0]);
        $this->assertTrue(DispatchService::mayTake($order, DeliveryMan::find(5)));
    }

    public function test_a_long_cook_holds_the_order_back_from_everyone_and_from_rescue(): void
    {
        $this->riders(3);
        DB::table('orders')->insert(['id' => 50, 'order_type' => 'parcel', 'scheduled' => 1, 'schedule_at' => now()->addHour(), 'delivery_address' => json_encode(['latitude' => '30', 'longitude' => '31'])]);
        $order = Order::withoutGlobalScopes()->with(['store', 'zone'])->find(50);
        $this->start($order);

        $this->assertSame('waiting', OrderDispatch::where('order_id', 50)->value('status'));
        $this->assertSame([], $this->offered($order));
        $this->assertSame([], $this->listFor(1, $order)['orders']);
        $this->assertTrue(DispatchService::isWaitingForFood($order));

        Carbon::setTestNow(now()->addMinutes(31));
        DB::table('delivery_histories')->update(['time' => now()]); // riders still online
        DispatchService::tick();
        $this->assertSame([1, 2, 3], $this->offered($order));
    }

    public function test_an_assigned_or_cancelled_order_stops_dispatching(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);
        DB::table('orders')->where('id', $order->id)->update(['order_status' => 'canceled']);
        $pushes = count($this->pushed);

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick();
        $this->assertSame('closed', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertCount($pushes, $this->pushed);
    }

    public function test_accept_lets_an_offered_rider_in_and_keeps_others_out(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        $this->assertSame(404, $this->accept(5, $order)->getStatusCode());
        $this->assertNull(DB::table('orders')->where('id', $order->id)->value('delivery_man_id'));

        $this->assertSame(200, $this->accept(2, $order)->getStatusCode());
        $this->assertSame(2, (int) DB::table('orders')->where('id', $order->id)->value('delivery_man_id'));
        $this->assertSame('accepted', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 2])->value('status'));
        $this->assertSame('taken', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        $this->assertSame('closed', OrderDispatch::where('order_id', $order->id)->value('status'));

        // The second tap finds it taken.
        $this->assertSame(404, $this->accept(1, $order)->getStatusCode());
    }

    public function test_an_expired_offer_cannot_be_accepted_until_the_zone_opens(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick(); // wave 2: riders 4 and 5
        $this->assertSame(404, $this->accept(1, $order)->getStatusCode());

        Carbon::setTestNow(now()->addSeconds(31));
        DispatchService::tick(); // zone open
        $this->assertSame(200, $this->accept(1, $order)->getStatusCode());
    }

    public function test_the_cash_cap_applies_only_when_the_admin_has_it_on(): void
    {
        $this->riders(3);
        DB::table('delivery_man_wallets')->where('delivery_man_id', 1)->update(['collected_cash' => 950]);
        DB::table('business_settings')->insert(['key' => 'dm_max_cash_in_hand', 'value' => '1000']);
        $order = $this->order(['payment_method' => 'cash_on_delivery']);

        // Off: no cap, whatever the amount says.
        $this->assertNull(DispatchService::cashCeiling($order));
        $this->assertSame([1, 2, 3], DispatchService::candidates($order)->pluck('id')->sort()->values()->all());

        DB::table('business_settings')->insert(['key' => 'cash_in_hand_overflow_delivery_man', 'value' => '1']);
        $this->assertSame(900.0, DispatchService::cashCeiling($order));
        $this->assertSame([2, 3], DispatchService::candidates($order)->pluck('id')->sort()->values()->all());

        $paid = $this->order(['payment_method' => 'digital_payment']);
        $this->assertNull(DispatchService::cashCeiling($paid));
    }

    public function test_rescue_opens_a_reserved_order_to_the_zone(): void
    {
        $this->riders(5);
        $order = $this->order();
        $this->start($order);
        DispatchService::openToZone($order, ['title' => 't', 'description' => 'd', 'image' => '']);

        $this->assertSame('open', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertCount(1, $this->listFor(5, $order)['orders']);
    }

    public function test_a_repeated_status_does_nothing_twice(): void
    {
        $this->riders(1);
        DB::table('delivery_men')->where('id', 1)->update(['current_orders' => 2]);
        $order = $this->order(['delivery_man_id' => 1, 'order_status' => 'delivered']);

        $request = Request::create('/', 'PUT', ['order_id' => $order->id, 'status' => 'delivered', 'otp' => 'x']);
        $request->merge(['dm' => DeliveryMan::find(1)]);
        $response = app(DeliverymanController::class)->update_order_status($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(2, (int) DB::table('delivery_men')->where('id', 1)->value('current_orders'));
    }

    public function test_arrival_is_recorded_once_for_each_stop_and_never_moves_the_status(): void
    {
        $this->riders(1);
        DB::table('delivery_men')->where('id', 1)->update(['current_orders' => 1]);
        $order = $this->order(['delivery_man_id' => 1, 'order_status' => 'handover', 'receiver_details' => json_encode(['latitude' => '30.1', 'longitude' => '31'])]);
        $dm = DeliveryMan::find(1);

        RiderArrivalService::check($dm, 30.01, 31); // about 1 km out
        $this->assertNull(DB::table('orders')->where('id', $order->id)->value('rider_at_store_at'));

        RiderArrivalService::check($dm, 30.0005, 31); // about 55 m
        $first = DB::table('orders')->where('id', $order->id)->value('rider_at_store_at');
        $this->assertNotNull($first);
        $this->assertSame('handover', DB::table('orders')->where('id', $order->id)->value('order_status'));

        Carbon::setTestNow(now()->addMinutes(3));
        RiderArrivalService::check($dm, 30.0005, 31);
        $this->assertSame($first, DB::table('orders')->where('id', $order->id)->value('rider_at_store_at'));

        DB::table('orders')->where('id', $order->id)->update(['order_status' => 'picked_up']);
        RiderArrivalService::check($dm, 30.1001, 31);
        $this->assertNotNull(DB::table('orders')->where('id', $order->id)->value('rider_at_customer_at'));
    }

    public function test_acceptance_rate_needs_enough_offers_and_ignores_ones_taken_first(): void
    {
        $this->riders(1);
        $make = function (int $n, string $status) {
            for ($i = 0; $i < $n; $i++) {
                $id = DB::table('orders')->insertGetId([]);
                OrderOffer::create(['order_id' => $id, 'delivery_man_id' => 1, 'wave' => 1, 'status' => $status, 'offered_at' => now(), 'expires_at' => now()]);
            }
        };
        $make(6, 'accepted');
        $make(50, 'taken');
        $this->assertNull(DispatchService::acceptanceRate(1));
        $make(4, 'declined');
        $this->assertSame(60, DispatchService::acceptanceRate(1));
    }

    public function test_busy_hint_names_a_store_only_when_it_is_really_busy(): void
    {
        DB::table('stores')->insert(['id' => 1, 'name' => 'Zaatar Kitchen']);
        $make = fn (int $n) => DB::table('orders')->insert(array_fill(0, $n, ['store_id' => 1, 'zone_id' => 1, 'order_type' => 'delivery', 'created_at' => now()]));
        $make(2);
        $this->assertNull(DispatchService::busyHint(1));
        \Illuminate\Support\Facades\Cache::flush();
        $make(1);
        $this->assertSame(['place' => 'Zaatar Kitchen', 'orders' => 3], DispatchService::busyHint(1));
    }

    public function test_the_dispatch_funnel_adds_up(): void
    {
        $now = Carbon::parse('2026-11-06 13:00:00');
        $row = fn (array $r) => $r + ['wave' => 1, 'opened_at' => null, 'first_offer' => '2026-11-06 12:00:00', 'accepted' => null, 'delivery_man_id' => null, 'order_status' => 'processing', 'picked_up' => null, 'rider_at_store_at' => null];
        $f = LiveOps::summarizeDispatch([
            $row(['accepted' => '2026-11-06 12:00:20', 'delivery_man_id' => 1, 'picked_up' => '2026-11-06 12:20:00', 'rider_at_store_at' => '2026-11-06 12:15:00']),
            $row(['wave' => 2, 'accepted' => '2026-11-06 12:01:00', 'delivery_man_id' => 1]),
            $row(['wave' => 3, 'opened_at' => '2026-11-06 12:01:00', 'accepted' => '2026-11-06 12:10:00', 'delivery_man_id' => 1]),
            $row(['first_offer' => '2026-11-06 12:58:00']), // still within its time
            $row(['first_offer' => null, 'wave' => 0]), // waiting for the food: not started
        ], ['accepted' => 3, 'declined' => 1, 'expired' => 2, 'taken' => 9], $now);

        $this->assertSame(5, $f['orders']);
        $this->assertSame(60, $f['acceptMedian']);
        $this->assertSame(600, $f['acceptP90']);
        $this->assertSame(50, $f['offerRate']); // taken is not counted
        $this->assertSame(['wave1' => 2, 'wave2' => 1, 'zone' => 1], $f['reached']);
        $this->assertSame(1, $f['slow']);
        $this->assertSame(300, $f['pickupWaitMedian']);
    }

    public function test_a_parcel_is_a_rider_problem_not_a_store_problem(): void
    {
        config(['order_confirmation_model' => 'store']);
        $parcel = new Order();
        $parcel->forceFill(['order_type' => 'parcel', 'payment_method' => 'cash_on_delivery', 'scheduled' => 0, 'order_status' => 'pending', 'created_at' => '2026-10-26 12:50:00']);
        $d = OrderRescueService::diagnose($parcel, Carbon::parse('2026-10-26 13:00:00'), false);
        $this->assertSame(['problem' => 'rider', 'minutes' => 10], $d);
    }

    private function autoZone(): void
    {
        DB::table('zones')->update(['dispatch_mode' => 'auto']);
    }

    private function assignedTo(Order $order): ?int
    {
        $id = DB::table('orders')->where('id', $order->id)->value('delivery_man_id');
        return $id === null ? null : (int) $id;
    }

    public function test_auto_mode_gives_the_order_to_the_best_rider_at_once(): void
    {
        $this->autoZone();
        $this->riders(3);
        $order = $this->order();
        $this->start($order);

        $this->assertSame(1, $this->assignedTo($order));
        $this->assertSame('accepted', DB::table('orders')->where('id', $order->id)->value('order_status'));
        $this->assertSame(1, (int) DB::table('delivery_men')->where('id', 1)->value('current_orders'));
        $this->assertSame([['t1', 'assign']], array_map(fn ($p) => [$p[0], $p[1]['type']], $this->pushed));
        $this->assertSame(30, $this->pushed[0][1]['decline_seconds']);
        $this->assertSame('assigned', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        // No one else can take it.
        $this->assertSame(404, $this->accept(2, $order)->getStatusCode());
        $this->assertSame(1, $this->assignedTo($order));
        // The rider app shows the hand-back countdown from these.
        $this->assertSame([$order->id => ['offer_seconds_left' => 30, 'offer_total_seconds' => 30]], DispatchService::handBackWindows(1, [$order->id]));
        $this->assertSame([], DispatchService::handBackWindows(2, [$order->id]));
    }

    public function test_an_auto_assigned_rider_can_hand_it_back_and_the_next_best_gets_it(): void
    {
        $this->autoZone();
        $this->riders(3);
        $order = $this->order();
        $this->start($order);

        DispatchService::decline($order->id, DeliveryMan::find(1));

        $this->assertSame(2, $this->assignedTo($order));
        $this->assertSame(0, (int) DB::table('delivery_men')->where('id', 1)->value('current_orders'));
        $this->assertSame('declined', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        $this->assertSame('assigned', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 2])->value('status'));
    }

    public function test_after_the_decline_window_the_rider_keeps_the_order(): void
    {
        $this->autoZone();
        $this->riders(2);
        $order = $this->order();
        $this->start($order);

        Carbon::setTestNow(now()->addSeconds(34));
        DispatchService::tick();
        $this->assertSame('accepted', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        $this->assertSame('closed', OrderDispatch::where('order_id', $order->id)->value('status'));

        DispatchService::decline($order->id, DeliveryMan::find(1)); // too late
        $this->assertSame(1, $this->assignedTo($order));
    }

    public function test_after_three_hand_backs_the_order_opens_to_the_zone(): void
    {
        $this->autoZone();
        $this->riders(5);
        $order = $this->order();
        $this->start($order);
        foreach ([1, 2, 3] as $id) {
            DispatchService::decline($order->id, DeliveryMan::find($id));
        }

        $this->assertNull($this->assignedTo($order));
        $this->assertSame('open', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertSame('pending', DB::table('orders')->where('id', $order->id)->value('order_status'));
        $this->assertCount(1, $this->listFor(5, $order)['orders']);
    }

    public function test_auto_mode_skips_a_rider_whose_phone_went_quiet(): void
    {
        $this->autoZone();
        $this->riders(2);
        DB::table('delivery_histories')->where('delivery_man_id', 1)->update(['time' => now()->subMinutes(3)]);
        $order = $this->order();
        $this->start($order);
        $this->assertSame(2, $this->assignedTo($order));
    }

    public function test_orders_due_together_are_matched_together(): void
    {
        $this->autoZone();
        // Rider 1 is close to both pickups, rider 2 only to the first.
        DB::table('delivery_men')->insert([['id' => 1, 'fcm_token' => 't1'], ['id' => 2, 'fcm_token' => 't2']]);
        DB::table('delivery_man_wallets')->insert([['delivery_man_id' => 1, 'collected_cash' => 0], ['delivery_man_id' => 2, 'collected_cash' => 0]]);
        DB::table('delivery_histories')->insert([
            ['delivery_man_id' => 1, 'latitude' => '30.005', 'longitude' => '31', 'time' => now()],
            ['delivery_man_id' => 2, 'latitude' => '29.993', 'longitude' => '31', 'time' => now()],
        ]);
        // Both orders reach dispatch in the same tick (the food was timed).
        $first = $this->order(['delivery_address' => json_encode(['latitude' => '30', 'longitude' => '31'])]);
        $second = $this->order(['delivery_address' => json_encode(['latitude' => '30.01', 'longitude' => '31'])]);
        foreach ([$first, $second] as $o) {
            DB::table('order_dispatches')->insert(['order_id' => $o->id, 'mode' => 'auto', 'status' => 'waiting',
                'next_at' => now(), 'data' => json_encode(['order_id' => $o->id]), 'created_at' => now(), 'updated_at' => now()]);
        }

        DispatchService::tick();

        // One at a time, the first order would take rider 1 and send rider 2
        // 1.9 km to the second. Together, each gets a rider about 600 m away.
        $this->assertSame(2, $this->assignedTo($first));
        $this->assertSame(1, $this->assignedTo($second));
    }

    private function employees(): void
    {
        config(['rider_business_model_conf' => ['value' => 'employee']]);
    }

    public function test_employees_get_the_order_assigned_with_no_way_to_decline(): void
    {
        $this->employees();
        DB::table('zones')->update(['dispatch_mode' => 'offers']); // ignored for staff
        $this->riders(3);
        $order = $this->order();
        $this->start($order);

        $this->assertSame(1, $this->assignedTo($order));
        $this->assertSame('assign', $this->pushed[0][1]['type']);
        $this->assertArrayNotHasKey('decline_seconds', $this->pushed[0][1]);
        $this->assertSame('accepted', OrderOffer::where(['order_id' => $order->id, 'delivery_man_id' => 1])->value('status'));
        $this->assertSame('closed', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertSame([], DispatchService::handBackWindows(1, [$order->id]));

        DispatchService::decline($order->id, DeliveryMan::find(1));
        $this->assertSame(1, $this->assignedTo($order));
    }

    public function test_employees_cannot_take_orders_from_a_list(): void
    {
        $this->employees();
        $this->riders(1);
        DB::table('delivery_men')->where('id', 1)->update(['active' => 0]); // not assignable
        $order = $this->order();
        $this->start($order);

        $response = $this->accept(1, $order);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('assigned_only', $response->getData(true)['errors'][0]['code']);
    }

    public function test_with_no_free_employee_it_keeps_trying_and_never_opens_to_the_zone(): void
    {
        $this->employees();
        $order = $this->order();
        $this->start($order);
        $this->assertSame('offering', OrderDispatch::where('order_id', $order->id)->value('status'));
        $this->assertSame([], $this->pushed);

        // A rider comes online: the next tick gives it to them.
        $this->riders(1);
        Carbon::setTestNow(now()->addSeconds(16));
        DB::table('delivery_histories')->update(['time' => now()]);
        DispatchService::tick();
        $this->assertSame(1, $this->assignedTo($order));
    }

    public function test_switching_back_to_freelance_restores_accept_and_decline(): void
    {
        $this->employees();
        $this->assertTrue(DispatchService::employeeModel());
        config(['rider_business_model_conf' => ['value' => 'freelance']]);
        $this->assertFalse(DispatchService::employeeModel());

        $this->riders(3);
        $order = $this->order();
        $this->start($order);
        $this->assertSame([1, 2, 3], $this->offered($order)); // zone mode 'offers' again
    }

    public function test_with_no_free_employee_ops_hear_at_three_minutes(): void
    {
        $this->employees();
        $order = $this->order();
        $this->start($order);

        Carbon::setTestNow(now()->addMinutes(3));
        OrderRescueService::run();

        // Each rescue step marks itself done before acting.
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has("order_rescue_{$order->id}_rider_escalate"));
        $this->assertFalse(\Illuminate\Support\Facades\Cache::has("order_rescue_{$order->id}_rider_offer"));
        $this->assertNotSame('open', OrderDispatch::where('order_id', $order->id)->value('status'));
    }
}

