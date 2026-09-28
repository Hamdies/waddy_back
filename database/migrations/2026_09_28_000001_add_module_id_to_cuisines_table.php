<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuisines become module-scoped "store types".
 *
 * They were global because only restaurants had them. Grocery now needs the
 * same store-level tag — Supermarkets, Dairy, Butchers & seafood — and those
 * must not appear on the food strip, nor "Pizza" on the grocery one.
 *
 * module_id is nullable: a NULL row is shown in every module. Every row that
 * exists today was created for restaurants, so they are backfilled to the food
 * module and nothing on the food home changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cuisines', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('id');
            $table->index('module_id');
        });

        $foodModuleId = DB::table('modules')->where('module_type', 'food')->orderBy('id')->value('id');
        if ($foodModuleId) {
            DB::table('cuisines')->whereNull('module_id')->update(['module_id' => $foodModuleId]);
        }
    }

    public function down(): void
    {
        Schema::table('cuisines', function (Blueprint $table) {
            $table->dropIndex(['module_id']);
            $table->dropColumn('module_id');
        });
    }
};
