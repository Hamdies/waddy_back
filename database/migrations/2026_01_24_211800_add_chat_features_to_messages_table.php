<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Chat System Enhancement:
     * - status: Track message delivery state (sent, delivered, read)
     * - read_at: Timestamp when message was actually read
     * - reply_to_message_id: Enable reply-to-message feature
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->enum('status', ['sent', 'delivered', 'read'])->default('sent')->after('is_seen');
            $table->timestamp('read_at')->nullable()->after('status');
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->after('read_at');
            
            // Index for efficient reply lookups
            $table->index('reply_to_message_id', 'idx_messages_reply_to');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_messages_reply_to');
            $table->dropColumn(['status', 'read_at', 'reply_to_message_id']);
        });
    }
};
