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
        $period = $period ?? now()->format('Y-m');
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
     * Get top voters (chillers) ranked by number of votes
     */
    public function getTopVoters(?string $period = null, ?int $zoneId = null, int $limit = 10): Collection
    {
        $period = $period ?? now()->format('Y-m');
        $maxLimit = max($limit, 10);
        $cacheKey = "top_voters:{$period}:" . ($zoneId ?? 'all');

        $fullList = Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($period, $zoneId, $maxLimit) {
            $topVoters = PlaceVote::query()
                ->select('user_id', DB::raw('COUNT(*) as votes_count'))
                ->where('period', $period)
                ->when($zoneId, fn($q) => $q->whereHas('place', fn($pq) => $pq->where('zone_id', $zoneId)))
                ->groupBy('user_id')
                ->orderByDesc('votes_count')
                ->limit($maxLimit)
                ->get();

            return $topVoters->map(function ($row, $index) {
                $user = User::select('id', 'f_name', 'l_name', 'image')->with('storage')->find($row->user_id);
                return [
                    'position' => $index + 1,
                    'user_id' => $row->user_id,
                    'username' => $user?->f_name,
                    'image' => $user?->image_full_url,
                    'votes_count' => (int) $row->votes_count,
                ];
            });
        });

        return $fullList->take($limit)->values();
    }

    /**
     * Get current voting period
     */
    public function getCurrentPeriod(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Get available periods (last 12 months)
     */
    public function getAvailablePeriods(): array
    {
        $periods = [];
        for ($i = 0; $i < 12; $i++) {
            $periods[] = now()->subMonths($i)->format('Y-m');
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

        // Clear top voters caches
        Cache::forget("top_voters:{$period}:all");
        foreach ($zones as $zoneId) {
            Cache::forget("top_voters:{$period}:{$zoneId}");
        }
    }
}
