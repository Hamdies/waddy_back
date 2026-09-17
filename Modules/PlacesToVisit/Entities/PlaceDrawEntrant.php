<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One ball in the claw machine — a voter who was in a week's prize draw,
 * whether or not they won.
 *
 * Exists so the draw can be *shown*, not just awarded. See
 * PrizeDrawService::drawFor() for the write and DrawController for the read.
 */
class PlaceDrawEntrant extends Model
{
    protected $table = 'place_draw_entrants';

    protected $guarded = ['id'];

    protected $casts = [
        'votes' => 'integer',
        'rank' => 'integer',
        'total_entrants' => 'integer',
    ];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(PlaceWinner::class, 'place_winner_id');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }

    /** Pulled entrants, in the order the claw took them. */
    public function scopePulled($query)
    {
        return $query->where('rank', '>', 0)->orderBy('rank');
    }

    public function wasPulled(): bool
    {
        return $this->rank > 0;
    }
}
