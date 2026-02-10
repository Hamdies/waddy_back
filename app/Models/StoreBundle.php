<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StoreBundle extends Model
{
    protected $fillable = [
        'store_id',
        'title',
        'description',
        'image',
        'price',
        'status',
    ];

    protected $casts = [
        'store_id' => 'integer',
        'price' => 'float',
        'status' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return BelongsToMany
     */
    public function items(): BelongsToMany
    {
        return $this->belongsToMany(Item::class, 'store_bundle_items')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query): mixed
    {
        return $query->where('status', 1);
    }
}
