<?php

namespace App\Http\Controllers\Api\V1\Vendor;

use App\CentralLogics\CategoryLogic;
use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Item;
use App\Models\StoreCategorySetting;
use App\Models\Translation;
use App\Services\StoreCategoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    public function get_categories(Request $request)
    {
        $vendor = $request['vendor'];
        try {
            // Shared categories and this store's own; never another store's own.
            $categories = Category::where(['position' => 0, 'status' => 1])
                ->where(fn ($q) => $q->whereNull('categories.store_id')->orWhere('categories.store_id', $vendor->stores[0]->id))
                ->when(config('module.current_module_data'), function ($query) {
                    $query->module(config('module.current_module_data')['id']);
                })
                ->orderBy('priority', 'desc')
                ->select('categories.*')
                ->selectRaw('categories.*, ( SELECT COUNT(*)
                            FROM items WHERE items.store_id = ?
                            AND JSON_CONTAINS(items.category_ids, JSON_OBJECT("id", CAST(categories.id AS CHAR)), "$") AND JSON_CONTAINS(items.category_ids, JSON_OBJECT("position", 1), "$") ) AS products_count', [$vendor->stores[0]->id])
                ->get();



            return response()->json($categories, 200);
        } catch (\Exception $e) {
            return response()->json([$e], 200);
        }
    }

    public function get_childes(Request $request, $id)
    {
        $vendor = $request['vendor'];

        try {
            $categories = Category::query();
            if (is_numeric($id)) {
                $categories = $categories->where('parent_id', $id);
            } else {
                $categories = $categories->whereHas('parent', function ($query) use ($id) {
                    $query->where('slug', $id);
                });
            }
            $categories = $categories->where('status', 1)
                ->where(fn ($q) => $q->whereNull('categories.store_id')->orWhere('categories.store_id', $vendor->stores[0]->id))
                ->orderBy('priority', 'desc')
                ->withCount(['products' => function ($query) use ($vendor) {
                    $query->where('store_id', $vendor->stores[0]->id);
                }])
                ->get();

            return response()->json($categories, 200);
        } catch (\Exception $e) {
            return response()->json([], 200);
        }
    }

    public function getCategoryWiseProducts(Request $request, $id)
    {
        $vendor = $request['vendor'];
        $limit = $request['limit'] ?? 25;
        $offset = $request['offset'] ?? 1;
        $items = Item::where('store_id', $vendor->stores[0]->id)
        ->where('is_approved', 1);
        if($request->sub_category == 1){
            $items = $items->where('category_id', $id);
        } else {
            $items = $items->whereRaw(
                "JSON_CONTAINS(category_ids, JSON_OBJECT('id', CAST(? AS CHAR), 'position', ?), '$')",
                [$id, 1]
            );
        }

        $items = $items->paginate($limit, ['*'], 'page', $offset);

        $formated_items = Helpers::product_data_formatting($items->items(), true, false, app()->getLocale());

        $data = [
            'total_size' => $items->total(),
            'limit' => $limit,
            'offset' => $offset,
            'items' => $formated_items
        ];

        return response()->json($data, 200);
    }

    /**
     * What the store app (6amMart 3.2) calls for a sub-category's items; it
     * got 404 here (SB-10).
     */
    public function getSubCategoryWiseProducts(Request $request, $id)
    {
        $request->merge(['sub_category' => 1]);
        return $this->getCategoryWiseProducts($request, $id);
    }

    /** CT-1: the Categories screen's two-level tree with this store's settings. */
    public function tree(Request $request)
    {
        $store = $request['vendor']->stores[0];
        return response()->json(['categories' => StoreCategoryService::tree($store)], 200);
    }

    /** CT-1: a category or subcategory of the store's own. */
    public function store(Request $request)
    {
        $store = $request['vendor']->stores[0];
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'parent_id' => 'nullable|integer',
            'priority' => 'nullable|in:' . implode(',', StoreCategorySetting::PRIORITIES),
            'schedule' => 'nullable|in:' . implode(',', StoreCategorySetting::SCHEDULES),
            'hidden' => 'nullable|boolean',
        ]);
        $parent = null;
        $validator->after(function ($v) use ($request, $store, &$parent) {
            if ($request['parent_id']) {
                $parent = StoreCategoryService::visibleTo(Category::query(), $store)
                    ->where(['id' => $request['parent_id'], 'position' => 0])->first();
                if (!$parent) {
                    $v->errors()->add('parent_id', translate('messages.category_not_found'));
                }
            }
        });
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        $category = DB::transaction(function () use ($request, $store, $parent) {
            $category = new Category([
                'parent_id' => $parent?->id ?? 0,
                'position' => $parent ? 1 : 0,
                'priority' => 0,
                'status' => 1,
                'module_id' => $store->module_id,
                'store_id' => $store->id,
            ]);
            $category->name = trim($request['name']);
            $category->save(); // the created hook adds the slug
            StoreCategoryService::set($store, $category->id, array_filter([
                'priority' => $request['priority'] ?? 'normal',
                // Schedule and visibility belong to the top level only.
                'schedule' => $parent ? null : ($request['schedule'] ?? 'always'),
                'hidden' => $parent ? null : $request->boolean('hidden'),
            ], fn ($v) => $v !== null));
            return $category;
        });

        return response()->json(['id' => $category->id, 'categories' => StoreCategoryService::tree($store)], 200);
    }

    /**
     * CT-1: settings for any category in the store's tree; name and parent
     * for the store's own only.
     */
    public function update(Request $request, $id)
    {
        $store = $request['vendor']->stores[0];
        $category = StoreCategoryService::visibleTo(Category::query(), $store)->find($id);
        if (!$category) {
            return response()->json(['errors' => [['code' => 'id', 'message' => translate('messages.category_not_found')]]], 404);
        }
        $own = $category->store_id === $store->id;
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'parent_id' => 'nullable|integer',
            'priority' => 'nullable|in:' . implode(',', StoreCategorySetting::PRIORITIES),
            'schedule' => 'nullable|in:' . implode(',', StoreCategorySetting::SCHEDULES),
            'hidden' => 'nullable|boolean',
        ]);
        $parent = null;
        $validator->after(function ($v) use ($request, $store, $category, $own, &$parent) {
            $renamed = $request->filled('name') && trim($request['name']) !== $category->name;
            $moved = $category->position != 0 && $request->filled('parent_id') && (int) $request['parent_id'] !== (int) $category->parent_id;
            if (!$own && ($renamed || $moved)) {
                $v->errors()->add('name', translate('messages.shared_category_locked'));
            }
            if ($moved) {
                $parent = StoreCategoryService::visibleTo(Category::query(), $store)
                    ->where(['id' => $request['parent_id'], 'position' => 0])->first();
                if (!$parent) {
                    $v->errors()->add('parent_id', translate('messages.category_not_found'));
                }
            }
        });
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }

        DB::transaction(function () use ($request, $store, $category, $own, $parent) {
            if ($own && $request->filled('name') && trim($request['name']) !== $category->name) {
                $name = trim($request['name']);
                $category->name = $name;
                // A translated name would keep showing over the new one.
                Translation::where(['translationable_type' => Category::class, 'translationable_id' => $category->id, 'key' => 'name'])
                    ->update(['value' => $name]);
            }
            if ($parent) {
                $category->parent_id = $parent->id;
                // Joins the end of its new category.
                StoreCategoryService::set($store, $category->id, ['sort' => null]);
                Item::withoutGlobalScopes()->where('store_id', $store->id)->where('category_id', $category->id)->get()
                    ->each(function (Item $item) use ($parent, $category) {
                        $item->category_ids = json_encode([
                            ['id' => (string) $parent->id, 'position' => 1],
                            ['id' => (string) $category->id, 'position' => 2],
                        ]);
                        $item->save();
                    });
            }
            $category->isDirty() && $category->save();

            $fields = array_filter([
                'priority' => $request['priority'],
                'schedule' => $category->position == 0 ? $request['schedule'] : null,
                'hidden' => $category->position == 0 && $request->has('hidden') ? $request->boolean('hidden') : null,
            ], fn ($v) => $v !== null);
            if ($fields) {
                StoreCategoryService::set($store, $category->id, $fields);
            }
        });

        return response()->json(['categories' => StoreCategoryService::tree($store)], 200);
    }

    /** CT-1: deletes one of the store's own categories once nothing is filed under it. */
    public function destroy(Request $request, $id)
    {
        $store = $request['vendor']->stores[0];
        $category = Category::where('store_id', $store->id)->find($id);
        if (!$category) {
            return response()->json(['errors' => [['code' => 'id', 'message' => translate('messages.shared_category_locked')]]], 403);
        }
        if (StoreCategoryService::itemCount($store, $category) > 0) {
            return response()->json(['errors' => [['code' => 'items', 'message' => translate('messages.category_has_items')]]], 403);
        }
        DB::transaction(function () use ($store, $category) {
            $ids = Category::where('parent_id', $category->id)->where('store_id', $store->id)->pluck('id')->push($category->id);
            Translation::where('translationable_type', Category::class)->whereIn('translationable_id', $ids)->delete();
            StoreCategorySetting::where('store_id', $store->id)->whereIn('category_id', $ids)->delete();
            Category::whereIn('id', $ids)->delete();
        });
        return response()->json(['categories' => StoreCategoryService::tree($store)], 200);
    }

    /**
     * CT-1: the store's order for one level of siblings (top categories, or
     * one category's subcategories), first to last.
     */
    public function order(Request $request)
    {
        $store = $request['vendor']->stores[0];
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array|max:200',
            'ids.*' => 'integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        $allowed = StoreCategoryService::visibleTo(Category::query(), $store)->whereIn('id', $request['ids'])->pluck('id')->all();
        DB::transaction(function () use ($request, $store, $allowed) {
            foreach (array_values($request['ids']) as $i => $id) {
                if (in_array((int) $id, $allowed, true)) {
                    StoreCategoryService::set($store, (int) $id, ['sort' => $i]);
                }
            }
        });
        return response()->json(['categories' => StoreCategoryService::tree($store)], 200);
    }
}
