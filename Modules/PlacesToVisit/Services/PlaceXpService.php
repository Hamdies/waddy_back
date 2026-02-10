<?php

namespace Modules\PlacesToVisit\Services;

use App\Models\User;
use App\Services\XpService;

class PlaceXpService
{
    /**
     * Award XP for voting on a place.
     */
    public static function awardVoteXp(User $user, int $voteId): void
    {
        $xp = config('placestovisit.xp.vote', 5);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                'place_vote',
                $xp,
                'place_vote',
                $voteId,
                'Voted for a hidden gem'
            );
        }
    }

    /**
     * Award XP for writing a review.
     */
    public static function awardReviewXp(User $user, int $voteId): void
    {
        $xp = config('placestovisit.xp.review', 10);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                'place_review',
                $xp,
                'place_vote',
                $voteId,
                'Reviewed a hidden gem'
            );
        }
    }

    /**
     * Award XP for a submission that gets approved.
     */
    public static function awardSubmissionApprovedXp(User $user, int $submissionId): void
    {
        $xp = config('placestovisit.xp.submission_approved', 25);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                'place_submission_approved',
                $xp,
                'place_submission',
                $submissionId,
                'Hidden gem submission approved'
            );
        }
    }

    /**
     * Award XP for uploading a photo with review.
     */
    public static function awardPhotoReviewXp(User $user, int $voteId): void
    {
        $xp = config('placestovisit.xp.photo_review', 15);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                'place_photo_review',
                $xp,
                'place_vote',
                $voteId,
                'Added photo to hidden gem review'
            );
        }
    }
}
