<?php

namespace Modules\PlacesToVisit\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceVote;

class LeaderboardService
{
    protected int $minVotes;
    protected int $limit;
    protected int $cacheMinutes;

    public function __construct()
    {
        $this->minVotes = config('placestovisit.min_votes_for_leaderboard', 5);
        $this->limit = config('placestovisit.leaderboard_limit', 10);
        $this->cacheMinutes = config('placestovisit.leaderboard_cache_minutes', 60);
    }

    /**
     * Get top places for leaderboard (votes-first ranking)
     */
    public function getTopPlaces(?string $period = null, ?int $categoryId = null, ?int $zoneId = null, ?int $limit = null): Collection
    {
        $period = $period ?? \Modules\PlacesToVisit\Services\RaceClock::period();
        $cacheKey = "leaderboard:{$period}:" . ($categoryId ?? 'all') . ':' . ($zoneId ?? 'all');

        // Cache the full top list once, then slice per request — a request
        // with a small limit must never poison the cache for other callers.
        $fullList = Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($period, $categoryId, $zoneId) {
            return Place::query()
                ->active()
                ->with(['translations', 'category', 'zone'])
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->when($zoneId, fn($q) => $q->where('zone_id', $zoneId))
                ->withCount(['votes' => fn($q) => $q->where('period', $period)])
                ->withAvg(['votes' => fn($q) => $q->where('period', $period)->whereNotNull('rating')], 'rating')
                ->having('votes_count', '>=', $this->minVotes)
                ->orderByDesc('votes_count')      // PRIMARY: Total votes (popularity)
                ->orderByDesc('votes_avg_rating') // SECONDARY: Quality
                ->limit($this->limit)
                ->get()
                ->values()
                ->map(fn($place, $index) => [
                    'id' => $place->id,
                    'rank' => $index + 1,
                    'title' => $place->title,
                    'description' => $place->description,
                    'image' => $place->image,
                    'cover_image' => $place->cover_image,
                    'category' => $place->category?->name,
                    'zone' => $place->zone?->display_name,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    'votes_count' => $place->votes_count,
                    'avg_rating' => round($place->votes_avg_rating ?? 0, 1),
                ]);
        });

