<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Modules\PlacesToVisit\Entities\Place;

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
    public function getTopPlaces(?string $period = null, ?int $categoryId = null): Collection
    {
        $period = $period ?? now()->format('Y-m');
        $cacheKey = "leaderboard:{$period}:" . ($categoryId ?? 'all');

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($period, $categoryId) {
            return Place::query()
                ->active()
                ->with(['translations', 'category'])
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                ->withCount(['votes' => fn($q) => $q->where('period', $period)])
                ->withAvg(['votes' => fn($q) => $q->where('period', $period)->whereNotNull('rating')], 'rating')
                ->having('votes_count', '>=', $this->minVotes)
                ->orderByDesc('votes_count')      // PRIMARY: Total votes (popularity)
                ->orderByDesc('votes_avg_rating') // SECONDARY: Quality
                ->limit($this->limit)
                ->get()
                ->map(fn($place) => [
                    'id' => $place->id,
                    'title' => $place->title,
                    'description' => $place->description,
                    'image' => $place->image,
                    'category' => $place->category?->name,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    'votes_count' => $place->votes_count,
                    'avg_rating' => round($place->votes_avg_rating ?? 0, 1),
                ]);
        });
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
        Cache::forget("leaderboard:{$period}:all");
        
        // Also clear category-specific caches
        $categories = \Modules\PlacesToVisit\Entities\PlaceCategory::pluck('id');
        foreach ($categories as $categoryId) {
            Cache::forget("leaderboard:{$period}:{$categoryId}");
        }
    }
}
