<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Services\LeaderboardService;
use Modules\PlacesToVisit\Services\TrendingService;

class PlaceController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService,
        protected TrendingService $trendingService
    ) {}

    /**
     * List places with filters and sorting
     * GET /api/v1/places
     */
    public function index(Request $request): JsonResponse
    {
        $period = now()->format('o-\WW');
        $userId = auth('api')->id();

        // Param aliases used by the mobile app
        if (!$request->filled('latitude') && $request->filled('lat')) {
            $request->merge(['latitude' => $request->lat]);
        }
        if (!$request->filled('longitude') && $request->filled('lng')) {
            $request->merge(['longitude' => $request->lng]);
        }


        $query = Place::query()
            ->active()
            ->with([
                'translations',
                'category',
                'zone',
                'offers' => fn($q) => $q->active()->current(),
                'images',
                'tags' => fn($q) => $q->active(),
            ])
            ->withCurrentPeriodStats($period)
            ->withCount('favorites')
            ->when($request->category_id, fn($q, $catId) => $q->where('category_id', $catId))
            ->when($request->zone_id, fn($q, $zoneId) => $q->where('zone_id', $zoneId))
            ->when($request->featured, fn($q) => $q->featured())
            ->when($request->tag_ids, function ($q, $tagIds) {
                $ids = is_array($tagIds) ? $tagIds : explode(',', $tagIds);
                $q->withTags($ids);
            })
            ->when($request->latitude && $request->longitude, function ($q) use ($request) {
                $q->nearby(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) ($request->radius ?? 10)
                );
            })
            ->when($request->search, function ($q, $search) {
                $q->whereHas('translations', fn($tq) => 
                    $tq->where('title', 'like', "%{$search}%")
                       ->orWhere('description', 'like', "%{$search}%")
                );
            });

        // Sorting
        $sortBy = $request->sort_by ?? 'newest';
        $query = match ($sortBy) {
            'votes' => $query->orderByDesc('votes_count'),
            'rating' => $query->orderByDesc('votes_avg_rating'),
            'featured' => $query->orderByDesc('is_featured')->latest(),
            'distance' => $query, // Already ordered by distance when nearby is used
            default => $query->latest(), // 'newest'
        };

        // App sends `offset` as the page number; Laravel expects `page`
        $page = (int) ($request->page ?? $request->offset ?? 1);
        $places = $query->paginate($request->per_page ?? 15, ['*'], 'page', $page);

        // Champion badges: overall weekly titles + reigning champion flag
        $placeIds = collect($places->items())->pluck('id');
        $titleCounts = \Modules\PlacesToVisit\Entities\PlaceWinner::query()
            ->whereIn('place_id', $placeIds)
            ->whereNull('zone_id')
            ->selectRaw('place_id, COUNT(*) as titles')
            ->groupBy('place_id')
            ->pluck('titles', 'place_id');
        $lastPeriod = app(\Modules\PlacesToVisit\Services\WinnerService::class)->lastClosedPeriod();
        $reigning = \Modules\PlacesToVisit\Entities\PlaceWinner::query()
            ->whereIn('place_id', $placeIds)
            ->where('period', $lastPeriod)
            ->pluck('place_id')
            ->unique()
            ->flip();

        // Append user-specific data
        $placesData = collect($places->items())->map(function ($place) use ($userId, $titleCounts, $reigning) {
            $data = $place->toArray();
            $data['is_favorited'] = $userId ? $place->isUserFavorite($userId) : false;
            $data['is_open_now'] = $place->isOpenNow();
            $data['titles_count'] = (int) ($titleCounts[$place->id] ?? 0);
            $data['is_current_champion'] = isset($reigning[$place->id]);
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $placesData,
            'total_size' => $places->total(),
            'offset' => $places->currentPage(),
            'meta' => [
                'current_page' => $places->currentPage(),
                'last_page' => $places->lastPage(),
                'per_page' => $places->perPage(),
                'total' => $places->total(),
            ],
        ]);
    }

    /**
     * Get place details
     * GET /api/v1/places/{id}
     */
    public function show(Request $request, Place $place): JsonResponse
    {
        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $period = now()->format('o-\WW');
        $userId = auth('api')->id();

        $place->load([
            'translations',
            'category',
            'zone',
            'offers' => fn($q) => $q->active()->current(),
            'images',
            'tags' => fn($q) => $q->active(),
        ]);
        
        // Get voting stats
        $place->loadCount(['votes' => fn($q) => $q->where('period', $period)]);
        $place->loadCount('favorites');
        $avgRating = $place->votes()
            ->where('period', $period)
            ->whereNotNull('rating')
            ->avg('rating');

        // Get reviews (non-flagged) — first page
        $reviews = $place->votes()
            ->where('period', $period)
            ->notFlagged()
            ->withReview()
            ->with('user:id,f_name,l_name,image')
            ->latest()
            ->take(10)
            ->get(['id', 'user_id', 'rating', 'review', 'image', 'created_at']);

        // Check if current user has voted
        $userVote = null;
        $isFavorited = false;
        if ($userId) {
            $userVote = $place->votes()
                ->where('user_id', $userId)
                ->where('period', $period)
                ->first(['id', 'rating', 'review', 'image']);
            $isFavorited = $place->isUserFavorite($userId);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $place->id,
                'title' => $place->title,
                'description' => $place->description,
                'image' => $place->image,
                'cover_image' => $place->cover_image,
                'images' => $place->images,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'address' => $place->address,
                'phone' => $place->phone,
                'website' => $place->website,
                'instagram' => $place->instagram,
                'opening_hours' => $place->opening_hours,
                'is_open_now' => $place->isOpenNow(),
                'is_featured' => $place->is_featured,
                'is_favorited' => $isFavorited,
                'favorites_count' => $place->favorites_count,
                'category' => $place->category,
                'zone' => $place->zone?->display_name,
                'tags' => $place->tags,
                'offers' => $place->offers,
                'titles_count' => \Modules\PlacesToVisit\Entities\PlaceWinner::titleCount($place->id),
                'is_current_champion' => \Modules\PlacesToVisit\Entities\PlaceWinner::query()
                    ->where('place_id', $place->id)
                    ->where('period', app(\Modules\PlacesToVisit\Services\WinnerService::class)->lastClosedPeriod())
                    ->exists(),
                'stats' => [
                    'votes_count' => $place->votes_count,
                    'avg_rating' => round($avgRating ?? 0, 1),
                    'period' => $period,
                ],
                'reviews' => $reviews,
                'user_vote' => $userVote,
            ],
        ]);
    }

    /**
     * Get leaderboard
     * GET /api/v1/places/leaderboard
     */
    public function leaderboard(Request $request): JsonResponse
    {
        $period = $request->period ?? now()->format('o-\WW');
        $categoryId = $request->category_id;
        $zoneId = $request->zone_id ? (int) $request->zone_id : null;
        $limit = $request->limit ? (int) $request->limit : null;

        $topPlaces = $this->leaderboardService->getTopPlaces($period, $categoryId, $zoneId, $limit);

        return response()->json([
            'success' => true,
            'period' => $period,
            'current_period' => $this->leaderboardService->getCurrentPeriod(),
            'data' => $topPlaces,
        ]);
    }

    /**
     * Get top voters (chillers)
     * GET /api/v1/places/top-voters
     */
    public function topVoters(Request $request): JsonResponse
    {
        $period = $request->period ?? now()->format('o-\WW');
        $zoneId = $request->zone_id ? (int) $request->zone_id : null;
        $limit = $request->limit ? (int) $request->limit : 10;

        $topVoters = $this->leaderboardService->getTopVoters($period, $zoneId, $limit);

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $topVoters,
        ]);
    }

    /**
     * Get trending / rising places
     * GET /api/v1/places/trending
     */
    public function trending(Request $request): JsonResponse
    {
        $categoryId = $request->category_id;

        $trending = $this->trendingService->getTrending($categoryId);

        return response()->json([
            'success' => true,
            'data' => $trending,
        ]);
    }
}
