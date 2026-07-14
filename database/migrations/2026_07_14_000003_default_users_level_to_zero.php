<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * users.level defaulted to 1, but calculateLevelFromXp() returns 0 for a
     * user below Level 1's XP threshold, and the User::created hook that
     * assigns Level 1 + unlocks its prizes only fires when level === 0 — which
     * never happened under the old default. Default to 0 so both agree:
     * "Starter" (level 0) until the first level threshold is crossed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('level')->default(1)->change();
        });
    }
};
