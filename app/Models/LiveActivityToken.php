<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LiveActivityToken extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'push_token',
        'platform',
    ];

    /**
     * Get the order this token belongs to.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the user this token belongs to.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
