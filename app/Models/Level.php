<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;

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
    
    protected $with = ['translations', 'storage'];

    /**
     * Get translations for this level.
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    /**
     * Get translated name based on locale.
     */
    public function getNameAttribute($value)
    {
        if (count($this->translations) > 0) {
            foreach ($this->translations as $translation) {
                if ($translation['key'] == 'name' && $translation['locale'] == app()->getLocale()) {
                    return $translation['value'];
                }
            }
        }
        return $value;
    }

    /**
     * Get the storage relationship.
     */
    public function storage()
    {
        return $this->morphMany(Storage::class, 'data');
    }

    /**
     * Get the full URL for the badge image.
     */
    public function getBadgeImageUrlAttribute(): ?string
    {
        $value = $this->badge_image;
        if (count($this->storage) > 0) {
            foreach ($this->storage as $storage) {
                if ($storage['key'] == 'badge_image') {
                    return Helpers::get_full_url('level', $value, $storage['value']);
                }
            }
        }
        
        return Helpers::get_full_url('level', $value, 'public');
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

    /**
     * Boot method to track storage location.
     */
    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($model) {
            if ($model->isDirty('badge_image')) {
                $value = Helpers::getDisk();

                DB::table('storages')->updateOrInsert([
                    'data_type' => get_class($model),
                    'data_id' => $model->id,
                    'key' => 'badge_image',
                ], [
                    'value' => $value,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
