<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A run of card numbers handed to one rider or store (the custody log).
 */
class ScratchRange extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'from_no' => 'integer',
        'to_no' => 'integer',
        'zone_id' => 'integer',
        'handed_at' => 'date',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ScratchBatch::class, 'batch_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function size(): int
    {
        return $this->to_no - $this->from_no + 1;
    }
}
