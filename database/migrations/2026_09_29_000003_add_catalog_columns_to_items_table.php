<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a listing (an `items` row) to its catalogue product.
 *
 * - catalog_product_id: NULL = unlinked, behaves exactly as items do today
 *   (specialty stores' own products, all of food — CAT-09, CAT-10).
 * - (store_id, catalog_product_id) unique: a store sells a product once
 *   (CAT-11). MySQL allows any number of NULLs under a unique index, so
 *   unlinked rows are unaffected.
 * - catalog_linked_at + catalog_content_backup: the content a listing had
 *   before it was linked, so the backfill can be rolled back. Image names in
 *   the backup count as references — the file is never deleted while a
 *   backup names it (CAT-15).
 *
 * NULL for every existing row: nothing changes until phase 2 links listings.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('items', 'catalog_product_id')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->unsignedBigInteger('catalog_product_id')->nullable();
            $table->timestamp('catalog_linked_at')->nullable();
            $table->json('catalog_content_backup')->nullable();

            $table->index('catalog_product_id');
            $table->unique(['store_id', 'catalog_product_id'], 'items_store_catalog_product_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('items', 'catalog_product_id')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropUnique('items_store_catalog_product_unique');
            $table->dropIndex(['catalog_product_id']);
            $table->dropColumn(['catalog_product_id', 'catalog_linked_at', 'catalog_content_backup']);
        });
    }
};
