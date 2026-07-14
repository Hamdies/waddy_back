<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // XP awarded to the referrer when a referred user's first order is delivered.
        DB::table('xp_settings')->updateOrInsert(
            ['key' => 'xp_referral_bonus'],
            [
                'value' => '50',
                'description' => 'XP awarded to the referrer on a referred user\'s first delivered order',
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
        DB::table('xp_settings')->where('key', 'xp_referral_bonus')->delete();
    }
};
