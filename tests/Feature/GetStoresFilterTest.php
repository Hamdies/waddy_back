<?php

namespace Tests\Feature;

use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Locks in the filter/sort behavior of GET /api/v1/stores/get-stores.
 *
 * Intentionally does NOT use RefreshDatabase: this project has no isolated
 * testing database, so these tests run read-only against the configured DB
 * and skip themselves when it holds no stores.
 */
class GetStoresFilterTest extends TestCase
{
    private ?int $zoneId = null;
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!DB::getSchemaBuilder()->hasTable('stores')) {
            $this->markTestSkipped('stores table does not exist');
        }

        $store = Store::where('status', 1)->first();
        if (!$store) {
            $this->markTestSkipped('no active stores in database');
        }

        $this->zoneId = $store->zone_id;
        $this->headers = [
            'zoneId' => json_encode([$this->zoneId]),
            'longitude' => (string) ($store->longitude ?? 0),
            'latitude' => (string) ($store->latitude ?? 0),
        ];
    }

    private function getStores(string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->withHeaders($this->headers)
            ->get('/api/v1/stores/get-stores/all?offset=1&limit=50' . ($query ? '&' . $query : ''));
    }

    private function assertStoreListShape($response): array
    {
        $response->assertOk()->assertJsonStructure([
            'total_size',
            'limit',
            'offset',
            'stores',
        ]);

        return $response->json('stores');
    }

    public function test_paramless_request_is_unchanged(): void
    {
        $this->assertStoreListShape($this->getStores());
    }

    public function test_missing_zone_header_is_rejected(): void
    {
        $this->get('/api/v1/stores/get-stores/all')->assertStatus(403);
    }

    public function test_free_delivery_filter_returns_only_free_delivery_stores(): void
    {
        $baseline = $this->assertStoreListShape($this->getStores());
        $stores = $this->assertStoreListShape($this->getStores('filter=[free_delivery]'));

        $this->assertLessThanOrEqual(count($baseline), count($stores));
        foreach ($stores as $store) {
            $this->assertTrue(
                (bool) $store['free_delivery'],
                "store {$store['id']} is not free delivery"
            );
        }
    }

    public function test_discounted_filter_returns_subset(): void
    {
        $baselineTotal = $this->getStores()->json('total_size');
        $response = $this->getStores('filter=[discounted]');
        $this->assertStoreListShape($response);
        $this->assertLessThanOrEqual($baselineTotal, $response->json('total_size'));
    }

    public function test_max_delivery_time_filter(): void
    {
        $stores = $this->assertStoreListShape($this->getStores('max_delivery_time=30'));

        foreach ($stores as $store) {
            $deliveryTime = $store['delivery_time'] ?? null;
            if (!$deliveryTime || !preg_match('/^(\d+)/', $deliveryTime, $m)) {
                continue; // unparseable rows are computed as 9999 and excluded by SQL
            }
            $minutes = (int) $m[1];
            if (str_contains($deliveryTime, 'hour')) {
                $minutes *= 60;
            }
            $this->assertLessThanOrEqual(
                30,
                $minutes,
                "store {$store['id']} delivery_time '{$deliveryTime}' exceeds 30 min"
            );
        }
    }

    public function test_sort_a_z_orders_names_within_open_groups(): void
    {
        $stores = $this->assertStoreListShape($this->getStores('sort=a_z'));

        for ($i = 1; $i < count($stores); $i++) {
            if (($stores[$i]['open'] ?? null) === ($stores[$i - 1]['open'] ?? null)) {
                $this->assertLessThanOrEqual(
                    0,
                    strcasecmp($stores[$i - 1]['name'], $stores[$i]['name']),
                    "stores not in a_z order: '{$stores[$i - 1]['name']}' before '{$stores[$i]['name']}'"
                );
            }
        }
    }

    public function test_sort_distance_orders_by_distance_within_open_groups(): void
    {
        $stores = $this->assertStoreListShape($this->getStores('sort=distance'));

        for ($i = 1; $i < count($stores); $i++) {
            $prev = $stores[$i - 1];
            $curr = $stores[$i];
            if (($curr['open'] ?? null) === ($prev['open'] ?? null)
                && isset($prev['distance'], $curr['distance'])) {
                $this->assertLessThanOrEqual(
                    (float) $curr['distance'],
                    (float) $prev['distance'],
                    "stores not in distance order at index {$i}"
                );
            }
        }
    }

    public function test_sort_distance_without_location_falls_back_gracefully(): void
    {
        $response = $this->withHeaders(['zoneId' => json_encode([$this->zoneId])])
            ->get('/api/v1/stores/get-stores/all?offset=1&limit=50&sort=distance');

        $this->assertStoreListShape($response);
    }

    public function test_sort_rating_returns_ok(): void
    {
        $this->assertStoreListShape($this->getStores('sort=rating'));
    }

    public function test_category_id_filter_returns_only_matching_stores(): void
    {
        $categoryId = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('stores', 'items.store_id', '=', 'stores.id')
            ->where('stores.zone_id', $this->zoneId)
            ->value('categories.id');

        if (!$categoryId) {
            $this->markTestSkipped('no categorized items in zone');
        }

        $stores = $this->assertStoreListShape($this->getStores('category_id=' . $categoryId));

        $matchingStoreIds = DB::table('items')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->where(function ($q) use ($categoryId) {
                $q->where('categories.id', $categoryId)
                    ->orWhere('categories.parent_id', $categoryId);
            })
            ->pluck('items.store_id')
            ->unique()
            ->all();

        foreach ($stores as $store) {
            $this->assertContains(
                $store['id'],
                $matchingStoreIds,
                "store {$store['id']} has no items in category {$categoryId}"
            );
        }
    }
}
