<?php

namespace App\Models;

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
     * Record an activity (order placed) for today.
     * Updates current_streak, longest_streak accordingly.
     */
    public static function recordActivity(User $user): self
    {
        $streak = static::firstOrCreate(
            ['user_id' => $user->id],
            ['current_streak' => 0, 'longest_streak' => 0]
        );

        $today = Carbon::today();
        $lastActivity = $streak->last_activity_date ? Carbon::parse($streak->last_activity_date) : null;

        // Already recorded today
        if ($lastActivity && $lastActivity->isSameDay($today)) {
            return $streak;
        }

        // Check if last activity was yesterday (streak continues)
        if ($lastActivity && $lastActivity->isSameDay($today->copy()->subDay())) {
            $streak->current_streak++;
        } else {
            // Streak broken or first activity
            $streak->current_streak = 1;
        }

        // Update longest streak
        if ($streak->current_streak > $streak->longest_streak) {
            $streak->longest_streak = $streak->current_streak;
        }

        $streak->last_activity_date = $today;
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
