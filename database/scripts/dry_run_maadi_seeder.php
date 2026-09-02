<?php

/**
 * Runs MaadiContentSeeder inside a transaction and rolls it back.
 *
 * The seeder is exercised against the real schema — every column, default and
 * constraint — and the database is left untouched. Anything that would fail on
 * a real run fails here, with the same error.
 *
 *   php8.2 artisan tinker --execute="require 'database/scripts/dry_run_maadi_seeder.php';"
 *
 * Delete this file once the seed has been applied and verified.
 */

use Illuminate\Support\Facades\DB;

$before = [
    'stores' => DB::table('stores')->count(),
    'vendors' => DB::table('vendors')->count(),
    'items' => DB::table('items')->count(),
    'store_schedule' => DB::table('store_schedule')->count(),
    'places' => DB::table('places')->count(),
    'place_translations' => DB::table('place_translations')->count(),
    'categories' => DB::table('categories')->count(),
    'cuisine_store' => DB::table('cuisine_store')->count(),
];

echo "=== BEFORE ===\n";
foreach ($before as $table => $count) {
    echo sprintf("  %-20s %d\n", $table, $count);
}

echo "\n=== RUNNING (will roll back) ===\n";

DB::beginTransaction();

$failed = null;

try {
    Artisan::call('db:seed', ['--class' => 'FoodCategoriesSeeder', '--force' => true]);
    echo trim(Artisan::output()) . "\n";

    Artisan::call('db:seed', ['--class' => 'MaadiContentSeeder', '--force' => true]);
    echo trim(Artisan::output()) . "\n";

    $after = [];
    foreach (array_keys($before) as $table) {
        $after[$table] = DB::table($table)->count();
    }

    echo "\n=== WOULD CREATE ===\n";
    foreach ($before as $table => $count) {
        $delta = $after[$table] - $count;
        echo sprintf("  %-20s %+d  (%d -> %d)\n", $table, $delta, $count, $after[$table]);
    }

    echo "\n=== STORES ===\n";
    $stores = DB::table('stores')
        ->join('modules', 'stores.module_id', '=', 'modules.id')
        ->select('stores.id', 'stores.name', 'modules.module_name', 'stores.zone_id')
        ->orderBy('stores.id')
        ->get();

    foreach ($stores as $store) {
        $items = DB::table('items')->where('store_id', $store->id)->count();
        $schedule = DB::table('store_schedule')->where('store_id', $store->id)->count();
        $cuisines = DB::table('cuisine_store')->where('store_id', $store->id)->count();
        echo sprintf(
            "  [%d] %-26s %-12s zone %s | %2d items, %d schedule rows, %d cuisines\n",
            $store->id, $store->name, $store->module_name, $store->zone_id ?? '-', $items, $schedule, $cuisines,
        );
    }

    echo "\n=== PLACES ===\n";
    $places = DB::table('places')
        ->leftJoin('place_translations', function ($join) {
            $join->on('places.id', '=', 'place_translations.place_id')
                ->where('place_translations.locale', '=', 'en');
        })
        ->leftJoin('place_zones', 'places.zone_id', '=', 'place_zones.id')
        ->select('places.id', 'place_translations.title', 'place_zones.name as zone')
        ->orderBy('places.id')
        ->get();

    foreach ($places as $place) {
        echo sprintf("  [%d] %-28s %s\n", $place->id, $place->title ?? '(no title)', $place->zone ?? '(no zone)');
    }

    // A store outside the delivery zone polygon is invisible in the app, so
    // this is the check that matters most.
    echo "\n=== ZONE COVERAGE ===\n";
    // The point must carry the same SRID as the stored polygon, which is 0
    // here rather than 4326, or ST_Contains refuses to compare them.
    $outside = DB::select("
        SELECT s.id, s.name
        FROM stores s
        JOIN zones z ON z.id = s.zone_id
        WHERE s.latitude IS NOT NULL
          AND NOT ST_Contains(
                ST_GeomFromText(ST_AsText(z.coordinates), 0),
                ST_GeomFromText(CONCAT('POINT(', s.longitude, ' ', s.latitude, ')'), 0)
              )
    ");

    if (count($outside) === 0) {
        echo "  every store falls inside its zone polygon\n";
    } else {
        foreach ($outside as $row) {
            echo "  OUTSIDE ZONE: [{$row->id}] {$row->name}\n";
        }
    }
} catch (\Throwable $e) {
    $failed = $e;
}

DB::rollBack();

echo "\n=== ROLLED BACK ===\n";

$restored = true;
foreach ($before as $table => $count) {
    $now = DB::table($table)->count();
    if ($now !== $count) {
        echo "  WARNING {$table} is {$now}, expected {$count}\n";
        $restored = false;
    }
}

if ($restored) {
    echo "  database unchanged\n";
}

if ($failed) {
    echo "\n=== FAILED ===\n";
    echo '  ' . get_class($failed) . ': ' . $failed->getMessage() . "\n";
    echo '  ' . $failed->getFile() . ':' . $failed->getLine() . "\n";
}
