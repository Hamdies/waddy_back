<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * Menu categories for the food module.
 *
 * These are what a restaurant's items are filed under, which is a different
 * question from what the restaurant IS — that is the cuisine tag. A steakhouse
 * carries the "Grills" cuisine and still files a dessert under "Desserts".
 *
 * Keyed on module + parent + name, so a re-run updates rather than duplicating
 * or, worse, collapsing the tree onto one row.
 */
class FoodCategoriesSeeder extends Seeder
{
    /** name => [arabic, position] */
    private const CATEGORIES = [
        'Egyptian' => ['مصري', 1],
        'Burgers' => ['برجر', 2],
        'Pizza' => ['بيتزا', 3],
        'Shawerma' => ['شاورما', 4],
        'Grills' => ['مشويات', 5],
        'Seafood' => ['مأكولات بحرية', 6],
        'Koshary' => ['كشري', 7],
        'Chicken' => ['فراخ', 8],
        'Fried Chicken' => ['فراخ مقلية', 9],
        'Sandwiches' => ['ساندويتشات', 10],
        'Pasta' => ['مكرونة', 11],
        'Salad' => ['سلطات', 12],
        'Breakfast' => ['فطار', 13],
        'Pies' => ['فطائر', 14],
        'Arabic' => ['عربي', 15],
        'Bakery & Pastries' => ['مخبوزات وحلويات', 16],
        'Desserts' => ['حلويات', 17],
        'Beverages' => ['مشروبات', 18],
        'Juices' => ['عصائر', 19],
        'Coffee & Tea' => ['قهوة وشاي', 20],
    ];

    public function run(): void
    {
        $module = Module::where('module_type', 'food')->first();

        if (!$module) {
            $this->command->error('No food module found.');

            return;
        }

        foreach (self::CATEGORIES as $name => [$arabic, $position]) {
            $category = Category::withoutGlobalScope('translate')->updateOrCreate(
                [
                    'module_id' => $module->id,
                    'parent_id' => 0,
                    'name' => $name,
                ],
                [
                    'position' => $position,
                    'priority' => 0,
                    'status' => 1,
                    'featured' => 0,
                ],
            );

            Translation::updateOrCreate(
                [
                    'translationable_type' => Category::class,
                    'translationable_id' => $category->id,
                    'locale' => 'ar',
                    'key' => 'name',
                ],
                ['value' => $arabic],
            );
        }

        $this->command->info('Seeded ' . count(self::CATEGORIES) . ' food categories.');
    }
}
