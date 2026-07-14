<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The leaderboard sorts active users by total_xp and counts users above a
     * given XP. Without this index both are full-table filesorts.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->index(['status', 'total_xp'], 'users_status_total_xp_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_status_total_xp_index');
        });
    }
};
