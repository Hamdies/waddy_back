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
        $period = now()->format('Y-m');
        $userId = auth('api')->id();
        
        $query = Place::query()
            ->active()
            ->with([
                'translations',
                'category',
                'offers' => fn($q) => $q->active()->current(),
                'images',
                'tags' => fn($q) => $q->active(),
            ])
            ->withCurrentPeriodStats($period)
            ->withCount('favorites')
            ->when($request->category_id, fn($q, $catId) => $q->where('category_id', $catId))
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

        $places = $query->paginate($request->per_page ?? 15);

        // Append user-specific data
        $placesData = collect($places->items())->map(function ($place) use ($userId) {
            $data = $place->toArray();
            $data['is_favorited'] = $userId ? $place->isUserFavorite($userId) : false;
            $data['is_open_now'] = $place->isOpenNow();
            return $data;
        });

        return response()->json([
            'success' => true,
            'data' => $placesData,
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

        $period = now()->format('Y-m');
        $userId = auth('api')->id();

        $place->load([
            'translations',
            'category',
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
                'tags' => $place->tags,
                'offers' => $place->offers,
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
        $period = $request->period ?? now()->format('Y-m');
        $categoryId = $request->category_id;

        $topPlaces = $this->leaderboardService->getTopPlaces($period, $categoryId);

        return response()->json([
            'success' => true,
            'period' => $period,
            'current_period' => $this->leaderboardService->getCurrentPeriod(),
            'data' => $topPlaces,
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
