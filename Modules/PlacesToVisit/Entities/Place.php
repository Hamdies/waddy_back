<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        return asset('storage/app/public/places/' . $value);
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
}
