<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A permanent, standing statement about a place.
 *
 * Deliberately has no `period` column: unlike PlaceVote, a review does not
 * belong to a weekly race and does not expire when one closes. One row per
 * user per place, forever; re-reviewing updates it.
 */
class PlaceReview extends Model
{
    protected $table = 'place_reviews';

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

    public function getImageUrlAttribute(): ?string
    {
        if (!$this->image) {
            return null;
        }
        return asset('storage/place_reviews/' . $this->image);
    }

    public function scopeNotFlagged($query)
    {
        return $query->where('is_flagged', false);
    }

    public function scopeWithText($query)
    {
        return $query->whereNotNull('review')->where('review', '!=', '');
    }

    public function scopeWithRating($query)
    {
        return $query->whereNotNull('rating');
    }
}
