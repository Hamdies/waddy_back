<?php

namespace Tests\Unit;

use App\CentralLogics\CouponLogic;
use App\Models\Coupon;
use Tests\TestCase;

class CouponTotalLimitTest extends TestCase
{
    private function coupon(?int $totalLimit, int $used): Coupon
    {
        return new Coupon([
            'code' => 'WELCOME20', 'coupon_type' => 'default', 'created_by' => 'vendor', 'store_id' => 7, 'module_id' => 1,
            'start_date' => now()->subDay()->toDateString(), 'expire_date' => now()->addDays(5)->toDateString(),
            'limit' => null, 'total_limit' => $totalLimit, 'total_uses' => $used, 'customer_id' => '["all"]',
        ]);
    }

    public function test_coupon_stops_when_total_uses_reach_the_cap(): void
    {
        $this->assertSame(406, CouponLogic::is_valid_for_guest($this->coupon(100, 100), 7, 1));
        $this->assertSame(406, CouponLogic::is_valide($this->coupon(100, 101), 5, 7, 1));
    }

    public function test_coupon_under_the_cap_or_without_one_still_works(): void
    {
        $this->assertSame(200, CouponLogic::is_valid_for_guest($this->coupon(100, 99), 7, 1));
        $this->assertSame(200, CouponLogic::is_valid_for_guest($this->coupon(null, 5000), 7, 1));
    }
}
