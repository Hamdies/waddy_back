<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LevelPrize extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'level_id' => 'integer',
        'value' => 'float',
        'min_order_amount' => 'float',
        'usage_limit' => 'integer',
        'validity_days' => 'integer',
        'status' => 'boolean',
    ];

    /**
     * Get the level this prize belongs to.
     */
    public function level()
    {
        return $this->belongsTo(Level::class);
    }

    /**
     * Get user's claimed prizes for this prize type.
     */
    public function userPrizes()
    {
        return $this->hasMany(UserLevelPrize::class);
    }

    /**
     * Get active prizes.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }
}
