<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class UserLevelPrize extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'level_prize_id' => 'integer',
        'order_id' => 'integer',
        'unlocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the prize definition.
     */
    public function prize()
    {
        return $this->belongsTo(LevelPrize::class, 'level_prize_id');
    }

    /**
     * Get the order this prize was used on.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Check if prize is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->gt($this->expires_at);
    }

    /**
     * Check if prize is usable.
     */
    public function isUsable(): bool
    {
        return in_array($this->status, ['unlocked', 'claimed']) && !$this->isExpired();
    }

    /**
     * Get unlocked and usable prizes.
     */
    public function scopeUsable($query)
    {
        return $query->whereIn('status', ['unlocked', 'claimed'])
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Mark prize as used.
     */
    public function markUsed(int $orderId = null): void
    {
        $this->update([
            'status' => 'used',
            'order_id' => $orderId,
            'used_at' => now(),
        ]);
    }

    /**
     * Expire prize.
     */
    public function expire(): void
    {
        $this->update(['status' => 'expired']);
    }
}
