<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('xp_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        $settings = [
            ['key' => 'xp_per_order', 'value' => '20', 'description' => 'XP awarded per completed order'],
            ['key' => 'xp_per_review', 'value' => '30', 'description' => 'XP awarded for rating and review'],
            ['key' => 'xp_daily_challenge', 'value' => '20', 'description' => 'XP for daily challenge'],
            ['key' => 'xp_weekly_challenge', 'value' => '100', 'description' => 'XP for weekly challenge'],
            ['key' => 'multiplier_food', 'value' => '1.0', 'description' => 'XP multiplier for food orders'],
            ['key' => 'multiplier_pharmacy', 'value' => '0.5', 'description' => 'XP multiplier for pharmacy orders'],
            ['key' => 'multiplier_grocery', 'value' => '0.25', 'description' => 'XP multiplier for grocery orders'],
            ['key' => 'multiplier_parcel', 'value' => '0.1', 'description' => 'XP multiplier for parcel orders'],
            ['key' => 'prize_validity_days', 'value' => '30', 'description' => 'Days until prize expires'],
            ['key' => 'leveling_status', 'value' => '1', 'description' => 'Enable/disable leveling system'],
        ];

        foreach ($settings as $setting) {
            \DB::table('xp_settings')->insert(array_merge($setting, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_settings');
    }
};
