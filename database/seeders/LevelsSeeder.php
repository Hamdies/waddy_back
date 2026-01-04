<?php

namespace Database\Seeders;

use App\Models\Level;
use App\Models\LevelPrize;
use Illuminate\Database\Seeder;

class LevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create levels
        $levels = [
            ['level_number' => 1, 'name' => 'Starter', 'xp_required' => 50, 'description' => 'Welcome to Waddy! You\'ve earned your first badge.'],
            ['level_number' => 2, 'name' => 'Lowkey', 'xp_required' => 200, 'description' => 'You\'re getting the hang of this!'],
            ['level_number' => 3, 'name' => 'Vibing', 'xp_required' => 600, 'description' => 'Now you\'re vibing with us!'],
            ['level_number' => 4, 'name' => 'Locked-In', 'xp_required' => 1200, 'description' => 'You\'re locked in and committed!'],
            ['level_number' => 5, 'name' => 'Main Character', 'xp_required' => 2000, 'description' => 'You\'re the main character now!'],
            ['level_number' => 6, 'name' => 'Certified', 'xp_required' => 3000, 'description' => 'Certified Waddy member!'],
            ['level_number' => 7, 'name' => 'Elite Mode', 'xp_required' => 4200, 'description' => 'Elite status unlocked!'],
            ['level_number' => 8, 'name' => 'Goated', 'xp_required' => 5600, 'description' => 'You\'re the GOAT!'],
            ['level_number' => 9, 'name' => 'Iconic', 'xp_required' => 7200, 'description' => 'An icon in the making!'],
            ['level_number' => 10, 'name' => 'Legendary', 'xp_required' => 9000, 'description' => 'You\'ve reached legendary status!'],
        ];

        foreach ($levels as $levelData) {
            $level = Level::updateOrCreate(
                ['level_number' => $levelData['level_number']],
                $levelData
            );

            // Create default prizes for each level
            $this->createDefaultPrizes($level);
        }
    }

    /**
     * Create default prizes for each level.
     */
    protected function createDefaultPrizes(Level $level): void
    {
        $prizes = [];

        switch ($level->level_number) {
            case 1:
                $prizes[] = [
                    'title' => 'Starter Badge',
                    'description' => 'Welcome badge for joining Waddy',
                    'prize_type' => 'badge',
                    'value' => null,
                ];
                break;

            case 2:
                $prizes[] = [
                    'title' => 'Free Drink',
                    'description' => 'Get a free drink with your order',
                    'prize_type' => 'free_item',
                    'value' => 30,
                    'min_order_amount' => 100,
                ];
                break;

            case 3:
                $prizes[] = [
                    'title' => 'Free Delivery',
                    'description' => 'One free delivery on your next order',
                    'prize_type' => 'free_delivery',
                    'value' => null,
                    'min_order_amount' => 100,
                ];
                break;

            case 4:
                $prizes[] = [
                    'title' => '20 EGP Off',
                    'description' => 'Get 20 EGP off your next order',
                    'prize_type' => 'discount',
                    'value' => 20,
                    'min_order_amount' => 150,
                ];
                break;

            case 5:
                $prizes[] = [
                    'title' => 'Free Side Item',
                    'description' => 'Get a free side with your meal',
                    'prize_type' => 'free_item',
                    'value' => 50,
                    'min_order_amount' => 150,
                ];
                break;

            case 6:
                $prizes[] = [
                    'title' => '2× Free Delivery',
                    'description' => 'Two free deliveries for your orders',
                    'prize_type' => 'free_delivery',
                    'value' => null,
                    'min_order_amount' => 100,
                    'usage_limit' => 2,
                ];
                break;

            case 7:
                $prizes[] = [
                    'title' => '50 EGP Wallet Credit',
                    'description' => '50 EGP added to your wallet',
                    'prize_type' => 'wallet_credit',
                    'value' => 50,
                ];
                break;

            case 8:
                $prizes[] = [
                    'title' => 'Birthday Treat',
                    'description' => 'A special dessert or merch on your birthday',
                    'prize_type' => 'custom',
                    'value' => 75,
                ];
                break;

            case 9:
                $prizes[] = [
                    'title' => 'Free Meal',
                    'description' => 'Free meal up to 100 EGP (one-time)',
                    'prize_type' => 'free_item',
                    'value' => 100,
                    'min_order_amount' => 100,
                ];
                break;

            case 10:
                $prizes[] = [
                    'title' => 'Legendary Box',
                    'description' => 'Legendary box OR 100 EGP credit',
                    'prize_type' => 'wallet_credit',
                    'value' => 100,
                ];
                break;
        }

        foreach ($prizes as $prizeData) {
            LevelPrize::updateOrCreate(
                [
                    'level_id' => $level->id,
                    'title' => $prizeData['title'],
                ],
                array_merge($prizeData, ['level_id' => $level->id])
            );
        }
    }
}
