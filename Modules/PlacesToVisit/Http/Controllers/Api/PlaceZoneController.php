<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceZone;

class PlaceZoneController extends Controller
{
    /**
     * List all active zones for mobile app
     * GET /api/v1/places/zones
     */
    public function index(Request $request): JsonResponse
    {
        $zones = PlaceZone::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn($zone) => [
                'id' => $zone->id,
                'name' => $zone->localized_name,
                'display_name' => $zone->localized_display_name,
            ]);

        return response()->json([
            'success' => true,
            'data' => $zones,
        ]);
    }

    /**
     * List places filtered by zone
     * GET /api/v1/places/zones/{zone_id}/places
     */
    public function places(Request $request, int $zoneId): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:100',
            'offset' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ]);

        $zone = PlaceZone::findOrFail($zoneId);
        
        $limit = $request->input('limit', 10);
        $offset = $request->input('offset', 1);
        $search = $request->input('search');

        $query = $zone->places()
            ->active()
            ->with([
                'translations',
                'category',
                'zone',
                'images',
                'tags' => fn($q) => $q->active(),
            ])
            ->withCount('favorites');

        if ($search) {
            $query->whereHas('translations', fn($q) => 
                $q->where('title', 'like', "%{$search}%")
                   ->orWhere('description', 'like', "%{$search}%")
            );
        }

        $total = $query->count();
        $places = $query->latest()
            ->limit($limit)
            ->offset(($offset - 1) * $limit)
            ->get()
            ->map(fn($place) => [
                'id' => $place->id,
                'title' => $place->title,
                'description' => $place->description,
                'image' => $place->main_image,
                'cover_image' => $place->cover_image,
                'category' => $place->category ? [
                    'id' => $place->category->id,
                    'name' => $place->category->localized_name,
                ] : null,
                'zone' => [
                    'id' => $place->zone->id,
                    'name' => $place->zone->localized_name,
                ],
                'rating' => $place->average_rating ?? 0,
                'review_count' => $place->votes_count ?? 0,
                'favorites_count' => $place->favorites_count ?? 0,
            ]);

        return response()->json([
            'success' => true,
            'data' => $places,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'pages' => ceil($total / $limit),
            ],
        ]);
    }
}
