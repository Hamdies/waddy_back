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
}
