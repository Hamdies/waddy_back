<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stable machine key for a category the app has to recognise.
 *
 * The pets store switches species ("Shopping for Luna" → Cats) and the user's
 * pet is stored as `species = cat`. Matching that to a category by name breaks
 * the first time an admin renames "Cats" or the name is read in Arabic, and the
 * slug gets a `-2` suffix whenever another module already has "cats". `code`
 * is set by the seeder and never shown, so renaming the category is safe.
 *
 * NULL for every existing category.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code', 32)->nullable()->after('store_id');
            $table->index(['module_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['module_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
