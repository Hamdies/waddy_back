<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One winning scratch card. Losing cards have no row.
 */
class ScratchCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'card_no' => 'integer',
        'value' => 'float',
        'min_order' => 'float',
        'user_id' => 'integer',
        'order_id' => 'integer',
        'coupon_id' => 'integer',
        'bound_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScratchBatch::class, 'batch_id');
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
