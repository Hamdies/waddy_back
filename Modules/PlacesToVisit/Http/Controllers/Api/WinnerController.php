<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Services\WinnerService;

class WinnerController extends Controller
{
    public function __construct(
        protected WinnerService $winnerService
    ) {}

    /**
     * Hall of fame — past weekly winners, newest first
     * GET /api/v1/places/winners
     */
    public function index(Request $request): JsonResponse
    {
        $winners = $this->winnerService->history(
            zoneId: $request->integer('zone_id') ?: null,
            limit: min((int) ($request->limit ?? 24), 52),
        );

        return response()->json([
            'success' => true,
            'data' => $winners->map(fn($w) => $this->winnerService->toApiPayload($w))->values(),
        ]);
    }

    /**
     * Most recent weekly champion (for the in-app news banner)
     * GET /api/v1/places/winners/latest
     */
    public function latest(Request $request): JsonResponse
    {
        $winner = $this->winnerService->latest(
            zoneId: $request->integer('zone_id') ?: null,
        );

        return response()->json([
            'success' => true,
            'data' => $winner ? $this->winnerService->toApiPayload($winner) : null,
        ]);
    }
}
