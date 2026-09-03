<?php

namespace Modules\PlacesToVisit\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceEvent;
use Modules\PlacesToVisit\Entities\PlaceReview;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Entities\PlaceVoteReport;

class VotingService
{
    /**
     * Submit or update a vote for a place.
     *
     * ONE vote per user per week: voting for a different place while a
     * weekly vote exists requires $switch=true, which moves the vote
     * (allegiance can change until the week locks).
     */
    public function vote(
        int $placeId,
        int $userId,
        ?int $rating = null,
        ?string $review = null,
        ?string $image = null,
        bool $switch = false
    ): array {
        $period = $this->getCurrentPeriod();

        // Any vote this week, regardless of place
        $weeklyVote = PlaceVote::with('place')
            ->where('user_id', $userId)
            ->where('period', $period)
            ->orderByDesc('id')
            ->first();

        if ($weeklyVote && $weeklyVote->place_id !== $placeId) {
            if (!$switch) {
                return [
                    'success' => false,
                    'code' => 'already_voted_this_week',
                    'message' => translate('messages.already_voted_this_week'),
                    'current_vote' => [
                        'place_id' => $weeklyVote->place_id,
                        'place_title' => $weeklyVote->place?->title,
                    ],
                ];
            }
            // Switch allegiance — the weekly vote moves to the new spot.
            // Stray extra votes from the pre-weekly-rule era go with it.
            PlaceVote::where('user_id', $userId)
                ->where('period', $period)
                ->delete();
            $weeklyVote = null;
        }

        $existingVote = $weeklyVote; // non-null only when same place

        if ($existingVote) {
            // Already backing this spot this week. A vote carries no payload
            // of its own any more — rating, review text and photo all live in
            // `place_reviews` — so there is nothing to update.
            return [
                'success' => true,
                'message' => translate('messages.vote_updated'),
                'action' => 'unchanged',
                'vote' => $existingVote,
            ];
        }

        // Create new vote. Deliberately writes no rating/review/image: those
        // are a review, they are permanent, and they belong to ReviewController.
        $vote = PlaceVote::create([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $period,
        ]);

        $this->clearLeaderboardCache();
        PlaceEvent::log($switch ? 'vote_switched' : 'vote_created', $userId, $placeId);
        app(PlacePushService::class)->checkLeadChange($placeId);

        // Award XP for vote (deduped per place + period — see PlaceXpService).
        // Review and photo XP are awarded by ReviewController, where the
        // review actually happens.
        $user = User::find($userId);
        if ($user) {
            PlaceXpService::awardVoteXp($user, $placeId, $period);
        }

        return [
            'success' => true,
            'message' => $switch
                ? translate('messages.vote_switched')
                : translate('messages.vote_recorded'),
            'action' => $switch ? 'switched' : 'created',
            'vote' => $vote,
        ];
    }

    /**
     * The user's single weekly vote (any place), with place loaded
     */
    public function getWeeklyVote(int $userId): ?PlaceVote
    {
        return PlaceVote::with('place')
            ->where('user_id', $userId)
            ->where('period', $this->getCurrentPeriod())
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Remove a vote
     */
    public function removeVote(int $placeId, int $userId): array
    {
        $period = $this->getCurrentPeriod();
        
        $deleted = PlaceVote::where([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $period,
        ])->delete();

        if ($deleted) {
            $this->clearLeaderboardCache();
            PlaceEvent::log('vote_removed', $userId, $placeId);
            app(PlacePushService::class)->checkLeadChange($placeId);

            return [
                'success' => true,
                'message' => translate('messages.vote_removed'),
            ];
        }

        return [
            'success' => false,
            'message' => translate('messages.no_vote_found'),
        ];
    }

    /**
     * Check if user has voted for a place in current period
     */
    public function hasVoted(int $placeId, int $userId): bool
    {
        return PlaceVote::where([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $this->getCurrentPeriod(),
        ])->exists();
    }

    /**
     * Get user's vote for a place
     */
    public function getUserVote(int $placeId, int $userId): ?PlaceVote
    {
        return PlaceVote::where([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $this->getCurrentPeriod(),
        ])->first();
    }

    /**
     * Report/flag a review with hardened validation
     */
    /**
     * Report a review. The parameter keeps its legacy `voteId` name for the
     * route shape, but identifies a PlaceReview — that is where review text
     * lives now, and a report is always about text.
     */
    public function reportVote(int $voteId, int $reporterId, ?string $reason = null): array
    {
        $vote = PlaceReview::find($voteId);

        if (!$vote) {
            return ['success' => false, 'message' => translate('messages.review_not_found')];
        }

        // Can't report your own review
        if ($vote->user_id === $reporterId) {
            return ['success' => false, 'message' => translate('messages.cannot_report_own_review')];
        }

        // Check if already reported by this user
        $alreadyReported = PlaceVoteReport::where('vote_id', $voteId)
            ->where('reporter_id', $reporterId)
            ->exists();

        if ($alreadyReported) {
            return ['success' => false, 'message' => translate('messages.already_reported')];
        }

        // Create report
        PlaceVoteReport::create([
            'vote_id' => $voteId,
            'reporter_id' => $reporterId,
            'reason' => $reason,
        ]);

        // Auto-flag after N reports
        $reportThreshold = config('placestovisit.report_auto_flag_threshold', 3);
        $reportCount = PlaceVoteReport::where('vote_id', $voteId)->count();

        if ($reportCount >= $reportThreshold) {
            $vote->update(['is_flagged' => true]);
        }

        return ['success' => true, 'message' => translate('messages.review_reported')];
    }

    /**
     * Flag a vote for moderation (admin)
     */
    public function flagVote(int $voteId): bool
    {
        return PlaceReview::where('id', $voteId)->update(['is_flagged' => true]) > 0;
    }

    /**
     * Unflag a vote (admin)
     */
    public function unflagVote(int $voteId): bool
    {
        return PlaceReview::where('id', $voteId)->update(['is_flagged' => false]) > 0;
    }

    /**
     * Get current voting period
     */
    public function getCurrentPeriod(): string
    {
        return \Modules\PlacesToVisit\Services\RaceClock::period();
    }

    /**
     * Clear leaderboard cache when votes change
     */
    protected function clearLeaderboardCache(): void
    {
        app(LeaderboardService::class)->clearCache();
        app(TrendingService::class)->clearCache();
    }
}
