<?php

namespace Database\Seeders;

use App\Models\XpChallenge;
use Illuminate\Database\Seeder;

class ChallengesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $challenges = [
            // Daily Challenges (20 XP)
            [
                'title' => 'Order Lunch Today',
                'description' => 'Complete any order today',
                'challenge_type' => 'complete_order',
                'frequency' => 'daily',
                'conditions' => [],
                'xp_reward' => 20,
                'time_limit_hours' => 24,
            ],
            [
                'title' => 'Treat Yourself',
                'description' => 'Place an order of 150 EGP or more',
                'challenge_type' => 'min_order_amount',
                'frequency' => 'daily',
                'conditions' => ['min_amount' => 150],
                'xp_reward' => 20,
                'time_limit_hours' => 24,
            ],
            [
                'title' => 'Quick Bite',
                'description' => 'Order from any restaurant',
                'challenge_type' => 'complete_order',
                'frequency' => 'daily',
                'conditions' => [],
                'xp_reward' => 20,
                'time_limit_hours' => 24,
            ],

            // Weekly Challenges (100 XP)
            [
                'title' => 'Order 3 Times This Week',
                'description' => 'Complete 3 orders this week',
                'challenge_type' => 'multiple_orders',
                'frequency' => 'weekly',
                'conditions' => ['order_count' => 3],
                'xp_reward' => 100,
                'time_limit_hours' => 168, // 7 days
            ],
            [
                'title' => 'Big Spender',
                'description' => 'Place an order of 250 EGP or more',
                'challenge_type' => 'min_order_amount',
                'frequency' => 'weekly',
                'conditions' => ['min_amount' => 250],
                'xp_reward' => 100,
                'time_limit_hours' => 168,
            ],
            [
                'title' => 'Explorer',
                'description' => 'Order from a new restaurant',
                'challenge_type' => 'new_store',
                'frequency' => 'weekly',
                'conditions' => [],
                'xp_reward' => 100,
                'time_limit_hours' => 168,
            ],
            [
                'title' => 'Double Down',
                'description' => 'Complete 2 orders this week',
                'challenge_type' => 'multiple_orders',
                'frequency' => 'weekly',
                'conditions' => ['order_count' => 2],
                'xp_reward' => 100,
                'time_limit_hours' => 168,
            ],
        ];

        foreach ($challenges as $challengeData) {
            XpChallenge::updateOrCreate(
                [
                    'title' => $challengeData['title'],
                    'frequency' => $challengeData['frequency'],
                ],
                $challengeData
            );
        }
    }
}
