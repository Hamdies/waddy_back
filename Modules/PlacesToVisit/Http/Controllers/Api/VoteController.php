<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Services\VotingService;
use App\CentralLogics\Helpers;

class VoteController extends Controller
{
    public function __construct(
        protected VotingService $votingService
    ) {}

    /**
     * Submit or update a vote (with optional photo)
     * POST /api/v1/places/{place}/vote
     */
    public function vote(Request $request, Place $place): JsonResponse
    {
        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = Helpers::upload('place_reviews/', 'png', $request->file('image'));
        }

        $result = $this->votingService->vote(
            placeId: $place->id,
            userId: auth()->id(),
            rating: $request->rating,
            review: $request->review,
            image: $imagePath
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
                'image' => $vote->image_url,
                'created_at' => $vote->created_at,
            ] : null,
            'period' => $this->votingService->getCurrentPeriod(),
        ]);
    }

    /**
     * Report/flag a review (hardened)
     * POST /api/v1/places/votes/{vote}/report
     */
    public function report(Request $request, int $voteId): JsonResponse
    {
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $result = $this->votingService->reportVote(
            voteId: $voteId,
            reporterId: auth()->id(),
            reason: $request->reason
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['success'] ? 200 : ($result['message'] === translate('messages.review_not_found') ? 404 : 422));
    }

    /**
     * Get reviews for a place with pagination
     * GET /api/v1/places/{place}/reviews
     */
    public function reviews(Request $request, Place $place): JsonResponse
    {
        $period = $request->period ?? now()->format('Y-m');

        $reviews = $place->votes()
            ->where('period', $period)
            ->notFlagged()
            ->withReview()
            ->with('user:id,f_name,l_name,image')
            ->latest()
            ->paginate($request->per_page ?? 15, ['id', 'user_id', 'rating', 'review', 'image', 'created_at']);

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $reviews->items(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }
}
