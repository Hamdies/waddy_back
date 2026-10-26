<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\OrderRescueService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OrderRescueServiceTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-26 13:00:00');
    }

    private function order(array $attrs): Order
    {
        $o = new Order();
        $o->forceFill(array_merge(['order_type' => 'delivery', 'payment_method' => 'cash_on_delivery', 'scheduled' => 0], $attrs));
        return $o;
    }

    public function test_unconfirmed_cash_order_under_store_model_is_a_store_problem(): void
    {
        config(['order_confirmation_model' => 'store']);
        $d = OrderRescueService::diagnose($this->order(['order_status' => 'pending', 'pending' => '2026-10-26 12:54:00']), $this->now, false);
        $this->assertSame(['problem' => 'store', 'minutes' => 6], $d);
    }

    public function test_under_deliveryman_model_a_pending_cash_order_waits_for_a_rider(): void
    {
        config(['order_confirmation_model' => 'deliveryman']);
        $d = OrderRescueService::diagnose($this->order(['order_status' => 'pending', 'pending' => '2026-10-26 12:50:00']), $this->now, false);
        $this->assertSame('rider', $d['problem']);
        $this->assertSame(10, $d['minutes']);
    }

    public function test_confirmed_without_rider_counts_from_confirmation(): void
    {
        config(['order_confirmation_model' => 'store']);
        $d = OrderRescueService::diagnose($this->order(['order_status' => 'processing', 'pending' => '2026-10-26 12:00:00', 'confirmed' => '2026-10-26 12:51:00']), $this->now, false);
        $this->assertSame(['problem' => 'rider', 'minutes' => 9], $d);
    }

    public function test_not_a_problem(): void
    {
        config(['order_confirmation_model' => 'store']);
        // rider assigned
        $this->assertNull(OrderRescueService::diagnose($this->order(['order_status' => 'processing', 'delivery_man_id' => 5, 'confirmed' => '2026-10-26 12:00:00']), $this->now, false));
        // store delivers itself
        $this->assertNull(OrderRescueService::diagnose($this->order(['order_status' => 'handover', 'confirmed' => '2026-10-26 12:00:00']), $this->now, true));
        // take-away needs no rider
        $this->assertNull(OrderRescueService::diagnose($this->order(['order_type' => 'take_away', 'order_status' => 'confirmed', 'confirmed' => '2026-10-26 12:00:00']), $this->now, false));
        // scheduled for later
        $this->assertNull(OrderRescueService::diagnose($this->order(['order_status' => 'pending', 'pending' => '2026-10-26 12:00:00', 'scheduled' => 1, 'schedule_at' => '2026-10-26 18:00:00']), $this->now, false));
    }

    public function test_steps_fire_only_in_their_window(): void
    {
        $this->assertFalse(OrderRescueService::due(4, 5));
        $this->assertTrue(OrderRescueService::due(5, 5));
        $this->assertTrue(OrderRescueService::due(34, 5));
        $this->assertFalse(OrderRescueService::due(249, 15));
    }
}
