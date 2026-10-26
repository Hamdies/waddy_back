<?php

namespace Tests\Unit;

use App\Services\RiderArrivalService;
use PHPUnit\Framework\TestCase;

class RiderArrivalServiceTest extends TestCase
{
    public function test_distance(): void
    {
        // About 111 m per 0.001 degree of latitude.
        $this->assertEqualsWithDelta(111, RiderArrivalService::distanceMeters(30.0, 31.0, 30.001, 31.0), 1);
        $this->assertSame(0.0, RiderArrivalService::distanceMeters(30.0, 31.0, 30.0, 31.0));
    }

    public function test_minutes_away(): void
    {
        $this->assertSame(1, RiderArrivalService::minutesAway(50));
        $this->assertSame(2, RiderArrivalService::minutesAway(500));
    }
}
