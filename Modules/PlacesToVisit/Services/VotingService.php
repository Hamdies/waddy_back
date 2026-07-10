<?php

namespace Modules\PlacesToVisit\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceVote;
use Modules\PlacesToVisit\Entities\PlaceVoteReport;

class VotingService
{
    /**
     * Submit or update a vote for a place
     */
    public function vote(
        int $placeId,
        int $userId,
        ?int $rating = null,
        ?string $review = null,
        ?string $image = null
    ): array {
        $period = $this->getCurrentPeriod();
        
        $existingVote = PlaceVote::where([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $period,
        ])->first();

        if ($existingVote) {
            // Update existing vote — don't wipe fields the caller didn't send
            $updateData = [];
            if ($rating !== null) {
                $updateData['rating'] = $rating;
            }
            if ($review !== null) {
                $updateData['review'] = $review;
            }
            if ($image !== null) {
                $updateData['image'] = $image;
            }
            if ($updateData !== []) {
                $existingVote->update($updateData);
            }

            $this->clearLeaderboardCache();

            // Review/photo added on update still earns its bonus XP once
            // (XP is deduped per place + period, so this can't double-award)
            $user = User::find($userId);
            if ($user) {
                if ($review && trim($review) !== '') {
                    PlaceXpService::awardReviewXp($user, $placeId, $period);
                }
                if ($image) {
                    PlaceXpService::awardPhotoReviewXp($user, $placeId, $period);
                }
            }

            return [
                'success' => true,
                'message' => translate('messages.vote_updated'),
                'action' => 'updated',
                'vote' => $existingVote,
            ];
        }

        // Create new vote
        $vote = PlaceVote::create([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $period,
            'rating' => $rating,
            'review' => $review,
            'image' => $image,
        ]);

        $this->clearLeaderboardCache();

        // Award XP for vote (deduped per place + period — see PlaceXpService)
        $user = User::find($userId);
        if ($user) {
            PlaceXpService::awardVoteXp($user, $placeId, $period);

            // Bonus XP for writing a review
            if ($review && trim($review) !== '') {
                PlaceXpService::awardReviewXp($user, $placeId, $period);
            }

            // Bonus XP for photo review
            if ($image) {
                PlaceXpService::awardPhotoReviewXp($user, $placeId, $period);
            }
        }

        return [
            'success' => true,
            'message' => translate('messages.vote_recorded'),
            'action' => 'created',
            'vote' => $vote,
        ];
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
    public function reportVote(int $voteId, int $reporterId, ?string $reason = null): array
    {
        $vote = PlaceVote::find($voteId);

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
        return PlaceVote::where('id', $voteId)->update(['is_flagged' => true]) > 0;
    }

    /**
     * Unflag a vote (admin)
     */
    public function unflagVote(int $voteId): bool
    {
        return PlaceVote::where('id', $voteId)->update(['is_flagged' => false]) > 0;
    }

    /**
     * Get current voting period
     */
    public function getCurrentPeriod(): string
    {
        return now()->format('Y-m');
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
