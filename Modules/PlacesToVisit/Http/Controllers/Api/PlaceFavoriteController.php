<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceFavorite;
use Modules\PlacesToVisit\Entities\Place;

class PlaceFavoriteController extends Controller
{
    /**
     * List user's favorite places
     * GET /api/v1/places/favorites
     */
    public function index(Request $request): JsonResponse
    {
        $userId = auth()->id();
        $period = now()->format('Y-m');

        $favorites = PlaceFavorite::where('user_id', $userId)
            ->with([
                'place' => fn($q) => $q->active()->with(['translations', 'category', 'images'])
                    ->withCount(['votes' => fn($vq) => $vq->where('period', $period)])
                    ->withAvg(['votes' => fn($vq) => $vq->where('period', $period)->whereNotNull('rating')], 'rating'),
            ])
            ->latest()
            ->paginate($request->per_page ?? 15);

        $data = collect($favorites->items())
            ->filter(fn($fav) => $fav->place !== null) // filter out deleted places
            ->map(fn($fav) => [
                'id' => $fav->id,
                'favorited_at' => $fav->created_at,
                'place' => [
                    'id' => $fav->place->id,
                    'title' => $fav->place->title,
                    'description' => $fav->place->description,
                    'image' => $fav->place->image,
                    'category' => $fav->place->category?->localized_name,
                    'votes_count' => $fav->place->votes_count,
                    'avg_rating' => round($fav->place->votes_avg_rating ?? 0, 1),
                    'is_open_now' => $fav->place->isOpenNow(),
                ],
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $favorites->currentPage(),
                'last_page' => $favorites->lastPage(),
                'per_page' => $favorites->perPage(),
                'total' => $favorites->total(),
            ],
        ]);
    }

    /**
     * Add place to favorites
     * POST /api/v1/places/{place}/favorite
     */
    public function store(Place $place): JsonResponse
    {
        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $userId = auth()->id();

        // Check if already favorited
        $exists = PlaceFavorite::where('user_id', $userId)
            ->where('place_id', $place->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.already_favorited'),
            ], 422);
        }

        PlaceFavorite::create([
            'user_id' => $userId,
            'place_id' => $place->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => translate('messages.place_favorited'),
        ]);
    }

    /**
     * Remove place from favorites
     * DELETE /api/v1/places/{place}/favorite
     */
    public function destroy(Place $place): JsonResponse
    {
        $deleted = PlaceFavorite::where('user_id', auth()->id())
            ->where('place_id', $place->id)
            ->delete();

        return response()->json([
            'success' => $deleted > 0,
            'message' => $deleted > 0
                ? translate('messages.favorite_removed')
                : translate('messages.not_in_favorites'),
        ], $deleted > 0 ? 200 : 404);
    }

    /**
     * Toggle favorite status
     * POST /api/v1/places/{place}/toggle-favorite
     */
    public function toggle(Place $place): JsonResponse
    {
        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $userId = auth()->id();

        $favorite = PlaceFavorite::where('user_id', $userId)
            ->where('place_id', $place->id)
            ->first();

        if ($favorite) {
            $favorite->delete();
            return response()->json([
                'success' => true,
                'is_favorited' => false,
                'message' => translate('messages.favorite_removed'),
            ]);
        }

        PlaceFavorite::create([
            'user_id' => $userId,
            'place_id' => $place->id,
        ]);

        return response()->json([
            'success' => true,
            'is_favorited' => true,
            'message' => translate('messages.place_favorited'),
        ]);
    }
}
