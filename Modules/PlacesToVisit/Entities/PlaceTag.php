<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlaceTag extends Model
{
    protected $table = 'place_tags';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['localized_name'];

    // ==================== Relationships ====================

    public function places(): BelongsToMany
    {
        return $this->belongsToMany(Place::class, 'place_tag_pivot', 'tag_id', 'place_id');
    }

    // ==================== Accessors ====================

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        return ($locale === 'ar' && $this->name_ar) ? $this->name_ar : $this->name;
    }

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
