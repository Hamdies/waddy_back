<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * noon is the only card gateway (decided 10-28, NP-14). Drops the Paymob row
 * and the 6amMart default gateway rows (Stripe, PayPal, …) from the admin
 * payment page. Cash on delivery, wallet and offline payment are business
 * settings, not addon_settings rows, so they stay.
 *
 * Not reversible: the removed rows held only those gateways' credentials.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        DB::table('addon_settings')
            ->where('settings_type', 'payment_config')
            ->where('key_name', '!=', 'noon')
            ->delete();
    }

    public function down(): void
    {
    }
};
