<?php

namespace App\Services;

use App\Models\User;
use App\Models\Level;
use App\Models\LevelPrize;
use App\Models\XpTransaction;
use App\Models\UserLevelPrize;
use App\Models\XpSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class XpService
{
    /**
     * Add XP to a user with duplicate prevention.
     */
    public static function addXp(
        User $user,
        string $xpSource,
        int $amount,
        string $referenceType = 'manual',
        ?int $referenceId = null,
        ?string $description = null
    ): ?XpTransaction {
        // Check if leveling system is enabled
        if (!XpSetting::isEnabled()) {
            return null;
        }

        // Prevent duplicates (unique constraint will also catch this)
        if ($referenceId && XpTransaction::exists($user->id, $referenceType, $referenceId, $xpSource)) {
            Log::info("XP duplicate prevented: user={$user->id}, type={$referenceType}, ref={$referenceId}, source={$xpSource}");
            return null;
        }

        try {
            return DB::transaction(function () use ($user, $xpSource, $amount, $referenceType, $referenceId, $description) {
                $previousLevel = $user->level;
                $previousXp = $user->total_xp;
                $newXp = $previousXp + $amount;

                // Update user XP
                $user->total_xp = $newXp;

                // Calculate new level
                $newLevel = self::calculateLevelFromXp($newXp);
                $user->level = $newLevel;
                $user->save();

                // Create transaction record
                $transaction = XpTransaction::create([
                    'user_id' => $user->id,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'xp_source' => $xpSource,
                    'xp_amount' => $amount,
                    'balance_after' => $newXp,
                    'description' => $description,
                ]);

                // Check for level up
                if ($newLevel > $previousLevel) {
                    self::handleLevelUp($user, $previousLevel, $newLevel);
                }

                return $transaction;
            });
        } catch (\Exception $e) {
            Log::error("XP add failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Add XP for a completed order with vertical weighting.
     */
    public static function addOrderXp(User $user, $order): void
    {
        // Get order amount and module type
        $orderAmount = $order->order_amount ?? 0;
        $moduleType = $order->module?->module_type ?? 'food';

        // XP for order completion
        $completionXp = XpSetting::getInt('xp_per_order', 20);
        self::addXp(
            $user,
            'completion_bonus',
            $completionXp,
            'order',
            $order->id,
            'Order completed'
        );

        // XP for amount spent (weighted by vertical)
        $spendXp = self::calculateWeightedXp($orderAmount, $moduleType);
        if ($spendXp > 0) {
            self::addXp(
                $user,
                'spend_amount',
                $spendXp,
                'order',
                $order->id,
                "Spent {$orderAmount} EGP (x" . XpSetting::getMultiplier($moduleType) . " multiplier)"
            );
        }
    }

    /**
     * Add XP for a review.
     */
    public static function addReviewXp(User $user, $review): void
    {
        $xp = XpSetting::getInt('xp_per_review', 30);
        self::addXp(
            $user,
            'review_bonus',
            $xp,
            'review',
            $review->id,
            'Rated and reviewed order'
        );
    }

    /**
     * Calculate weighted XP based on vertical multiplier.
     */
    public static function calculateWeightedXp(float $amount, string $moduleType): int
    {
        $multiplier = XpSetting::getMultiplier($moduleType);
        return (int) floor($amount * $multiplier);
    }

    /**
     * Calculate level from total XP.
     */
    public static function calculateLevelFromXp(int $totalXp): int
    {
        $level = Level::where('xp_required', '<=', $totalXp)
            ->orderByDesc('level_number')
            ->first();

        return $level ? $level->level_number : 1;
    }

    /**
     * Handle level up - unlock prizes.
     */
    protected static function handleLevelUp(User $user, int $fromLevel, int $toLevel): void
    {
        Log::info("User {$user->id} leveled up from {$fromLevel} to {$toLevel}");

        // Unlock prizes for each new level
        for ($level = $fromLevel + 1; $level <= $toLevel; $level++) {
            $levelModel = Level::where('level_number', $level)->first();
            if (!$levelModel) continue;

            $prizes = LevelPrize::where('level_id', $levelModel->id)
                ->where('status', true)
                ->get();

            foreach ($prizes as $prize) {
                // Check if user already has this prize
                $exists = UserLevelPrize::where('user_id', $user->id)
                    ->where('level_prize_id', $prize->id)
                    ->exists();

                if (!$exists) {
                    $validityDays = $prize->validity_days ?? XpSetting::getInt('prize_validity_days', 30);

                    UserLevelPrize::create([
                        'user_id' => $user->id,
                        'level_prize_id' => $prize->id,
                        'status' => 'unlocked',
                        'unlocked_at' => now(),
                        'expires_at' => now()->addDays($validityDays),
                    ]);
                }
            }

            // TODO: Send push notification for level up
            // Helpers::send_push_notification(...)
        }
    }

    /**
     * Get user's level info for API response.
     */
    public static function getLevelInfo(User $user): array
    {
        $currentLevel = Level::where('level_number', $user->level)->first();
        $nextLevel = Level::where('level_number', $user->level + 1)->first();

        $progress = $user->xp_progress;

        return [
            'current_level' => $user->level,
            'level_name' => $currentLevel?->name ?? 'Starter',
            'level_badge' => $currentLevel?->badge_image,
            'total_xp' => $user->total_xp,
            'xp_to_next_level' => $progress['xp_to_next_level'],
            'progress_percentage' => $progress['progress_percentage'],
            'is_max_level' => $progress['is_max_level'],
            'next_level' => $nextLevel ? [
                'level_number' => $nextLevel->level_number,
                'name' => $nextLevel->name,
                'xp_required' => $nextLevel->xp_required,
            ] : null,
        ];
    }
}
