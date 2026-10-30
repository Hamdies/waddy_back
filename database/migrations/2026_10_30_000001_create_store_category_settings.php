<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CT-1: how one store shows a category on its own page — priority level,
 * hand-set order inside that level, hidden, and when it shows (mornings,
 * evenings, Thu–Sat). Works for shared module categories, which the store
 * can't rename or delete, and for the store's own (`categories.store_id`).
 * No row = normal priority, always shown, admin order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_category_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('category_id');
            $table->string('priority', 10)->default('normal'); // high | normal | low
            $table->unsignedInteger('sort')->nullable(); // order inside the priority level; NULL = admin order
            $table->boolean('hidden')->default(false);
            $table->string('schedule', 10)->default('always'); // always | morning | evening | weekend
            $table->timestamps();
            $table->unique(['store_id', 'category_id'], 'uq_store_category_settings');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_category_settings');
    }
};
