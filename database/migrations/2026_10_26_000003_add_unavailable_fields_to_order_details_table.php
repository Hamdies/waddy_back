<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * XS-4: an item ran out after the order was placed. The store reports it,
 * the customer answers (continue without it, or cancel), the store acts.
 * unavailable_status: waiting | remove | cancel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('unavailable_status', 16)->nullable();
            $table->timestamp('unavailable_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['unavailable_status', 'unavailable_at']);
        });
    }
};
