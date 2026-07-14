<?php

namespace App\Models;

use App\Support\AppClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class XpSeasonScore extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'xp_earned' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Add positive XP to the current weekly and monthly season buckets for a
     * user. Negative amounts (reversals) are ignored — a refund shouldn't drag
     * a season score below what the user actually earned that period, and it
     * keeps the "earned this season" framing honest.
     */
    public static function recordEarning(int $userId, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        foreach (['weekly', 'monthly'] as $periodType) {
            $period = AppClock::periodFor($periodType);

            // Atomic upsert + increment so concurrent awards don't clobber.
            DB::table('xp_season_scores')->upsert(
                [[
                    'user_id' => $userId,
                    'period_type' => $periodType,
                    'period' => $period,
                    'xp_earned' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['user_id', 'period_type', 'period'],
                // On conflict, add to the running total.
                ['xp_earned' => DB::raw('xp_season_scores.xp_earned + ' . (int) $amount), 'updated_at' => now()]
            );
        }
    }
}
