<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * users.level defaulted to 1, but calculateLevelFromXp() returns 0 for a
     * user below Level 1's XP threshold, and the User::created hook that
     * assigns Level 1 + unlocks its prizes only fires when level === 0 — which
     * never happened under the old default. Default to 0 so both agree:
     * "Starter" (level 0) until the first level threshold is crossed.
     *
     * Uses a raw ALTER to change only the column default. A Blueprint
     * ->change() would force Doctrine DBAL to introspect the existing
     * tinyint column, which fails ("Unknown column type tinyinteger") on
     * this stack.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE `users` ALTER COLUMN `level` SET DEFAULT 0');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE `users` ALTER COLUMN `level` SET DEFAULT 1');
    }
};
