<?php

namespace App\Providers;

use Carbon\Carbon;
use App\Models\Module;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
Carbon::setWeekStartsAt(Carbon::MONDAY);
Carbon::setWeekEndsAt(Carbon::SUNDAY);
class ConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Look up one business setting from a single cached query.
     *
     * This used to issue 13 separate `where key = ?` queries on every request,
     * before any controller ran. They all come from one small table, so one
     * cached read serves the lot.
     *
     * The cache key is deliberately the same one Helpers::get_business_settings
     * uses, so both share a single entry and a single invalidation path
     * (App\Observers\BusinessSettingObserver).
     *
     * Returns the model (or null) rather than the value, so `if ($x)` callers
     * keep distinguishing "row absent" from "row present but empty".
     */
    private function businessSetting(string $key)
    {
        static $all = null;

        if ($all === null) {
            $all = Cache::rememberForever('business_settings_all_data', function () {
                return BusinessSetting::select('key', 'value')->get();
            });
        }

        return $all->firstWhere('key', $key);
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $mode = env('APP_MODE');
        try {
            $data = $this->businessSetting('mail_config');
            $emailServices = json_decode($data['value'], true);
            if ($emailServices) {
                $config = array(
                    'status' => (Boolean)(isset($emailServices['status'])?$emailServices['status']:1),
                    'driver' => $emailServices['driver'],
                    'host' => $emailServices['host'],
                    'port' => $emailServices['port'],
                    'username' => $emailServices['username'],
                    'password' => $emailServices['password'],
                    'encryption' => $emailServices['encryption'],
                    'from' => array('address' => $emailServices['email_id'], 'name' => $emailServices['name']),
                    'sendmail' => '/usr/sbin/sendmail -bs',
                    'pretend' => false,
                );
                Config::set('mail', $config);
            }

            $odv = $this->businessSetting('order_delivery_verification');
            if ($odv) {
                Config::set('order_delivery_verification', $odv->value);
            } else {
                Config::set('order_delivery_verification', 0);
            }

            $pagination = $this->businessSetting('default_pagination');
            if ($pagination) {
                Config::set('default_pagination', $pagination->value);
            } else {
                Config::set('default_pagination', 25);
            }

            $round_up_to_digit = $this->businessSetting('digit_after_decimal_point');
            if ($round_up_to_digit) {
                Config::set('round_up_to_digit', $round_up_to_digit->value);
            } else {
                Config::set('round_up_to_digit', 2);
            }

            $dm_maximum_orders = $this->businessSetting('dm_maximum_orders');
            if ($dm_maximum_orders) {
                Config::set('dm_maximum_orders', $dm_maximum_orders->value);
            } else {
                Config::set('dm_maximum_orders', 1);
            }

            $order_confirmation_model = $this->businessSetting('order_confirmation_model');
            if ($order_confirmation_model) {
                Config::set('order_confirmation_model', $order_confirmation_model->value);
            } else {
                Config::set('order_confirmation_model', 'deliveryman');
            }

            $timezone = $this->businessSetting('timezone');
            if ($timezone) {
                Config::set('timezone', $timezone->value);
                date_default_timezone_set($timezone->value);
            }

            $timeformat = $this->businessSetting('timeformat');
            if ($timeformat && $timeformat->value == '12') {
                Config::set('timeformat', 'h:i:a');
            }
            else{
                Config::set('timeformat', 'H:i');
            }

            $canceled_by_store = $this->businessSetting('canceled_by_store');
            if ($canceled_by_store) {
                Config::set('canceled_by_store', (boolean)$canceled_by_store->value);
            }

            $canceled_by_deliveryman = $this->businessSetting('canceled_by_deliveryman');
            if ($canceled_by_deliveryman) {
                Config::set('canceled_by_deliveryman', (boolean)$canceled_by_deliveryman->value);
            }

            $toggle_veg_non_veg = (boolean) $this->businessSetting('toggle_veg_non_veg')?->value;
            if($toggle_veg_non_veg)
            {
                Config::set('toggle_veg_non_veg', $toggle_veg_non_veg);
            }
            else{
                Config::set('toggle_veg_non_veg', false);
            }

            $data = $this->businessSetting('s3_credential');
            $credentials= null;
            if($data?->value){
                $credentials = json_decode($data['value'], true);
            }
            $config = (boolean) $this->businessSetting('local_storage')?->value;
            if ($credentials) {
                Config::set('filesystems.default', $config ? ($config == 0 ? 's3' : 'local') : 'local');
                Config::set('filesystems.disks.s3.key', $credentials['key']);
                Config::set('filesystems.disks.s3.secret', $credentials['secret']);
                Config::set('filesystems.disks.s3.region', $credentials['region']);
                Config::set('filesystems.disks.s3.bucket', $credentials['bucket']);
                Config::set('filesystems.disks.s3.url', $credentials['url']);
                Config::set('filesystems.disks.s3.endpoint', $credentials['end_point']);
            }

        } catch (\Exception $ex) {
        }
    }
}
