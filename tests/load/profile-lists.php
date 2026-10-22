<?php
// Where the heavy home-screen lists spend their time, measured on the real database.
// Read-only. Run on the server:   php artisan tinker tests/load/profile-lists.php
// Change the zone / module / location below to profile another area.

use App\CentralLogics\Helpers;
use App\CentralLogics\ProductLogic;
use App\CentralLogics\StoreLogic;
use App\Models\Module;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

$zone = '[1]';
$moduleId = 1;
[$lat, $lng] = [29.9635, 31.2933];

Config::set('module.current_module_data', Module::find($moduleId));

$profile = function (string $label, callable $query, callable $format) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t0 = microtime(true);
    $data = $query();
    $t1 = microtime(true);
    $format($data);
    $t2 = microtime(true);
    $log = collect(DB::getQueryLog());
    DB::disableQueryLog();

    printf("\n== %s: query %d ms, formatting %d ms, %d SQL statements (%d ms in MySQL)\n",
        $label, ($t1 - $t0) * 1000, ($t2 - $t1) * 1000, $log->count(), $log->sum('time'));
    foreach ($log->sortByDesc('time')->take(4) as $q) {
        printf("   %5d ms  %s\n", $q['time'], substr(preg_replace('/\s+/', ' ', $q['query']), 0, 160));
    }
};

foreach ([1, 2] as $run) {
    echo "\n######## run $run" . ($run === 2 ? ' (warm)' : ' (cold)') . "\n";
    $profile('stores/popular (50 stores)',
        fn () => StoreLogic::get_popular_stores($zone, 50, 1, 'all', $lng, $lat),
        fn ($r) => Helpers::store_data_formatting($r['stores'], true));
    $profile('items/popular (12 items)',
        fn () => ProductLogic::popular_products($zone, 12, 1, 'all'),
        fn ($r) => Helpers::productListDataFormatting($r['products']));
}
echo "\nstores in module $moduleId: " . DB::table('stores')->where('module_id', $moduleId)->count()
    . ", items: " . DB::table('items')->where('module_id', $moduleId)->count()
    . ", orders: " . DB::table('orders')->count() . "\n";
