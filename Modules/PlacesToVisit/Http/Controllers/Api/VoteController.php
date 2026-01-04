<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Services\VotingService;

class VoteController extends Controller
{
    public function __construct(
        protected VotingService $votingService
    ) {}

    /**
     * Submit or update a vote
     * POST /api/v1/places/{place}/vote
     */
    public function vote(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
        ]);

        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $result = $this->votingService->vote(
            placeId: $place->id,
            userId: auth()->id(),
            rating: $request->rating,
            review: $request->review
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            'action' => $result['action'] ?? null,
        ]);
    }

    /**
     * Remove a vote
     * DELETE /api/v1/places/{place}/vote
     */
    public function removeVote(Place $place): JsonResponse
    {
        $result = $this->votingService->removeVote(
            placeId: $place->id,
            userId: auth()->id()
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : 404);
    }

    /**
     * Check if user has voted
     * GET /api/v1/places/{place}/vote-status
     */
    public function status(Place $place): JsonResponse
    {
        $userId = auth()->id();
        $vote = $this->votingService->getUserVote($place->id, $userId);

        return response()->json([
            'success' => true,
            'has_voted' => $vote !== null,
            'vote' => $vote ? [
                'rating' => $vote->rating,
                'review' => $vote->review,
                'created_at' => $vote->created_at,
            ] : null,
            'period' => $this->votingService->getCurrentPeriod(),
        ]);
    }

    /**
     * Report/flag a review
     * POST /api/v1/places/votes/{vote}/report
     */
    public function report(int $voteId): JsonResponse
    {
        $flagged = $this->votingService->flagVote($voteId);

        return response()->json([
            'success' => $flagged,
            'message' => $flagged 
                ? translate('messages.review_reported') 
                : translate('messages.review_not_found'),
        ], $flagged ? 200 : 404);
    }
}
