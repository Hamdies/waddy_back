<?php

namespace Tests\Unit;

use App\Services\StoreDailySummaryService;
use Tests\TestCase;

class StoreDailySummaryServiceTest extends TestCase
{
    private array $today = ['orders' => 12, 'canceled' => 1, 'earnings' => 1500.0, 'prep_minutes' => 14];

    public function test_english_summary_has_the_numbers_and_trend(): void
    {
        $m = StoreDailySummaryService::message($this->today, ['orders' => 9, 'canceled' => 0, 'earnings' => 0, 'prep_minutes' => null], 'en');
        $this->assertSame("Today's summary", $m['title']);
        $this->assertStringContainsString('12 orders (+3 vs yesterday)', $m['description']);
        $this->assertStringContainsString('1 canceled', $m['description']);
        $this->assertStringContainsString('avg prep 14 min', $m['description']);
    }

    public function test_arabic_and_quiet_parts_left_out(): void
    {
        $m = StoreDailySummaryService::message(['orders' => 4, 'canceled' => 0, 'earnings' => 0, 'prep_minutes' => null], ['orders' => 4, 'canceled' => 0, 'earnings' => 0, 'prep_minutes' => null], 'ar');
        $this->assertSame('ملخص اليوم', $m['title']);
        $this->assertStringContainsString('4 طلب (مثل أمس)', $m['description']);
        $this->assertStringNotContainsString('ملغي', $m['description']);
        $this->assertStringNotContainsString('التجهيز', $m['description']);
    }
}
