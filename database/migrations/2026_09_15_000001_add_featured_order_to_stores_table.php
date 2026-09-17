<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The rank behind the numerals.
 *
 * The app's food home paints 1..10 on the featured stores, which is the
 * strongest attention pattern on the screen — and until now the order was
 * whatever the query happened to return, so the numbers looked like a
 * judgement and were an accident. Featured stores are already hand-picked;
 * this is the field that lets them be hand-*ordered* at the same time.
 *
 * Nullable, and null is not zero: an unranked featured store still appears,
 * it just sorts after every ranked one (see StoreLogic::get_stores). That
 * keeps the toggle usable on its own for anyone who does not care about
 * position.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('stores', 'featured_order')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->unsignedSmallInteger('featured_order')->nullable()->after('featured');
            // The featured list is read on every home load, in every zone.
            $table->index(['featured', 'featured_order'], 'stores_featured_order_index');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('stores', 'featured_order')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table) {
            $table->dropIndex('stores_featured_order_index');
            $table->dropColumn('featured_order');
        });
    }
};
