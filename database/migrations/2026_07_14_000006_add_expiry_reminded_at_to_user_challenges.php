<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Dedupes the "challenge ending soon" reminder so a challenge is only
     * nudged once per run window.
     */
    public function up(): void
    {
        Schema::table('user_challenges', function (Blueprint $table) {
            $table->timestamp('expiry_reminded_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_challenges', function (Blueprint $table) {
            $table->dropColumn('expiry_reminded_at');
        });
    }
};
