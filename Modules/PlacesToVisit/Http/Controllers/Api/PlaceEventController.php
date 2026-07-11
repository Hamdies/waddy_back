<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlaceEvent;

class PlaceEventController extends Controller
{
    /**
     * KPI event ingestion from the app (fire-and-forget on the client).
     * POST /api/v1/places/events
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event' => 'required|string|in:' . implode(',', PlaceEvent::CLIENT_EVENTS),
            'place_id' => 'nullable|integer',
            'zone_id' => 'nullable|integer',
        ]);

        PlaceEvent::log(
            event: $request->string('event'),
            userId: auth('api')->id(),
            placeId: $request->integer('place_id') ?: null,
            zoneId: $request->integer('zone_id') ?: null,
        );

        return response()->json(['success' => true]);
    }
}
