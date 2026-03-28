<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('idempotency_key', 36)->nullable()->after('otp')->index();
            $table->string('device_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->bigInteger('order_timestamp')->nullable()->after('device_fingerprint');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['idempotency_key']);
            $table->dropColumn(['idempotency_key', 'device_fingerprint', 'order_timestamp']);
        });
    }
};
