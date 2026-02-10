<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_vote_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vote_id')->constrained('place_votes')->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->unique(['vote_id', 'reporter_id']); // one report per user per vote
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_vote_reports');
    }
};
