<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;

class PlaceCategory extends Model
{
    protected $table = 'place_categories';
    
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    protected $appends = ['localized_name'];

    /** Only `spots` categories outside the admin panel (PET-05). */
    protected static function booted(): void
    {
        static::addGlobalScope(new SurfaceScope());
    }

    public function places(): HasMany
    {
        return $this->hasMany(Place::class, 'category_id');
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

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc');
    }
}
