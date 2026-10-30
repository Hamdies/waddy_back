<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\StorePushService;
use Tests\TestCase;

/**
 * When the store's other phones are told to stop ringing (SB-1).
 */
class StorePushServiceTest extends TestCase
{
    private function order(string $status, string $payment = 'cash_on_delivery', string $type = 'delivery'): Order
    {
        $order = new Order();
        $order->order_status = $status;
        $order->payment_method = $payment;
        $order->order_type = $type;
        return $order;
    }

    public function test_starting_or_ending_an_order_is_handled(): void
    {
        foreach (['processing', 'handover', 'picked_up', 'delivered', 'canceled', 'failed', 'refunded'] as $status) {
            $this->assertTrue(StorePushService::isHandled($this->order($status), 'confirmed'), $status);
        }
    }

    public function test_no_change_is_not_handled(): void
    {
        $this->assertFalse(StorePushService::isHandled($this->order('processing'), 'processing'));
    }

    public function test_store_confirming_cash_under_store_model_is_handled(): void
    {
        config(['order_confirmation_model' => 'store']);
        $this->assertTrue(StorePushService::isHandled($this->order('confirmed'), 'pending'));
    }

    public function test_rider_confirming_cash_under_deliveryman_model_is_not_handled(): void
    {
        config(['order_confirmation_model' => 'deliveryman']);
        $this->assertFalse(StorePushService::isHandled($this->order('confirmed'), 'pending'));
    }

    public function test_take_away_confirmed_is_handled_under_any_model(): void
    {
        config(['order_confirmation_model' => 'deliveryman']);
        $this->assertTrue(StorePushService::isHandled($this->order('confirmed', 'cash_on_delivery', 'take_away'), 'pending'));
    }

    public function test_payment_arriving_starts_the_alert_and_is_not_handled(): void
    {
        config(['order_confirmation_model' => 'store']);
        $this->assertFalse(StorePushService::isHandled($this->order('confirmed', 'digital_payment'), 'pending'));
    }

    public function test_rider_accepting_is_not_handled(): void
    {
        $this->assertFalse(StorePushService::isHandled($this->order('accepted'), 'confirmed'));
    }

    // ---------------------------------------------------------------- MA-3

    public function test_push_switches_default_on(): void
    {
        $store = new \App\Models\Store();
        $this->assertSame(StorePushService::PUSH_DEFAULTS, StorePushService::prefs($store));
        $this->assertTrue(StorePushService::wants($store, 'reviews'));
    }

    public function test_switched_off_kind_is_not_wanted_but_new_orders_are(): void
    {
        $store = new \App\Models\Store();
        $store->push_prefs = ['reviews' => false, 'bogus' => false];
        $this->assertFalse(StorePushService::wants($store, 'reviews'));
        $this->assertTrue(StorePushService::wants($store, 'rider'));
        $this->assertTrue(StorePushService::wants($store, null));
        $this->assertArrayNotHasKey('bogus', StorePushService::prefs($store));
    }

    public function test_store_hears_of_a_cancel_it_did_not_make(): void
    {
        $order = $this->order('canceled');
        $order->canceled_by = 'customer';
        $this->assertTrue(StorePushService::tellsStoreOfCancel($order, 'processing'));
        $order->canceled_by = 'store';
        $this->assertFalse(StorePushService::tellsStoreOfCancel($order, 'processing'));
    }

    public function test_no_cancel_push_for_orders_the_store_never_saw_or_finished(): void
    {
        $order = $this->order('canceled');
        $order->canceled_by = 'admin';
        $this->assertFalse(StorePushService::tellsStoreOfCancel($order, 'delivered'));
        $this->assertFalse(StorePushService::tellsStoreOfCancel($order, null));
    }
}
