<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlaceZone extends Model
{
    protected $table = 'place_zones';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['localized_name', 'localized_display_name'];

    public function places(): HasMany
    {
        return $this->hasMany(Place::class, 'zone_id');
    }

    // ==================== Accessors ====================

    public function getLocalizedNameAttribute(): string
    {
        $locale = app()->getLocale();
        return ($locale === 'ar' && $this->name_ar) ? $this->name_ar : $this->name;
    }

    public function getLocalizedDisplayNameAttribute(): string
    {
        $locale = app()->getLocale();
        if ($locale === 'ar' && $this->display_name_ar) {
            return $this->display_name_ar;
        }
        return $this->display_name ?? $this->name;
    }

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }
}
