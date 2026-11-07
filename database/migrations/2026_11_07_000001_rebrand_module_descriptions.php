<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The 6amMart demo copy is still in the module descriptions and reaches the
 * apps with every order. Swaps the name in place and fixes the "You'r" typo in
 * the same sentence; admins may have edited the rest, so it stays as is.
 *
 * Not reversible: nothing should bring the old name back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            DB::table('modules')
                ->where('description', 'like', '%6amMart%')
                ->update(['description' => DB::raw($this->rebranded('description'))]);
        }

        if (Schema::hasTable('translations')) {
            DB::table('translations')
                ->where('translationable_type', 'App\\Models\\Module')
                ->where('value', 'like', '%6amMart%')
                ->update(['value' => DB::raw($this->rebranded('value'))]);
        }
    }

    public function down(): void
    {
    }

    private function rebranded(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE({$column}, '6amMart', 'Waddy'), 'You&#39;r', 'Your'), 'You''r', 'Your')";
    }
};
