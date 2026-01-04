<?php

namespace Modules\PlacesToVisit\Services;

use Illuminate\Support\Facades\Cache;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceVote;

class VotingService
{
    /**
     * Submit or update a vote for a place
     */
    public function vote(
        int $placeId,
        int $userId,
        ?int $rating = null,
        ?string $review = null
    ): array {
        $period = $this->getCurrentPeriod();
        
        $existingVote = PlaceVote::where([
            'place_id' => $placeId,
            'user_id' => $userId,
            'period' => $period,
        ])->first();

        if ($existingVote) {
            // Update existing vote
            $existingVote->update([
                'rating' => $rating,
                'review' => $review,
            ]);
            
            $this->clearLeaderboardCache();
            
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
        ]);

        $this->clearLeaderboardCache();

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
     * Flag a vote for moderation
     */
    public function flagVote(int $voteId): bool
    {
        return PlaceVote::where('id', $voteId)->update(['is_flagged' => true]) > 0;
    }

    /**
     * Unflag a vote
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
    }
}
