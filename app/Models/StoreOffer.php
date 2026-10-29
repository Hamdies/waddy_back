<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** OF-1: an offer a store runs and pays for. See StoreOfferService. */
class StoreOffer extends Model
{
    public const TYPES = ['percent', 'amount', 'free_delivery'];

    protected $fillable = ['store_id', 'type', 'value', 'min_order', 'max_discount', 'start_date', 'end_date',
        'start_time', 'end_time', 'budget', 'status', 'ended_reason'];

    protected $casts = [
        'store_id' => 'integer',
        'value' => 'float',
        'min_order' => 'float',
        'max_discount' => 'float',
        'budget' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /** percent and amount share one slot (one store-wide discount at a time). */
    public function kind(): string
    {
        return $this->type === 'free_delivery' ? 'delivery' : 'discount';
    }
}
