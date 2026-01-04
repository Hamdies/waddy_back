<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\Zone;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PlaceBanner extends Model
{
    protected $table = 'place_banners';

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'priority' => 'integer',
        'data' => 'integer',
        'zone_id' => 'integer',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    protected $appends = ['image_full_url', 'localized_title', 'localized_description'];

    // ==================== Relationships ====================

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlaceCategory::class, 'data');
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'data');
    }

    // ==================== Accessors ====================

    public function getImageFullUrlAttribute(): string
    {
        return Helpers::get_full_url('place_banner', $this->image, 'public');
    }

    public function getLocalizedTitleAttribute(): string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' && $this->title_ar ? $this->title_ar : $this->title;
    }

    public function getLocalizedDescriptionAttribute(): ?string
    {
        $locale = app()->getLocale();
        return $locale === 'ar' && $this->description_ar ? $this->description_ar : $this->description;
    }

    // ==================== Scopes ====================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('priority', 'desc')->orderBy('created_at', 'desc');
    }

    public function scopeValid($query)
    {
        $now = now();
        return $query->where(function ($q) use ($now) {
            $q->whereNull('start_date')
              ->orWhere('start_date', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('end_date')
              ->orWhere('end_date', '>=', $now);
        });
    }

    public function scopeInZone($query, $zoneId)
    {
        return $query->where(function ($q) use ($zoneId) {
            $q->whereNull('zone_id')
              ->orWhere('zone_id', $zoneId);
        });
    }

    // ==================== Boot ====================

    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($model) {
            if ($model->isDirty('image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
