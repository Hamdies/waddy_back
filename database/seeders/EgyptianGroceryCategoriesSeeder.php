<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class EgyptianGroceryCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds Egyptian grocery categories and subcategories with Arabic translations and images.
     */
    public function run(): void
    {
        // Find the grocery module
        $groceryModule = Module::where('module_type', 'grocery')->first();

        if (!$groceryModule) {
            $this->command->error('Grocery module not found! Please create a grocery module first.');
            return;
        }

        $moduleId = $groceryModule->id;
        $this->command->info("Using Grocery Module ID: {$moduleId}");

        // Define main categories with subcategories
        $categories = $this->getCategoriesData();

        foreach ($categories as $categoryData) {
            $this->createCategoryWithSubcategories($categoryData, $moduleId);
        }

        $this->command->info('Egyptian grocery categories seeded successfully!');
    }

    /**
     * Create a category with its subcategories.
     */
    private function createCategoryWithSubcategories(array $categoryData, int $moduleId): void
    {
        // Create main category
        $mainCategory = Category::updateOrCreate(
            [
                'module_id' => $moduleId,
                'parent_id' => 0,
            ],
            [
                'name' => $categoryData['name'],
                'image' => $categoryData['image'] ?? null,
                'position' => $categoryData['position'] ?? 0,
                'priority' => $categoryData['priority'] ?? 0,
                'status' => 1,
                'featured' => $categoryData['featured'] ?? 0,
            ]
        );

        // Check if category was found by name instead
        if (!$mainCategory->wasRecentlyCreated) {
            $existingCategory = Category::where('module_id', $moduleId)
                ->where('parent_id', 0)
                ->whereHas('translations', function ($query) use ($categoryData) {
                    $query->where('key', 'name')
                        ->where('value', $categoryData['name']);
                })->first();

            if ($existingCategory) {
                $mainCategory = $existingCategory;
            } else {
                // Try to find by raw name attribute
                $mainCategory = Category::where('module_id', $moduleId)
                    ->where('parent_id', 0)
                    ->where('name', $categoryData['name'])
                    ->first() ?? $mainCategory;
            }
        }

        // Update image if provided
        if (isset($categoryData['image'])) {
            $mainCategory->image = $categoryData['image'];
            $mainCategory->save();
        }

        // Add Arabic translation for main category
        $this->addTranslation($mainCategory, 'name', $categoryData['ar']);

        $this->command->info("Created category: {$categoryData['name']}");

        // Create subcategories
        if (isset($categoryData['subcategories'])) {
            foreach ($categoryData['subcategories'] as $index => $subData) {
                $subCategory = Category::updateOrCreate(
                    [
                        'module_id' => $moduleId,
                        'parent_id' => $mainCategory->id,
                        'name' => $subData['name'],
                    ],
                    [
                        'image' => $subData['image'] ?? null,
                        'position' => $index,
                        'priority' => 0,
                        'status' => 1,
                        'featured' => 0,
                    ]
                );

                // Add Arabic translation for subcategory
                $this->addTranslation($subCategory, 'name', $subData['ar']);

                $this->command->line("  - Created subcategory: {$subData['name']}");
            }
        }
    }

    /**
     * Add or update translation for a model.
     */
    private function addTranslation(Category $model, string $key, string $value): void
    {
        Translation::updateOrCreate(
            [
                'translationable_type' => 'App\Models\Category',
                'translationable_id' => $model->id,
                'locale' => 'ar',
                'key' => $key,
            ],
            [
                'value' => $value,
            ]
        );
    }

    /**
     * Get the categories data structure.
     */
    private function getCategoriesData(): array
    {
        return [
            // 1. Fruits & Vegetables
            [
                'name' => 'Fruits & Vegetables',
                'ar' => 'خضروات وفواكه',
                'image' => 'seeder/fruits_vegetables.png',
                'position' => 1,
                'priority' => 1,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Fresh Fruits', 'ar' => 'فواكه طازجة'],
                    ['name' => 'Fresh Vegetables', 'ar' => 'خضروات طازجة'],
                    ['name' => 'Herbs & Greens', 'ar' => 'أعشاب وورقيات'],
                    ['name' => 'Organic Produce', 'ar' => 'منتجات عضوية'],
                    ['name' => 'Egyptian Fruits', 'ar' => 'فواكه مصرية'],
                ],
            ],

            // 2. Dairy & Eggs
            [
                'name' => 'Dairy & Eggs',
                'ar' => 'ألبان وبيض',
                'image' => 'seeder/dairy_eggs.png',
                'position' => 2,
                'priority' => 2,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Fresh Milk', 'ar' => 'حليب طازج'],
                    ['name' => 'Cheese', 'ar' => 'جبن'],
                    ['name' => 'Yogurt & Laban', 'ar' => 'زبادي ولبن'],
                    ['name' => 'Eggs', 'ar' => 'بيض'],
                    ['name' => 'Butter & Cream', 'ar' => 'زبدة وقشطة'],
                    ['name' => 'Egyptian Cheese', 'ar' => 'جبن مصري'],
                ],
            ],

            // 3. Meat & Poultry
            [
                'name' => 'Meat & Poultry',
                'ar' => 'لحوم ودواجن',
                'image' => 'seeder/meat_poultry.png',
                'position' => 3,
                'priority' => 3,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Fresh Beef', 'ar' => 'لحم بقري طازج'],
                    ['name' => 'Fresh Chicken', 'ar' => 'دجاج طازج'],
                    ['name' => 'Lamb & Goat', 'ar' => 'لحم ضأن وماعز'],
                    ['name' => 'Processed Meat', 'ar' => 'لحوم مصنعة'],
                    ['name' => 'Kofta & Minced', 'ar' => 'كفتة ولحم مفروم'],
                ],
            ],

            // 4. Fish & Seafood
            [
                'name' => 'Fish & Seafood',
                'ar' => 'أسماك ومأكولات بحرية',
                'image' => 'seeder/fish_seafood.png',
                'position' => 4,
                'priority' => 4,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Fresh Fish', 'ar' => 'أسماك طازجة'],
                    ['name' => 'Frozen Fish', 'ar' => 'أسماك مجمدة'],
                    ['name' => 'Shrimp & Seafood', 'ar' => 'جمبري ومأكولات بحرية'],
                    ['name' => 'Nile Fish', 'ar' => 'أسماك نيلية'],
                ],
            ],

            // 5. Bakery
            [
                'name' => 'Bakery',
                'ar' => 'مخبوزات',
                'image' => 'seeder/bakery.png',
                'position' => 5,
                'priority' => 5,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Bread', 'ar' => 'خبز'],
                    ['name' => 'Egyptian Baladi Bread', 'ar' => 'عيش بلدي'],
                    ['name' => 'Pastries', 'ar' => 'معجنات'],
                    ['name' => 'Cakes & Desserts', 'ar' => 'كيك وحلويات'],
                    ['name' => 'Feteer & Pies', 'ar' => 'فطير وفطائر'],
                ],
            ],

            // 6. Beverages
            [
                'name' => 'Beverages',
                'ar' => 'مشروبات',
                'image' => 'seeder/beverages.png',
                'position' => 6,
                'priority' => 6,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Water', 'ar' => 'مياه'],
                    ['name' => 'Juices', 'ar' => 'عصائر'],
                    ['name' => 'Soft Drinks', 'ar' => 'مشروبات غازية'],
                    ['name' => 'Tea & Coffee', 'ar' => 'شاي وقهوة'],
                    ['name' => 'Energy Drinks', 'ar' => 'مشروبات طاقة'],
                    ['name' => 'Egyptian Drinks', 'ar' => 'مشروبات مصرية'],
                ],
            ],

            // 7. Household
            [
                'name' => 'Household',
                'ar' => 'منتجات منزلية',
                'image' => 'seeder/household.png',
                'position' => 7,
                'priority' => 7,
                'featured' => 0,
                'subcategories' => [
                    ['name' => 'Cleaning Supplies', 'ar' => 'منظفات'],
                    ['name' => 'Paper Products', 'ar' => 'منتجات ورقية'],
                    ['name' => 'Laundry', 'ar' => 'غسيل'],
                    ['name' => 'Kitchen Supplies', 'ar' => 'مستلزمات مطبخ'],
                    ['name' => 'Air Fresheners', 'ar' => 'معطرات جو'],
                ],
            ],

            // 8. Grocery Staples
            [
                'name' => 'Grocery Staples',
                'ar' => 'بقالة',
                'image' => 'seeder/grocery_staples.png',
                'position' => 8,
                'priority' => 8,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Rice', 'ar' => 'أرز'],
                    ['name' => 'Pasta & Noodles', 'ar' => 'مكرونة'],
                    ['name' => 'Cooking Oil', 'ar' => 'زيت طهي'],
                    ['name' => 'Sugar & Sweeteners', 'ar' => 'سكر ومحليات'],
                    ['name' => 'Flour', 'ar' => 'دقيق'],
                    ['name' => 'Spices', 'ar' => 'بهارات'],
                    ['name' => 'Canned Foods', 'ar' => 'معلبات'],
                    ['name' => 'Legumes & Beans', 'ar' => 'بقوليات'],
                    ['name' => 'Egyptian Groceries', 'ar' => 'بقالة مصرية'],
                ],
            ],

            // 9. Snacks
            [
                'name' => 'Snacks',
                'ar' => 'وجبات خفيفة',
                'image' => 'seeder/snacks.png',
                'position' => 9,
                'priority' => 9,
                'featured' => 1,
                'subcategories' => [
                    ['name' => 'Chips & Crisps', 'ar' => 'شيبسي'],
                    ['name' => 'Biscuits & Cookies', 'ar' => 'بسكويت'],
                    ['name' => 'Chocolate & Candy', 'ar' => 'شوكولاتة وحلوى'],
                    ['name' => 'Nuts & Seeds', 'ar' => 'مكسرات وبذور'],
                    ['name' => 'Egyptian Snacks', 'ar' => 'سناكس مصرية'],
                ],
            ],

            // 10. Frozen Foods
            [
                'name' => 'Frozen Foods',
                'ar' => 'أطعمة مجمدة',
                'image' => 'seeder/frozen_foods.png',
                'position' => 10,
                'priority' => 10,
                'featured' => 0,
                'subcategories' => [
                    ['name' => 'Frozen Vegetables', 'ar' => 'خضروات مجمدة'],
                    ['name' => 'Ice Cream', 'ar' => 'آيس كريم'],
                    ['name' => 'Ready Meals', 'ar' => 'وجبات جاهزة'],
                    ['name' => 'Frozen Meat & Fish', 'ar' => 'لحوم وأسماك مجمدة'],
                    ['name' => 'Frozen Pastries', 'ar' => 'معجنات مجمدة'],
                ],
            ],

            // 11. Baby Care
            [
                'name' => 'Baby Care',
                'ar' => 'أطفال',
                'image' => 'seeder/baby_care.png',
                'position' => 11,
                'priority' => 11,
                'featured' => 0,
                'subcategories' => [
                    ['name' => 'Baby Food', 'ar' => 'طعام أطفال'],
                    ['name' => 'Diapers', 'ar' => 'حفاضات'],
                    ['name' => 'Baby Formula', 'ar' => 'حليب أطفال'],
                    ['name' => 'Baby Care Products', 'ar' => 'منتجات عناية بالأطفال'],
                    ['name' => 'Baby Accessories', 'ar' => 'مستلزمات أطفال'],
                ],
            ],

            // 12. Personal Care
            [
                'name' => 'Personal Care',
                'ar' => 'عناية شخصية',
                'image' => 'seeder/personal_care.png',
                'position' => 12,
                'priority' => 12,
                'featured' => 0,
                'subcategories' => [
                    ['name' => 'Shampoo & Hair Care', 'ar' => 'شامبو وعناية بالشعر'],
                    ['name' => 'Soap & Body Wash', 'ar' => 'صابون وجل استحمام'],
                    ['name' => 'Skincare', 'ar' => 'عناية بالبشرة'],
                    ['name' => 'Oral Care', 'ar' => 'عناية بالفم'],
                    ['name' => 'Deodorants', 'ar' => 'مزيلات عرق'],
                    ['name' => 'Feminine Care', 'ar' => 'عناية نسائية'],
                ],
            ],
        ];
    }
}
