<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One printed box of scratch cards (W1, W2, ...). See ScratchCardService.
 */
class ScratchBatch extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'outcome_mix' => 'array',
        'active' => 'boolean',
        'use_before' => 'date',
        'quantity' => 'integer',
        'zone_id' => 'integer',
    ];

    public function codes(): HasMany
    {
        return $this->hasMany(ScratchCode::class, 'batch_id');
    }

    public function ranges(): HasMany
    {
        return $this->hasMany(ScratchRange::class, 'batch_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /** Past its use-before date: nothing in it can be bound or spent. */
    public function isExpired(): bool
    {
        return $this->use_before->endOfDay()->isPast();
    }
}
