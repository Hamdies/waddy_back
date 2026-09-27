<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Physical scratch cards (SC-01, SC-15). See waddi_user/docs/scratch_card_plan.md.
 *
 * - `scratch_batches`: one printed box (W1, W2, ...), with its exact prize mix.
 * - `scratch_codes`: one row per WINNING card. Losing cards are printed with
 *   no code and have no row. First apply binds the code to a customer and mints
 *   a personal coupon carrying the same code (`coupon_id`).
 * - `scratch_ranges`: which card numbers went to which rider/store, the log the
 *   custody report is built from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scratch_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->unsignedInteger('quantity');
            $table->json('outcome_mix');
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->boolean('active')->default(false);
            $table->date('use_before');
            $table->timestamps();
        });

        Schema::create('scratch_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('scratch_batches')->cascadeOnDelete();
            $table->unsignedInteger('card_no');
            $table->string('code', 16)->unique();
            $table->string('outcome_type', 20); // free_delivery | discount
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('min_order', 10, 2)->default(0);
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('bound_at')->nullable();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('coupon_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['batch_id', 'card_no']);
        });

        Schema::create('scratch_ranges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('scratch_batches')->cascadeOnDelete();
            $table->unsignedInteger('from_no');
            $table->unsignedInteger('to_no');
            $table->string('holder_type', 10); // rider | store
            $table->string('holder_name', 100);
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->date('handed_at');
            $table->string('notes', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scratch_ranges');
        Schema::dropIfExists('scratch_codes');
        Schema::dropIfExists('scratch_batches');
    }
};
