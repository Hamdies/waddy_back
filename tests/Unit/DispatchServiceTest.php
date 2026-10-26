<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\DispatchService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DispatchServiceTest extends TestCase
{
    public function test_nearest_first_and_free_beats_busy_at_similar_distance(): void
    {
        $ranked = DispatchService::rank([
            ['id' => 1, 'meters' => 2200, 'current_orders' => 0],
            ['id' => 2, 'meters' => 300, 'current_orders' => 1],
            ['id' => 3, 'meters' => 420, 'current_orders' => 0],
            ['id' => 4, 'meters' => 900, 'current_orders' => 0],
        ])->pluck('id')->all();
        // 300 m busy and 420 m free are both within the first 500 m: free wins.
        $this->assertSame([3, 2, 4, 1], $ranked);
    }

    public function test_first_wave_waits_for_a_long_cook(): void
    {
        $now = Carbon::parse('2026-10-26 13:00:00');
        $order = new Order();
        $order->forceFill(['order_status' => 'processing', 'processing' => '2026-10-26 12:55:00', 'processing_time' => 40]);
        // ready 13:35, lead 15 -> offer at 13:20
        $this->assertSame('2026-10-26 13:20:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));

        $order->processing_time = 20; // ready 13:15 -> offer now
        $this->assertSame('2026-10-26 13:00:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));

        $order->order_status = 'pending'; // a rider is needed to confirm: now
        $this->assertSame('2026-10-26 13:00:00', DispatchService::firstWaveAt($order, $now)->format('Y-m-d H:i:s'));
    }
}
