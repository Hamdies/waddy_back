<?php

namespace Modules\PlacesToVisit\Entities;

use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlaceImage extends Model
{
    protected $table = 'place_images';

    protected $guarded = ['id'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    protected $appends = ['image_full_url'];

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class);
    }

    public function getImageFullUrlAttribute(): string
    {
        return Helpers::get_full_url('places', $this->image, 'public');
    }
}
