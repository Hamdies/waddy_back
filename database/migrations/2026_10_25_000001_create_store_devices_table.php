<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per phone a store owner or employee is logged in on (SB-4).
 *
 * vendors.firebase_token and vendor_employees.firebase_token hold one token
 * each, so only the last phone to log in got pushes. Those columns are still
 * written for the web panel and older code; pushes to a store fan out to
 * every row here as well.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->index();
            $table->foreignId('vendor_id')->nullable()->index();
            $table->foreignId('vendor_employee_id')->nullable()->index();
            $table->string('fcm_token')->unique();
            $table->string('platform', 16)->nullable();
            // The app's language (X-localization), for pushes the server writes.
            $table->string('language', 8)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_devices');
    }
};
