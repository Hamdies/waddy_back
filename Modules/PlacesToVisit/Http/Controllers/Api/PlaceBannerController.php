<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceBanner;

class PlaceBannerController extends Controller
{
    /**
     * List active banners
     * GET /api/v1/places/banners
     */
    public function index(Request $request): JsonResponse
    {
        $zoneId = $request->header('zoneId') ?? $request->zone_id;
        
        $banners = PlaceBanner::query()
            ->active()
            ->valid()
            ->when($zoneId, fn($q) => $q->inZone($zoneId))
            ->when($request->featured, fn($q) => $q->featured())
            ->ordered()
            ->get()
            ->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->localized_title,
                    'description' => $banner->localized_description,
                    'image' => $banner->image_full_url,
                    'type' => $banner->type,
                    'data' => $banner->data,
                    'external_link' => $banner->external_link,
                    'is_featured' => $banner->is_featured,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }

    /**
     * Get featured banners
     * GET /api/v1/places/banners/featured
     */
    public function featured(Request $request): JsonResponse
    {
        $zoneId = $request->header('zoneId') ?? $request->zone_id;
        
        $banners = PlaceBanner::query()
            ->active()
            ->valid()
            ->featured()
            ->when($zoneId, fn($q) => $q->inZone($zoneId))
            ->ordered()
            ->take(5)
            ->get()
            ->map(function ($banner) {
                return [
                    'id' => $banner->id,
                    'title' => $banner->localized_title,
                    'description' => $banner->localized_description,
                    'image' => $banner->image_full_url,
                    'type' => $banner->type,
                    'data' => $banner->data,
                    'external_link' => $banner->external_link,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $banners,
        ]);
    }
}
