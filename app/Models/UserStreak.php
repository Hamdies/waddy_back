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
     * Get streak data for API response.
     */
    public function getStreakData(): array
    {
        return [
            'current_streak' => $this->current_streak,
            'longest_streak' => $this->longest_streak,
            'streak_bonus_xp' => XpSetting::getInt('streak_bonus_xp', 10),
            'last_activity_date' => $this->last_activity_date?->toDateString(),
        ];
    }
}
