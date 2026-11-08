<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\V1\Auth\DMPasswordResetController;
use App\Http\Controllers\Api\V1\DeliverymanController;
use App\Models\DeliveryMan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Rider API holes found in the 2026-11 review, against an in-memory database
 * (never the local MySQL): reset takeover, skipped statuses, other riders'
 * payout methods, and password change without the current password.
 */
class RiderApiSecurityTest extends TestCase
{
    private const NEW_PASSWORD = 'N3w-Passw0rd!x';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false],
            'cache.default' => 'array',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('storages', function ($t) { $t->id(); $t->string('data_type'); $t->string('data_id'); $t->string('key')->nullable(); $t->string('value')->nullable(); $t->timestamps(); });
        Schema::create('business_settings', function ($t) { $t->id(); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('delivery_men', function ($t) {
            $t->id(); $t->string('type')->default('zone_wise'); $t->string('phone'); $t->string('email')->nullable();
            $t->string('password')->nullable(); $t->string('auth_token')->nullable(); $t->string('image')->nullable();
            $t->string('f_name')->nullable(); $t->string('l_name')->nullable(); $t->integer('vehicle_id')->nullable();
            $t->integer('zone_id')->default(1); $t->integer('current_orders')->default(0); $t->timestamps();
        });
        Schema::create('password_resets', function ($t) {
            $t->string('email')->nullable(); $t->string('phone')->nullable(); $t->string('token');
            $t->string('created_by')->nullable()->default('user'); $t->integer('otp_hit_count')->default(0);
            $t->boolean('is_temp_blocked')->default(0); $t->timestamp('temp_block_time')->nullable(); $t->timestamp('created_at')->nullable();
        });
        Schema::create('orders', function ($t) {
            $t->id(); $t->string('order_type')->default('delivery'); $t->string('order_status')->default('accepted');
            $t->integer('zone_id')->default(1); $t->integer('store_id')->nullable(); $t->integer('delivery_man_id')->nullable();
            $t->timestamp('confirmed')->nullable(); $t->timestamps();
        });
        Schema::create('stores', function ($t) { $t->id(); $t->string('name')->nullable(); $t->timestamps(); });
        Schema::create('disbursement_withdrawal_methods', function ($t) {
            $t->id(); $t->integer('delivery_man_id')->nullable(); $t->integer('withdrawal_method_id')->nullable();
            $t->string('method_name')->nullable(); $t->text('method_fields')->nullable(); $t->boolean('is_default')->default(0); $t->timestamps();
        });
        Schema::create('d_m_vehicles', function ($t) { $t->id(); $t->timestamps(); });
        Schema::create('translations', function ($t) { $t->id(); $t->string('translationable_type'); $t->integer('translationable_id'); $t->string('locale'); $t->string('key'); $t->text('value')->nullable(); $t->timestamps(); });
        Schema::create('cache', function ($t) { $t->string('key')->primary(); $t->mediumText('value'); $t->integer('expiration'); });
        Schema::create('d_m_reviews', function ($t) { $t->id(); $t->integer('delivery_man_id'); $t->integer('rating')->default(5); $t->timestamps(); });
        Schema::create('user_infos', function ($t) { $t->id(); $t->integer('deliveryman_id')->nullable(); $t->string('f_name')->nullable(); $t->timestamps(); });

        DB::table('delivery_men')->insert([
            ['id' => 1, 'phone' => '+201000000001', 'email' => null, 'password' => Hash::make('old-1'), 'auth_token' => 'tok1'],
            ['id' => 2, 'phone' => '+201000000002', 'email' => null, 'password' => Hash::make('old-2'), 'auth_token' => 'tok2'],
        ]);
        DB::table('stores')->insert(['id' => 1, 'name' => 'S']);
    }

    private function rider(int $id): DeliveryMan
    {
        return DeliveryMan::withoutGlobalScopes()->find($id);
    }

    private function hit(string $method, array $input, ?int $dmId = null, string $controller = DeliverymanController::class)
    {
        $request = Request::create('/', 'POST', $input);
        if ($dmId) {
            $request->merge(['dm' => $this->rider($dmId)]);
        }
        return app($controller)->$method($request);
    }

    private function reset(string $phone, string $token)
    {
        return $this->hit('reset_password_submit', [
            'phone' => $phone, 'reset_token' => $token,
            'password' => self::NEW_PASSWORD, 'confirm_password' => self::NEW_PASSWORD,
        ], null, DMPasswordResetController::class);
    }

    public function test_a_customer_reset_code_cannot_change_rider_passwords(): void
    {
        // What the customer flow writes: keyed by phone, no email.
        DB::table('password_resets')->insert(['phone' => '+201099999999', 'token' => '123456', 'created_by' => 'user', 'created_at' => now()]);

        $response = $this->reset('+201000000001', '123456');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertTrue(Hash::check('old-1', $this->rider(1)->password));
        $this->assertTrue(Hash::check('old-2', $this->rider(2)->password));
    }

    public function test_a_rider_reset_changes_only_that_rider_and_signs_them_out(): void
    {
        DB::table('password_resets')->insert(['phone' => '+201000000001', 'token' => '654321', 'created_by' => 'deliveryman', 'created_at' => now()]);

        $this->assertSame(400, $this->reset('+201000000002', '654321')->getStatusCode(), 'another rider\'s code');
        $this->assertSame(200, $this->reset('+201000000001', '654321')->getStatusCode());

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->rider(1)->password));
        $this->assertNull($this->rider(1)->auth_token);
        $this->assertTrue(Hash::check('old-2', $this->rider(2)->password));
        $this->assertSame(0, DB::table('password_resets')->count());
    }

    public function test_delivered_cannot_skip_pickup(): void
    {
        DB::table('orders')->insert(['id' => 10, 'store_id' => 1, 'delivery_man_id' => 1, 'order_status' => 'accepted']);

        $response = $this->hit('update_order_status', ['order_id' => 10, 'status' => 'delivered'], 1);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('accepted', DB::table('orders')->value('order_status'));
    }

    public function test_only_parcels_can_be_marked_handover_by_the_rider(): void
    {
        DB::table('orders')->insert(['id' => 11, 'store_id' => 1, 'delivery_man_id' => 1, 'order_status' => 'accepted']);

        $this->assertSame(403, $this->hit('update_order_status', ['order_id' => 11, 'status' => 'handover'], 1)->getStatusCode());
    }

    public function test_a_retry_of_the_current_status_is_accepted_without_side_effects(): void
    {
        DB::table('orders')->insert(['id' => 12, 'store_id' => 1, 'delivery_man_id' => 1, 'order_status' => 'picked_up']);

        $this->assertSame(200, $this->hit('update_order_status', ['order_id' => 12, 'status' => 'picked_up'], 1)->getStatusCode());
    }

    public function test_a_rider_cannot_touch_another_riders_payout_method(): void
    {
        DB::table('disbursement_withdrawal_methods')->insert(['id' => 5, 'delivery_man_id' => 1, 'method_name' => 'bank']);

        $this->assertSame(404, $this->hit('disbursement_withdrawal_method_delete', ['id' => 5], 2)->getStatusCode());
        $this->assertSame(404, $this->hit('disbursement_withdrawal_method_default', ['id' => 5, 'is_default' => 1], 2)->getStatusCode());
        $this->assertSame(1, DB::table('disbursement_withdrawal_methods')->count());

        $this->assertSame(200, $this->hit('disbursement_withdrawal_method_delete', ['id' => 5], 1)->getStatusCode());
        $this->assertSame(0, DB::table('disbursement_withdrawal_methods')->count());
    }

    public function test_changing_the_password_needs_the_current_one(): void
    {
        $base = ['f_name' => 'A', 'l_name' => 'B', 'password' => self::NEW_PASSWORD];

        $this->assertSame(403, $this->hit('update_profile', $base, 1)->getStatusCode(), 'missing');
        $this->assertSame(403, $this->hit('update_profile', $base + ['current_password' => 'wrong'], 1)->getStatusCode(), 'wrong');
        $this->assertTrue(Hash::check('old-1', $this->rider(1)->password));

        $this->assertSame(200, $this->hit('update_profile', $base + ['current_password' => 'old-1'], 1)->getStatusCode());
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $this->rider(1)->password));
    }

    public function test_profile_saves_without_an_email(): void
    {
        $this->assertSame(200, $this->hit('update_profile', ['f_name' => 'A', 'l_name' => 'B'], 1)->getStatusCode());
    }
}
