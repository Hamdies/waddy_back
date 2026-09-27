<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UserLevelPrize extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'level_prize_id' => 'integer',
        'order_id' => 'integer',
        'uses_count' => 'integer',
        'unlocked_at' => 'datetime',
        'expires_at' => 'datetime',
        'claimed_at' => 'datetime',
        'used_at' => 'datetime',
        'last_used_at' => 'datetime',
        'period_started_at' => 'datetime',
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
     * The personal coupon minted when a discount prize is claimed.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * Check if prize is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && Carbon::now()->gt($this->expires_at);
    }

    /**
     * Whether this free-delivery prize can be spent on an order in the given
     * module with the given subtotal (before coupon and referral discounts).
     *
     * The single rule for both `xp/checkout-prizes` and PlaceNewOrder. The
     * checkout list used to check only expiry and the minimum, so it offered
     * prizes the order then refused — the app quoted free delivery and the
     * server charged it (X-24).
     */
    public function canRedeemFreeDelivery(?int $moduleId, float $subtotal): bool
    {
        $prize = $this->prize;

        return $prize
            && $prize->prize_type === 'free_delivery'
            && $this->isUsable()
            && $prize->isApplicableToModule($moduleId)
            && (!$prize->min_order_amount || $subtotal >= $prize->min_order_amount);
    }

    /**
     * Check if prize is usable (considering period limits).
     */
    public function isUsable(): bool
    {
        if (!in_array($this->status, ['unlocked', 'claimed'])) {
            return false;
        }
        
        if ($this->isExpired()) {
            return false;
        }
        
        // Check period-based usage limits
        if (!$this->canUseInPeriod()) {
            return false;
        }
        
        return true;
    }

    /**
     * Check if the prize can be used in the current period.
     */
    public function canUseInPeriod(): bool
    {
        $prize = $this->prize;
        
        // No period limit configured
        if (!$prize || !$prize->max_uses_per_period || !$prize->period_type) {
            return true;
        }
        
        // Check if we need to reset the period
        if ($this->shouldResetPeriod()) {
            return true; // Can use because period has reset
        }
        
        // Check if uses in current period exceed limit
        return $this->uses_count < $prize->max_uses_per_period;
    }

    /**
     * Check if the usage period should be reset.
     */
    protected function shouldResetPeriod(): bool
    {
        if (!$this->period_started_at) {
            return true; // No period started yet
        }

        $prize = $this->prize;
        if (!$prize || !$prize->period_type) {
            return false;
        }

        $now = Carbon::now();
        $periodStart = Carbon::parse($this->period_started_at);

        return match($prize->period_type) {
            'daily' => $now->startOfDay()->gt($periodStart->startOfDay()),
            'weekly' => $now->startOfWeek()->gt($periodStart->startOfWeek()),
            'monthly' => $now->startOfMonth()->gt($periodStart->startOfMonth()),
            'once' => false, // Never reset for 'once' type
            default => false,
        };
    }

    /**
     * Record a usage of this prize (with pessimistic locking).
     */
    public function recordUsage(int $orderId = null): void
    {
        DB::transaction(function () use ($orderId) {
            // Re-fetch with lock to prevent concurrent usage
            $locked = static::lockForUpdate()->find($this->id);
            if (!$locked || !in_array($locked->status, ['unlocked', 'claimed'])) {
                return;
            }

            $prize = $locked->prize;

            // Reset period if needed
            if ($locked->shouldResetPeriod()) {
                $locked->uses_count = 0;
                $locked->period_started_at = now();
            }

            $locked->uses_count++;
            $locked->last_used_at = now();
            $locked->order_id = $orderId;

            // Mark as used if: no prize definition, one-time prize, or period limit exhausted
            $shouldMarkUsed = !$prize
                || $prize->period_type === 'once'
                || (!$prize->period_type)
                || ($prize->max_uses_per_period && $locked->uses_count >= $prize->max_uses_per_period);

            if ($shouldMarkUsed) {
                $locked->status = 'used';
                $locked->used_at = now();
            }

            $locked->save();

            // Sync state back to this instance
            $this->refresh();
        });
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
     * Mark prize as used (legacy - use recordUsage for period tracking).
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
