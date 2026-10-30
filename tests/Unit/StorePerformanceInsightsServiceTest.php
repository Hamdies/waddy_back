<?php

namespace Tests\Unit;

use App\Services\StorePerformanceInsightsService as Insights;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StorePerformanceInsightsServiceTest extends TestCase
{
    // Tuesday 27 Oct 2026, 17:40 in Cairo (UTC+3 until 29 Oct).
    private function now(): Carbon
    {
        return Carbon::parse('2026-10-27 14:40:00', 'UTC');
    }

    public function test_quarter_is_ninety_days_including_today_and_compares_with_the_ninety_before(): void
    {
        [$from, $to, $prevFrom, $prevTo] = Insights::window('quarter', $this->now());
        $this->assertEquals('2026-07-30 00:00', $from->format('Y-m-d H:i'));
        $this->assertEquals('2026-10-27 17:40', $to->format('Y-m-d H:i'));
        $this->assertEquals('2026-05-01', $prevFrom->format('Y-m-d'));
        $this->assertEquals('2026-07-29 17:40', $prevTo->format('Y-m-d H:i'));
    }

    public function test_a_week_has_each_weekday_once_and_a_month_some_twice_over(): void
    {
        [$from, $to] = Insights::window('week', $this->now());
        $this->assertEquals([1, 1, 1, 1, 1, 1, 1], Insights::dayCounts($from, $to));
        [$from, $to] = Insights::window('month', $this->now());
        $counts = Insights::dayCounts($from, $to);
        $this->assertEquals(30, array_sum($counts));
        $this->assertEquals(5, $counts[2]); // Tuesdays, today included
    }

    public function test_orders_land_on_the_local_weekday_and_hour(): void
    {
        date_default_timezone_set('UTC');
        // 22:30 UTC Monday is 01:30 Tuesday in Cairo.
        $grid = Insights::byDayHour(['2026-10-26 22:30:00', '2026-10-27 09:05:00', '2026-10-27 09:55:00']);
        $this->assertEquals(1, $grid[2][1]);
        $this->assertEquals(2, $grid[2][12]);
        $this->assertEquals(3, array_sum(array_map('array_sum', $grid)));
    }

    public function test_returning_means_twice_in_the_period_or_ordered_before_it(): void
    {
        $o = fn ($user, $amount) => (object) ['user_id' => $user, 'order_amount' => $amount];
        $c = Insights::customers(collect([
            $o(1, 100), $o(1, 100),            // twice now
            $o(2, 50),                          // once now, also before
            $o(3, 80),                          // new
            $o(4, 10), $o(4, 10), $o(4, 10), $o(4, 10), $o(4, 10),
        ]), [2 => true]);
        $this->assertEquals(4, $c['total']);
        $this->assertEquals(3, $c['returning']);
        $this->assertEquals(1, $c['new']);
        $this->assertEquals(8, $c['returning_orders']);
        $this->assertEquals(300.0, $c['returning_sales']);
        $this->assertEquals(80.0, $c['new_sales']);
        $this->assertEquals(0.75, $c['rate']);
        $this->assertEquals([2, 1, 0, 1], $c['frequency']);
    }

    public function test_no_customers_has_no_rate(): void
    {
        $this->assertNull(Insights::customers(collect(), [])['rate']);
    }

    public function test_ordered_with_counts_each_order_once_and_ranks_by_how_often(): void
    {
        $l = fn ($order, $item) => (object) ['order_id' => $order, 'item_id' => $item];
        $with = Insights::orderedWith(collect([
            $l(1, 10), $l(1, 20), $l(1, 20),
            $l(2, 10), $l(2, 30),
            $l(3, 10), $l(3, 20),
            $l(4, 40),
        ]));
        $this->assertEquals([20, 30], $with[10]);
        $this->assertEquals([10], $with[20]);
        $this->assertArrayNotHasKey(40, $with);
    }
}
