<?php

namespace Tests\Unit;

use App\Services\LateApologyService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;

class LateApologyServiceTest extends TestCase
{
    public function test_minutes_late(): void
    {
        $promised = Carbon::parse('2026-10-26 13:00:00');
        $this->assertSame(25, LateApologyService::minutesLate($promised, Carbon::parse('2026-10-26 13:25:30')));
        $this->assertSame(-5, LateApologyService::minutesLate($promised, Carbon::parse('2026-10-26 12:55:00')));
        $this->assertNull(LateApologyService::minutesLate(null, Carbon::parse('2026-10-26 13:25:00')));
    }
}
