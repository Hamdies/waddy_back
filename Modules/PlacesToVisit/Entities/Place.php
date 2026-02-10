<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Place extends Model
{
    protected $table = 'places';
    
    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'opening_hours' => 'array',
    ];

    protected $appends = ['title', 'description'];

    // ==================== Image Accessor ====================

    /**
     * Get full image URL (overrides the raw 'image' column)
     */
    public function getImageAttribute($value): ?string
    {
        if (!$value) {
            return null;
        }
        return asset('storage/places/' . $value);
    }

    /**
     * Get raw image filename (for admin/uploads)
     */
    public function getRawImageAttribute(): ?string
    {
        return $this->attributes['image'] ?? null;
    }

    // ==================== Relationships ====================

    public function category(): BelongsTo
    {
        return $this->belongsTo(PlaceCategory::class, 'category_id');
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PlaceTranslation::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(PlaceVote::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(PlaceOffer::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(PlaceImage::class)->orderBy('sort_order');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(PlaceFavorite::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PlaceTag::class, 'place_tag_pivot', 'place_id', 'tag_id');
    }

    // ==================== Localization ====================

    public function translation(?string $locale = null): ?PlaceTranslation
    {
        $locale = $locale ?? app()->getLocale();
        return $this->translations->firstWhere('locale', $locale) 
            ?? $this->translations->firstWhere('locale', 'en');
    }

    public function getTitleAttribute(): ?string
    {
        return $this->translation()?->title;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->translation()?->description;
    }

    // ==================== Opening Hours ====================

    /**
     * Check if place is currently open based on opening_hours JSON
     */
    public function isOpenNow(): ?bool
    {
        if (!$this->opening_hours) {
            return null; // Unknown
        }

        $dayOfWeek = strtolower(now()->format('l')); // monday, tuesday, etc.
        $currentTime = now()->format('H:i');

        $todayHours = $this->opening_hours[$dayOfWeek] ?? null;

        if (!$todayHours || ($todayHours['closed'] ?? false)) {
            return false;
        }

        $open = $todayHours['open'] ?? null;
        $close = $todayHours['close'] ?? null;

        if (!$open || !$close) {
            return null;
        }

        return $currentTime >= $open && $currentTime <= $close;
    }

    // ==================== Voting Stats ====================

    public function getVotesCountForPeriod(?string $period = null): int
    {
        $period = $period ?? now()->format('Y-m');
        return $this->votes()->where('period', $period)->count();
    }

    public function getAverageRatingForPeriod(?string $period = null): ?float
    {
        $period = $period ?? now()->format('Y-m');
        return $this->votes()
            ->where('period', $period)
            ->whereNotNull('rating')
            ->avg('rating');
    }

    public function hasUserVoted(int $userId, ?string $period = null): bool
    {
        $period = $period ?? now()->format('Y-m');
        return $this->votes()
            ->where('user_id', $userId)
            ->where('period', $period)
            ->exists();
    }

    public function isUserFavorite(int $userId): bool
    {
        return $this->favorites()->where('user_id', $userId)->exists();
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

    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeWithCurrentPeriodStats($query, ?string $period = null)
    {
        $period = $period ?? now()->format('Y-m');
        
        return $query
            ->withCount(['votes' => fn($q) => $q->where('period', $period)])
            ->withAvg(['votes' => fn($q) => $q->where('period', $period)->whereNotNull('rating')], 'rating');
    }

    public function scopeNearby($query, float $lat, float $lng, float $radiusKm = 10)
    {
        // Haversine formula for distance calculation
        $haversine = "(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))";
        
        return $query
            ->selectRaw("*, {$haversine} AS distance", [$lat, $lng, $lat])
            ->having('distance', '<', $radiusKm)
            ->orderBy('distance');
    }

    public function scopeWithTags($query, array $tagIds)
    {
        return $query->whereHas('tags', fn($q) => $q->whereIn('place_tags.id', $tagIds));
    }
}
