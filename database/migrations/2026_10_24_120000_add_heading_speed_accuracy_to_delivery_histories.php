<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the rider app send which way the rider faces and how fast they move,
 * so the customer's map marker can turn with the road (B6). Nullable: older
 * rider builds do not send them. Guarded because the table was created by
 * the install SQL dump, not by a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_histories', 'heading')) {
                $table->float('heading')->nullable()->after('latitude'); // degrees 0-360
            }
            if (!Schema::hasColumn('delivery_histories', 'speed')) {
                $table->float('speed')->nullable()->after('heading'); // m/s
            }
            if (!Schema::hasColumn('delivery_histories', 'accuracy')) {
                $table->float('accuracy')->nullable()->after('speed'); // metres
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_histories', function (Blueprint $table) {
            foreach (['heading', 'speed', 'accuracy'] as $column) {
                if (Schema::hasColumn('delivery_histories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
