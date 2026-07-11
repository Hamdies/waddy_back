<?php

namespace Modules\PlacesToVisit\Services;

use App\Models\User;
use App\Services\XpService;

class PlaceXpService
{
    /**
     * Numeric id for a period string ("2026-W28" -> 202628) so weekly XP
     * can be deduped on the period itself, not the place.
     */
    protected static function periodRefId(string $period): int
    {
        return (int) preg_replace('/\D/', '', $period);
    }

    /**
     * Award XP for the weekly vote.
     *
     * Keyed to the WEEK, not the place: with one vote per user per week,
     * switching your vote to another spot must never re-award XP —
     * XpService::addXp dedupes on (user, reference_type, reference_id, source).
     */
    public static function awardVoteXp(User $user, int $placeId, ?string $period = null): void
    {
        $period = $period ?? \Modules\PlacesToVisit\Services\RaceClock::period();
        $xp = config('placestovisit.xp.vote', 5);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                "place_vote:{$period}",
                $xp,
                'place_vote_week',
                self::periodRefId($period),
                'Voted for a hidden gem'
            );
        }
    }

    /**
     * Award XP for writing a review (once per week — switching votes and
     * re-reviewing can't farm the bonus).
     */
    public static function awardReviewXp(User $user, int $placeId, ?string $period = null): void
    {
        $period = $period ?? \Modules\PlacesToVisit\Services\RaceClock::period();
        $xp = config('placestovisit.xp.review', 10);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                "place_review:{$period}",
                $xp,
                'place_review_week',
                self::periodRefId($period),
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
     * Award XP for uploading a photo with review (once per week).
     */
    public static function awardPhotoReviewXp(User $user, int $placeId, ?string $period = null): void
    {
        $period = $period ?? \Modules\PlacesToVisit\Services\RaceClock::period();
        $xp = config('placestovisit.xp.photo_review', 15);
        if ($xp > 0) {
            XpService::addXp(
                $user,
                "place_photo_review:{$period}",
                $xp,
                'place_photo_week',
                self::periodRefId($period),
                'Added photo to hidden gem review'
            );
        }
    }
}
