<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Out of stock for a while (store app Menu): when the store hid an item, and
 * when the scheduler should show it again (null: until the store does).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->timestamp('unavailable_since')->nullable();
            $table->timestamp('available_again_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['available_again_at']);
            $table->dropColumn(['unavailable_since', 'available_again_at']);
        });
    }
};
