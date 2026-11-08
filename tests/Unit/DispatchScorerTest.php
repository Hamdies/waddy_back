<?php

namespace Tests\Unit;

use App\Services\DispatchScorer;
use PHPUnit\Framework\TestCase;

/** The dispatch cost model, without a database. 0.001° of latitude ≈ 111 m. */
class DispatchScorerTest extends TestCase
{
    private const STORE = [30.0, 31.0];

    private function score(array $rider, array $order = [], array $opts = []): ?array
    {
        return DispatchScorer::score($rider, $order + ['pickup' => self::STORE, 'drop' => [30.02, 31.0]], $opts);
    }

    public function test_a_close_rider_who_must_deliver_first_is_slower_than_a_free_one(): void
    {
        // 200 m from the store, but carrying an order 3 km the other way.
        $busy = $this->score(['at' => [30.002, 31.0], 'stops' => [['point' => [29.97, 31.0], 'drop' => true]]]);
        // 1 km away with nothing to do.
        $free = $this->score(['at' => [30.009, 31.0]]);
        $this->assertFalse($busy['stack']);
        $this->assertLessThan($busy['pickup_minutes'], $free['pickup_minutes']);
        $this->assertEqualsWithDelta(3.0, $free['pickup_minutes'], 0.2);
        $this->assertLessThan($busy['cost'], $free['cost']);
    }

    public function test_a_rider_passing_the_store_only_pays_the_detour(): void
    {
        // Collecting from the same store, customer next door to the new one.
        $rider = ['at' => [30.003, 31.0], 'stops' => [['point' => self::STORE, 'drop' => false], ['point' => [30.021, 31.0], 'drop' => true]]];
        $stacked = $this->score($rider);
        $this->assertTrue($stacked['stack']);
        // One more handover (3 min) and a few seconds of riding.
        $this->assertEqualsWithDelta(3.0, $stacked['detour'], 0.5);
        // A free rider a little closer still costs more: a whole trip.
        $this->assertLessThan($this->score(['at' => [30.002, 31.0]])['cost'], $stacked['cost']);
    }

    public function test_stacking_never_makes_a_held_customer_wait_too_long(): void
    {
        // The held customer is 1 km north; the new one is 4 km south.
        $rider = ['at' => [30.0, 31.0], 'stops' => [['point' => [30.009, 31.0], 'drop' => true]]];
        $result = $this->score($rider, ['drop' => [29.964, 31.0]]);
        $this->assertFalse($result['stack']);
    }

    public function test_the_rider_who_arrives_as_the_food_is_ready_beats_one_who_would_wait(): void
    {
        $order = ['ready_in' => 12];
        $early = $this->score(['at' => [30.002, 31.0]], $order);   // there in under a minute, waits 11
        $onTime = $this->score(['at' => [30.035, 31.0]], $order);  // about 12 min away
        $this->assertLessThan($early['cost'], $onTime['cost']);
    }

    public function test_food_that_is_ready_goes_to_the_nearest(): void
    {
        $this->assertLessThan(
            $this->score(['at' => [30.02, 31.0]])['cost'],
            $this->score(['at' => [30.005, 31.0]])['cost']
        );
    }

    public function test_equally_close_riders_share_the_work(): void
    {
        $busyDay = $this->score(['at' => [30.005, 31.0], 'today' => 8, 'today_median' => 4]);
        $quietDay = $this->score(['at' => [30.005, 31.0], 'today' => 2, 'today_median' => 4]);
        $this->assertLessThan($busyDay['cost'], $quietDay['cost']);
    }

    public function test_an_old_or_vague_fix_counts_against_a_rider(): void
    {
        $fresh = $this->score(['at' => [30.005, 31.0], 'age_seconds' => 5, 'accuracy' => 10]);
        $this->assertLessThan($this->score(['at' => [30.005, 31.0], 'age_seconds' => 180])['cost'], $fresh['cost']);
        $this->assertLessThan($this->score(['at' => [30.005, 31.0], 'accuracy' => 400])['cost'], $fresh['cost']);
    }

    public function test_a_moving_rider_is_where_they_are_heading(): void
    {
        // 1 km north of the store, riding south at 8 m/s, fix 30 s old: ~360 m closer now.
        $at = DispatchScorer::projected(['at' => [30.009, 31.0], 'heading' => 180, 'speed' => 8, 'age_seconds' => 30]);
        $this->assertEqualsWithDelta(30.009 - 360 / 111320, $at[0], 0.0002);
        // Standing still: no change.
        $this->assertSame([30.009, 31.0], DispatchScorer::projected(['at' => [30.009, 31.0], 'heading' => 180, 'speed' => 0.5]));
    }

    public function test_a_vehicle_does_not_get_trips_beyond_its_range(): void
    {
        $this->assertNull($this->score(['at' => [30.001, 31.0], 'max_km' => 1.5]));
        $this->assertNotNull($this->score(['at' => [30.001, 31.0], 'max_km' => 5]));
    }

    public function test_riders_who_often_refuse_count_a_little_against(): void
    {
        $reliable = $this->score(['at' => [30.005, 31.0], 'accept_rate' => 1.0]);
        $this->assertLessThan($this->score(['at' => [30.005, 31.0], 'accept_rate' => 0.2])['cost'], $reliable['cost']);
    }

    public function test_matching_gives_the_rider_to_the_order_that_needs_them_most(): void
    {
        // Rider 1 is best for both; order 20 has no good alternative.
        $match = DispatchScorer::assign([
            10 => [1 => 2.0, 2 => 3.0],
            20 => [1 => 2.5, 2 => 9.0],
        ]);
        $this->assertSame([20 => 1, 10 => 2], $match);
    }

    public function test_matching_never_gives_one_rider_two_orders_in_a_round(): void
    {
        $match = DispatchScorer::assign([10 => [1 => 1.0], 20 => [1 => 2.0]]);
        $this->assertSame([10 => 1], $match);
    }
}
