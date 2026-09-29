<?php

namespace App\Models;

use App\CentralLogics\Helpers;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One real product in the master catalogue (docs/catalog_plan.md).
 *
 * Owns the content; each store's `items` row is a listing that carries a
 * copy of it plus its own price and stock. Files live in the same `product/`
 * directory as item images, so a listing can point at the catalogue's file
 * directly — which is why product files are only ever deleted through
 * Helpers::deleteProductImageIfUnreferenced().
 */
class CatalogProduct extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'module_id' => 'integer',
        'brand_id' => 'integer',
        'unit_id' => 'integer',
        'category_id' => 'integer',
        'images' => 'array',
        'category_ids' => 'array',
        'status' => 'boolean',
        'last_propagated_at' => 'datetime',
    ];

    protected $appends = ['image_full_url', 'images_full_url'];

    /**
     * Every store's listing, whoever is asking: propagation has to reach all
     * of them even when a zone admin saves the product.
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Item::class, 'catalog_product_id')
            ->withoutGlobalScope(StoreScope::class)
            ->withoutGlobalScope(ZoneScope::class);
    }

    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translationable');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    /** Listings still carrying older content: a queued propagation never ran or failed (CAT-17). */
    public function scopeDrifted(Builder $query): Builder
    {
        return $query->where(function ($query) {
            $query->whereNull('last_propagated_at')
                ->orWhereColumn('updated_at', '>', 'last_propagated_at');
        })->whereHas('listings');
    }

    public function getImageFullUrlAttribute(): ?string
    {
        return $this->image
            ? Helpers::get_full_url('product', $this->image, $this->image_storage ?: 'public')
            : null;
    }

    public function getImagesFullUrlAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($image) => is_array($image) ? $image : ['img' => $image, 'storage' => 'public'])
            ->filter(fn ($image) => !empty($image['img']))
            ->map(fn ($image) => Helpers::get_full_url('product', $image['img'], $image['storage'] ?? 'public'))
            ->values()
            ->all();
    }
}
