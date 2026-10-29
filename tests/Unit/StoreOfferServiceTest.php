<?php

namespace Tests\Unit;

use App\Services\StoreOfferService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class StoreOfferServiceTest extends TestCase
{
    private function at(string $local): Carbon
    {
        return Carbon::parse($local, 'Africa/Cairo');
    }

    public function test_dates_and_daily_hours(): void
    {
        $open = fn ($at) => StoreOfferService::windowOpen('2026-11-01', '2026-11-30', '15:00:00', '17:59:59', $this->at($at));
        $this->assertTrue($open('2026-11-10 15:00:00'));
        $this->assertTrue($open('2026-11-10 17:59:00'));
        $this->assertFalse($open('2026-11-10 18:00:00'));
        $this->assertFalse($open('2026-10-31 16:00:00'));
        $this->assertTrue($open('2026-11-30 16:00:00'));
        $this->assertFalse($open('2026-12-01 16:00:00'));
    }

    public function test_hours_may_run_past_midnight(): void
    {
        $late = fn ($at) => StoreOfferService::windowOpen('2026-11-01', '2026-11-30', '22:00', '02:00', $this->at($at));
        $this->assertTrue($late('2026-11-10 23:30:00'));
        $this->assertTrue($late('2026-11-11 01:59:00'));
        $this->assertFalse($late('2026-11-10 12:00:00'));
    }

    public function test_amount_off_needs_the_minimum_and_never_beats_the_basket_or_budget(): void
    {
        $this->assertEquals(0, StoreOfferService::amountOff(50, 200, 199.99));
        $this->assertEquals(50, StoreOfferService::amountOff(50, 200, 200));
        $this->assertEquals(30, StoreOfferService::amountOff(50, 0, 30));
        $this->assertEquals(20, StoreOfferService::amountOff(50, 200, 300, 20));
        $this->assertEquals(0, StoreOfferService::amountOff(50, 200, 300, 0));
    }
}
