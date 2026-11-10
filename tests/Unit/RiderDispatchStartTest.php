<?php

namespace Tests\Unit;

use App\CentralLogics\Helpers;
use PHPUnit\Framework\TestCase;

/**
 * Q-4: guards c84f999. Rider dispatch never started for weeks because a failed
 * push earlier in send_order_notification skipped it.
 */
class RiderDispatchStartTest extends TestCase
{
    private function starts(array $order, bool $storeDelivers = false, string $model = 'deliveryman'): bool
    {
        $order += ['order_type' => 'delivery', 'order_status' => 'pending', 'payment_method' => 'cash_on_delivery', 'scheduled' => 0];
        return Helpers::riderDispatchStarts((object) $order, $storeDelivers, $model);
    }

    public function test_a_new_cash_delivery_starts_at_pending_under_the_rider_model(): void
    {
        $this->assertTrue($this->starts([]));
        $this->assertFalse($this->starts([], false, 'store'));
        $this->assertTrue($this->starts(['order_status' => 'confirmed'], false, 'store'));
    }

    public function test_a_paid_delivery_starts_at_confirmed_not_pending(): void
    {
        $this->assertFalse($this->starts(['payment_method' => 'digital_payment']));
        $this->assertTrue($this->starts(['payment_method' => 'digital_payment', 'order_status' => 'confirmed']));
    }

    public function test_no_riders_when_the_store_delivers_or_for_take_away_or_scheduled(): void
    {
        $this->assertFalse($this->starts([], true));
        $this->assertFalse($this->starts(['order_type' => 'take_away']));
        $this->assertFalse($this->starts(['scheduled' => 1]));
    }

    public function test_parcels_start_on_pending_and_confirmed(): void
    {
        $this->assertTrue($this->starts(['order_type' => 'parcel']));
        $this->assertTrue($this->starts(['order_type' => 'parcel', 'order_status' => 'confirmed', 'payment_method' => 'digital_payment']));
        $this->assertFalse($this->starts(['order_type' => 'parcel', 'order_status' => 'picked_up']));
    }

    public function test_later_statuses_never_restart_dispatch(): void
    {
        foreach (['accepted', 'processing', 'handover', 'picked_up', 'delivered', 'canceled'] as $status) {
            $this->assertFalse($this->starts(['order_status' => $status]), $status);
        }
    }

    public function test_dispatch_starts_before_any_push_can_fail(): void
    {
        $m = new \ReflectionMethod(Helpers::class, 'send_order_notification');
        $body = implode('', array_slice(file($m->getFileName()), $m->getStartLine(), $m->getEndLine() - $m->getStartLine()));
        $dispatch = strpos($body, 'self::startRiderDispatch(');
        $this->assertNotFalse($dispatch, 'send_order_notification no longer starts rider dispatch');
        foreach (['self::orderStep(', 'send_push_notif_to', 'StorePushService::send(', 'sendLiveActivityUpdate('] as $push) {
            $at = strpos($body, $push);
            if ($at !== false) {
                $this->assertLessThan($at, $dispatch, "$push runs before rider dispatch starts");
            }
        }
    }
}
