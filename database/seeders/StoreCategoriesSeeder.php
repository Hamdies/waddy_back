<?php

namespace Database\Seeders;

use App\CentralLogics\Helpers;
use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Moves every specialty grocery store onto its own flat category list.
 *
 * Specialty = a grocery store NOT tagged with the "Supermarkets" store type
 * (dairy, butcher, roastery, bakery…). Until now their products sat in the
 * shared supermarket tree. For each such store, every category its products
 * currently use becomes a store-owned category of the same name (Arabic name
 * copied too), and the products are re-filed into it. The app then shows that
 * store with a menu-style page: text tabs, products underneath, no sub-cats.
 *
 * Only the store's own products move; the shared tree is not modified.
 * Idempotent: store categories key on store + name, and products already in
 * one of the store's own categories are left alone.
 *
 *   php artisan db:seed --class=StoreCategoriesSeeder --force
 */
class StoreCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $moduleId = Module::where('module_type', 'grocery')->whereNull('variant')->value('id');
        if (!$moduleId) {
            $this->command->error('Grocery module not found.');

            return;
        }

        $stores = Store::withoutGlobalScope('translate')
            ->where('module_id', $moduleId)
            ->whereNotIn('id', Helpers::supermarketStoreIds())
            ->orderBy('id')
            ->get();

        DB::transaction(function () use ($stores, $moduleId) {
            foreach ($stores as $store) {
                $items = Item::withoutGlobalScopes()->where('store_id', $store->id)->get(['id', 'category_id']);
                $sourceIds = $items->pluck('category_id')->filter()->unique();

                $sources = Category::withoutGlobalScope('translate')
                    ->whereIn('id', $sourceIds)
                    ->where(fn ($q) => $q->whereNull('store_id')->orWhere('store_id', '!=', $store->id))
                    ->get();

                $created = [];
                $moved = 0;
                $priority = $sources->count();

                foreach ($sources as $source) {
                    $name = $source->getRawOriginal('name');
                    $own = Category::withoutGlobalScope('translate')->updateOrCreate(
                        ['module_id' => $moduleId, 'store_id' => $store->id, 'parent_id' => 0, 'name' => $name],
                        ['position' => 0, 'priority' => $priority--, 'status' => 1, 'image' => $source->getRawOriginal('image')],
                    );

                    $arabic = Translation::where([
                        'translationable_type' => Category::class,
                        'translationable_id' => $source->id,
                        'locale' => 'ar',
                        'key' => 'name',
                    ])->value('value');
                    if ($arabic) {
                        Translation::updateOrCreate(
                            ['translationable_type' => Category::class, 'translationable_id' => $own->id, 'locale' => 'ar', 'key' => 'name'],
                            ['value' => $arabic],
                        );
                    }

                    // Admin's convention: position 1 = the product's category.
                    $moved += Item::withoutGlobalScopes()
                        ->where('store_id', $store->id)
                        ->where('category_id', $source->id)
                        ->update([
                            'category_id' => $own->id,
                            'category_ids' => json_encode([['id' => (string) $own->id, 'position' => 1]]),
                        ]);
                    $created[] = $name;
                }

                $label = str_pad('#' . $store->id . ' ' . $store->getRawOriginal('name'), 32);
                $this->command->line($created
                    ? "{$label} {$moved} products → " . implode(', ', $created)
                    : "{$label} already on its own categories");
            }
        });
    }
}
