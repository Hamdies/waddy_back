<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('xp_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('reference_type'); // 'order', 'review', 'challenge', 'manual'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('xp_source'); // 'completion_bonus', 'spend_amount', 'review_bonus', 'challenge_reward'
            $table->integer('xp_amount');
            $table->unsignedBigInteger('balance_after');
            $table->text('description')->nullable();
            $table->boolean('is_reversed')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Prevent duplicate XP for same action
            $table->unique(['user_id', 'reference_type', 'reference_id', 'xp_source'], 'unique_xp_award');

            // Query performance indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_transactions');
    }
};
