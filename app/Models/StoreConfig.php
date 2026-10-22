<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class StoreConfig extends Model
{
    use HasFactory;

    protected $table;

    // Every StoreConfig instance (one per store row loaded) used to ask information_schema which
    // name the table has, ~5 ms each; the answer never changes after install, so ask once a day.
    private static ?string $tableName = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = self::$tableName ??= Cache::remember('store_config_table_name', 86400,
            fn () => Schema::hasTable('storeConfigs') ? 'storeConfigs' : 'store_configs');
    }

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'minimum_stock_for_warning' => 'integer',
        'is_recommended' => 'boolean',
        'is_recommended_deleted' => 'boolean',
        'halal_tag_status' => 'boolean',
        'extra_packaging_status' => 'boolean',
        'extra_packaging_amount' => 'float',
    ];

    public function Store()
    {
        return $this->belongsTo(Store::class);
    }
}
