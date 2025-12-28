<?php

namespace App\Observers;

use App\Models\Review;
use App\Models\User;
use App\Services\XpService;
use Illuminate\Support\Facades\Log;

class ReviewObserver
{
    /**
     * Handle the Review "created" event.
     * Award XP when a user submits a review.
     */
    public function created(Review $review): void
    {
        $this->handleReviewCreated($review);
    }

    /**
     * Handle review creation - award XP.
     */
    protected function handleReviewCreated(Review $review): void
    {
        // Get the user
        $user = User::find($review->user_id);
        if (!$user) {
            return;
        }

        try {
            Log::info("Processing XP for review: review_id={$review->id}, user_id={$user->id}");

            // Award XP for review
            XpService::addReviewXp($user, $review);

            Log::info("XP processed successfully for review: {$review->id}");
        } catch (\Exception $e) {
            Log::error("Failed to process XP for review {$review->id}: " . $e->getMessage());
        }
    }
}
