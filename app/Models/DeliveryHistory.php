<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class DeliveryHistory extends Model
{
    protected $casts = [
        'order_id' => 'integer',
        'deliveryman_id' => 'integer',
        'time' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    protected $guarded = ['id'];

    /**
     * Stores a rider's latest fix (one row per rider). Shared by the HTTP
     * endpoint and the WebSocket handler. `heading`, `speed` and `accuracy`
     * are optional (older rider builds do not send them), and so is the
     * reverse-geocoded `location` text, which nothing on the tracking path
     * reads.
     */
    public static function recordFix(int $deliveryManId, array $data): void
    {
        $values = [
            'longitude' => $data['longitude'],
            'latitude' => $data['latitude'],
            'time' => now(),
            'location' => $data['location'] ?? '',
            'updated_at' => now(),
        ];
        if (self::hasMotionColumns()) {
            foreach (['heading', 'speed', 'accuracy'] as $key) {
                $values[$key] = isset($data[$key]) && is_numeric($data[$key]) ? (float) $data[$key] : null;
            }
        }
        self::updateOrCreate(['delivery_man_id' => $deliveryManId], $values);
        // The customer's rider-location endpoint caches this for 5 s; drop it
        // so the next read sees the new fix.
        Cache::forget('rider_loc:' . $deliveryManId);
    }

    /** Until the 2026_10_24 migration runs, write the old columns only. */
    private static function hasMotionColumns(): bool
    {
        static $has = null;
        return $has ??= Schema::hasColumn('delivery_histories', 'heading');
    }
    public function delivery_man()
    {
        return $this->belongsTo(DeliveryMan::class, 'delivery_man_id');
    }
}
