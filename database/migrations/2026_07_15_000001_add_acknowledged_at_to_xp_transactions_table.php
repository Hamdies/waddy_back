<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds an acknowledgement timestamp used to gate the client-side "Level Up!"
     * celebration. A level_up transaction stays unacknowledged until the app
     * shows its celebration once and POSTs the acknowledge endpoint — so a level
     * earned by a background order (app closed) still celebrates on next open.
     */
    public function up(): void
    {
        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable()->after('is_reversed');
            // Fast lookup of a user's unacknowledged level-ups.
            $table->index(['user_id', 'xp_source', 'acknowledged_at'], 'xp_tx_ack_idx');
        });
    }

    public function down(): void
    {
        Schema::table('xp_transactions', function (Blueprint $table) {
            $table->dropIndex('xp_tx_ack_idx');
            $table->dropColumn('acknowledged_at');
        });
    }
};
