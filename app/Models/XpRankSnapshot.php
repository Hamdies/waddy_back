<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The last leaderboard rank a user was shown at, per period type. Powers the
 * real ▲/▼/HELD movement column: the leaderboard compares a user's live rank to
 * their snapshot, then refreshes it. One row per (user_id, period_type).
 */
class XpRankSnapshot extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'rank' => 'integer',
        'captured_at' => 'datetime',
    ];

    /**
     * Load prior snapshots for a set of users in one query, keyed by user_id.
     */
    public static function priorRanks(array $userIds, string $periodType)
    {
        if (empty($userIds)) {
            return collect();
        }

        return static::where('period_type', $periodType)
            ->whereIn('user_id', $userIds)
            ->get()
            ->keyBy('user_id');
    }

    /**
     * Upsert fresh rank snapshots for a batch of users in one statement.
     * `$ranks` is [userId => rank]. Safe to call repeatedly (idempotent upsert).
     */
    public static function capture(array $ranks, string $periodType, string $period): void
    {
        if (empty($ranks)) {
            return;
        }

        $now = now();
        $rows = [];
        foreach ($ranks as $userId => $rank) {
            $rows[] = [
                'user_id' => (int) $userId,
                'period_type' => $periodType,
                'period' => $period,
                'rank' => (int) $rank,
                'captured_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('xp_rank_snapshots')->upsert(
            $rows,
            ['user_id', 'period_type'],
            ['period', 'rank', 'captured_at', 'updated_at']
        );
    }
}
