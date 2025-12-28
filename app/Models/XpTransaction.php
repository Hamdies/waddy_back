<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XpTransaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'user_id' => 'integer',
        'reference_id' => 'integer',
        'xp_amount' => 'integer',
        'balance_after' => 'integer',
        'is_reversed' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that owns this transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if transaction already exists (prevent duplicates).
     */
    public static function exists(int $userId, string $referenceType, int $referenceId, string $xpSource): bool
    {
        return static::where('user_id', $userId)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('xp_source', $xpSource)
            ->exists();
    }

    /**
     * Get transactions for a specific reference.
     */
    public function scopeForReference($query, string $referenceType, int $referenceId)
    {
        return $query->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }
}
