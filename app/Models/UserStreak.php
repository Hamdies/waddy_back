<?php

namespace App\Models;

use App\Support\AppClock;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserStreak extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'current_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_activity_date' => 'date',
    ];

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record an activity for the given day.
     *
     * Day boundaries are app-local (see AppClock), so a late-night order that
     * is delivered after midnight UTC still counts on the local day it was
     * placed. Pass the order's placement time as $activityAt; defaults to now.
     */
    public static function recordActivity(User $user, $activityAt = null): self
    {
        $streak = static::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        $tz = AppClock::timezone();
        $activityDay = ($activityAt ? Carbon::parse($activityAt)->timezone($tz) : AppClock::now())
            ->startOfDay();
        $lastActivity = $streak->last_activity_date
            ? Carbon::parse($streak->last_activity_date)->timezone($tz)->startOfDay()
            : null;

        // Already recorded for this day
        if ($lastActivity && $lastActivity->isSameDay($activityDay)) {
            return $streak;
        }

        // Check if last activity was the previous day (streak continues)
        if ($lastActivity && $lastActivity->isSameDay($activityDay->copy()->subDay())) {
            $streak->current_streak++;
        } else {
            // Streak broken or first activity
            $streak->current_streak = 1;
        }

        // Update longest streak
        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_activity_date = $activityDay;
        $streak->save();

        return $streak;
    }

    /**
     * The streak as it stands today (app-local).
     *
     * `current_streak` is only reset by the user's *next* activity, so a
     * streak broken days ago still reads as its old length. It is alive only
     * if the last activity was today or yesterday (X-17).
     */
    public function effectiveStreak(): int
    {
        if (!$this->last_activity_date || $this->current_streak <= 0) {
            return 0;
        }

        $tz = AppClock::timezone();
        $last = Carbon::parse($this->last_activity_date)->timezone($tz)->startOfDay();
        $yesterday = AppClock::now()->startOfDay()->subDay();

        return $last->lt($yesterday) ? 0 : (int) $this->current_streak;
    }

    /**
     * Get streak data for API response.
     */
    public function getStreakData(): array
    {
        return [
            'current_streak' => $this->effectiveStreak(),
            'longest_streak' => $this->longest_streak,
            'streak_bonus_xp' => XpSetting::getInt('streak_bonus_xp', 10),
            'last_activity_date' => $this->last_activity_date?->toDateString(),
        ];
    }
}
