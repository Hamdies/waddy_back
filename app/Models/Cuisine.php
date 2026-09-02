<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * What a restaurant IS, as opposed to what it happens to sell.
 *
 * @property int $id
 * @property string $name
 * @property string|null $image
 * @property bool $status
 * @property int $priority
 */
class Cuisine extends Model
{
    use HasFactory;

    protected $with = ['translations'];

    protected $fillable = [
        'name',
        'image',
        'status',
        'priority',
    ];

    protected $casts = [
        'id' => 'integer',
        'status' => 'boolean',
        'priority' => 'integer',
    ];

    protected $appends = ['image_full_url'];

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function stores(): BelongsToMany
    {
        return $this->belongsToMany(Store::class, 'cuisine_store');
    }

    /**
     * Mirrors Category: a translated name wins over the stored column, so the
     * same row reads correctly in either locale.
     */
    public function getNameAttribute($value): string
    {
        foreach ($this->translations as $translation) {
            if ($translation['key'] === 'name') {
                return $translation['value'];
            }
        }

        return $value;
    }

    public function getImageFullUrlAttribute(): ?string
    {
        $value = $this->attributes['image'] ?? null;

        return $value ? asset('storage/cuisine/' . $value) : null;
    }

    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    protected static function booted(): ?Builder
    {
        static::addGlobalScope('translate', function (Builder $builder) {
            $builder->with(['translations' => function ($query) {
                return $query->where('locale', app()->getLocale());
            }]);
        });

        return null;
    }
}
