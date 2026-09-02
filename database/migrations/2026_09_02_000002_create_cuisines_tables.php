<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuisines describe what a restaurant IS, which its menu cannot.
 *
 * Store discovery currently derives a store's type from the categories of the
 * items it sells (see StoreLogic::get_stores). That works for a pizzeria, but
 * an Italian restaurant sells pizza, pasta, salads and desserts — no item is
 * ever in an "Italian" category, so the restaurant is unfindable by what it
 * actually is. A store-level tag is the only thing that can express it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuisines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->boolean('status')->default(true);
            $table->integer('priority')->default(0);
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('cuisine_store', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cuisine_id')->constrained('cuisines')->cascadeOnDelete();
            $table->unsignedBigInteger('store_id');
            $table->timestamps();

            // A store carries a cuisine once; the pair is the natural key.
            $table->unique(['cuisine_id', 'store_id']);
            $table->index('store_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuisine_store');
        Schema::dropIfExists('cuisines');
    }
};
