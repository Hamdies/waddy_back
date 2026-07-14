<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class XpSetting extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    /** Cache key holding the whole key=>value settings map. */
    public const CACHE_KEY = 'xp_settings_map';

    /**
     * All settings as a key=>value map, cached forever (busted on write).
     * Collapses the previously per-key queries — the config endpoint and the
     * per-item XP formula both read this table many times per request.
     */
    public static function all_settings(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return static::query()->pluck('value', 'key')->toArray();
        });
    }

    /**
     * Bust the settings cache. Call after any write to xp_settings.
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get setting value by key.
     */
    public static function getValue(string $key, $default = null)
    {
        $settings = self::all_settings();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
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
     * Keep the cached map in sync on any write through Eloquent, so callers
     * (admin settings update, seed migrations, setValue) don't each have to
     * remember to bust it.
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::flushCache());
        static::deleted(fn () => self::flushCache());
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
        return (string) $value === '1';
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
