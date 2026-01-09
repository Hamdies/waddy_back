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
        // Add signup bonus XP setting
        \DB::table('xp_settings')->updateOrInsert(
            ['key' => 'xp_signup_bonus'],
            [
                'value' => '50',
                'description' => 'XP awarded for new user registration',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \DB::table('xp_settings')->where('key', 'xp_signup_bonus')->delete();
    }
};
