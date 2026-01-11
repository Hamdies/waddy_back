<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class XpSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Get setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Get setting as integer.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        return (int) static::getValue($key, $default);
    }

    /**
     * Get setting as float.
     */
    public static function getFloat(string $key, float $default = 0.0): float
    {
        return (float) static::getValue($key, $default);
    }

    /**
     * Set setting value.
     */
    public static function setValue(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => (string) $value]
        );
    }

    /**
     * Check if leveling system is enabled.
     */
    public static function isEnabled(): bool
    {
        $value = static::getValue('leveling_status', '1');
        return $value == 1;
    }

    /**
     * Get vertical multiplier.
     */
    public static function getMultiplier(string $moduleType): float
    {
        $key = 'multiplier_' . strtolower($moduleType);
        return static::getFloat($key, 1.0);
    }
}
