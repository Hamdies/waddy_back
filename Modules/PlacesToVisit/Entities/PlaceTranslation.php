<?php

namespace Modules\PlacesToVisit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceTranslation extends Model
{
    protected $table = 'place_translations';
    
    protected $guarded = ['id'];

    public $timestamps = false;

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }
}
