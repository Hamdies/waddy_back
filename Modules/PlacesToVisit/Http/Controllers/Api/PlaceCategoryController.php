<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\PlacesToVisit\Entities\PlaceCategory;

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
            ->get(['id', 'name', 'image', 'priority']);

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
            $q->active()->with('translations');
        }]);

        return response()->json([
            'success' => true,
            'data' => $category,
        ]);
    }
}
