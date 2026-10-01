<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Remind me every N weeks" for one product (PET-10, D4).
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $user_pet_id
 * @property int $item_id
 * @property int $store_id
 * @property int $interval_days
 * @property \Illuminate\Support\Carbon $next_at
 * @property \Illuminate\Support\Carbon|null $last_sent_at
 * @property bool $active
 */
class PetReminder extends Model
{
    public const INTERVALS = [14, 21, 28, 42];

    protected $fillable = ['user_pet_id', 'item_id', 'store_id', 'interval_days', 'next_at', 'active'];

    protected $casts = [
        'user_id' => 'integer',
        'user_pet_id' => 'integer',
        'item_id' => 'integer',
        'store_id' => 'integer',
        'interval_days' => 'integer',
        'next_at' => 'datetime',
        'last_sent_at' => 'datetime',
        'active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pet(): BelongsTo
    {
        return $this->belongsTo(UserPet::class, 'user_pet_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
