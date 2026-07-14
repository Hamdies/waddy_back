<?php

namespace App\Services;

use App\Models\User;
use App\Models\XpChallenge;
use App\Models\UserChallenge;
use App\Models\XpSetting;
use App\Support\AppClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChallengeService
{
    /**
     * Get available challenges for a user.
     * Uses midnight reset logic for daily, Saturday reset for weekly.
     */
    public static function getAvailableChallenges(User $user): array
    {
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

        // Midnight reset (app-local): if last claim was before today, allow a new one
        $canGetNew = !$lastClaimedDaily ||
            !AppClock::isToday($lastClaimedDaily->claimed_at);

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

        // Weekly reset (app-local ISO week): if last claim was in a different
        // week, allow a new challenge. Single boundary shared with streaks and
        // the Places race — no more ISO year-rollover edge case.
        $canGetNew = !$lastClaimedWeekly ||
            !AppClock::sameWeek($lastClaimedWeekly->claimed_at, AppClock::now());

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
     *
     * Guarded against parallel GETs to /xp/challenges double-assigning: the
     * transaction locks the user's existing challenge rows of this frequency,
     * then re-checks for an active one before creating, so only the first
     * request wins.
     */
    public static function assignChallenge(User $user, string $frequency): ?UserChallenge
    {
        $challenge = XpChallenge::getRandomByFrequency($frequency);
        if (!$challenge) {
            return null;
        }

        return DB::transaction(function () use ($user, $frequency, $challenge) {
            // Serialize concurrent assignment on the user row itself, so the
            // first-ever assignment (no challenge rows yet to lock) is covered.
            User::lockForUpdate()->find($user->id);

            $existingActive = UserChallenge::where('user_id', $user->id)
                ->whereHas('challenge', fn ($q) => $q->where('frequency', $frequency))
                ->where('status', 'active')
                ->first();

            if ($existingActive) {
                return $existingActive;
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
        });
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
                    // Accumulate spend across orders so the client's progress
                    // bar (amount_spent / target) fills in gradually instead of
                    // only ever completing on a single large order.
                    $target = $challenge->conditions['min_amount'] ?? 250;
                    $progress['amount_spent'] = ($progress['amount_spent'] ?? 0) + $order->order_amount;
                    $progress['target'] = $target;
                    if ($progress['amount_spent'] >= $target) {
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
                self::notifyChallengeCompleted($user, $challenge);
            } else {
                $userChallenge->save();
            }
        }
    }

    /**
     * Push + in-app notification when a challenge becomes claimable — this is
     * the retention hook challenges exist for ("done, come claim your XP").
     */
    protected static function notifyChallengeCompleted(User $user, XpChallenge $challenge): void
    {
        if (!$user->cm_firebase_token) {
            return;
        }

        $data = [
            'title' => translate('Challenge Complete!'),
            'description' => translate('You finished') . ' "' . $challenge->title . '" — '
                . translate('claim your') . ' ' . $challenge->xp_reward . ' XP',
            'image' => '',
            'type' => 'challenge_complete',
            'challenge_id' => (string) $challenge->id,
            'order_id' => '',
            'module_id' => '',
            'order_type' => '',
        ];

        try {
            \App\CentralLogics\Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
            \Illuminate\Support\Facades\DB::table('user_notifications')->insert([
                'data' => json_encode($data),
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send challenge-complete notification: ' . $e->getMessage());
        }
    }

    /**
     * Claim challenge reward.
     */
    public static function claimReward(UserChallenge $userChallenge): bool
    {
        // Atomic status flip: only the request that actually moves the row
        // from 'completed' to 'claimed' gets to award XP. A parallel claim
        // affects 0 rows and is rejected here instead of double-claiming.
        $claimed = UserChallenge::where('id', $userChallenge->id)
            ->where('status', 'completed')
            ->update([
                'status' => 'claimed',
                'claimed_at' => now(),
                'next_available_at' => now()->addHours(24),
            ]);

        if (!$claimed) {
            return false;
        }

        $userChallenge->refresh();

        $challenge = $userChallenge->challenge;
        $user = $userChallenge->user;

        // Award XP (also deduped by the unique_xp_award constraint)
        $xpSource = $challenge->frequency === 'daily' ? 'daily_challenge' : 'weekly_challenge';
        XpService::addXp(
            $user,
            $xpSource,
            $challenge->xp_reward,
            'challenge',
            $userChallenge->id,
            "Completed: {$challenge->title}"
        );

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
