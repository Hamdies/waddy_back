<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XpChallenge extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'conditions' => 'array',
        'xp_reward' => 'integer',
        'time_limit_hours' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Get user challenges for this challenge template.
     */
    public function userChallenges()
    {
        return $this->hasMany(UserChallenge::class);
    }

    /**
     * Get active challenges.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get daily challenges.
     */
    public function scopeDaily($query)
    {
        return $query->where('frequency', 'daily');
    }

    /**
     * Get weekly challenges.
     */
    public function scopeWeekly($query)
    {
        return $query->where('frequency', 'weekly');
    }

    /**
     * Get a random active challenge by frequency.
     */
    public static function getRandomByFrequency(string $frequency): ?self
    {
        return static::active()
            ->where('frequency', $frequency)
            ->inRandomOrder()
            ->first();
    }
}
