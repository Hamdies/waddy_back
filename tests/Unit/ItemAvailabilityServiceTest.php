<?php

namespace Tests\Unit;

use App\Services\ItemAvailabilityService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ItemAvailabilityServiceTest extends TestCase
{
    // Tuesday 27 Oct 2026, 17:40 in Cairo. Egypt is on summer time (UTC+3)
    // until Thursday 29 Oct, UTC+2 after: the local day decides, not UTC.
    private function now(): Carbon
    {
        return Carbon::parse('2026-10-27 14:40:00', 'UTC');
    }

    public function test_two_hours(): void
    {
        $this->assertEquals('2026-10-27 16:40', ItemAvailabilityService::until('2h', $this->now(), [])->format('Y-m-d H:i'));
    }

    public function test_until_turned_on_has_no_time(): void
    {
        $this->assertNull(ItemAvailabilityService::until('manual', $this->now(), []));
        $this->assertNull(ItemAvailabilityService::until('anything', $this->now(), []));
    }

    public function test_rest_of_today_is_the_next_opening(): void
    {
        // Opens 10:00 Wednesday (day 3) local = 07:00 UTC (summer time).
        $until = ItemAvailabilityService::until('today', $this->now(), [3 => '10:00:00', 4 => '09:00:00']);
        $this->assertEquals('2026-10-28 07:00', $until->setTimezone('UTC')->format('Y-m-d H:i'));
    }

    public function test_rest_of_today_skips_closed_days(): void
    {
        // Only opens Friday (day 5) at 12:00 local = 10:00 UTC (winter time).
        $until = ItemAvailabilityService::until('today', $this->now(), [5 => '12:00:00']);
        $this->assertEquals('2026-10-30 10:00', $until->setTimezone('UTC')->format('Y-m-d H:i'));
    }

    public function test_rest_of_today_without_schedule(): void
    {
        $until = ItemAvailabilityService::until('today', $this->now(), []);
        $this->assertEquals('2026-10-28 03:00', $until->setTimezone('UTC')->format('Y-m-d H:i'));
    }
}
