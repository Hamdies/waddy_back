<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support tickets in the admin panel are the admin ↔ customer chats.
 * A chat is "resolved" while no message is newer than resolved_at, so a
 * customer writing again reopens it without any hook on message creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->unsignedBigInteger('assignee_admin_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn(['resolved_at', 'priority', 'assignee_admin_id']);
        });
    }
};
