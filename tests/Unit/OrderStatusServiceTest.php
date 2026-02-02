<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Order;
use App\Models\DeliveryMan;
use App\Services\OrderStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class OrderStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that valid status transitions are allowed
     */
    public function test_validate_valid_status_transitions(): void
    {
        // Test valid transitions
        $this->assertTrue(OrderStatusService::validateTransition('pending', 'confirmed'));
        $this->assertTrue(OrderStatusService::validateTransition('pending', 'canceled'));
        $this->assertTrue(OrderStatusService::validateTransition('confirmed', 'accepted'));
        $this->assertTrue(OrderStatusService::validateTransition('picked_up', 'delivered'));
        $this->assertTrue(OrderStatusService::validateTransition('delivered', 'refund_requested'));
    }

    /**
     * Test that invalid status transitions are blocked
     */
    public function test_validate_invalid_status_transitions(): void
    {
        // Test invalid transitions
        $this->assertFalse(OrderStatusService::validateTransition('pending', 'delivered'));
        $this->assertFalse(OrderStatusService::validateTransition('canceled', 'pending'));
        $this->assertFalse(OrderStatusService::validateTransition('delivered', 'pending'));
        $this->assertFalse(OrderStatusService::validateTransition('refunded', 'delivered'));
    }

    /**
     * Test that unknown status is handled properly
     */
    public function test_validate_unknown_status(): void
    {
        $this->assertFalse(OrderStatusService::validateTransition('unknown_status', 'pending'));
    }

    /**
     * Test OTP verification rate limiting
     */
    public function test_otp_rate_limiting(): void
    {
        // Create a mock order
        $order = new Order();
        $order->id = 12345;
        $order->otp = '1234';
        
        // Clear any existing cache
        Cache::forget("otp_attempts_{$order->id}");
        
        // First few wrong attempts should return remaining attempts
        for ($i = 0; $i < 4; $i++) {
            $result = OrderStatusService::verifyOTP($order, 'wrong');
            $this->assertFalse($result['success']);
            $this->assertStringContainsString('attempts remaining', $result['message']);
        }
        
        // 5th wrong attempt should trigger rate limit on next attempt
        $result = OrderStatusService::verifyOTP($order, 'wrong');
        $this->assertFalse($result['success']);
        
        // 6th attempt should be rate limited
        $result = OrderStatusService::verifyOTP($order, 'wrong');
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('too_many_otp_attempts', $result['message']);
        
        // Clean up
        Cache::forget("otp_attempts_{$order->id}");
    }

    /**
     * Test correct OTP clears rate limit
     */
    public function test_correct_otp_clears_rate_limit(): void
    {
        $order = new Order();
        $order->id = 67890;
        $order->otp = '5678';
        
        // Set up some failed attempts
        Cache::put("otp_attempts_{$order->id}", 3, now()->addMinutes(15));
        
        // Correct OTP should work and clear attempts
        $result = OrderStatusService::verifyOTP($order, '5678');
        $this->assertTrue($result['success']);
        
        // Cache should be cleared
        $this->assertEquals(0, Cache::get("otp_attempts_{$order->id}", 0));
    }

    /**
     * Test getting available transitions for an order
     */
    public function test_get_available_transitions(): void
    {
        $order = new Order();
        
        // Test pending order
        $order->order_status = 'pending';
        $transitions = OrderStatusService::getAvailableTransitions($order);
        $this->assertContains('confirmed', $transitions);
        $this->assertContains('canceled', $transitions);
        $this->assertNotContains('delivered', $transitions);
        
        // Test delivered order (only refund_requested allowed)
        $order->order_status = 'delivered';
        $transitions = OrderStatusService::getAvailableTransitions($order);
        $this->assertContains('refund_requested', $transitions);
        $this->assertCount(1, $transitions);
        
        // Test refunded order (no transitions allowed)
        $order->order_status = 'refunded';
        $transitions = OrderStatusService::getAvailableTransitions($order);
        $this->assertEmpty($transitions);
    }
}
