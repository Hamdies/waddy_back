<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master catalogue (CAT-01, CAT-02).
 *
 * One row per real product — the content a shopper sees (name, photos,
 * description, brand, size, category). The store-specific facts (price,
 * stock, discount, on/off) stay on `items`, which becomes the listing and
 * links here through items.catalog_product_id. Content is copied onto linked
 * listings on save, so every existing read path keeps reading `items`.
 *
 * Nothing reads or writes this table yet; phase 2's backfill fills it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalog_products')) {
            return;
        }

        Schema::create('catalog_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            // EAN-13 / GTIN. Optional (decision 2): fresh and loose goods have none.
            $table->string('barcode', 32)->nullable()->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            // Which disk holds `image`; `images` entries carry their own, as on items.
            $table->string('image_storage', 10)->default('public');
            $table->json('images')->nullable();
            $table->unsignedBigInteger('brand_id')->nullable();
            $table->unsignedBigInteger('unit_id')->nullable();
            // Pack size as printed ("1 L", "250 g"); part of the no-barcode match key.
            $table->string('size_value', 50)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            // Admin's convention: position 1 = category, 2 = sub-category (CAT-08).
            $table->json('category_ids')->nullable();
            $table->boolean('status')->default(true);
            // Older than updated_at = some listings still carry old content (CAT-17).
            $table->timestamp('last_propagated_at')->nullable();
            $table->timestamps();

            $table->index(['module_id', 'status']);
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_products');
    }
};
