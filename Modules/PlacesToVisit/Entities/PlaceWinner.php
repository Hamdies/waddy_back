<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceWinner extends Model
{
    protected $table = 'place_winners';

    protected $guarded = ['id'];

    protected $casts = [
        'votes_count' => 'integer',
        'avg_rating' => 'float',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(PlaceZone::class, 'zone_id');
    }

    /** Number of weekly titles a place holds (for "3× champion" badges) */
    public static function titleCount(int $placeId): int
    {
        return static::where('place_id', $placeId)->whereNull('zone_id')->count();
    }
}
