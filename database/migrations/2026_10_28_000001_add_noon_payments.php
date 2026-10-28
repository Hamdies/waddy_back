<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * noon payments (NP-01, NP-06): the admin card row, a lookup index for
 * webhooks (noon order id lives in payment_requests.transaction_id), and
 * failed_at so a failure hook runs at most once.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('addon_settings')) {
            $exists = DB::table('addon_settings')
                ->where('key_name', 'noon')
                ->where('settings_type', 'payment_config')
                ->exists();

            if (! $exists) {
                $values = json_encode([
                    'gateway' => 'noon',
                    'mode' => 'test',
                    'status' => '0',
                    'region' => 'eg',
                    'business_identifier' => '',
                    'application_identifier' => '',
                    'application_key' => '',
                    'order_category' => '',
                    'webhook_key_id' => '',
                    'webhook_secret' => '',
                ]);

                DB::table('addon_settings')->insert([
                    'id' => Str::uuid()->toString(),
                    'key_name' => 'noon',
                    'live_values' => $values,
                    'test_values' => $values,
                    'settings_type' => 'payment_config',
                    'mode' => 'test',
                    'is_active' => 0,
                    'additional_data' => json_encode([
                        'gateway_title' => 'Card',
                        'gateway_image' => '',
                        'storage' => 'public',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Schema::table('payment_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_requests', 'failed_at')) {
                $table->timestamp('failed_at')->nullable();
            }
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_requests', function (Blueprint $table) {
            $table->dropIndex(['transaction_id']);
            $table->dropColumn('failed_at');
        });

        if (Schema::hasTable('addon_settings')) {
            DB::table('addon_settings')
                ->where('key_name', 'noon')
                ->where('settings_type', 'payment_config')
                ->delete();
        }
    }
};
