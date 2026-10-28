<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\StoreSalesReportService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StoreSalesReportServiceTest extends TestCase
{
    // Tuesday 27 Oct 2026, 17:40 in Cairo (UTC+3 until 29 Oct).
    private function now(): Carbon
    {
        return Carbon::parse('2026-10-27 14:40:00', 'UTC');
    }

    public function test_today_runs_from_local_midnight_and_compares_with_yesterday_so_far(): void
    {
        [$from, $to, $prevFrom, $prevTo] = StoreSalesReportService::window('today', $this->now());
        $this->assertEquals('2026-10-27 00:00', $from->format('Y-m-d H:i'));
        $this->assertEquals('2026-10-27 17:40', $to->format('Y-m-d H:i'));
        $this->assertEquals('2026-10-26 00:00', $prevFrom->format('Y-m-d H:i'));
        $this->assertEquals('2026-10-26 17:40', $prevTo->format('Y-m-d H:i'));
    }

    public function test_week_is_seven_days_including_today(): void
    {
        [$from, , $prevFrom] = StoreSalesReportService::window('week', $this->now());
        $this->assertEquals('2026-10-21', $from->format('Y-m-d'));
        $this->assertEquals('2026-10-14', $prevFrom->format('Y-m-d'));
        $this->assertCount(7, StoreSalesReportService::buckets('week', $from));
    }

    public function test_today_has_an_hour_bucket_per_local_hour(): void
    {
        [$from] = StoreSalesReportService::window('today', $this->now());
        $buckets = StoreSalesReportService::buckets('today', $from);
        $this->assertCount(24, $buckets);
        // 22:30 UTC on the 26th is 01:30 on the 27th in Cairo.
        $this->assertArrayHasKey(StoreSalesReportService::bucketKey('today', '2026-10-26 22:30:00'), $buckets);
        $this->assertEquals('2026-10-27 01:00', StoreSalesReportService::bucketKey('today', '2026-10-26 22:30:00'));
    }

    public function test_prep_time_leaves_out_missing_and_runaway_stamps(): void
    {
        $o = fn ($p, $h) => (object) ['processing' => $p, 'handover' => $h];
        $mins = StoreSalesReportService::prepMinutes(collect([
            $o('2026-10-27 10:00:00', '2026-10-27 10:12:00'),
            $o('2026-10-27 11:00:00', '2026-10-27 11:18:00'),
            $o('2026-10-27 12:00:00', null),
            $o('2026-10-27 13:00:00', '2026-10-27 18:00:00'),
        ]));
        $this->assertEquals(15.0, $mins);
        $this->assertNull(StoreSalesReportService::prepMinutes(collect()));
    }

    public function test_health_splits_store_rejections_from_other_cancellations_and_counts_late(): void
    {
        $o = fn ($status, $by = 'none', $delivered = null, $promised = null) =>
            (object) ['order_status' => $status, 'canceled_by' => $by, 'delivered' => $delivered, 'promised_delivery_at' => $promised];
        $h = StoreSalesReportService::health(collect([
            $o('delivered', 'none', '2026-10-27 13:10:00', '2026-10-27 13:00:00'),
            $o('delivered', 'none', '2026-10-27 12:50:00', '2026-10-27 13:00:00'),
            $o('delivered'),
            $o('canceled', 'store'),
            $o('canceled', 'admin'),
            $o('refunded'),
        ]));
        $this->assertEquals(['placed' => 6, 'delivered' => 3, 'rejected' => 1, 'canceled' => 1, 'failed' => 0, 'refunded' => 1, 'late' => 1], $h);
    }
    public function test_split_reads_real_orders_whose_created_at_is_a_string(): void
    {
        // Order::getCreatedAtAttribute returns a string; Carbon calls on it were a 500.
        $order = fn ($id, $at) => (new Order())->setRawAttributes(['id' => $id, 'created_at' => $at]);
        [$from, , , $prevTo] = StoreSalesReportService::window('today', $this->now());
        [$current, $previous] = StoreSalesReportService::split(collect([
            $order(1, '2026-10-27 08:00:00'), // 11:00 Cairo today
            $order(2, '2026-10-26 20:59:00'), // 23:59 Cairo yesterday, after this time
            $order(3, '2026-10-26 10:00:00'), // 13:00 Cairo yesterday
        ]), $from, $prevTo);
        $this->assertIsString($order(9, '2026-10-27 08:00:00')->created_at);
        $this->assertEquals([1], $current->pluck('id')->values()->all());
        $this->assertEquals([3], $previous->pluck('id')->values()->all());
    }
}
