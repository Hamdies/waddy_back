<?php

namespace Tests\Unit;

use App\Models\DeliveryHistory;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/** SEC-4: impossible location jumps, without a database. */
class RiderLocationJumpTest extends TestCase
{
    private const CAIRO = [30.0444, 31.2357];
    private const ALEX = [31.2001, 29.9187];

    private function jump(array $from, array $to, int $secondsLater): bool
    {
        $at = Carbon::create(2026, 11, 10, 12);
        return DeliveryHistory::impossibleJump($from[0], $from[1], $at, $to[0], $to[1], $at->copy()->addSeconds($secondsLater));
    }

    public function test_cairo_to_alexandria_in_ten_seconds_is_rejected(): void
    {
        $this->assertTrue($this->jump(self::CAIRO, self::ALEX, 10));
    }

    public function test_a_motorbike_at_city_speed_is_accepted(): void
    {
        // ~110 m in 10 s ≈ 40 km/h.
        $this->assertFalse($this->jump(self::CAIRO, [30.0454, 31.2357], 10));
    }

    public function test_a_far_fix_after_the_window_is_accepted(): void
    {
        // Back online or out of a tunnel: the old fix is too old to judge by.
        $this->assertFalse($this->jump(self::CAIRO, self::ALEX, DeliveryHistory::JUMP_WINDOW_SECONDS));
    }

    public function test_no_previous_time_is_accepted(): void
    {
        $this->assertFalse(DeliveryHistory::impossibleJump(0, 0, null, self::ALEX[0], self::ALEX[1], Carbon::now()));
    }

    public function test_distance_is_about_right(): void
    {
        $this->assertEqualsWithDelta(180, DeliveryHistory::distanceKm(...self::CAIRO, ...self::ALEX), 5);
    }
}
