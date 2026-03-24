<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Level;
use App\Models\LevelPrize;
use App\Models\XpSetting;
use App\Models\XpTransaction;
use App\Models\UserLevelPrize;
use App\Services\XpService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class XpServiceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that calculateLevelFromXp returns correct level
     */
    public function test_calculate_level_from_xp(): void
    {
        // Create test levels
        Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);
        Level::create(['level_number' => 2, 'xp_required' => 100, 'name' => 'Bronze', 'status' => true]);
        Level::create(['level_number' => 3, 'xp_required' => 300, 'name' => 'Silver', 'status' => true]);
        Level::create(['level_number' => 4, 'xp_required' => 600, 'name' => 'Gold', 'status' => true]);

        // Test various XP amounts
        $this->assertEquals(1, XpService::calculateLevelFromXp(0));
        $this->assertEquals(1, XpService::calculateLevelFromXp(50));
        $this->assertEquals(1, XpService::calculateLevelFromXp(99));
        $this->assertEquals(2, XpService::calculateLevelFromXp(100));
        $this->assertEquals(2, XpService::calculateLevelFromXp(299));
        $this->assertEquals(3, XpService::calculateLevelFromXp(300));
        $this->assertEquals(4, XpService::calculateLevelFromXp(600));
        $this->assertEquals(4, XpService::calculateLevelFromXp(1000));
    }

    /**
     * Test that calculateLevelFromXp returns 0 when no levels exist
     */
    public function test_calculate_level_returns_zero_when_no_levels(): void
    {
        $this->assertEquals(0, XpService::calculateLevelFromXp(100));
    }

    /**
     * Test that addXp correctly updates user XP and level
     */
    public function test_add_xp_updates_user(): void
    {
        // Enable XP system
        XpSetting::create(['key' => 'leveling_enabled', 'value' => '1']);
        
        // Create test levels
        Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);
        Level::create(['level_number' => 2, 'xp_required' => 100, 'name' => 'Bronze', 'status' => true]);

        // Create user with 0 XP
        $user = User::factory()->create(['total_xp' => 0, 'level' => 0]);

        // Add XP
        $transaction = XpService::addXp($user, 'test', 50, 'manual');

        // Refresh user from database
        $user->refresh();

        // Verify XP was added
        $this->assertEquals(50, $user->total_xp);
        $this->assertEquals(1, $user->level); // Should be level 1 now
        $this->assertNotNull($transaction);
        $this->assertEquals(50, $transaction->xp_amount);
        $this->assertEquals(50, $transaction->balance_after);
    }

    /**
     * Test that addXp triggers level up and unlocks prizes
     */
    public function test_add_xp_triggers_level_up(): void
    {
        // Enable XP system
        XpSetting::create(['key' => 'leveling_enabled', 'value' => '1']);
        
        // Create test levels
        $level1 = Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);
        $level2 = Level::create(['level_number' => 2, 'xp_required' => 100, 'name' => 'Bronze', 'status' => true]);

        // Create prize for level 2
        $prize = LevelPrize::create([
            'level_id' => $level2->id,
            'prize_type' => 'free_delivery',
            'title' => 'Free Delivery',
            'value' => 1,
            'status' => true,
            'validity_days' => 30,
        ]);

        // Create user at level 1 with 90 XP
        $user = User::factory()->create(['total_xp' => 90, 'level' => 1]);

        // Add 20 XP to push over level 2 threshold
        XpService::addXp($user, 'test', 20, 'manual');

        $user->refresh();

        // Verify level up
        $this->assertEquals(110, $user->total_xp);
        $this->assertEquals(2, $user->level);

        // Verify prize was unlocked
        $userPrize = UserLevelPrize::where('user_id', $user->id)
            ->where('level_prize_id', $prize->id)
            ->first();
        
        $this->assertNotNull($userPrize);
        $this->assertEquals('unlocked', $userPrize->status);
    }

    /**
     * Test that duplicate XP transactions are prevented
     */
    public function test_duplicate_xp_prevented(): void
    {
        // Enable XP system
        XpSetting::create(['key' => 'leveling_enabled', 'value' => '1']);
        Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);

        $user = User::factory()->create(['total_xp' => 0, 'level' => 0]);

        // Add XP for order 123
        $transaction1 = XpService::addXp($user, 'completion_bonus', 20, 'order', 123);
        $this->assertNotNull($transaction1);

        $user->refresh();
        $this->assertEquals(20, $user->total_xp);

        // Try to add XP for the same order again
        $transaction2 = XpService::addXp($user, 'completion_bonus', 20, 'order', 123);
        $this->assertNull($transaction2); // Should be null (duplicate prevented)

        $user->refresh();
        $this->assertEquals(20, $user->total_xp); // XP should not increase
    }

    /**
     * Test getLevelInfo returns correct structure
     */
    public function test_get_level_info(): void
    {
        Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);
        Level::create(['level_number' => 2, 'xp_required' => 100, 'name' => 'Bronze', 'status' => true]);

        $user = User::factory()->create(['total_xp' => 50, 'level' => 1]);

        $levelInfo = XpService::getLevelInfo($user);

        $this->assertEquals(1, $levelInfo['current_level']);
        $this->assertEquals('Starter', $levelInfo['level_name']);
        $this->assertEquals(50, $levelInfo['total_xp']);
        $this->assertEquals(50, $levelInfo['xp_to_next_level']); // 100 - 50
        $this->assertEquals(50, $levelInfo['progress_percentage']); // 50/100 * 100
        $this->assertFalse($levelInfo['is_max_level']);
        $this->assertNotNull($levelInfo['next_level']);
        $this->assertEquals(2, $levelInfo['next_level']['level_number']);
    }

    /**
     * Test calculateItemXp returns correct XP for given inputs.
     */
    public function test_calculate_item_xp(): void
    {
        // Set the rate: 0.1 XP per LE
        XpSetting::create(['key' => 'xp_per_currency_unit', 'value' => '0.1']);
        // Default multiplier for food = 1.0
        XpSetting::create(['key' => 'multiplier_food', 'value' => '1.0']);

        // 250 LE × 1 qty × 1.0 multiplier × 0.1 rate = 25 XP
        $this->assertEquals(25, XpService::calculateItemXp(250, 1, 'food'));

        // 180 LE × 2 qty × 1.0 multiplier × 0.1 rate = 36 XP
        $this->assertEquals(36, XpService::calculateItemXp(180, 2, 'food'));

        // 99 LE × 1 qty × 1.0 multiplier × 0.1 rate = floor(9.9) = 9 XP
        $this->assertEquals(9, XpService::calculateItemXp(99, 1, 'food'));
    }

    /**
     * Test calculateItemXp with different module multipliers.
     */
    public function test_calculate_item_xp_with_multipliers(): void
    {
        XpSetting::create(['key' => 'xp_per_currency_unit', 'value' => '0.1']);
        XpSetting::create(['key' => 'multiplier_food', 'value' => '1.0']);
        XpSetting::create(['key' => 'multiplier_grocery', 'value' => '0.5']);

        // Food: 200 LE × 1 × 1.0 × 0.1 = 20 XP
        $this->assertEquals(20, XpService::calculateItemXp(200, 1, 'food'));

        // Grocery: 200 LE × 1 × 0.5 × 0.1 = 10 XP
        $this->assertEquals(10, XpService::calculateItemXp(200, 1, 'grocery'));
    }

    /**
     * Test that addItemXp creates separate XP transactions per order detail.
     */
    public function test_add_item_xp_creates_transactions_per_detail(): void
    {
        // Enable XP system
        XpSetting::create(['key' => 'leveling_enabled', 'value' => '1']);
        XpSetting::create(['key' => 'xp_per_currency_unit', 'value' => '0.1']);
        XpSetting::create(['key' => 'multiplier_food', 'value' => '1.0']);

        Level::create(['level_number' => 1, 'xp_required' => 0, 'name' => 'Starter', 'status' => true]);

        $user = User::factory()->create(['total_xp' => 0, 'level' => 0]);

        // Create a mock order with details
        $order = \App\Models\Order::factory()->create([
            'user_id' => $user->id,
            'order_status' => 'delivered',
        ]);

        // Create two order details
        $detail1 = \App\Models\OrderDetail::create([
            'order_id' => $order->id,
            'item_id' => null,
            'price' => 250,
            'quantity' => 1,
        ]);
        $detail2 = \App\Models\OrderDetail::create([
            'order_id' => $order->id,
            'item_id' => null,
            'price' => 100,
            'quantity' => 2,
        ]);

        XpService::addItemXp($user, $order);

        // Verify two item_purchase transactions were created
        $itemTransactions = XpTransaction::where('user_id', $user->id)
            ->where('xp_source', 'item_purchase')
            ->get();

        $this->assertCount(2, $itemTransactions);

        // Detail 1: 250 × 1 × 1.0 × 0.1 = 25 XP
        $tx1 = $itemTransactions->firstWhere('reference_id', $detail1->id);
        $this->assertNotNull($tx1);
        $this->assertEquals(25, $tx1->xp_amount);

        // Detail 2: 100 × 2 × 1.0 × 0.1 = 20 XP
        $tx2 = $itemTransactions->firstWhere('reference_id', $detail2->id);
        $this->assertNotNull($tx2);
        $this->assertEquals(20, $tx2->xp_amount);
    }
}
