<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timed offers (see DispatchService). order_dispatches is where an order is in
 * dispatch, which used to live only in cache; order_offers is who was offered
 * it and what they did, which also gives the dispatch numbers.
 *
 * zones.dispatch_mode: 'waves' alerts the nearest riders while the whole zone
 * can see the order; 'offers' reserves it for them for a limited time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_dispatches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->unique();
            $table->string('mode', 10);
            $table->unsignedTinyInteger('wave')->default(0);
            $table->string('status', 10)->index(); // waiting, offering, open, closed
            $table->dateTime('next_at');
            $table->dateTime('opened_at')->nullable();
            $table->text('data');
            $table->timestamps();
        });

        Schema::create('order_offers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('delivery_man_id');
            $table->unsignedTinyInteger('wave');
            $table->string('status', 10); // offered, accepted, declined, expired, taken
            $table->unsignedInteger('distance_m')->nullable();
            $table->dateTime('offered_at');
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();
            $table->unique(['order_id', 'delivery_man_id']);
            $table->index(['delivery_man_id', 'status']);
        });

        Schema::table('zones', function (Blueprint $table) {
            $table->string('dispatch_mode', 10)->default('offers');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('dispatch_mode');
        });
        Schema::dropIfExists('order_offers');
        Schema::dropIfExists('order_dispatches');
    }
};
