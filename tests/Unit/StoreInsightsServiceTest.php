<?php

namespace Tests\Unit;

use App\Services\StoreInsightsService;
use PHPUnit\Framework\TestCase;

class StoreInsightsServiceTest extends TestCase
{
    public function test_median(): void
    {
        $this->assertSame(10, StoreInsightsService::median([10]));
        $this->assertSame(12, StoreInsightsService::median([20, 8, 12]));
        $this->assertSame(11, StoreInsightsService::median([8, 10, 12, 30]));
    }

    public function test_size_buckets(): void
    {
        $this->assertSame('small', StoreInsightsService::bucketFor(1));
        $this->assertSame('small', StoreInsightsService::bucketFor(2));
        $this->assertSame('medium', StoreInsightsService::bucketFor(3));
        $this->assertSame('medium', StoreInsightsService::bucketFor(5));
        $this->assertSame('large', StoreInsightsService::bucketFor(6));
        $this->assertSame('large', StoreInsightsService::bucketFor(40));
    }
}
