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
        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->enum('updated_by_type', ['admin', 'vendor', 'deliveryman', 'customer', 'system'])->default('system');
            $table->unsignedBigInteger('updated_by_id')->nullable()->comment('ID of user/admin/dm who made the change');
            $table->string('reason')->nullable()->comment('Reason for status change, especially for cancellations');
            $table->text('metadata')->nullable()->comment('Additional JSON data about the change');
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            
            // Indexes
            $table->foreign('order_id')->references('id')->on('orders')->onDelete('cascade');
            $table->index(['order_id', 'created_at']);
            $table->index('new_status');
            $table->index('updated_by_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
    }
};
