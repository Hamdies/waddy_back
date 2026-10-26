<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Busy mode (SM-4): a store can add minutes to every new order's prep time,
 * or pause new orders, for a while. Both end on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('busy_extra_minutes')->nullable();
            $table->timestamp('busy_until')->nullable();
            $table->timestamp('paused_until')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn(['busy_extra_minutes', 'busy_until', 'paused_until']);
        });
    }
};
