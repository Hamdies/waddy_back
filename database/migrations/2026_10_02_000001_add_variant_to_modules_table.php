<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A module that behaves like an existing type but is its own product.
 *
 * Pets is `module_type = grocery` so that orders, order-status messages, the
 * admin product forms, POS and the store app all treat pet shops exactly like
 * grocery stores (PET-01, docs/pets_module_plan.md in waddi_user). Only the
 * customer app needs to tell them apart, and it reads this column to do it.
 *
 * NULL for every existing module: nothing changes for them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->string('variant', 32)->nullable()->after('module_type');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropColumn('variant');
        });
    }
};
