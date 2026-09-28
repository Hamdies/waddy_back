<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Store-owned categories.
 *
 * Grocery has two kinds of store. Supermarkets share one aisle tree (Fruit &
 * Veg › Fresh Fruit, …) — module-wide categories, `store_id` NULL, exactly as
 * before. Specialty shops (a dairy, a butcher, a roastery) get a short flat
 * list of their own — "Milk", "Cheese" — that belongs to that one store and
 * must never appear in the supermarket aisles or the grocery home strip.
 *
 * NULL for every existing row: nothing changes until a store category is made.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->unsignedBigInteger('store_id')->nullable()->after('module_id');
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['store_id']);
            $table->dropColumn('store_id');
        });
    }
};
