<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreBundleItem extends Model
{
    protected $fillable = [
        'store_bundle_id',
        'item_id',
        'quantity',
    ];

    protected $casts = [
        'store_bundle_id' => 'integer',
        'item_id' => 'integer',
        'quantity' => 'integer',
    ];

    /**
     * @return BelongsTo
     */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(StoreBundle::class, 'store_bundle_id');
    }

    /**
     * @return BelongsTo
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
