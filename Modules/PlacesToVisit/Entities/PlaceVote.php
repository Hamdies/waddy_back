<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceVote extends Model
{
    protected $table = 'place_votes';
    
    protected $guarded = ['id'];

    protected $casts = [
        'rating' => 'integer',
        'is_flagged' => 'boolean',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ==================== Scopes ====================

    public function scopeForPeriod($query, ?string $period = null)
    {
        $period = $period ?? now()->format('Y-m');
        return $query->where('period', $period);
    }

    public function scopeNotFlagged($query)
    {
        return $query->where('is_flagged', false);
    }

    public function scopeWithRating($query)
    {
        return $query->whereNotNull('rating');
    }

    public function scopeWithReview($query)
    {
        return $query->whereNotNull('review')->where('review', '!=', '');
    }

    // ==================== Helpers ====================

    public static function getCurrentPeriod(): string
    {
        return now()->format('Y-m');
    }
}
