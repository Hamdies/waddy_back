<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A voucher won in the weekly voter draw — a free item at the venue that won
 * the week, redeemed in person at the counter (never an in-app order).
 */
class PlacePrize extends Model
{
    protected $table = 'place_prizes';

    protected $guarded = ['id'];

    protected $casts = [
        'value_cap' => 'float',
        'expires_at' => 'datetime',
        'redeemed_at' => 'datetime',
        'reminder_sent_at' => 'datetime',
    ];

    public const STATUS_ACTIVE = 'active';
    public const STATUS_REDEEMED = 'redeemed';
    public const STATUS_EXPIRED = 'expired';

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(PlaceWinner::class, 'place_winner_id');
    }

    // ==================== State ====================

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at && $this->expires_at->isPast());
    }

    public function isRedeemable(): bool
    {
        return $this->status === self::STATUS_ACTIVE && !$this->isExpired();
    }

    /** Seconds left before the voucher dies (0 once it's gone) */
    public function secondsRemaining(): int
    {
        if (!$this->expires_at || $this->isExpired()) {
            return 0;
        }
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }
}
