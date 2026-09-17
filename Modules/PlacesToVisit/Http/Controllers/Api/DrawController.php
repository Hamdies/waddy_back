<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\PlacesToVisit\Entities\PlaceDrawEntrant;
use Modules\PlacesToVisit\Entities\PlacePrize;
use Modules\PlacesToVisit\Services\WinnerService;

/**
 * The claw-machine replay: who was in the draw, and who the server pulled.
 *
 * Everything here is history. `PrizeDrawService::drawFor()` decided the result
 * at crown time, created the vouchers and pushed the winners, all before this
 * endpoint is ever called. The client animates a decided outcome — it is not
 * asking who won, it is asking what happened.
 */
class DrawController extends Controller
{
    public function __construct(
        protected WinnerService $winnerService,
    ) {}

    /**
     * GET /api/v1/places/draw/{period?}
     *
     * Public. Auth is optional and only adds `is_me` / `my_prize_id`.
     */
    public function show(?string $period = null): JsonResponse
    {
        $period = $period ?: $this->winnerService->lastClosedPeriod();

        $entrants = PlaceDrawEntrant::with(['user', 'place.translations'])
            ->forPeriod($period)
            // Winners first and in pull order, then everyone else. The client
            // reads `winner_ids` for the order, but sending them sorted keeps
            // the pile's first twelve from being all losers.
            ->orderByRaw('CASE WHEN rank = 0 THEN 1 ELSE 0 END, rank ASC')
            ->get();

        if ($entrants->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => $this->emptyPayload($period),
            ]);
        }

        // `auth('api')->id()` rather than `$request->user()`: this route is
        // public, so no auth middleware has run and `$request->user()` would
        // be null even for a signed-in caller sending a valid token. Same
        // pattern as PlaceController and PlaceEventController.
        $userId = auth('api')->id();
        $place = $entrants->first()->place;

        $winners = $entrants->where('rank', '>', 0)->sortBy('rank');

        // The caller's own prize, and only their own: `winners/recent` has
        // never exposed other people's codes and this must not become the
        // first place that does.
        $myPrizeId = $userId
            ? PlacePrize::where('period', $period)->where('user_id', $userId)->value('id')
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'place' => $place ? [
                    'id' => $place->id,
                    'title' => $place->title,
                    'image' => $place->image,
                ] : null,
                'pull_count' => $winners->count(),
                'total_entrants' => $entrants->first()->total_entrants,
                'entrants' => $entrants->map(
                    fn(PlaceDrawEntrant $e) => $this->entrantPayload($e, $userId)
                )->values(),
                'winner_ids' => $winners->pluck('user_id')->map(fn($id) => (int) $id)->values(),
                'my_prize_id' => $myPrizeId,
            ],
        ]);
    }

    /**
     * A period with no recorded entrants — either nobody voted, or the draw
     * predates CLAW-Z1. The client renders its empty state either way rather
     * than a half-built machine.
     */
    protected function emptyPayload(string $period): array
    {
        return [
            'period' => $period,
            'place' => null,
            'pull_count' => 0,
            'total_entrants' => null,
            'entrants' => [],
            'winner_ids' => [],
            'my_prize_id' => null,
        ];
    }

    protected function entrantPayload(PlaceDrawEntrant $entrant, ?int $viewerId): array
    {
        return [
            'user_id' => (int) $entrant->user_id,
            'name' => $this->maskedName($entrant->user),
            'handle' => '',
            'votes' => (int) $entrant->votes,
            'rank' => (int) $entrant->rank,
            'is_me' => $viewerId !== null && (int) $entrant->user_id === $viewerId,
        ];
    }

    /**
     * "Farida N." — the same first-plus-initial rule
     * LeaderboardService::getRecentPrizeWinners() already applies.
     *
     * Deliberately duplicated in shape rather than diverged: these are the
     * same people appearing on two screens, and two masking rules would show
     * one user under two names.
     */
    protected function maskedName($user): string
    {
        $first = trim((string) ($user?->f_name ?? ''));
        $lastInitial = trim((string) ($user?->l_name ?? ''));
        $lastInitial = $lastInitial !== '' ? mb_substr($lastInitial, 0, 1) . '.' : '';
        $name = trim("{$first} {$lastInitial}");

        return $name !== '' ? $name : translate('messages.a_waddi_voter');
    }
}
