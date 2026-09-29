<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use App\Models\CatalogProduct;
use App\Models\Category;
use App\Models\Store;
use App\Models\Unit;
use App\Services\CatalogMergeService;
use App\Services\CatalogService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Admin master catalogue (docs/catalog_plan.md, phase 4).
 *
 * Every content write goes through CatalogService::saveContent(), which
 * copies it to each store's listing. Price, stock and discount are the
 * store's and are only set here when a listing is first created.
 */
class CatalogController extends Controller
{
    public function __construct(private CatalogService $catalog, private CatalogMergeService $merger)
    {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));

        $products = CatalogProduct::query()
            ->where('module_id', Config::get('module.current_module_id'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('barcode', $search);
                    foreach (preg_split('/\s+/', $search) as $word) {
                        $query->orWhere('name', 'like', "%{$word}%");
                    }
                });
            })
            ->when($request->query('drifted'), fn ($query) => $query->drifted())
            ->withCount('listings')
            ->withMin('listings', 'price')
            ->with('category:id,name')
            ->orderBy('name')
            ->paginate(config('default_pagination'))
            ->withQueryString();

        $driftedCount = CatalogProduct::where('module_id', Config::get('module.current_module_id'))->drifted()->count();

        return view('admin-views.catalog.index', compact('products', 'search', 'driftedCount'));
    }

    public function create()
    {
        return view('admin-views.catalog.form', $this->formData(new CatalogProduct(['status' => true])));
    }

    public function store(Request $request)
    {
        $this->validateContent($request);

        $product = new CatalogProduct(['module_id' => Config::get('module.current_module_id'), 'status' => true]);
        $this->catalog->saveContent($product, $this->contentAttributes($request, $product), $this->translations($request));

        Toastr::success(translate('messages.catalog_product_created'));

        return to_route('admin.item.catalog.edit', $product->id);
    }

    public function edit(int $id)
    {
        $product = $this->find($id);

        $listings = DB::table('items')
            ->join('stores', 'stores.id', '=', 'items.store_id')
            ->where('items.catalog_product_id', $product->id)
            ->orderBy('items.price')
            ->get(['items.id', 'items.price', 'items.discount', 'items.discount_type', 'items.stock', 'items.status', 'stores.id as store_id', 'stores.name as store_name']);

        // Stores that could list it: this module's, not selling it yet, supermarkets first.
        $supermarkets = Helpers::supermarketStoreIds();
        $availableStores = Store::withoutGlobalScope('translate')
            ->where('module_id', $product->module_id)
            ->whereNotIn('id', $listings->pluck('store_id'))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->sortByDesc(fn ($store) => in_array($store->id, $supermarkets, true))
            ->values();

        return view('admin-views.catalog.form', $this->formData($product) + compact('listings', 'availableStores'));
    }

    public function update(Request $request, int $id)
    {
        $product = $this->find($id);
        $this->validateContent($request, $product);

        $count = $this->catalog->saveContent($product, $this->contentAttributes($request, $product), $this->translations($request));

        Toastr::success($count > 0
            ? translate('messages.catalog_product_updated_for_stores', ['count' => $count])
            : translate('messages.catalog_product_updated'));

        return back();
    }

    public function addToStores(Request $request, int $id)
    {
        $product = $this->find($id);
        $request->validate([
            'stores' => 'required|array',
            'stores.*.price' => 'nullable|numeric|min:0.01',
            'stores.*.stock' => 'nullable|integer|min:0',
        ]);

        $added = 0;
        foreach ($request->input('stores', []) as $storeId => $row) {
            if (empty($row['selected']) || !isset($row['price']) || $row['price'] === '') {
                continue;
            }
            $store = Store::withoutGlobalScope('translate')->where('module_id', $product->module_id)->find((int) $storeId);
            if ($store && $this->catalog->addListing($product, $store->id, (float) $row['price'], (int) ($row['stock'] ?? 0))) {
                $added++;
            }
        }

        $added > 0
            ? Toastr::success(translate('messages.catalog_added_to_stores', ['count' => $added]))
            : Toastr::warning(translate('messages.catalog_pick_store_and_price'));

        return back();
    }

    /** Step 1: pick the duplicate and see what will happen, store by store. */
    public function mergePreview(Request $request, int $id)
    {
        $survivor = $this->find($id);
        $loser = $request->query('duplicate') ? $this->find((int) $request->query('duplicate')) : null;

        $candidates = CatalogProduct::where('module_id', $survivor->module_id)
            ->where('id', '!=', $survivor->id)
            ->withCount('listings')
            ->orderBy('name')
            ->get(['id', 'name', 'barcode']);

        $plan = $loser ? $this->merger->plan($survivor, $loser) : [];
        $blockers = $loser ? $this->merger->blockers($survivor, $loser) : [];

        return view('admin-views.catalog.merge', compact('survivor', 'loser', 'candidates', 'plan', 'blockers'));
    }

    /** Step 2: the survivor keeps its content; the duplicate's listings move to it. */
    public function merge(Request $request, int $id)
    {
        $survivor = $this->find($id);
        $loser = $this->find((int) $request->input('duplicate'));

        if ($blockers = $this->merger->blockers($survivor, $loser)) {
            foreach ($blockers as $blocker) {
                Toastr::error($blocker);
            }

            return back();
        }

        $summary = $this->merger->merge($survivor, $loser);
        Toastr::success(translate('messages.catalog_merged', $summary));

        return to_route('admin.item.catalog.edit', $survivor->id);
    }

    private function find(int $id): CatalogProduct
    {
        return CatalogProduct::where('module_id', Config::get('module.current_module_id'))->findOrFail($id);
    }

    private function formData(CatalogProduct $product): array
    {
        $languages = json_decode(BusinessSetting::where('key', 'language')->value('value') ?? '[]', true) ?: [];

        // Shared supermarket tree only: store-owned categories never hold catalogue products.
        $categories = Category::withoutGlobalScope('translate')
            ->where('position', 0)
            ->whereNull('store_id')
            ->where('module_id', $product->module_id ?? Config::get('module.current_module_id'))
            ->with(['childes' => fn ($query) => $query->withoutGlobalScope('translate')->whereNull('store_id')->orderBy('priority', 'desc')->orderBy('name')])
            ->orderBy('priority', 'desc')
            ->orderBy('name')
            ->get(['id', 'name']);

        $translations = $product->exists
            ? DB::table('translations')
                ->where('translationable_type', CatalogProduct::class)
                ->where('translationable_id', $product->id)
                ->get(['locale', 'key', 'value'])
                ->mapWithKeys(fn ($t) => ["{$t->key}.{$t->locale}" => $t->value])
                ->all()
            : [];

        return [
            'product' => $product,
            'languages' => $languages,
            'translations' => $translations,
            'categories' => $categories,
            'units' => Unit::withoutGlobalScope('translate')->orderBy('unit')->get(['id', 'unit']),
            'listings' => collect(),
            'availableStores' => collect(),
        ];
    }

    private function validateContent(Request $request, ?CatalogProduct $product = null): void
    {
        $request->validate([
            'name.default' => 'required|string|max:191',
            'name.*' => 'nullable|string|max:191',
            'description.*' => 'nullable|string|max:1000',
            'barcode' => 'nullable|string|max:32|unique:catalog_products,barcode' . ($product ? ',' . $product->id : ''),
            'category_id' => 'required|integer|exists:categories,id',
            'unit_id' => 'nullable|integer|exists:units,id',
            'image' => 'nullable|image|max:' . (config('upload.max_image_kb') ?? 2048),
            'images.*' => 'nullable|image|max:' . (config('upload.max_image_kb') ?? 2048),
        ]);
    }

    private function contentAttributes(Request $request, CatalogProduct $product): array
    {
        $category = Category::withoutGlobalScope('translate')->findOrFail((int) $request->input('category_id'));
        $categoryIds = $category->parent_id
            ? [['id' => (string) $category->parent_id, 'position' => 1], ['id' => (string) $category->id, 'position' => 2]]
            : [['id' => (string) $category->id, 'position' => 1]];

        $attributes = [
            'name' => trim($request->input('name.default')),
            'description' => $request->input('description.default'),
            'barcode' => $request->filled('barcode') ? trim($request->input('barcode')) : null,
            'unit_id' => $request->input('unit_id') ?: null,
            'category_id' => $category->id,
            'category_ids' => $categoryIds,
            'status' => $request->boolean('status'),
        ];

        // Old files are removed by saveContent() once no store or backup uses them.
        if ($request->hasFile('image')) {
            $attributes['image'] = Helpers::upload('product/', 'png', $request->file('image'));
            $attributes['image_storage'] = Helpers::getDisk();
        }

        $removed = (array) $request->input('remove_images', []);
        $gallery = array_values(array_filter(
            $this->catalog->decodeImages($product->images),
            fn ($image) => !in_array($image['img'], $removed, true)
        ));
        foreach ((array) $request->file('images', []) as $file) {
            $gallery[] = ['img' => Helpers::upload('product/', 'png', $file), 'storage' => Helpers::getDisk()];
        }
        $attributes['images'] = $gallery;

        return $attributes;
    }

    /** name[xx] / description[xx] for every language except the default column. */
    private function translations(Request $request): array
    {
        $rows = [];
        foreach (['name', 'description'] as $key) {
            foreach ((array) $request->input($key, []) as $locale => $value) {
                if ($locale !== 'default' && trim((string) $value) !== '') {
                    $rows[] = ['locale' => $locale, 'key' => $key, 'value' => trim($value)];
                }
            }
        }

        return $rows;
    }
}
