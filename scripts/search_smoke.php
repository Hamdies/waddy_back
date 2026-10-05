<?php
/**
 * Read-only smoke check of the customer search endpoints, for servers where
 * phpunit (a dev dependency) is not installed:  php scripts/search_smoke.php
 *
 * Sends GET requests through the app kernel and writes nothing.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

use App\Models\Item;
use App\Models\Store;

$failed = 0;
$check = function (string $name, bool $ok, string $detail = '') use (&$failed) {
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . ($ok || $detail === '' ? '' : "  -> $detail") . PHP_EOL;
    $failed += $ok ? 0 : 1;
};

$headersFor = fn (Store $s) => [
    'zoneId' => json_encode([$s->zone_id]),
    'moduleId' => (string) $s->module_id,
    'longitude' => (string) ($s->longitude ?? 0),
    'latitude' => (string) ($s->latitude ?? 0),
];

$get = function (string $path, array $params, array $headers) use ($kernel) {
    $server = ['HTTP_ACCEPT' => 'application/json'];
    foreach ($headers as $k => $v) {
        $server['HTTP_' . strtoupper(str_replace('-', '_', $k))] = $v;
    }
    $response = $kernel->handle(Illuminate\Http\Request::create($path, 'GET', $params, [], [], $server));

    return [$response->getStatusCode(), json_decode($response->getContent(), true) ?? []];
};

$item = Item::active()->with('store')->whereHas('store')->whereRaw("TRIM(items.name) <> ''")->first();
if (!$item) {
    exit("No searchable item in this database; nothing to check.\n");
}

$store = $item->store;
$h = $headersFor($store);
$word = preg_split('/\s+/u', trim($item->name))[0];
$storeWord = preg_split('/\s+/u', trim($store->name))[0];
echo "Item #{$item->id} \"{$item->name}\" (store #{$store->id}, zone {$store->zone_id}, module {$store->module_id})\n\n";

$search = fn (string $name, array $extra = [], ?array $headers = null) =>
    $get('/api/v1/items/search', ['name' => $name, 'limit' => 50, 'offset' => 1] + $extra, $headers ?? $h);

echo "Items\n";
[$code, $r] = $search($item->name);
$check('item finds itself by full name', $code === 200 && in_array($item->id, array_column($r['products'] ?? [], 'id')), "status $code");

$plain = $search($word)[1]['total_size'] ?? null;
$check('trailing space = same total', ($search($word . ' ')[1]['total_size'] ?? -1) === $plain);
$check('double trailing space = same total', ($search($word . '   ')[1]['total_size'] ?? -1) === $plain);
$check('leading space = same total', ($search(' ' . $word)[1]['total_size'] ?? -1) === $plain);
// All words must match; when nothing contains every word it falls back to any word.
$check('unmatched extra word falls back to the matching word', ($search($word . ' zzzzqqqq')[1]['total_size'] ?? -1) === $plain);
$ranked = array_column($search($item->name)[1]['products'] ?? [], 'id');
$check('exact-name item ranks first', ($ranked[0] ?? null) === $item->id || ($search($item->name)[1]['products'][0]['name'] ?? null) === $item->name);
$check('"%" does not match the whole catalog', ($search('%')[1]['total_size'] ?? PHP_INT_MAX) < max($plain, 1), "plain=$plain");
$cap = $search($word, ['limit' => 100000])[1]['limit'] ?? 0;
$check('limit is capped', $cap > 0 && $cap <= 100, "limit=$cap");
$check('blank name rejected (403)', $get('/api/v1/items/search', ['name' => ''], $h)[0] === 403);
$check('min_price=0 = no min_price', ($search($word, ['min_price' => 0])[1]['total_size'] ?? -1) === $plain);

$floor = $search($word, ['min_price' => $item->price + 0.01])[1]['products'] ?? [];
$check('min_price applies without max_price', collect($floor)->every(fn ($p) => $p['price'] > $item->price));

$sugg = fn (string $n) => $get('/api/v1/items/search-suggestion', ['name' => $n, 'limit' => 50], $h)[1]['total_size'] ?? -1;
$check('suggestions ignore trailing space', $sugg($word) === $sugg($word . ' '));
$check('suggestions agree with search', $sugg($word) === $plain, 'suggest=' . $sugg($word) . " search=$plain");

[$cCode, $c] = $get('/api/v1/get-combined-data', ['list_type' => 'item', 'data_type' => 'searched', 'name' => $word . ' ', 'limit' => 50, 'offset' => 1], $h);
$check('combined-data search agrees with search', $cCode === 200 && ($c['total_size'] ?? -1) === $plain, "status $cCode");

$other = Store::where('zone_id', '!=', $store->zone_id)->first();
if ($other) {
    $ids = array_column($search($item->name, [], $headersFor($other))[1]['products'] ?? [], 'id');
    $check('item not returned in another zone', !in_array($item->id, $ids));
} else {
    echo "  SKIP  zone isolation (only one zone has stores)\n";
}

echo "\nStores\n";
$stores = fn (string $n, array $headers) => $get('/api/v1/stores/search', ['name' => $n, 'limit' => 50, 'offset' => 1], $headers);
[$sc, $s] = $stores($storeWord, $h);
$check('store search answers 200', $sc === 200, "status $sc");
$check('trailing space = same total', ($stores($storeWord . ' ', $h)[1]['total_size'] ?? -1) === ($s['total_size'] ?? null));
$check('store finds itself', in_array($store->id, array_column($stores($store->name, $h)[1]['stores'] ?? [], 'id')));

$leaks = collect($s['stores'] ?? [])->filter(fn ($row) => Store::find($row['id'])?->zone_id !== $store->zone_id)->count();
$check('every result is in the requested zone', $leaks === 0, "$leaks leaked");
$check('discount_status is a bool, category_ids an array', collect($s['stores'] ?? [])->every(
    fn ($row) => is_bool($row['discount_status'] ?? null) && is_array($row['category_ids'] ?? null)));

if ($other) {
    $ids = array_column($stores($store->name, $headersFor($other))[1]['stores'] ?? [], 'id');
    $check('store not returned in another zone', !in_array($store->id, $ids));
}

echo "\n" . ($failed ? "$failed check(s) FAILED" : 'All checks passed') . PHP_EOL;
exit($failed ? 1 : 0);
