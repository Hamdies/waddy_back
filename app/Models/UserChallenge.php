<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserChallenge extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'xp_challenge_id' => 'integer',
        'progress' => 'array',
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'claimed_at' => 'datetime',
        'next_available_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the challenge template.
     */
    public function challenge()
    {
        return $this->belongsTo(XpChallenge::class, 'xp_challenge_id');
    }

    /**
     * Check if challenge is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->gt($this->expires_at);
    }

    /**
     * Check if challenge is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Get active challenges for user.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Get completed and unclaimed challenges.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Get the frequency of this challenge.
     */
    public function getFrequencyAttribute(): string
    {
        return $this->challenge->frequency ?? 'daily';
    }

    /**
     * Update progress.
     */
    public function updateProgress(array $progress): void
    {
        $this->progress = array_merge($this->progress ?? [], $progress);
        $this->save();
    }

    /**
     * Mark as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark as claimed with 24h cooldown.
     */
    public function markClaimed(): void
    {
        $this->update([
            'status' => 'claimed',
            'claimed_at' => now(),
            'next_available_at' => now()->addHours(24),
        ]);
    }
}
