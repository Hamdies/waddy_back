<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Services\LeaderboardService;

class PlaceController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService
    ) {}

    /**
     * List places with filters
     * GET /api/v1/places
     */
    public function index(Request $request): JsonResponse
    {
        $period = now()->format('Y-m');
        
        $places = Place::query()
            ->active()
            ->with(['translations', 'category', 'offers' => fn($q) => $q->active()->current()])
            ->withCurrentPeriodStats($period)
            ->when($request->category_id, fn($q, $catId) => $q->where('category_id', $catId))
            ->when($request->featured, fn($q) => $q->featured())
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
            })
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'success' => true,
            'data' => $places->items(),
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

        $place->load(['translations', 'category', 'offers' => fn($q) => $q->active()->current()]);
        
        // Get voting stats
        $place->loadCount(['votes' => fn($q) => $q->where('period', $period)]);
        $avgRating = $place->votes()
            ->where('period', $period)
            ->whereNotNull('rating')
            ->avg('rating');

        // Get reviews (non-flagged)
        $reviews = $place->votes()
            ->where('period', $period)
            ->notFlagged()
            ->withReview()
            ->with('user:id,f_name,l_name,image')
            ->latest()
            ->take(10)
            ->get(['id', 'user_id', 'rating', 'review', 'created_at']);

        // Check if current user has voted
        $userVote = null;
        if ($userId) {
            $userVote = $place->votes()
                ->where('user_id', $userId)
                ->where('period', $period)
                ->first(['id', 'rating', 'review']);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $place->id,
                'title' => $place->title,
                'description' => $place->description,
                'image' => $place->image,
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'address' => $place->address,
                'is_featured' => $place->is_featured,
                'category' => $place->category,
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
}
