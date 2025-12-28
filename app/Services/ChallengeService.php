<?php

namespace App\Services;

use App\Models\User;
use App\Models\XpChallenge;
use App\Models\UserChallenge;
use App\Models\XpSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ChallengeService
{
    /**
     * Get available challenges for a user.
     * Uses midnight reset logic for daily, Saturday reset for weekly.
     */
    public static function getAvailableChallenges(User $user): array
    {
        // Check if user has at least 1 completed order
        if ($user->orders()->where('order_status', 'delivered')->count() === 0) {
            return [];
        }

        $challenges = [];

        // Get daily challenge
        $dailyChallenge = self::getAvailableDailyChallenge($user);
        if ($dailyChallenge) {
            $challenges['daily'] = $dailyChallenge;
        }

        // Get weekly challenge
        $weeklyChallenge = self::getAvailableWeeklyChallenge($user);
        if ($weeklyChallenge) {
            $challenges['weekly'] = $weeklyChallenge;
        }

        return $challenges;
    }

    /**
     * Get available daily challenge for user.
     * Resets at midnight (overrides 24h cooldown).
     */
    protected static function getAvailableDailyChallenge(User $user): ?array
    {
        // Check for active daily challenge
        $activeDaily = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'daily'))
            ->where('status', 'active')
            ->first();

        if ($activeDaily) {
            return self::formatChallengeResponse($activeDaily);
        }

        // Check for completed (unclaimed) daily challenge
        $completedDaily = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'daily'))
            ->where('status', 'completed')
            ->first();

        if ($completedDaily) {
            return self::formatChallengeResponse($completedDaily);
        }

        // Check if user can get a new daily challenge
        $lastClaimedDaily = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'daily'))
            ->where('status', 'claimed')
            ->latest('claimed_at')
            ->first();

        // Midnight reset: If last claim was before today, allow new challenge
        $canGetNew = !$lastClaimedDaily ||
            !Carbon::parse($lastClaimedDaily->claimed_at)->isToday();

        // Also check 24h cooldown if claimed today
        if (!$canGetNew && $lastClaimedDaily?->next_available_at) {
            $canGetNew = now()->gt($lastClaimedDaily->next_available_at);
        }

        if ($canGetNew) {
            // Assign a new random daily challenge
            $newChallenge = self::assignChallenge($user, 'daily');
            if ($newChallenge) {
                return self::formatChallengeResponse($newChallenge);
            }
        }

        return null;
    }

    /**
     * Get available weekly challenge for user.
     * Resets on Saturday (overrides 24h cooldown).
     */
    protected static function getAvailableWeeklyChallenge(User $user): ?array
    {
        // Check for active weekly challenge
        $activeWeekly = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'weekly'))
            ->where('status', 'active')
            ->first();

        if ($activeWeekly) {
            return self::formatChallengeResponse($activeWeekly);
        }

        // Check for completed (unclaimed) weekly challenge
        $completedWeekly = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'weekly'))
            ->where('status', 'completed')
            ->first();

        if ($completedWeekly) {
            return self::formatChallengeResponse($completedWeekly);
        }

        // Check if user can get a new weekly challenge
        $lastClaimedWeekly = $user->userChallenges()
            ->whereHas('challenge', fn($q) => $q->where('frequency', 'weekly'))
            ->where('status', 'claimed')
            ->latest('claimed_at')
            ->first();

        // Saturday reset: If last claim was in a different week, allow new challenge
        $canGetNew = !$lastClaimedWeekly ||
            Carbon::parse($lastClaimedWeekly->claimed_at)->weekOfYear !== now()->weekOfYear ||
            Carbon::parse($lastClaimedWeekly->claimed_at)->year !== now()->year;

        // Also check 24h cooldown if claimed this week
        if (!$canGetNew && $lastClaimedWeekly?->next_available_at) {
            $canGetNew = now()->gt($lastClaimedWeekly->next_available_at);
        }

        if ($canGetNew) {
            // Assign a new random weekly challenge
            $newChallenge = self::assignChallenge($user, 'weekly');
            if ($newChallenge) {
                return self::formatChallengeResponse($newChallenge);
            }
        }

        return null;
    }

    /**
     * Assign a random challenge to user.
     */
    public static function assignChallenge(User $user, string $frequency): ?UserChallenge
    {
        $challenge = XpChallenge::getRandomByFrequency($frequency);
        if (!$challenge) {
            return null;
        }

        $timeLimit = $challenge->time_limit_hours ?? 24;

        return UserChallenge::create([
            'user_id' => $user->id,
            'xp_challenge_id' => $challenge->id,
            'status' => 'active',
            'progress' => self::initializeProgress($challenge),
            'started_at' => now(),
            'expires_at' => now()->addHours($timeLimit),
        ]);
    }

    /**
     * Initialize progress based on challenge type.
     */
    protected static function initializeProgress(XpChallenge $challenge): array
    {
        $conditions = $challenge->conditions ?? [];

        switch ($challenge->challenge_type) {
            case 'multiple_orders':
                return [
                    'orders_completed' => 0,
                    'target' => $conditions['order_count'] ?? 2,
                ];
            case 'min_order_amount':
                return [
                    'amount_spent' => 0,
                    'target' => $conditions['min_amount'] ?? 250,
                ];
            case 'complete_order':
            case 'new_store':
            default:
                return [
                    'completed' => false,
                ];
        }
    }

    /**
     * Check challenge progress after an order.
     */
    public static function checkProgress(User $user, $order): void
    {
        // Get active challenges
        $activeChallenges = $user->userChallenges()
            ->where('status', 'active')
            ->with('challenge')
            ->get();

        foreach ($activeChallenges as $userChallenge) {
            // Skip expired challenges
            if ($userChallenge->isExpired()) {
                $userChallenge->update(['status' => 'expired']);
                continue;
            }

            $challenge = $userChallenge->challenge;
            $progress = $userChallenge->progress ?? [];
            $completed = false;

            switch ($challenge->challenge_type) {
                case 'complete_order':
                    $completed = true;
                    break;

                case 'min_order_amount':
                    $target = $challenge->conditions['min_amount'] ?? 250;
                    if ($order->order_amount >= $target) {
                        $completed = true;
                    }
                    break;

                case 'multiple_orders':
                    $progress['orders_completed'] = ($progress['orders_completed'] ?? 0) + 1;
                    $target = $challenge->conditions['order_count'] ?? 2;
                    if ($progress['orders_completed'] >= $target) {
                        $completed = true;
                    }
                    break;

                case 'new_store':
                    // Check if user has ordered from this store before
                    $previousOrders = $user->orders()
                        ->where('store_id', $order->store_id)
                        ->where('id', '!=', $order->id)
                        ->where('order_status', 'delivered')
                        ->exists();
                    if (!$previousOrders) {
                        $completed = true;
                    }
                    break;
            }

            $userChallenge->progress = $progress;
            if ($completed) {
                $userChallenge->markCompleted();
                Log::info("Challenge completed: user={$user->id}, challenge={$challenge->id}");
            } else {
                $userChallenge->save();
            }
        }
    }

    /**
     * Claim challenge reward.
     */
    public static function claimReward(UserChallenge $userChallenge): bool
    {
        if ($userChallenge->status !== 'completed') {
            return false;
        }

        $challenge = $userChallenge->challenge;
        $user = $userChallenge->user;

        // Award XP
        $xpSource = $challenge->frequency === 'daily' ? 'daily_challenge' : 'weekly_challenge';
        XpService::addXp(
            $user,
            $xpSource,
            $challenge->xp_reward,
            'challenge',
            $userChallenge->id,
            "Completed: {$challenge->title}"
        );

        // Mark as claimed with 24h cooldown
        $userChallenge->markClaimed();

        return true;
    }

    /**
     * Format challenge for API response.
     */
    protected static function formatChallengeResponse(UserChallenge $userChallenge): array
    {
        $challenge = $userChallenge->challenge;

        return [
            'id' => $userChallenge->id,
            'challenge_id' => $challenge->id,
            'title' => $challenge->title,
            'description' => $challenge->description,
            'type' => $challenge->frequency,
            'challenge_type' => $challenge->challenge_type,
            'xp_reward' => $challenge->xp_reward,
            'status' => $userChallenge->status,
            'progress' => $userChallenge->progress,
            'conditions' => $challenge->conditions,
            'started_at' => $userChallenge->started_at?->toIso8601String(),
            'expires_at' => $userChallenge->expires_at?->toIso8601String(),
            'completed_at' => $userChallenge->completed_at?->toIso8601String(),
        ];
    }

    /**
     * Expire old challenges (cron job).
     */
    public static function expireOldChallenges(): int
    {
        return UserChallenge::where('status', 'active')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
