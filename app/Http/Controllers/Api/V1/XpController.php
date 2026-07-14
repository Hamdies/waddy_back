<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserChallenge;
use App\Models\UserLevelPrize;
use App\Models\XpTransaction;
use App\Models\Level;
use App\Models\RewardItem;
use App\Models\XpSetting;
use App\Services\XpService;
use App\Services\ChallengeService;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class XpController extends Controller
{
    /**
     * Get XP configuration settings for client-side calculation.
     * This is a PUBLIC endpoint (no auth required) for caching on app startup.
     */
    public function getConfig()
    {
        return response()->json([
            'enabled' => XpSetting::isEnabled(),
            'xp_per_order' => XpSetting::getInt('xp_per_order', 20),
            'xp_per_review' => XpSetting::getInt('xp_per_review', 30),
            'xp_signup_bonus' => XpSetting::getInt('xp_signup_bonus', 50),
            // Per-currency-unit rate used by the item XP formula. Exposed so the
            // client's estimate matches the server (was hardcoded to 0.1 client-side).
            'xp_per_currency_unit' => XpSetting::getFloat('xp_per_currency_unit', 0.1),
            'max_level' => Level::active()->max('level_number') ?? 10,
            'multipliers' => [
                'food' => XpSetting::getMultiplier('food'),
                'grocery' => XpSetting::getMultiplier('grocery'),
                'pharmacy' => XpSetting::getMultiplier('pharmacy'),
                'ecommerce' => XpSetting::getMultiplier('ecommerce'),
                'parcel' => XpSetting::getMultiplier('parcel'),
                'service' => XpSetting::getMultiplier('service'),
            ],
            'multiplier_event' => [
                'active' => (bool) XpSetting::getValue('multiplier_event_active', false),
                'multiplier' => XpSetting::getFloat('multiplier_event_multiplier', 1.0),
                'ends_at' => XpSetting::getValue('multiplier_event_ends_at'),
            ],
            'streak_bonus_xp' => XpSetting::getInt('streak_bonus_xp', 10),
        ], 200);
    }

    /**
     * Get user's current level and XP progress.
     */
    public function getLevel(Request $request)
    {
        $user = $request->user();
        
        $levelInfo = XpService::getLevelInfo($user);
        
        return response()->json($levelInfo, 200);
    }

    /**
     * Get all levels with their details.
     * If user is authenticated, includes their prize instance IDs for claiming.
     */
    public function getAllLevels(Request $request)
    {
        $user = $request->user();
        
        return response()->json($this->buildLevelsData($user, false), 200);
    }

    /**
     * Get merged level details: current level info + all levels with prizes.
     * Combined endpoint replacing separate /level and /levels calls.
     */
    public function getLevelDetails(Request $request)
    {
        $user = $request->user();
        
        return response()->json($this->buildLevelsData($user, true), 200);
    }

    /**
     * Build levels data array, shared by getAllLevels and getLevelDetails.
     */
    private function buildLevelsData(?\App\Models\User $user, bool $includeFullLevelInfo = false): array
    {
        $userLevel = $user ? $user->level : 0;
        $userXp = $user ? $user->total_xp : 0;
        $xpProgress = $user ? $user->xp_progress : null;
        
        // Get user's prizes indexed by level_prize_id for quick lookup
        $userPrizes = collect();
        if ($user) {
            $userPrizes = UserLevelPrize::where('user_id', $user->id)
                ->get()
                ->keyBy('level_prize_id');
        }
        
        $levels = Level::active()
            ->with(['prizes' => function($q) {
                $q->where('status', 1);
            }])
            ->orderBy('level_number')
            ->get()
            ->map(function ($level) use ($userPrizes, $user, $userLevel) {
                return [
                    'level_number' => $level->level_number,
                    'is_unlocked' => $level->level_number <= $userLevel,
                    'name' => $level->name,
                    'xp_required' => $level->xp_required,
                    'description' => $level->description,
                    'badge_image' => $level->badge_image_url,
                    'prizes' => $level->prizes->map(function ($prize) use ($userPrizes) {
                        // Read-only: prize instances are created at level-up
                        // (XpService::handleLevelUp). Historical gaps are
                        // repaired by `php artisan xp:backfill-prizes`, not on
                        // this GET request.
                        $userPrize = $userPrizes->get($prize->id);

                        return [
                            'id' => $prize->id,
                            'instance_id' => $userPrize?->id,
                            'title' => $prize->title,
                            'description' => $prize->description,
                            'prize_type' => $prize->prize_type,
                            'value' => $prize->value,
                            'validity_days' => $prize->validity_days,
                            'status' => $userPrize?->status,
                            'is_claimed' => $userPrize ? in_array($userPrize->status, ['claimed', 'used']) : false,
                            'is_unlocked' => $userPrize !== null,
                        ];
                    }),
                ];
            });

        $data = [
            'levels' => $levels,
            'current_level' => $userLevel,
            'current_xp' => $userXp,
            'xp_for_next_level' => $xpProgress['xp_for_next_level'] ?? null,
            'xp_to_next_level' => $xpProgress['xp_to_next_level'] ?? 0,
            'progress_percentage' => $xpProgress['progress_percentage'] ?? 0,
        ];

        // Include full level info and streak for the merged endpoint
        if ($includeFullLevelInfo && $user) {
            $levelInfo = XpService::getLevelInfo($user);
            $data['level_name'] = $levelInfo['level_name'];
            $data['level_badge'] = $levelInfo['level_badge'];
            $data['is_max_level'] = $levelInfo['is_max_level'];
            $data['next_level'] = $levelInfo['next_level'];

            // Streak data
            $streak = $user->streak;
            $data['streak'] = $streak ? [
                'current_streak' => $streak->current_streak,
                'longest_streak' => $streak->longest_streak,
                'streak_bonus_xp' => XpSetting::getInt('streak_bonus_xp', 10),
                'last_activity_date' => $streak->last_activity_date?->toDateString(),
            ] : [
                'current_streak' => 0,
                'longest_streak' => 0,
                'streak_bonus_xp' => XpSetting::getInt('streak_bonus_xp', 10),
                'last_activity_date' => null,
            ];
        }

        return $data;
    }

    /**
     * Get user's claimable free_delivery prizes for checkout.
     */
    public function getCheckoutPrizes(Request $request)
    {
        $user = $request->user();
        $orderAmount = (float) $request->query('order_amount', 0);

        $prizes = UserLevelPrize::where('user_id', $user->id)
            ->whereIn('status', ['unlocked', 'claimed'])
            ->whereHas('prize', function($q) {
                $q->where('prize_type', 'free_delivery');
            })
            ->with('prize.level')
            ->get()
            ->filter(function($userPrize) use ($orderAmount) {
                // Filter out expired prizes
                if ($userPrize->isExpired()) {
                    return false;
                }
                // Filter by min_order_amount if provided
                if ($orderAmount > 0 && $userPrize->prize->min_order_amount) {
                    return $orderAmount >= $userPrize->prize->min_order_amount;
                }
                return true;
            })
            ->values()
            ->map(function($userPrize) {
                return [
                    'id' => $userPrize->id,
                    'title' => $userPrize->prize->title,
                    'min_order_amount' => $userPrize->prize->min_order_amount,
                    'expires_at' => $userPrize->expires_at?->toIso8601String(),
                    'level_name' => $userPrize->prize->level?->name,
                ];
            });

        return response()->json(['prizes' => $prizes], 200);
    }

    /**
     * Get user's available challenges.
     */
    public function getChallenges(Request $request)
    {
        $user = $request->user();
        
        $challenges = ChallengeService::getAvailableChallenges($user);

        return response()->json([
            'challenges' => $challenges,
            'has_daily' => isset($challenges['daily']),
            'has_weekly' => isset($challenges['weekly']),
        ], 200);
    }

    /**
     * Claim challenge reward.
     */
    public function claimChallenge(Request $request, $id)
    {
        $user = $request->user();

        $userChallenge = UserChallenge::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$userChallenge) {
            return response()->json([
                'errors' => [['code' => 'challenge', 'message' => translate('messages.challenge_not_found')]]
            ], 404);
        }

        if ($userChallenge->status !== 'completed') {
            return response()->json([
                'errors' => [['code' => 'challenge', 'message' => translate('messages.challenge_not_completed')]]
            ], 403);
        }

        $result = ChallengeService::claimReward($userChallenge);

        if ($result) {
            // Refresh user data
            $user->refresh();
            
            return response()->json([
                'message' => translate('messages.challenge_reward_claimed'),
                'xp_earned' => $userChallenge->challenge->xp_reward,
                'new_total_xp' => $user->total_xp,
                'new_level' => $user->level,
            ], 200);
        }

        return response()->json([
            'errors' => [['code' => 'challenge', 'message' => translate('messages.failed_to_claim_reward')]]
        ], 500);
    }

    /**
     * Get user's level prizes.
     */
    public function getPrizes(Request $request)
    {
        $user = $request->user();

        $prizes = UserLevelPrize::where('user_id', $user->id)
            ->with('prize.level')
            ->get()
            ->map(function ($userPrize) {
                $prize = $userPrize->prize;
                return [
                    'id' => $userPrize->id,
                    'prize_id' => $prize->id,
                    'level' => $prize->level?->level_number,
                    'level_name' => $prize->level?->name,
                    'title' => $prize->title,
                    'description' => $prize->description,
                    'prize_type' => $prize->prize_type,
                    'value' => $prize->value,
                    'min_order_amount' => $prize->min_order_amount,
                    'usage_limit' => $prize->usage_limit,
                    'status' => $userPrize->status,
                    'is_usable' => $userPrize->isUsable(),
                    'unlocked_at' => $userPrize->unlocked_at?->toIso8601String(),
                    'expires_at' => $userPrize->expires_at?->toIso8601String(),
                    'used_at' => $userPrize->used_at?->toIso8601String(),
                ];
            });

        // Group by status
        $usable = $prizes->filter(fn($p) => $p['is_usable']);
        $used = $prizes->filter(fn($p) => $p['status'] === 'used');
        $expired = $prizes->filter(fn($p) => $p['status'] === 'expired');

        return response()->json([
            'usable_prizes' => $usable->values(),
            'used_prizes' => $used->values(),
            'expired_prizes' => $expired->values(),
        ], 200);
    }

    /**
     * Claim a level prize.
     */
    public function claimPrize(Request $request, $id)
    {
        $user = $request->user();

        // Status checks and the claim itself must happen under a row lock,
        // otherwise two parallel requests can both pass the 'unlocked' check
        // and credit the wallet twice.
        try {
            $result = DB::transaction(function () use ($id, $user) {
                $userPrize = UserLevelPrize::lockForUpdate()
                    ->where('id', $id)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$userPrize) {
                    return ['error' => 'not_found'];
                }

                $userPrize->load('prize');

                if ($userPrize->status !== 'unlocked') {
                    return ['error' => 'already_claimed'];
                }

                if ($userPrize->isExpired()) {
                    $userPrize->expire();
                    return ['error' => 'expired'];
                }

                $userPrize->update([
                    'status' => 'claimed',
                    'claimed_at' => now(),
                ]);

                // Handle wallet credit prizes — auto-use immediately
                if ($userPrize->prize->prize_type === 'wallet_credit' && $userPrize->prize->value > 0) {
                    $walletTransaction = \App\CentralLogics\CustomerLogic::create_wallet_transaction(
                        $user->id,
                        $userPrize->prize->value,
                        'xp_prize',
                        'Level Prize: ' . $userPrize->prize->title
                    );

                    // Roll the claim back if the credit could not be created
                    // (e.g. wallet disabled) so the prize is not consumed for nothing.
                    if (!$walletTransaction) {
                        throw new \RuntimeException('Wallet credit failed for prize ' . $userPrize->id);
                    }

                    $userPrize->markUsed();
                }

                return ['prize' => $userPrize];
            });
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::error('Prize claim failed: ' . $e->getMessage());
            return response()->json([
                'errors' => [['code' => 'prize', 'message' => translate('messages.failed_to_claim_reward')]]
            ], 500);
        }

        if (isset($result['error'])) {
            return match ($result['error']) {
                'not_found' => response()->json([
                    'errors' => [['code' => 'prize', 'message' => translate('messages.prize_not_found')]]
                ], 404),
                'already_claimed' => response()->json([
                    'errors' => [['code' => 'prize', 'message' => translate('messages.prize_already_claimed_or_used')]]
                ], 403),
                'expired' => response()->json([
                    'errors' => [['code' => 'prize', 'message' => translate('messages.prize_expired')]]
                ], 403),
            };
        }

        $userPrize = $result['prize'];
        $userPrize->refresh();

        return response()->json([
            'message' => translate('messages.prize_claimed_successfully'),
            'prize' => [
                'id' => $userPrize->id,
                'title' => $userPrize->prize->title,
                'type' => $userPrize->prize->prize_type,
                'value' => $userPrize->prize->value,
                'status' => $userPrize->status,
            ],
        ], 200);
    }

    /**
     * Get reward items available at a store.
     */
    public function getRewardItems(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'store_id' => 'required|integer|exists:stores,id',
            'reward_type' => 'nullable|string|in:free_item,free_side,birthday_gift',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $rewardItems = RewardItem::getForStore(
            $request->store_id,
            $request->reward_type
        );

        $items = $rewardItems->map(function ($rewardItem) {
            $item = $rewardItem->item;
            return [
                'id' => $rewardItem->id,
                'item_id' => $item->id,
                'item_name' => $item->name,
                'item_image' => $item->image_full_url ?? null,
                'reward_type' => $rewardItem->reward_type,
                'max_value' => $rewardItem->max_value,
            ];
        });

        return response()->json(['reward_items' => $items], 200);
    }

    /**
     * Get XP history — unified activity feed.
     */
    public function getHistory(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer|min:1|max:50',
            'offset' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $user = $request->user();

        $paginator = XpTransaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($request->limit, ['*'], 'page', $request->offset);

        // Level-up events are stored as real transaction rows (source
        // 'level_up', 0 XP) since Phase 3, so the feed maps them directly
        // instead of re-deriving thresholds per page (which duplicated rows
        // across page boundaries).
        $history = collect($paginator->items())->map(function ($tx) {
            $type = match(true) {
                in_array($tx->xp_source, ['completion_bonus', 'spend_amount', 'item_purchase']) => 'order',
                $tx->xp_source === 'review_bonus' => 'review',
                in_array($tx->xp_source, ['challenge_reward', 'daily_challenge', 'weekly_challenge']) => 'challenge',
                $tx->xp_source === 'signup_bonus' => 'signup',
                $tx->xp_source === 'streak_bonus' => 'streak',
                $tx->xp_source === 'referral_bonus' => 'referral',
                $tx->xp_source === 'refund_reversal' => 'refund',
                $tx->xp_source === 'level_up' => 'level_up',
                default => 'other',
            };

            return [
                'type' => $type,
                'xp' => $tx->xp_amount,
                'description' => $tx->description,
                'created_at' => $tx->created_at->toIso8601String(),
            ];
        })->values();

        $totalEarned = XpTransaction::where('user_id', $user->id)
            ->where('xp_amount', '>', 0)
            ->sum('xp_amount');

        return response()->json([
            'history' => $history,
            'total_earned' => (int) $totalEarned,
            'total_size' => $paginator->total(),
            'limit' => (int) $request->limit,
            'offset' => (int) $request->offset,
        ], 200);
    }

    /**
     * Get XP leaderboard.
     */
    public function getLeaderboard(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type', 'global'); // 'global' or 'zone'
        // 'alltime' (default) | 'weekly' | 'monthly'
        $period = $request->query('period', 'alltime');

        if (in_array($period, ['weekly', 'monthly'], true)) {
            return $this->seasonLeaderboard($user, $period);
        }

        return $this->allTimeLeaderboard($user, $type);
    }

    /**
     * All-time leaderboard from users.total_xp, optionally scoped to zone.
     */
    private function allTimeLeaderboard(User $user, string $type)
    {
        $zoneId = ($type === 'zone' && $user->zone_id) ? $user->zone_id : null;

        // Top-20 is identical for everyone in a scope, so cache it briefly.
        $cacheKey = 'xp_leaderboard_' . $type . '_' . ($zoneId ?? 'all');
        $topUsers = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($zoneId) {
            $query = User::where('total_xp', '>', 0)
                ->where('status', 1)
                ->orderByDesc('total_xp');

            if ($zoneId) {
                $query->where('zone_id', $zoneId);
            }

            return $query->limit(20)->get(['id', 'f_name', 'l_name', 'image', 'total_xp', 'level']);
        });

        $leaderboard = $topUsers->map(function ($u, $index) use ($user) {
            return [
                'rank' => $index + 1,
                'name' => $this->displayName($u),
                'total_xp' => $u->total_xp,
                'level' => $u->level,
                'image' => $u->image_full_url,
                'is_me' => $u->id === $user->id,
            ];
        });

        // Requesting user's rank (live — cheap with the (status,total_xp) index).
        $userRankQuery = User::where('total_xp', '>', $user->total_xp)
            ->where('status', 1);
        if ($zoneId) {
            $userRankQuery->where('zone_id', $zoneId);
        }
        $userRank = $userRankQuery->count() + 1;

        return response()->json([
            'leaderboard' => $leaderboard,
            'my_rank' => $userRank,
            'my_xp' => $user->total_xp,
            'my_level' => $user->level,
            'in_top' => $leaderboard->contains('is_me', true),
            'type' => $type,
            'period' => 'alltime',
        ], 200);
    }

    /**
     * Seasonal leaderboard from xp_season_scores for the current period.
     */
    private function seasonLeaderboard(User $user, string $periodType)
    {
        $period = \App\Support\AppClock::periodFor($periodType);

        $cacheKey = "xp_leaderboard_{$periodType}_{$period}";
        $rows = \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($periodType, $period) {
            return \App\Models\XpSeasonScore::query()
                ->where('period_type', $periodType)
                ->where('period', $period)
                ->where('xp_earned', '>', 0)
                ->orderByDesc('xp_earned')
                ->with(['user:id,f_name,l_name,image,level,status'])
                ->limit(20)
                ->get()
                ->filter(fn ($row) => $row->user && $row->user->status == 1)
                ->values();
        });

        $leaderboard = $rows->map(function ($row, $index) use ($user) {
            return [
                'rank' => $index + 1,
                'name' => $this->displayName($row->user),
                'total_xp' => $row->xp_earned,
                'level' => $row->user->level,
                'image' => $row->user->image_full_url,
                'is_me' => $row->user_id === $user->id,
            ];
        });

        // My score + rank this period.
        $myScore = \App\Models\XpSeasonScore::where('user_id', $user->id)
            ->where('period_type', $periodType)
            ->where('period', $period)
            ->value('xp_earned') ?? 0;

        $myRank = \App\Models\XpSeasonScore::where('period_type', $periodType)
            ->where('period', $period)
            ->where('xp_earned', '>', $myScore)
            ->count() + 1;

        return response()->json([
            'leaderboard' => $leaderboard,
            'my_rank' => $myScore > 0 ? $myRank : null,
            'my_xp' => $myScore,
            'my_level' => $user->level,
            'in_top' => $leaderboard->contains('is_me', true),
            'type' => 'global',
            'period' => $periodType,
            'period_key' => $period,
        ], 200);
    }

    /**
     * Public display name: first name + last initial (null-safe).
     */
    private function displayName(User $u): string
    {
        $lastInitial = $u->l_name ? substr($u->l_name, 0, 1) . '.' : '';
        return trim($u->f_name . ' ' . $lastInitial);
    }
}
