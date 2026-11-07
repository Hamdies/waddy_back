<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When the assigned rider first reached each stop, from their location updates
 * (RiderArrivalService). Gives the real wait at the store.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('rider_at_store_at')->nullable();
            $table->dateTime('rider_at_customer_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['rider_at_store_at', 'rider_at_customer_at']);
        });
    }
};
