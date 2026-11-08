<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Smart dispatch (DispatchScorer): why a rider was picked, and for auto-assign
 * what to put the order back to if they decline.
 *
 * - score: the rider's cost in minutes when picked (lower is better).
 * - pickup_minutes: when they could be at the pickup.
 * - prev_status: the order's status before an auto-assignment.
 *
 * zones.dispatch_mode gains a third value, 'auto' (string column, no change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->decimal('score', 8, 2)->nullable()->after('distance_m');
            $table->decimal('pickup_minutes', 6, 1)->nullable()->after('score');
            $table->string('prev_status', 20)->nullable()->after('pickup_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('order_offers', function (Blueprint $table) {
            $table->dropColumn(['score', 'pickup_minutes', 'prev_status']);
        });
    }
};
