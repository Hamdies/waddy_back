<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One rider being offered one order. See DispatchService. */
class OrderOffer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'order_id' => 'integer',
        'delivery_man_id' => 'integer',
        'wave' => 'integer',
        'distance_m' => 'integer',
        'offered_at' => 'datetime',
        'expires_at' => 'datetime',
        'responded_at' => 'datetime',
    ];
}