        return $limit ? $fullList->take($limit)->values() : $fullList;
    }

    /**
     * Top voters — the loyalty layer, and the one place in Spots where
     * history matters.
     *
     * Ranked on CUMULATIVE points: one point per week a user cast their vote,
     * summed across every week they've played. Missing a week *pauses* you
     * (you stop gaining, you keep your total); it never resets — resets punish
     * an otherwise low-friction habit and churn users out of a young feature.
     *
     * Points are derived from place_votes rather than kept in their own table:
     * with one vote per user per week, COUNT(DISTINCT period) *is* the score,
     * and a derived score can't drift out of sync with the votes it describes.
     *
     * @param string $scope 'all_time' (cumulative, the real leaderboard) or
     *                      'week' (single-period counts — retained for callers
     *                      that still want the weekly slice)
     */
    public function getTopVoters(?int $zoneId = null, int $limit = 10, string $scope = 'all_time', ?string $period = null): Collection
    {
        $maxLimit = max($limit, 10);

        if ($scope === 'week') {
            $period = $period ?? \Modules\PlacesToVisit\Services\RaceClock::period();
            $cacheKey = "top_voters:week:{$period}:" . ($zoneId ?? 'all');
            $query = fn() => PlaceVote::query()
                ->select('user_id', DB::raw('COUNT(*) as points'), DB::raw('MIN(id) as first_vote_id'))
                ->where('period', $period)
                ->where('is_flagged', false)
                ->when($zoneId, fn($q) => $q->whereHas('place', fn($pq) => $pq->where('zone_id', $zoneId)))
                ->groupBy('user_id')
                ->orderByDesc('points')
                ->orderBy('first_vote_id')
                ->limit($maxLimit)
                ->get();
        } else {
            $cacheKey = 'top_voters:all_time:' . ($zoneId ?? 'all');
            $query = fn() => PlaceVote::query()
                ->select('user_id', DB::raw('COUNT(DISTINCT period) as points'), DB::raw('MIN(id) as first_vote_id'))
                ->where('is_flagged', false)
                ->when($zoneId, fn($q) => $q->whereHas('place', fn($pq) => $pq->where('zone_id', $zoneId)))
                ->groupBy('user_id')
                ->orderByDesc('points')
                ->orderBy('first_vote_id') // ties go to whoever started first
                ->limit($maxLimit)
                ->get();
        }

        $fullList = Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($query) {
            return $query()->map(function ($row, $index) {
                $user = User::select('id', 'f_name', 'l_name', 'image')->with('storage')->find($row->user_id);
                return [
                    'position' => $index + 1,
                    'user_id' => $row->user_id,
                    'username' => $user?->f_name,
                    'image' => $user?->image_full_url,
                    'points' => (int) $row->points,
                    // Legacy key — the app parses this today. Kept so an older
                    // build doesn't render a wall of zeroes mid-rollout.
                    'votes_count' => (int) $row->points,
                ];
            });
        });

        return $fullList->take($limit)->values();
    }

    /**
     * Recent voter-prize winners — social proof, not a ranking.
     *
     * Names are trimmed to "Ahmed H." and the voucher code is never exposed:
     * this feed is public and a code is bearer-grade until it's redeemed.
     */
    public function getRecentPrizeWinners(int $limit = 10): Collection
    {
        $limit = max(1, min($limit, 30));

        return Cache::remember("recent_prize_winners:{$limit}", 15 * 60, function () use ($limit) {
            return \Modules\PlacesToVisit\Entities\PlacePrize::with(['place.translations', 'user'])
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->map(function ($prize) {
                    $user = $prize->user;
                    $first = trim((string) ($user?->f_name ?? ''));
                    $lastInitial = trim((string) ($user?->l_name ?? ''));
                    $lastInitial = $lastInitial !== '' ? mb_substr($lastInitial, 0, 1) . '.' : '';
                    $name = trim("{$first} {$lastInitial}");

                    return [
                        'id' => $prize->id,
                        'period' => $prize->period,
                        'username' => $name !== '' ? $name : translate('messages.a_waddi_voter'),
                        'image' => $user?->image_full_url,
                        'won_at' => $prize->created_at?->toIso8601String(),
                        'place' => $prize->place ? [
                            'id' => $prize->place->id,
                            'title' => $prize->place->title,
                            'image' => $prize->place->image,
                        ] : null,
                    ];
                })
                ->values();
        });
    }

    /**
     * Get current voting period
     */
    public function getCurrentPeriod(): string
    {
        return \Modules\PlacesToVisit\Services\RaceClock::period();
    }

    /**
     * Get available periods (last 12 weeks)
     */
    public function getAvailablePeriods(): array
    {
        $periods = [];
        for ($i = 0; $i < 12; $i++) {
            $periods[] = RaceClock::now()->subWeeks($i)->format('o-\WW');
        }
        return $periods;
    }

    /**
     * Clear leaderboard cache
     */
    public function clearCache(?string $period = null): void
    {
        $period = $period ?? $this->getCurrentPeriod();
        
        // Clear leaderboard caches
        Cache::forget("leaderboard:{$period}:all:all");
        
        // Clear category-specific and zone-specific caches
        $categories = \Modules\PlacesToVisit\Entities\PlaceCategory::pluck('id');
        $zones = \Modules\PlacesToVisit\Entities\PlaceZone::pluck('id');
        
        foreach ($categories as $categoryId) {
            Cache::forget("leaderboard:{$period}:{$categoryId}:all");
            foreach ($zones as $zoneId) {
                Cache::forget("leaderboard:{$period}:{$categoryId}:{$zoneId}");
            }
        }
        foreach ($zones as $zoneId) {
            Cache::forget("leaderboard:{$period}:all:{$zoneId}");
        }

        // Clear top voters caches — both the weekly slice and the cumulative
        // all-time board, which a new vote can also reorder.
        Cache::forget("top_voters:week:{$period}:all");
        Cache::forget('top_voters:all_time:all');
        foreach ($zones as $zoneId) {
            Cache::forget("top_voters:week:{$period}:{$zoneId}");
            Cache::forget("top_voters:all_time:{$zoneId}");
        }
    }

    /**
     * Drop the recent-winners feed after a draw. Cached per limit, so clear
     * the handful of limits the app actually asks for.
     */
    public function clearRecentWinnersCache(): void
    {
        foreach ([5, 8, 10, 12, 15, 20, 24, 30] as $limit) {
            Cache::forget("recent_prize_winners:{$limit}");
        }
    }
}
