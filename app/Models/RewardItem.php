<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RewardItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'item_id' => 'integer',
        'store_id' => 'integer',
        'max_value' => 'float',
        'status' => 'boolean',
    ];

    /**
     * Get the item.
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Get the store.
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get active reward items.
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Get reward items for a specific store.
     */
    public function scopeForStore($query, int $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    /**
     * Get reward items by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('reward_type', $type);
    }

    /**
     * Get available reward items for a store.
     */
    public static function getForStore(int $storeId, string $rewardType = null)
    {
        $query = static::active()->forStore($storeId)->with('item');

        if ($rewardType) {
            $query->ofType($rewardType);
        }

        return $query->get();
    }
}
