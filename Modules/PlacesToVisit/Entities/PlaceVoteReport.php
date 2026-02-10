<?php

namespace Modules\PlacesToVisit\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceVoteReport extends Model
{
    protected $table = 'place_vote_reports';

    protected $guarded = ['id'];

    public function vote(): BelongsTo
    {
        return $this->belongsTo(PlaceVote::class, 'vote_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}
