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
        Schema::create('xp_challenges', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->enum('challenge_type', [
                'complete_order',
                'min_order_amount',
                'new_store',
                'multiple_orders',
                'specific_category'
            ]);
            $table->enum('frequency', ['daily', 'weekly']);
            $table->json('conditions')->nullable(); // {"min_amount": 250, "order_count": 3}
            $table->unsignedInteger('xp_reward');
            $table->unsignedInteger('time_limit_hours')->default(24);
            $table->boolean('status')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('xp_challenges');
    }
};
