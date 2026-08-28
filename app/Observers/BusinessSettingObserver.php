<?php

namespace App\Observers;

use App\Models\BusinessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class BusinessSettingObserver
{
    /**
     * Handle the BusinessSetting "created" event.
     */
    public function created(BusinessSetting $businessSetting): void
    {
        $this->refreshBusinessSettingsCache($businessSetting->key);
    }

    /**
     * Handle the BusinessSetting "updated" event.
     */
    public function updated(BusinessSetting $businessSetting): void
    {
        $this->refreshBusinessSettingsCache($businessSetting->key);
    }

    /**
     * Handle the BusinessSetting "deleted" event.
     */
    public function deleted(BusinessSetting $businessSetting): void
    {
        $this->refreshBusinessSettingsCache($businessSetting->key);
    }

    /**
     * Handle the BusinessSetting "restored" event.
     */
    public function restored(BusinessSetting $businessSetting): void
    {
        $this->refreshBusinessSettingsCache($businessSetting->key);
    }

    /**
     * Handle the BusinessSetting "force deleted" event.
     */
    public function forceDeleted(BusinessSetting $businessSetting): void
    {
        $this->refreshBusinessSettingsCache($businessSetting->key);
    }

    private function refreshBusinessSettingsCache($config=null)
    {
        // Forget the known keys explicitly. The `cache` table sweep below only
        // finds anything when CACHE_DRIVER=database; on the file/redis drivers
        // it returns nothing, which silently left settings cached forever after
        // an admin edit.
        Cache::forget('business_settings_all_data');

        $prefix = 'business_settings_';
        $cacheKeys = config('cache.default') === 'database'
            ? DB::table('cache')->where('key', 'like', "%" . $prefix . "%")->pluck('key')
            : collect();
        $appName = env('APP_NAME').'_cache';
        $remove_prefix = strtolower(str_replace('=', '', $appName));
        $sanitizedKeys = $cacheKeys->map(function ($key) use ($remove_prefix) {
            $key = str_replace($remove_prefix, '', $key);
            return $key;
        });
        foreach ($sanitizedKeys as $key) {
            Cache::forget($key);
        }

        $check = ['currency_model', 'currency_symbol_position', 'system_default_currency', 'language', 'company_name', 'decimal_point_settings', 'product_brand', 'digital_product', 'company_email'];
        if (in_array($config, $check) && session()->has($config)) {
            session()->forget($config);
        }
    }
}
