<?php

namespace App\Services;

use App\Models\User;
use App\Models\Level;
use App\Models\LevelPrize;
use App\Models\XpTransaction;
use App\Models\UserLevelPrize;
use App\Models\UserStreak;
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
                // Handle null values for new users
                $previousLevel = $user->level ?? 0;
                $previousXp = $user->total_xp ?? 0;
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

                // Roll positive earnings into the current weekly/monthly season
                // buckets (single choke point — all awards pass through here).
                if ($amount > 0) {
                    \App\Models\XpSeasonScore::recordEarning($user->id, $amount);
                }

                // Check for level up (handle new users going from 0 to 1, and existing users leveling up)
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
     * Add XP for a completed order with per-item XP calculation.
     */
    public static function addOrderXp(User $user, $order): void
    {
        // XP for order completion (flat bonus)
        $completionXp = XpSetting::getInt('xp_per_order', 20);
        self::addXp(
            $user,
            'completion_bonus',
            $completionXp,
            'order',
            $order->id,
            'Order completed'
        );

        // XP for each item based on price
        self::addItemXp($user, $order);

        // Update streak and award streak bonus XP. Anchor the streak day to
        // when the order was placed (app-local), not when it was delivered —
        // a late-night order delivered after midnight still counts on its day.
        try {
            $streak = UserStreak::recordActivity($user, $order->created_at);
            if ($streak->current_streak > 1) {
                $streakBonusXp = XpSetting::getInt('streak_bonus_xp', 10);
                if ($streakBonusXp > 0) {
                    self::addXp(
                        $user,
                        'streak_bonus',
                        $streakBonusXp,
                        'order',
                        $order->id,
                        "Streak day {$streak->current_streak} bonus"
                    );
                }
            }
        } catch (\Exception $e) {
            Log::error("Streak update failed: " . $e->getMessage());
        }
    }

    /**
     * Add XP for each item in the order based on its price.
     */
    public static function addItemXp(User $user, $order): void
    {
        $moduleType = $order->module?->module_type ?? 'food';
        $details = $order->details;

        foreach ($details as $detail) {
            $price = $detail->price ?? 0;
            $quantity = $detail->quantity ?? 1;

            $itemXp = self::calculateItemXp($price, $quantity, $moduleType);

            if ($itemXp > 0) {
                $itemName = $detail->item?->name ?? 'Item';
                self::addXp(
                    $user,
                    'item_purchase',
                    $itemXp,
                    'order_detail',
                    $detail->id,
                    "{$itemName} — {$price} LE × {$quantity} (x" . XpSetting::getMultiplier($moduleType) . " multiplier)"
                );
            }
        }
    }

    /**
     * Calculate XP for a single item based on price, quantity, and module multiplier.
     * Formula: floor(price × quantity × multiplier × xp_per_currency_unit)
     */
    public static function calculateItemXp(float $price, int $quantity, string $moduleType): int
    {
        $multiplier = XpSetting::getMultiplier($moduleType);
        $rate = XpSetting::getFloat('xp_per_currency_unit', 0.1);

        // Apply event multiplier if active and not expired
        $eventActive = (string) XpSetting::getValue('multiplier_event_active', '0') === '1';
        if ($eventActive) {
            $endsAt = XpSetting::getValue('multiplier_event_ends_at');
            if (!$endsAt || now()->lt($endsAt)) {
                $eventMultiplier = XpSetting::getFloat('multiplier_event_multiplier', 1.0);
                $multiplier *= $eventMultiplier;
            }
        }

        return (int) floor($price * $quantity * $multiplier * $rate);
    }

    /**
     * Add XP for a review.
     */
    public static function addReviewXp(User $user, $review): void
    {
        $xp = XpSetting::getInt('xp_per_review', 30);

        // Reviews are per-item in this codebase, so a multi-item order would
        // otherwise award the review bonus once per line. Key the dedupe to
        // the order (falling back to the review id for reviews with no order)
        // so the bonus lands at most once per order.
        $referenceType = $review->order_id ? 'order' : 'review';
        $referenceId = $review->order_id ?? $review->id;

        self::addXp(
            $user,
            'review_bonus',
            $xp,
            $referenceType,
            $referenceId,
            'Rated and reviewed order'
        );
    }

    /**
     * Add XP for user registration (signup bonus).
     */
    public static function addSignupXp(User $user): void
    {
        $xp = XpSetting::getInt('xp_signup_bonus', 50);
        if ($xp > 0) {
            self::addXp(
                $user,
                'signup_bonus',
                $xp,
                'registration',
                $user->id,
                'Welcome bonus for signing up'
            );
        }
    }

    /**
     * Calculate level from total XP.
     * Returns 0 if user hasn't reached Level 1 yet.
     */
    public static function calculateLevelFromXp(int $totalXp): int
    {
        $level = Level::where('xp_required', '<=', $totalXp)
            ->orderByDesc('level_number')
            ->first();

        return $level ? $level->level_number : 0;
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

            self::unlockPrizesForLevel($user, $levelModel);

            // Record a real level-up row (0 XP) so the history feed reads it
            // directly instead of re-deriving thresholds per page. Deduped on
            // the level number via the unique_xp_award constraint.
            if (!XpTransaction::exists($user->id, 'level_up', $level, 'level_up')) {
                XpTransaction::create([
                    'user_id' => $user->id,
                    'reference_type' => 'level_up',
                    'reference_id' => $level,
                    'xp_source' => 'level_up',
                    'xp_amount' => 0,
                    'balance_after' => $user->total_xp ?? 0,
                    'description' => 'Reached Level ' . $level . ': ' . ($levelModel->name ?? ''),
                ]);
            }

            if ($user->cm_firebase_token) {
                $levelName = $levelModel->name ?? "Level $level";
                $data = [
                    'title' => translate('Level Up!'),
                    'description' => translate("Congratulations! You've reached") . " $levelName",
                    'image' => $levelModel->badge_image_url ?? '',
                    'type' => 'level_up',
                    'level' => (string) $level,
                    'level_name' => $levelName,
                    'order_id' => '',
                    'module_id' => '',
                    'order_type' => '',
                ];
                
                try {
                    \App\CentralLogics\Helpers::send_push_notif_to_device($user->cm_firebase_token, $data);
                    
                    DB::table('user_notifications')->insert([
                        'data' => json_encode($data),
                        'user_id' => $user->id,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                } catch (\Exception $e) {
                    Log::error("Failed to send level up notification: " . $e->getMessage());
                }
            }
        }
    }

    /**
     * Create the UserLevelPrize instances for a level (idempotent).
     *
     * Shared by the level-up handler and the `xp:backfill-prizes` command.
     * firstOrCreate + the unique (user_id, level_prize_id) constraint make it
     * safe to call repeatedly and from concurrent paths. Returns how many new
     * instances were created.
     */
    public static function unlockPrizesForLevel(User $user, Level $levelModel): int
    {
        $prizes = LevelPrize::where('level_id', $levelModel->id)
            ->where('status', true)
            ->get();

        $created = 0;

        foreach ($prizes as $prize) {
            $validityDays = $prize->validity_days ?? XpSetting::getInt('prize_validity_days', 30);
            $status = $prize->isBadge() ? 'used' : 'unlocked';

            $instance = UserLevelPrize::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'level_prize_id' => $prize->id,
                ],
                [
                    'status' => $status,
                    'unlocked_at' => now(),
                    'expires_at' => $prize->isBadge() ? null : now()->addDays($validityDays),
                    'used_at' => $prize->isBadge() ? now() : null,
                ]
            );

            if ($instance->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
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
            'level_badge' => $currentLevel?->badge_image_url,
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

    /**
     * Add XP for a referral (when referred user places first order).
     */
    public static function addReferralXp(User $referrer, User $referredUser, $order): void
    {
        $xp = XpSetting::getInt('xp_referral_bonus', 50);
        if ($xp > 0) {
            // Only award for the referred user's first order
            $orderCount = $referredUser->orders()->where('order_status', 'delivered')->count();
            if ($orderCount <= 1) {
                self::addXp(
                    $referrer,
                    'referral_bonus',
                    $xp,
                    'referral',
                    $referredUser->id,
                    'Referral bonus: ' . ($referredUser->f_name ?? 'User') . ' placed first order'
                );
            }
        }
    }

    /**
     * Reverse all XP earned from an order when it is refunded.
     *
     * Writes negative compensating transactions (never deletes history),
     * recalculates the user's total XP and level, and — if the level drops —
     * revokes only never-claimed prizes from the levels the user fell below.
     * Claimed/used prizes and already-granted wallet credit are left alone.
     */
    public static function reverseOrderXp(User $user, $order): void
    {
        if (!XpSetting::isEnabled()) {
            return;
        }

        // Idempotency: if we've already reversed this order, do nothing.
        if (XpTransaction::exists($user->id, 'order_refund', $order->id, 'refund_reversal')) {
            Log::info("XP refund already reversed: user={$user->id}, order={$order->id}");
            return;
        }

        // Collect every non-reversal transaction that was awarded for this
        // order: the order-level rows (reference_type 'order') plus each
        // per-item row (reference_type 'order_detail'). Streak bonus is an
        // 'order' row but is intentionally kept — the streak day still counts.
        $detailIds = $order->details->pluck('id')->all();

        $earned = XpTransaction::where('user_id', $user->id)
            ->where('xp_amount', '>', 0)
            ->where(function ($q) use ($order, $detailIds) {
                $q->where(function ($sub) use ($order) {
                    $sub->where('reference_type', 'order')
                        ->where('reference_id', $order->id)
                        ->where('xp_source', '!=', 'streak_bonus');
                });
                if (!empty($detailIds)) {
                    $q->orWhere(function ($sub) use ($detailIds) {
                        $sub->where('reference_type', 'order_detail')
                            ->whereIn('reference_id', $detailIds);
                    });
                }
            })
            ->get();

        $totalToReverse = (int) $earned->sum('xp_amount');

        if ($totalToReverse <= 0) {
            Log::info("No XP to reverse for order: user={$user->id}, order={$order->id}");
            return;
        }

        try {
            DB::transaction(function () use ($user, $order, $earned, $totalToReverse) {
                $previousLevel = $user->level ?? 0;
                $previousXp = $user->total_xp ?? 0;

                // Floor at 0 so a partially-consumed history can't go negative.
                $newXp = max(0, $previousXp - $totalToReverse);
                $actualReversed = $previousXp - $newXp;

                $user->total_xp = $newXp;
                $newLevel = self::calculateLevelFromXp($newXp);
                $user->level = $newLevel;
                $user->save();

                // Single compensating transaction, keyed so a repeat refund
                // event can't double-deduct (unique_xp_award constraint).
                XpTransaction::create([
                    'user_id' => $user->id,
                    'reference_type' => 'order_refund',
                    'reference_id' => $order->id,
                    'xp_source' => 'refund_reversal',
                    'xp_amount' => -$actualReversed,
                    'balance_after' => $newXp,
                    'description' => 'XP reversed — order #' . $order->id . ' refunded',
                ]);

                // Mark the originating transactions as reversed for auditing.
                XpTransaction::whereIn('id', $earned->pluck('id'))->update(['is_reversed' => true]);

                if ($newLevel < $previousLevel) {
                    self::revokeUnclaimedPrizesAboveLevel($user, $newLevel);
                }

                Log::info("XP reversed: user={$user->id}, order={$order->id}, amount={$actualReversed}, level {$previousLevel}->{$newLevel}");
            });
        } catch (\Exception $e) {
            Log::error("XP reversal failed for order {$order->id}: " . $e->getMessage());
        }
    }

    /**
     * Revoke only never-claimed prizes from levels above the given level.
     *
     * Deletes 'unlocked' prize instances for levels the user has dropped
     * below. Claimed, used, and expired prizes are untouched — we never claw
     * back a reward the user has already acted on.
     */
    protected static function revokeUnclaimedPrizesAboveLevel(User $user, int $newLevel): void
    {
        $revoked = UserLevelPrize::where('user_id', $user->id)
            ->where('status', 'unlocked')
            ->whereHas('prize.level', function ($q) use ($newLevel) {
                $q->where('level_number', '>', $newLevel);
            })
            ->delete();

        if ($revoked > 0) {
            Log::info("Revoked {$revoked} unclaimed prize(s) for user {$user->id} above level {$newLevel}");
        }
    }
}
