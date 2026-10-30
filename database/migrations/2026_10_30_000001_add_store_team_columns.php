<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ST-1: Team & roles in the store app. The base schema came from an SQL
 * install, so each column is added only when it is missing.
 *
 * - employee_roles.system_key: the starter roles (manager, cashier, kitchen,
 *   picker) the app names in the store's language and never deletes.
 * - vendor_employees.status: 0 = suspended (cannot sign in).
 * - last_active_at on owners and employees: "Active now", "2 hours ago".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_roles', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_roles', 'system_key')) {
                $table->string('system_key', 16)->nullable();
            }
        });
        Schema::table('vendor_employees', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_employees', 'status')) {
                $table->boolean('status')->default(1);
            }
            if (!Schema::hasColumn('vendor_employees', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable();
            }
        });
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'last_active_at')) {
                $table->timestamp('last_active_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // status may predate this migration, so it stays.
        Schema::table('employee_roles', fn (Blueprint $table) => $table->dropColumn('system_key'));
        Schema::table('vendor_employees', fn (Blueprint $table) => $table->dropColumn('last_active_at'));
        Schema::table('vendors', fn (Blueprint $table) => $table->dropColumn('last_active_at'));
    }
};
