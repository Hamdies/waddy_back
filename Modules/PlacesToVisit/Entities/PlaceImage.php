<?php

namespace Modules\PlacesToVisit\Entities;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceImage extends Model
{
    protected $table = 'place_images';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected $appends = ['image_full_url'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    // ==================== Image Accessors ====================

    /**
     * Get full image URL (overrides the raw 'image' column).
     *
     * This mirrors Place::getImageAttribute() deliberately. Before, Place
     * resolved `image` to a URL while PlaceImage left it as the bare storage
     * filename and exposed the URL only through the appended
     * `image_full_url` — so the same key name meant two different things
     * depending on which model produced it. Every API consumer that read
     * `image` (as the customer app does) got a working URL for the place logo
     * and an unfetchable filename for every gallery photo, which is why place
     * details rendered a row of broken-image placeholders on places that
     * genuinely had photos.
     *
     * Fixing it here rather than in the client means already-shipped app
     * builds are fixed too.
     */
    public function getImageAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        return asset('storage/places/' . $value);
    }

    /**
     * Get raw image filename (for admin deletes and uploads).
     *
     * Anything touching the filesystem — Helpers::check_and_delete() and
     * friends — must use this, never `image`, which is now a URL.
     */
    public function getRawImageAttribute(): ?string
    {
        return $this->attributes['image'] ?? null;
    }

    public function getImageFullUrlAttribute(): string
    {
        // Reads the raw attribute directly: `$this->image` now returns a full
        // URL, and feeding that back through get_full_url() would build a path
        // out of a URL.
        return Helpers::get_full_url('places', $this->attributes['image'] ?? null, 'public');
    }
}
