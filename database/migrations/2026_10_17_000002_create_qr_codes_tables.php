<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracked QR codes. The code encodes our short link (/q/{code}); a scan
 * hits us, is counted, and redirects to `target_url`. The destination can
 * change after printing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 100);
            $table->string('type', 20)->default('link');
            $table->text('target_url');
            // The generator's settings, so the same look can be re-downloaded.
            $table->json('style')->nullable();
            $table->unsignedInteger('scans_count')->default(0);
            $table->timestamp('last_scanned_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('qr_code_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_code_id')->constrained('qr_codes')->cascadeOnDelete();
            $table->timestamp('scanned_at');
            // sha256 of IP + user agent + app key, cut to 32 chars: counts unique
            // visitors without storing an IP.
            $table->string('visitor_hash', 32);
            $table->string('device', 10);
            $table->index(['qr_code_id', 'scanned_at']);
            $table->index(['qr_code_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_code_scans');
        Schema::dropIfExists('qr_codes');
    }
};
