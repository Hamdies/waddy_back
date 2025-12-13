<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if addon_settings table exists
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        // Check if akedly config already exists
        $exists = DB::table('addon_settings')
            ->where('key_name', 'akedly')
            ->where('settings_type', 'sms_config')
            ->exists();

        if (!$exists) {
            DB::table('addon_settings')->insert([
                'id' => Str::uuid()->toString(),
                'key_name' => 'akedly',
                'live_values' => json_encode([
                    'gateway' => 'akedly',
                    'mode' => 'live',
                    'status' => 0,
                    'api_key' => '',
                    'pipeline_id' => ''
                ]),
                'test_values' => json_encode([
                    'gateway' => 'akedly',
                    'mode' => 'live',
                    'status' => 0,
                    'api_key' => '',
                    'pipeline_id' => ''
                ]),
                'settings_type' => 'sms_config',
                'mode' => 'live',
                'is_active' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('addon_settings')) {
            return;
        }

        DB::table('addon_settings')
            ->where('key_name', 'akedly')
            ->where('settings_type', 'sms_config')
            ->delete();
    }
};
