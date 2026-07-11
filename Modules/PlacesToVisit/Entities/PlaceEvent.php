<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * KPI event stream (§5 of the product doc). Server-trusted events (votes)
 * are logged by VotingService; view/share events arrive from the app via
 * POST /places/events.
 */
class PlaceEvent extends Model
{
    protected $table = 'place_events';
    protected $guarded = ['id'];
    public $timestamps = false;

    protected $casts = ['meta' => 'array'];

    /** Events the app is allowed to submit */
    public const CLIENT_EVENTS = [
        'banner_view', 'race_view', 'hof_view', 'details_view',
        'share_open', 'share_done',
    ];

    /** Fire-and-forget log — analytics must never break the request */
    public static function log(
        string $event,
        ?int $userId = null,
        ?int $placeId = null,
        ?int $zoneId = null,
        ?array $meta = null
    ): void {
        try {
            static::create([
                'event' => $event,
                'user_id' => $userId,
                'place_id' => $placeId,
                'zone_id' => $zoneId,
                'period' => now()->format('o-\WW'),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            Log::warning("place_event log failed ({$event}): " . $e->getMessage());
        }
    }
}
