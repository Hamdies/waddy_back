<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\PlacesToVisit\Entities\Place;

class TrendingService
{
    protected int $limit;
    protected int $cacheMinutes;
    protected int $trendingWindowDays;

    public function __construct()
    {
        $this->limit = config('placestovisit.trending.limit', 10);
        $this->cacheMinutes = config('placestovisit.trending.cache_minutes', 30);
        $this->trendingWindowDays = config('placestovisit.trending.window_days', 7);
    }

    /**
     * Get trending places — weighted by vote recency
     * Places that received many votes recently rank higher than places
     * with the same total votes spread over the entire month.
     */
    public function getTrending(?int $categoryId = null): Collection
    {
        $cacheKey = "trending:places:" . ($categoryId ?? 'all');

        return Cache::remember($cacheKey, $this->cacheMinutes * 60, function () use ($categoryId) {
            $windowStart = now()->subDays($this->trendingWindowDays);
            $period = now()->format('Y-m');

            return Place::query()
                ->active()
                ->with(['translations', 'category'])
                ->when($categoryId, fn($q) => $q->where('category_id', $categoryId))
                // Count recent votes (within trending window)
                ->withCount([
                    'votes as recent_votes_count' => fn($q) => $q
                        ->where('period', $period)
                        ->where('created_at', '>=', $windowStart),
                ])
                // Count total period votes
                ->withCount([
                    'votes as total_votes_count' => fn($q) => $q->where('period', $period),
                ])
                // Average rating
                ->withAvg([
                    'votes' => fn($q) => $q->where('period', $period)->whereNotNull('rating'),
                ], 'rating')
                ->having('total_votes_count', '>', 0)
                // Trending score: recent votes weighted 2x + total votes
                ->orderByRaw('(recent_votes_count * 2 + total_votes_count) DESC')
                ->orderByDesc('votes_avg_rating')
                ->limit($this->limit)
                ->get()
                ->map(fn($place) => [
                    'id' => $place->id,
                    'title' => $place->title,
                    'description' => $place->description,
                    'image' => $place->image,
                    'category' => $place->category?->localized_name,
                    'latitude' => $place->latitude,
                    'longitude' => $place->longitude,
                    'recent_votes' => $place->recent_votes_count,
                    'total_votes' => $place->total_votes_count,
                    'avg_rating' => round($place->votes_avg_rating ?? 0, 1),
                ]);
        });
    }

    /**
     * Clear trending cache
     */
    public function clearCache(): void
    {
        Cache::forget('trending:places:all');
        $categories = \Modules\PlacesToVisit\Entities\PlaceCategory::pluck('id');
        foreach ($categories as $categoryId) {
            Cache::forget("trending:places:{$categoryId}");
        }
    }
}
