<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\PlacesToVisit\Entities\PlaceCategory;
use Modules\PlacesToVisit\Entities\PlaceTag;

class PlaceCategoryController extends Controller
{
    /**
     * List all active place categories
     * GET /api/v1/places/categories
     */
    public function index(): JsonResponse
    {
        $categories = PlaceCategory::query()
            ->active()
            ->ordered()
            ->withCount(['places' => fn($q) => $q->active()])
            ->get()
            ->map(fn($cat) => [
                'id' => $cat->id,
                'name' => $cat->localized_name,
                'image' => $cat->image,
                'priority' => $cat->priority,
                'places_count' => $cat->places_count,
            ]);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Get a single category with its places
     * GET /api/v1/places/categories/{id}
     */
    public function show(PlaceCategory $category): JsonResponse
    {
        if (!$category->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.category_not_found'),
            ], 404);
        }

        $category->load(['places' => function ($q) {
            $q->active()->with(['translations', 'images', 'tags']);
        }]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $category->id,
                'name' => $category->localized_name,
                'image' => $category->image,
                'places' => $category->places,
            ],
        ]);
    }

    /**
     * List all active tags
     * GET /api/v1/places/tags
     */
    public function tags(): JsonResponse
    {
        $tags = PlaceTag::active()
            ->get()
            ->map(fn($tag) => [
                'id' => $tag->id,
                'name' => $tag->localized_name,
                'icon' => $tag->icon,
            ]);

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }
}
