<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserChallenge;
use App\Models\UserLevelPrize;
use App\Models\XpTransaction;
use App\Models\Level;
use App\Models\RewardItem;
use App\Services\XpService;
use App\Services\ChallengeService;
use App\CentralLogics\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class XpController extends Controller
{
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
     */
    public function getAllLevels()
    {
        $levels = Level::active()
            ->with(['prizes' => function($q) {
                $q->where('status', 1);
            }])
            ->orderBy('level_number')
            ->get()
            ->map(function ($level) {
                return [
                    'level_number' => $level->level_number,
                    'name' => $level->name,
                    'xp_required' => $level->xp_required,
                    'description' => $level->description,
                    'badge_image' => $level->badge_image_url,
                    'prizes' => $level->prizes->map(function ($prize) {
                        return [
                            'id' => $prize->id,
                            'title' => $prize->title,
                            'description' => $prize->description,
                            'prize_type' => $prize->prize_type,
                            'value' => $prize->value,
                            'validity_days' => $prize->validity_days,
                        ];
                    }),
                ];
            });

        return response()->json(['levels' => $levels], 200);
    }

    /**
     * Get user's claimable free_delivery prizes for checkout.
     */
    public function getCheckoutPrizes(Request $request)
    {
        $user = $request->user();
        $orderAmount = (float) $request->query('order_amount', 0);

        $prizes = UserLevelPrize::where('user_id', $user->id)
            ->where('status', 'claimed')
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
     * Get user's XP transaction history.
     */
    public function getTransactions(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'required|integer|min:1|max:50',
            'offset' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $paginator = XpTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->limit, ['*'], 'page', $request->offset);

        $data = [
            'total_size' => $paginator->total(),
            'limit' => (int) $request->limit,
            'offset' => (int) $request->offset,
            'transactions' => $paginator->items(),
        ];

        return response()->json($data, 200);
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

        $userPrize = UserLevelPrize::where('id', $id)
            ->where('user_id', $user->id)
            ->with('prize')
            ->first();

        if (!$userPrize) {
            return response()->json([
                'errors' => [['code' => 'prize', 'message' => translate('messages.prize_not_found')]]
            ], 404);
        }

        if (!in_array($userPrize->status, ['unlocked'])) {
            return response()->json([
                'errors' => [['code' => 'prize', 'message' => translate('messages.prize_already_claimed_or_used')]]
            ], 403);
        }

        if ($userPrize->isExpired()) {
            $userPrize->expire();
            return response()->json([
                'errors' => [['code' => 'prize', 'message' => translate('messages.prize_expired')]]
            ], 403);
        }

        // Mark as claimed
        $userPrize->update([
            'status' => 'claimed',
            'claimed_at' => now(),
        ]);

        // Handle wallet credit prizes
        if ($userPrize->prize->prize_type === 'wallet_credit' && $userPrize->prize->value > 0) {
            // Add to wallet using existing CustomerLogic
            \App\CentralLogics\CustomerLogic::create_wallet_transaction(
                $user->id,
                $userPrize->prize->value,
                'add_fund_by_admin',
                'Level Prize: ' . $userPrize->prize->title
            );
            
            $userPrize->markUsed();
        }

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
}
