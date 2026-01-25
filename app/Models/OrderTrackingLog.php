<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTrackingLog extends Model
{
    protected $table = 'order_tracking_logs';

    protected $fillable = [
        'order_id',
        'status',
        'sub_status',
        'lat',
        'lng',
        'heading',
        'speed',
    ];

    protected $casts = [
        'order_id' => 'integer',
        'lat' => 'float',
        'lng' => 'float',
        'heading' => 'float',
        'speed' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the order this tracking log belongs to
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope to get logs for a specific order
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope to get logs ordered by latest first
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
