<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Services\PrizeDrawService;

class PrizeController extends Controller
{
    public function __construct(
        protected PrizeDrawService $prizeDrawService
    ) {}

    /**
     * The signed-in user's vouchers — live ones first, then the archive.
     * GET /api/v1/places/prizes/my
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $prizes = PlacePrize::with(['place.translations'])
            ->where('user_id', $userId)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        // Reconcile lazily so a voucher never *looks* live past its window
        // just because the hourly cron hasn't fired yet.
        $prizes->each(function (PlacePrize $prize) {
            if ($prize->status === PlacePrize::STATUS_ACTIVE && $prize->isExpired()) {
                $prize->update(['status' => PlacePrize::STATUS_EXPIRED]);
            }
        });

        [$active, $history] = $prizes->partition(
            fn(PlacePrize $prize) => $prize->status === PlacePrize::STATUS_ACTIVE
        );

        return response()->json([
            'success' => true,
            'data' => [
                'active' => $active->map(fn($p) => $this->prizeDrawService->toApiPayload($p))->values(),
                'history' => $history->map(fn($p) => $this->prizeDrawService->toApiPayload($p))->values(),
            ],
        ]);
    }

    /**
     * A single voucher (for the win-card / deep link from the push).
     * GET /api/v1/places/prizes/{prize}
     */
    public function show(Request $request, int $prize): JsonResponse
    {
        $record = PlacePrize::with(['place.translations'])
            ->where('user_id', $request->user()->id)
            ->find($prize);

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.prize_not_found'),
            ], 404);
        }

        if ($record->status === PlacePrize::STATUS_ACTIVE && $record->isExpired()) {
            $record->update(['status' => PlacePrize::STATUS_EXPIRED]);
        }

        return response()->json([
            'success' => true,
            'data' => $this->prizeDrawService->toApiPayload($record),
        ]);
    }
}
