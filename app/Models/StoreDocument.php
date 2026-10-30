<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One upload of a store document, or one payout account change (MA-4).
 * See StoreDocumentService for how the rows of a type combine.
 */
class StoreDocument extends Model
{
    public const TYPES = ['commercial_register', 'tax_card', 'health_licence', 'national_id', 'bank'];

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'reviewed_by' => 'integer',
        'details' => 'array',
        'expires_at' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
