<?php

namespace Modules\PlacesToVisit\Http\Controllers\Api;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceReview;

/**
 * Reviews, fully independent of voting.
 *
 * A user can review without voting and vote without reviewing; removing one
 * never touches the other. Reviews are permanent and are not scoped to the
 * weekly race period.
 */
class ReviewController extends Controller
{
    /**
     * List a place's reviews.
     * GET /api/v1/places/{place}/reviews
     */
    public function index(Request $request, Place $place): JsonResponse
    {
        $page = (int) ($request->page ?? $request->offset ?? 1);

        $reviews = $place->reviews()
            ->notFlagged()
            ->withText()
            ->with('user:id,f_name,l_name,image')
            ->latest()
            ->paginate(
                $request->per_page ?? 15,
                ['id', 'user_id', 'rating', 'review', 'image', 'created_at'],
                'page',
                $page
            );

        return response()->json([
            'success' => true,
            'data' => $reviews->items(),
            'total_size' => $reviews->total(),
            'offset' => $reviews->currentPage(),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
            ],
        ]);
    }

    /**
     * Create or update the caller's review for this place.
     * POST /api/v1/places/{place}/review
     */
    public function store(Request $request, Place $place): JsonResponse
    {
        // Older builds sent the text as `comment`.
        if (!$request->filled('review') && $request->filled('comment')) {
            $request->merge(['review' => $request->comment]);
        }

        $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'review' => 'nullable|string|max:1000',
            'image'  => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        // A review with neither a score nor words is not a review.
        if (!$request->filled('rating') && !$request->filled('review')) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.review_cannot_be_empty'),
            ], 422);
        }

        if (!$place->is_active) {
            return response()->json([
                'success' => false,
                'message' => translate('messages.place_not_found'),
            ], 404);
        }

        $data = [
            'rating' => $request->rating,
            'review' => $request->review,
        ];
        if ($request->hasFile('image')) {
            $data['image'] = Helpers::upload('place_reviews/', 'png', $request->file('image'));
        }

        $review = PlaceReview::updateOrCreate(
            ['place_id' => $place->id, 'user_id' => auth()->id()],
            $data
        );

        // Review / photo XP moved here from the vote path, so it is earned by
        // reviewing rather than by voting. Still deduped per place + period
        // inside PlaceXpService, so re-editing a review can't farm it.
        $user = \App\Models\User::find(auth()->id());
        if ($user) {
            $period = \Modules\PlacesToVisit\Services\RaceClock::period();
            if ($request->filled('review')) {
                \Modules\PlacesToVisit\Services\PlaceXpService::awardReviewXp($user, $place->id, $period);
            }
            if (!empty($data['image'])) {
                \Modules\PlacesToVisit\Services\PlaceXpService::awardPhotoReviewXp($user, $place->id, $period);
            }
        }

        return response()->json([
            'success' => true,
            'message' => translate('messages.review_submitted'),
            'data' => $review->fresh(),
        ]);
    }

    /**
     * Delete the caller's review. Does NOT touch their vote.
     * DELETE /api/v1/places/{place}/review
     */
    public function destroy(Place $place): JsonResponse
    {
        $deleted = PlaceReview::where('place_id', $place->id)
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json([
            'success' => (bool) $deleted,
            'message' => $deleted
                ? translate('messages.review_removed')
                : translate('messages.review_not_found'),
        ], $deleted ? 200 : 404);
    }

    /**
     * The caller's own review for this place, if any.
     * GET /api/v1/places/{place}/my-review
     */
    public function mine(Place $place): JsonResponse
    {
        $review = PlaceReview::where('place_id', $place->id)
            ->where('user_id', auth()->id())
            ->first();

        return response()->json([
            'success' => true,
            'has_reviewed' => $review !== null,
            'review' => $review ? [
                'rating' => $review->rating,
                'review' => $review->review,
                'image' => $review->image_url,
                'created_at' => $review->created_at,
            ] : null,
        ]);
    }
}
