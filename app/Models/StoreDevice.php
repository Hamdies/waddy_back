<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A phone that is logged in to the store app for one store (SB-4).
 */
class StoreDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'vendor_id' => 'integer',
        'vendor_employee_id' => 'integer',
        'last_seen_at' => 'datetime',
    ];
}
