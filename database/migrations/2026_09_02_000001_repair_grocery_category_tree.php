<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the grocery category tree.
 *
 * EgyptianGroceryCategoriesSeeder keyed its top-level updateOrCreate on
 * module_id + parent_id alone, which matches the FIRST top-level grocery
 * category on every iteration. So no top-level category was ever created:
 * each pass overwrote category 1 and parented that pass's subcategories to
 * it. The result on production was 2 top-level rows and 77 subcategories all
 * hanging off "Personal Care".
 *
 * This migration creates the real top-level set (matching the categories the
 * app actually displays) and re-parents every subcategory to the right one by
 * name. Nothing is deleted: rows whose parent cannot be determined are left
 * where they are and reported, so a wrong guess here is visible rather than
 * silent.
 */
return new class extends Migration
{
    /** Top-level categories, in display order, as the app shows them. */
    private const TOP_LEVEL = [
        'Fruit & Veg' => 'فاكهة وخضروات',
        'Bakery' => 'مخبوزات',
        'Poultry, Meat & Seafood' => 'دواجن ولحوم ومأكولات بحرية',
        'Dairy & Eggs' => 'ألبان وبيض',
        'Deli' => 'أطعمة جاهزة',
        'Beverages' => 'مشروبات',
        'Milk' => 'حليب',
        'Coffee & Tea' => 'قهوة وشاي',
        'Snacks & Chocolate' => 'سناكس وشوكولاتة',
        'Ice Cream' => 'آيس كريم',
        'Frozen Food' => 'أطعمة مجمدة',
        'Condiments' => 'صلصات وتوابل',
        'Cooking & Baking' => 'طبخ وخبيز',
        'Breakfast Food' => 'إفطار',
        'Canned & Jarred' => 'معلبات',
        'Protein & Special Diet' => 'بروتين ودايت',
        'Cleaning & Laundry' => 'تنظيف وغسيل',
        'Household Essentials' => 'مستلزمات منزلية',
        'Disposables' => 'أدوات يُستعمل مرة واحدة',
        'Health & Beauty' => 'صحة وجمال',
        'Personal Care' => 'عناية شخصية',
        'Baby Corner' => 'ركن الأطفال',
    ];

    /**
     * Which top-level category each existing subcategory belongs under.
     * Keyed by the subcategory's raw name column.
     */
    private const SUBCATEGORY_PARENT = [
        // Fruit & Veg
        'Fresh Fruits' => 'Fruit & Veg',
        'Fresh Vegetables' => 'Fruit & Veg',
        'Fresh Produce' => 'Fruit & Veg',
        'Herbs & Greens' => 'Fruit & Veg',
        'Organic Produce' => 'Fruit & Veg',
        'Egyptian Fruits' => 'Fruit & Veg',

        // Bakery
        'Bread' => 'Bakery',
        'Egyptian Baladi Bread' => 'Bakery',
        'Pastries' => 'Bakery',
        'Cakes & Desserts' => 'Bakery',
        'Feteer & Pies' => 'Bakery',

        // Poultry, Meat & Seafood
        'Fresh Beef' => 'Poultry, Meat & Seafood',
        'Fresh Chicken' => 'Poultry, Meat & Seafood',
        'Lamb & Goat' => 'Poultry, Meat & Seafood',
        'Processed Meat' => 'Poultry, Meat & Seafood',
        'Kofta & Minced' => 'Poultry, Meat & Seafood',
        'Fresh Fish' => 'Poultry, Meat & Seafood',
        'Frozen Fish' => 'Poultry, Meat & Seafood',
        'Shrimp & Seafood' => 'Poultry, Meat & Seafood',
        'Nile Fish' => 'Poultry, Meat & Seafood',

        // Dairy & Eggs
        'Cheese' => 'Dairy & Eggs',
        'Yogurt & Laban' => 'Dairy & Eggs',
        'Eggs' => 'Dairy & Eggs',
        'Butter & Cream' => 'Dairy & Eggs',
        'Egyptian Cheese' => 'Dairy & Eggs',

        // Milk
        'Fresh Milk' => 'Milk',

        // Beverages
        'Water' => 'Beverages',
        'Juices' => 'Beverages',
        'Soft Drinks' => 'Beverages',
        'Energy Drinks' => 'Beverages',
        'Egyptian Drinks' => 'Beverages',

        // Coffee & Tea
        'Tea & Coffee' => 'Coffee & Tea',

        // Snacks & Chocolate
        'Chips & Crisps' => 'Snacks & Chocolate',
        'Biscuits & Cookies' => 'Snacks & Chocolate',
        'Chocolate & Candy' => 'Snacks & Chocolate',
        'Nuts & Seeds' => 'Snacks & Chocolate',
        'Egyptian Snacks' => 'Snacks & Chocolate',

        // Ice Cream
        'Ice Cream' => 'Ice Cream',

        // Frozen Food
        'Frozen Vegetables' => 'Frozen Food',
        'Ready Meals' => 'Frozen Food',
        'Frozen Meat & Fish' => 'Frozen Food',
        'Frozen Pastries' => 'Frozen Food',

        // Cooking & Baking
        'Rice' => 'Cooking & Baking',
        'Pasta & Noodles' => 'Cooking & Baking',
        'Cooking Oil' => 'Cooking & Baking',
        'Sugar & Sweeteners' => 'Cooking & Baking',
        'Flour' => 'Cooking & Baking',
        'Spices' => 'Cooking & Baking',
        'Egyptian Groceries' => 'Cooking & Baking',

        // Canned & Jarred
        'Canned Foods' => 'Canned & Jarred',
        'Legumes & Beans' => 'Canned & Jarred',

        // Cleaning & Laundry
        'Cleaning Supplies' => 'Cleaning & Laundry',
        'Laundry' => 'Cleaning & Laundry',

        // Household Essentials
        'Paper Products' => 'Household Essentials',
        'Kitchen Supplies' => 'Household Essentials',
        'Air Fresheners' => 'Household Essentials',

        // Personal Care
        'Shampoo & Hair Care' => 'Personal Care',
        'Soap & Body Wash' => 'Personal Care',
        'Skincare' => 'Personal Care',
        'Oral Care' => 'Personal Care',
        'Deodorants' => 'Personal Care',
        'Feminine Care' => 'Personal Care',

        // Baby Corner
        'Baby Food' => 'Baby Corner',
        'Diapers' => 'Baby Corner',
        'Baby Formula' => 'Baby Corner',
        'Baby Care Products' => 'Baby Corner',
        'Baby Accessories' => 'Baby Corner',
    ];

    /**
     * Rows that read as top-level names but exist as subcategories. They were
     * created by an earlier pass and duplicate the tree we are building, so
     * they are demoted out of the way rather than deleted — anything already
     * pointing at them keeps working.
     */
    private const STRAY_SUBCATEGORIES = [
        'Bakery & Deli',
        'Meat, Poultry & Seafood',
        'Beverages & Snacks',
        'Pet Supplies',
        'Demo sub category',
    ];

    public function up(): void
    {
        $moduleId = DB::table('modules')->where('module_type', 'grocery')->value('id');

        if (!$moduleId) {
            return;
        }

        DB::transaction(function () use ($moduleId) {
            $now = now();

            // Category 1 was overwritten repeatedly by the buggy seeder and
            // still carries a "Demo category" English translation from the
            // original install, which is what the app renders. Reclaim it as
            // Personal Care so the row keeps its id and any references to it.
            $this->repairPersonalCare($moduleId, $now);

            $topLevelIds = $this->ensureTopLevel($moduleId, $now);

            // Re-parent every known subcategory. Matching is on the raw name
            // column, ignoring rows that are already correct.
            foreach (self::SUBCATEGORY_PARENT as $child => $parent) {
                if (!isset($topLevelIds[$parent])) {
                    continue;
                }

                DB::table('categories')
                    ->where('module_id', $moduleId)
                    ->where('parent_id', '!=', 0)
                    ->where('name', $child)
                    ->update([
                        'parent_id' => $topLevelIds[$parent],
                        'updated_at' => $now,
                    ]);
            }

            // A subcategory that shares its name with its own parent (Ice
            // Cream, Dairy & Eggs) would otherwise point at itself.
            DB::table('categories')
                ->where('module_id', $moduleId)
                ->whereColumn('id', 'parent_id')
                ->update(['parent_id' => 0, 'updated_at' => $now]);

            $this->demoteStrays($moduleId, $topLevelIds, $now);

            // A subcategory carrying the same name as one of the new
            // top-level categories (an earlier pass created "Dairy & Eggs"
            // as a child) is a duplicate of it. Hide it at the root so the
            // real top-level row is the only one with that name.
            DB::table('categories')
                ->where('module_id', $moduleId)
                ->whereIn('name', array_keys(self::TOP_LEVEL))
                ->whereNotIn('id', array_values($topLevelIds))
                ->update(['parent_id' => 0, 'status' => 0, 'updated_at' => $now]);
        });
    }

    /**
     * Category 1 keeps its id but gets a clean name, translation and ordering.
     */
    private function repairPersonalCare(int $moduleId, $now): void
    {
        $personalCare = DB::table('categories')
            ->where('module_id', $moduleId)
            ->where('id', 1)
            ->first();

        if (!$personalCare) {
            return;
        }

        DB::table('categories')->where('id', 1)->update([
            'name' => 'Personal Care',
            'parent_id' => 0,
            'position' => 0,
            'priority' => 0,
            'status' => 1,
            'updated_at' => $now,
        ]);

        // The English translation still says "Demo category"; getNameAttribute
        // prefers the translation over the column, so this is what users see.
        DB::table('translations')
            ->where('translationable_type', 'App\Models\Category')
            ->where('translationable_id', 1)
            ->where('key', 'name')
            ->where('locale', 'en')
            ->update(['value' => 'Personal Care']);

        $this->setTranslation(1, 'ar', 'name', self::TOP_LEVEL['Personal Care']);
    }

    /**
     * @return array<string,int> top-level category name => id
     */
    private function ensureTopLevel(int $moduleId, $now): array
    {
        $ids = [];
        $position = 0;

        foreach (self::TOP_LEVEL as $name => $arabic) {
            $existing = DB::table('categories')
                ->where('module_id', $moduleId)
                ->where('parent_id', 0)
                ->where('name', $name)
                ->value('id');

            if ($existing) {
                DB::table('categories')->where('id', $existing)->update([
                    'position' => $position,
                    'status' => 1,
                    'updated_at' => $now,
                ]);
                $ids[$name] = $existing;
            } else {
                $ids[$name] = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'parent_id' => 0,
                    'position' => $position,
                    'priority' => 0,
                    'module_id' => $moduleId,
                    'status' => 1,
                    'featured' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->setTranslation($ids[$name], 'ar', 'name', $arabic);
            $position++;
        }

        return $ids;
    }

    /**
     * Stray rows keep existing but stop competing with the real tree.
     */
    private function demoteStrays(int $moduleId, array $topLevelIds, $now): void
    {
        foreach (self::STRAY_SUBCATEGORIES as $stray) {
            $rows = DB::table('categories')
                ->where('module_id', $moduleId)
                ->where('name', $stray)
                ->whereNotIn('id', array_values($topLevelIds))
                ->get();

            foreach ($rows as $row) {
                // Hidden rather than removed: an item may still reference it,
                // and a hidden category is recoverable where a deleted one is
                // not.
                DB::table('categories')->where('id', $row->id)->update([
                    'status' => 0,
                    // Parked at the root as well as hidden: left under
                    // Personal Care it would still count as a child of a
                    // category it has nothing to do with.
                    'parent_id' => 0,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function setTranslation(int $categoryId, string $locale, string $key, string $value): void
    {
        $exists = DB::table('translations')
            ->where('translationable_type', 'App\Models\Category')
            ->where('translationable_id', $categoryId)
            ->where('locale', $locale)
            ->where('key', $key)
            ->exists();

        if ($exists) {
            DB::table('translations')
                ->where('translationable_type', 'App\Models\Category')
                ->where('translationable_id', $categoryId)
                ->where('locale', $locale)
                ->where('key', $key)
                ->update(['value' => $value]);

            return;
        }

        DB::table('translations')->insert([
            'translationable_type' => 'App\Models\Category',
            'translationable_id' => $categoryId,
            'locale' => $locale,
            'key' => $key,
            'value' => $value,
        ]);
    }

    /**
     * The broken state this repairs is not worth restoring, and the rows it
     * creates are safe to leave in place, so down() is deliberately a no-op.
     */
    public function down(): void
    {
    }
};
