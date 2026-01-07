<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LevelPrize extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'level_id' => 'integer',
        'value' => 'float',
        'min_order_amount' => 'float',
        'usage_limit' => 'integer',
        'validity_days' => 'integer',
        'status' => 'boolean',
    ];

    protected $with = ['translations'];

    /**
     * Get translations for this prize.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    /**
     * Get translated title based on locale.
     */
    public function getTitleAttribute($value)
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'title' && $translation['locale'] == app()->getLocale()) {
                    return $translation['value'];
                }
            }
        }
        return $value;
    }

    /**
     * Get the level this prize belongs to.
     */
    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * Get user's claimed prizes for this prize type.
     */
    public function userPrizes()
    {
        return $this->hasMany(UserLevelPrize::class);
    }

    /**
     * Get active prizes.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}

