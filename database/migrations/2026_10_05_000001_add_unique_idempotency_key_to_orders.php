<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database backstop for order idempotency: one order per (user, key).
 *
 * The application locks a key while a request is in flight and answers a
 * replay with the original order (OrderSecurityService::guard). This index is
 * what guarantees it if that ever fails, such as the lock expiring under a
 * very slow request or a cache wipe at the wrong moment.
 *
 * NULL keys (older apps send none) are exempt: MySQL treats NULLs as distinct
 * in a unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The old cache-only check could be defeated by an eviction, so
        // duplicates may already exist. Keep the earliest order's key and
        // clear it on the rest, otherwise the index cannot be created.
        DB::statement(<<<'SQL'
            UPDATE orders o
            JOIN (
                SELECT user_id, idempotency_key, MIN(id) AS keep_id
                FROM orders
                WHERE idempotency_key IS NOT NULL
                GROUP BY user_id, idempotency_key
                HAVING COUNT(*) > 1
            ) d
              ON o.user_id = d.user_id
             AND o.idempotency_key = d.idempotency_key
             AND o.id <> d.keep_id
            SET o.idempotency_key = NULL
        SQL);

        Schema::table('orders', function (Blueprint $table) {
            $table->unique(['user_id', 'idempotency_key'], 'orders_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_user_idempotency_unique');
        });
    }
};
