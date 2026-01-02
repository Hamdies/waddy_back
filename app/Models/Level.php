<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Level extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'level_number' => 'integer',
        'xp_required' => 'integer',
        'status' => 'boolean',
    ];

    protected $appends = ['badge_image_url'];

    /**
     * Get the full URL for the badge image.
     */
    public function getBadgeImageUrlAttribute(): ?string
    {
        if ($this->badge_image) {
            return asset('storage/app/public/level/' . $this->badge_image);
        }
        return null;
    }

    /**
     * Get the prizes for this level.
     */
    public function prizes()
    {
        return $this->hasMany(LevelPrize::class);
    }

    /**
     * Get active levels.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get level by XP amount.
     */
    public static function getLevelForXp(int $xp): ?self
    {
        return static::active()
            ->where('xp_required', '<=', $xp)
            ->orderByDesc('level_number')
            ->first();
    }

    /**
     * Get next level.
     */
    public function getNextLevel(): ?self
    {
        return static::active()
            ->where('level_number', '>', $this->level_number)
            ->orderBy('level_number')
            ->first();
    }
}
