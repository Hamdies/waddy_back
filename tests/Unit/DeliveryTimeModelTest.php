<?php

namespace Tests\Unit;

use App\Services\DeliveryTimeModel;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryTimeModelTest extends TestCase
{
    public function test_peak_hours_are_cairo_lunch_and_dinner(): void
    {
        $this->assertTrue(DeliveryTimeModel::isPeak(Carbon::parse('2026-10-26 13:00', 'Africa/Cairo')));
        $this->assertTrue(DeliveryTimeModel::isPeak(Carbon::parse('2026-10-26 20:30', 'Africa/Cairo')));
        $this->assertFalse(DeliveryTimeModel::isPeak(Carbon::parse('2026-10-26 10:00', 'Africa/Cairo')));
        // 18:00 UTC is 21:00 Cairo (summer time)
        $this->assertTrue(DeliveryTimeModel::isPeak(Carbon::parse('2026-07-01 18:00', 'UTC')));
    }

    public function test_median(): void
    {
        $this->assertSame(3.0, DeliveryTimeModel::median([1, 3, 9]));
        $this->assertSame(2.5, DeliveryTimeModel::median([1, 2, 3, 40]));
    }
}
