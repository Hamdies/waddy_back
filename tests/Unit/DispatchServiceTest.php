<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DispatchServiceTest extends TestCase
{
    public function test_a_close_rider_who_must_deliver_first_is_slower_than_a_free_one(): void
    {
        $store = [29.96, 31.25];
        // 200 m from the store, but carrying an order about 3 km away.
        $busy = DispatchService::minutesToPickup([29.962, 31.25], $store, [[29.99, 31.25]]);
        // 1 km away with nothing to do.
        $free = DispatchService::minutesToPickup([29.97, 31.25], $store);
        $this->assertLessThan($busy, $free);
        $this->assertEqualsWithDelta(3.3, $free, 0.2);
        $this->assertEqualsWithDelta(22, $busy, 1); // there, 3 min handing over, and back
    }

    public function test_soonest_first_then_stacking_then_the_fairest_share(): void
    {
        $ranked = DispatchService::rank([
            ['id' => 1, 'minutes' => 9.0, 'meters' => 100, 'today' => 0],
            ['id' => 2, 'minutes' => 3.5, 'meters' => 900, 'today' => 6],
            ['id' => 3, 'minutes' => 2.5, 'meters' => 700, 'today' => 1], // same 2 min band as 2
            ['id' => 4, 'minutes' => 3.9, 'meters' => 800, 'today' => 9, 'stack' => true],
        ])->pluck('id')->all();
        $this->assertSame([4, 3, 2, 1], $ranked);
    }

    public function test_a_parcel_is_collected_from_its_sender(): void
    {
        $order = new Order();
        $order->forceFill(['order_type' => 'parcel', 'delivery_address' => json_encode(['latitude' => '29.96', 'longitude' => '31.25'])]);
        $this->assertSame([29.96, 31.25], DispatchService::pickupPoint($order));

        $order->delivery_address = json_encode(['latitude' => null, 'longitude' => null]);
        $this->assertNull(DispatchService::pickupPoint($order)); // the zone broadcast takes over
    }

    public function test_first_wave_waits_for_a_long_cook(): void
    {
        $now = Carbon::parse('2026-10-26 13:00:00');
        $order = new Order();
        $order->forceFill(['order_status' => 'processing', 'processing' => '2026-10-26 12:55:00', 'processing_time' => 40]);
        // ready 13:35, lead 10 -> offer at 13:25
        $this->assertSame('2026-10-26 13:25:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));

        $order->processing_time = 15; // ready 13:10 -> offer now
        $this->assertSame('2026-10-26 13:00:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));

        $order->order_status = 'pending'; // a rider is needed to confirm: now
        $this->assertSame('2026-10-26 13:00:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));
    }
}
