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
    public static function recordFix(int $deliveryManId, array $data): bool
    {
        $row = self::firstOrNew(['delivery_man_id' => $deliveryManId]);
        // SEC-4: a modified app could teleport next to every new order to win
        // dispatch, or fake arrival. Drop the fix; the caller still answers
        // 200 so the app doesn't retry.
        if ($row->exists && self::impossibleJump(
            (float) $row->latitude, (float) $row->longitude, $row->time,
            (float) $data['latitude'], (float) $data['longitude'], now()
        )) {
            self::countReject($deliveryManId);
            return false;
        }

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
        $row->fill($values)->save();
        // The customer's rider-location endpoint caches this for 5 s; drop it
        // so the next read sees the new fix.
        Cache::forget('rider_loc:' . $deliveryManId);
        return true;
    }

    public const MAX_KMH = 150;
    public const JUMP_WINDOW_SECONDS = 120;

    /**
     * Faster than MAX_KMH from a fix under JUMP_WINDOW_SECONDS old. An older
     * fix (back online, out of a tunnel) is always accepted.
     */
    public static function impossibleJump(float $lat1, float $lng1, $at, float $lat2, float $lng2, $now): bool
    {
        if (!$at) {
            return false;
        }
        $seconds = max(1, $now->getTimestamp() - $at->getTimestamp());
        if ($seconds >= self::JUMP_WINDOW_SECONDS) {
            return false;
        }
        $kmh = self::distanceKm($lat1, $lng1, $lat2, $lng2) / ($seconds / 3600);
        return $kmh > self::MAX_KMH;
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Per rider per day; one warning when it reaches 20. */
    private static function countReject(int $deliveryManId): void
    {
        $key = 'rider_loc_rejects:' . $deliveryManId . ':' . now()->toDateString();
        Cache::add($key, 0, now()->addDay());
        $count = Cache::increment($key);
        if ($count === 20) {
            \Illuminate\Support\Facades\Log::warning('Rider location: 20 impossible jumps today', ['delivery_man_id' => $deliveryManId]);
        }
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
