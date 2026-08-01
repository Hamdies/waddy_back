<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to launch delivery in an area we don't serve yet.
 *
 * @property int $id
 * @property int $user_id       users.id when is_guest = 0, guests.id when 1
 * @property bool $is_guest
 * @property float $latitude
 * @property float $longitude
 * @property string|null $address
 * @property int|null $nearest_zone_id
 * @property string $source
 * @property int|null $store_id
 * @property int|null $module_id
 * @property string|null $fcm_token
 * @property bool $has_push
 * @property bool $notified
 * @property string|null $ip_address
 */
class ZoneRequest extends Model
{
    protected $fillable = [
        'user_id',
        'is_guest',
        'latitude',
        'longitude',
        'address',
        'nearest_zone_id',
        'source',
        'store_id',
        'module_id',
        'fcm_token',
        'has_push',
        'notified',
        'ip_address',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'is_guest' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'nearest_zone_id' => 'integer',
        'store_id' => 'integer',
        'module_id' => 'integer',
        'has_push' => 'boolean',
        'notified' => 'boolean',
    ];

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'nearest_zone_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Rows within roughly [$km] of a point.
     *
     * Bounding box only — deliberately no haversine. The index on
     * (latitude, longitude) can serve a BETWEEN range but not a trig
     * expression, and at this table's purpose (an approximate "how many
     * others near here?" count) a box is accurate enough that the extra
     * precision would not change a single expansion decision.
     *
     * 0.009° latitude ≈ 1km. Longitude degrees shrink with latitude, so the
     * lng span is divided by cos(lat) — at Cairo's ~30°N that widens it by
     * ~15%, which a fixed constant would get wrong.
     */
    public function scopeNear($query, float $lat, float $lng, float $km = 3.0)
    {
        $latDelta = $km * 0.009;
        $cos = cos(deg2rad($lat));
        // Guard against a division blow-up near the poles.
        $lngDelta = abs($cos) < 0.01 ? 180.0 : $latDelta / $cos;

        return $query
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);
    }
}
