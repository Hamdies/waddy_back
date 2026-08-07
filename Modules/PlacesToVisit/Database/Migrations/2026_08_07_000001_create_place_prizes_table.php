<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_prizes', function (Blueprint $table) {
            $table->id();
            $table->string('period', 10)->index();              // e.g. 2026-W32
            $table->unsignedBigInteger('place_winner_id')->index();
            $table->unsignedBigInteger('place_id')->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('code', 12)->unique();               // e.g. WD7K-3XQ2
            $table->enum('status', ['active', 'redeemed', 'expired'])->default('active');
            $table->decimal('value_cap', 8, 2)->nullable();     // snapshot at draw time
            $table->string('currency', 8)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->string('redeemed_ip', 45)->nullable();
            $table->timestamp('reminder_sent_at')->nullable();  // dedupes the 24h-left push
            $table->timestamps();

            // One prize per user per week — the hard guard that keeps the draw
            // idempotent even when WinnerService lazy-closes on every read.
            $table->unique(['period', 'user_id']);
            $table->index(['status', 'expires_at']);

            $table->foreign('place_winner_id')->references('id')->on('place_winners')->onDelete('cascade');
            $table->foreign('place_id')->references('id')->on('places')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_prizes');
    }
};
