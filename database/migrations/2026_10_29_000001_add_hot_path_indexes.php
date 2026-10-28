<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LV-3: indexes for the queries the store and rider apps poll. The base schema
 * came from an SQL install, so prod may already have some of them; each one is
 * added only when no index on that table already starts with the same columns.
 * MySQL 8 builds them online (no table lock).
 */
return new class extends Migration
{
    private const INDEXES = [
        // Store app: running / completed / canceled lists, sales report, insights.
        ['orders', 'idx_orders_store_status', ['store_id', 'order_status']],
        ['orders', 'idx_orders_store_created', ['store_id', 'created_at']],
        // Rider app: current orders, and the zone request list / dispatch.
        ['orders', 'idx_orders_dm_status', ['delivery_man_id', 'order_status']],
        ['orders', 'idx_orders_zone_status_dm', ['zone_id', 'order_status', 'delivery_man_id']],
        ['order_details', 'idx_order_details_order', ['order_id']],
        ['order_transactions', 'idx_order_transactions_order', ['order_id']],
        ['order_transactions', 'idx_order_transactions_dm_created', ['delivery_man_id', 'created_at']],
        ['delivery_histories', 'idx_delivery_histories_dm', ['delivery_man_id']],
        // Every authenticated call looks the caller up by token.
        ['vendors', 'idx_vendors_auth_token', ['auth_token']],
        ['vendor_employees', 'idx_vendor_employees_auth_token', ['auth_token']],
        ['delivery_men', 'idx_delivery_men_auth_token', ['auth_token']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as [$table, $name, $columns]) {
            if (!Schema::hasTable($table) || !Schema::hasColumns($table, $columns) || $this->covered($table, $columns)) {
                continue;
            }
            $cols = implode(', ', array_map(fn ($c) => $this->column($table, $c), $columns));
            DB::statement("ALTER TABLE `$table` ADD INDEX `$name` ($cols)");
        }
    }

    public function down(): void
    {
        foreach (self::INDEXES as [$table, $name]) {
            if (Schema::hasTable($table) && collect(DB::select("SHOW INDEX FROM `$table` WHERE Key_name = ?", [$name]))->isNotEmpty()) {
                DB::statement("ALTER TABLE `$table` DROP INDEX `$name`");
            }
        }
    }

    /** Whether an existing index begins with exactly these columns, in order. */
    private function covered(string $table, array $columns): bool
    {
        return collect(DB::select("SHOW INDEX FROM `$table`"))
            ->groupBy('Key_name')
            ->contains(fn ($rows) => array_slice($rows->sortBy('Seq_in_index')->pluck('Column_name')->all(), 0, count($columns)) === $columns);
    }

    /**
     * Text columns (tokens on some installs) need a prefix length. Read from
     * information_schema: Schema::getColumnType goes through Doctrine, which
     * throws on any table with an enum column (orders has several).
     */
    private function column(string $table, string $column): string
    {
        $type = strtolower((string) DB::table('information_schema.columns')
            ->where('table_schema', DB::getDatabaseName())->where('table_name', $table)->where('column_name', $column)
            ->value('data_type'));
        return in_array($type, ['text', 'mediumtext', 'longtext'], true) ? "`$column`(64)" : "`$column`";
    }
};
