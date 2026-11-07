<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Where one order is in dispatch. See DispatchService. */
class OrderDispatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'order_id' => 'integer',
        'wave' => 'integer',
        'next_at' => 'datetime',
        'opened_at' => 'datetime',
        'data' => 'array',
    ];
}
