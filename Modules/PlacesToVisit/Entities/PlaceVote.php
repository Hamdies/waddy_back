<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceVote extends Model
{
    protected $table = 'place_votes';
    
    protected $guarded = ['id'];

    protected $casts = [
        'rating' => 'integer',
        'is_flagged' => 'boolean',
    ];

    protected $appends = ['image_url'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PlaceVoteReport::class, 'vote_id');
    }

    // ==================== Accessors ====================

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        return asset('storage/place_reviews/' . $this->image);
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
