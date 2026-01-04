<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('period', 10); // Format: 2026-01 (monthly periods)
            $table->tinyInteger('rating')->nullable(); // 1-5, optional quality score
            $table->text('review')->nullable();
            $table->boolean('is_flagged')->default(false); // For moderation
            $table->timestamps();
            
            // One vote per user per place per period
            $table->unique(['place_id', 'user_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_votes');
    }
};
