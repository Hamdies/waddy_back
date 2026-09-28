<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use App\Models\Discount;
use App\Models\Module;
use App\Models\Store;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Grocery store types plus a test catalogue that exercises them.
 *
 * Store types (Supermarkets, Roasteries, …) are what a store IS — they live in
 * the cuisines table scoped to the grocery module (migration
 * 2026_09_28_000001) and drive the grocery home's strip. Item categories
 * (Fresh Milk, Frozen) are the aisles inside a store and are untouched here.
 *
 * The stores are TEST DATA with invented names — unlike MaadiContentSeeder,
 * which is hand-checked against real businesses. They exist so every control
 * on the grocery home has something to show: each type has stores, delivery
 * times straddle the 30-minute chip, fees differ, some stores deliver free,
 * and some carry a store discount (the offer collar) or discounted items (the
 * Offers chip).
 *
 * Idempotent: types key on name + module, stores on phone, items on
 * store + name. Images are left null — upload type/store art in admin.
 *
 * Run after `php artisan migrate` and after EgyptianGroceryCategoriesSeeder:
 *   php artisan db:seed --class=GroceryStoreTypesSeeder
 */
class GroceryStoreTypesSeeder extends MaadiContentSeeder
{
    /** name => [arabic, priority]. Higher priority sorts first in the app. */
    private const TYPES = [
        'Supermarkets' => ['سوبر ماركت', 100],
        'Fresh produce' => ['خضار وفاكهة', 95],
        'Butchers & seafood' => ['جزارة وأسماك', 90],
        'Dairy' => ['ألبان', 85],
        'Bakeries & sweets' => ['مخابز وحلويات', 80],
        'Roasteries' => ['محامص', 75],
        'Global & organic' => ['عالمي وأورجانيك', 70],
    ];

    /** Real stores already seeded by MaadiContentSeeder, tagged by name. */
    private const EXISTING_STORE_TYPES = [
        'Seoudi Market Maadi' => ['Supermarkets', 'Fresh produce'],
        'Metro Market Degla' => ['Supermarkets'],
        'Gourmet Egypt Maadi' => ['Supermarkets', 'Global & organic'],
    ];

    public function run(): void
    {
        $grocery = Module::where('module_type', 'grocery')->first();
        $zone = Zone::first();

        if (!$grocery || !$zone) {
            $this->command->error('Expected a grocery module and at least one zone.');

            return;
        }

        DB::transaction(function () use ($grocery, $zone) {
            $this->seedTypes($grocery->id);
            $this->tagExistingStores($grocery->id);

            foreach ($this->stores() as $data) {
                $store = $this->createStore($data, $grocery->id, $zone->id);
                $this->setStoreDiscount($store, $data['store_discount'] ?? null);
            }
        });

        $this->command->info('Seeded ' . count(self::TYPES) . ' grocery store types and ' . count($this->stores()) . ' stores.');
    }

    private function seedTypes(int $moduleId): void
    {
        foreach (self::TYPES as $name => [$arabic, $priority]) {
            $type = Cuisine::withoutGlobalScope('translate')->updateOrCreate(
                ['name' => $name, 'module_id' => $moduleId],
                ['status' => 1, 'priority' => $priority],
            );

            Translation::updateOrCreate(
                [
                    'translationable_type' => Cuisine::class,
                    'translationable_id' => $type->id,
                    'locale' => 'ar',
                    'key' => 'name',
                ],
                ['value' => $arabic],
            );
        }
    }

    private function tagExistingStores(int $moduleId): void
    {
        foreach (self::EXISTING_STORE_TYPES as $storeName => $types) {
            $store = Store::withoutGlobalScope('translate')
                ->where('module_id', $moduleId)
                ->where('name', $storeName)
                ->first();

            if (!$store) {
                $this->command->warn("Store '{$storeName}' not found, not tagged.");

                continue;
            }

            $ids = Cuisine::withoutGlobalScope('translate')
                ->where('module_id', $moduleId)
                ->whereIn('name', $types)
                ->pluck('id')
                ->all();

            // syncWithoutDetaching: an admin may already have tagged these.
            $store->cuisines()->syncWithoutDetaching($ids);
        }
    }

    /** A running percent-off store discount, or none (clears a stale one). */
    private function setStoreDiscount(Store $store, ?int $percent): void
    {
        if (!$percent) {
            Discount::where('store_id', $store->id)->delete();

            return;
        }

        Discount::updateOrCreate(
            ['store_id' => $store->id],
            [
                'start_date' => now()->subDay()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'start_time' => '00:00:00',
                'end_time' => '23:59:00',
                'min_purchase' => 100,
                'max_discount' => 100,
                'discount' => $percent,
                'discount_type' => 'percent',
            ],
        );
    }

    // ==================== Data ====================

    /**
     * Shared vendor/contact fields, so each store below reads as its catalogue.
     * Phones are in an unused +2010990xxxxx block — the stores' natural key.
     */
    private function base(int $n, string $slug, float $lat, float $lng, string $address): array
    {
        return [
            'phone' => sprintf('+201099%06d', 100 + $n * 2),
            'vendor_phone' => sprintf('+201099%06d', 101 + $n * 2),
            'vendor_f_name' => 'Test',
            'vendor_l_name' => 'Vendor ' . $n,
            'vendor_email' => "{$slug}@test.waddyapp.com",
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
        ];
    }

    private function stores(): array
    {
        return [
            // ── Supermarkets ──
            $this->base(1, 'degla-mini-market', 29.9612, 31.2765, 'Road 233, Degla, Maadi') + [
                'name' => 'Degla Mini Market',
                'name_ar' => 'دجلة ميني ماركت',
                'cuisines' => ['Supermarkets'],
                'delivery_time' => '10-20 min',
                'shipping_charge' => 10,
                'minimum_order' => 50,
                'items' => [
                    ['name' => 'Juhayna Skimmed Milk 1L', 'name_ar' => 'جهينة لبن خالي الدسم ١ لتر', 'category' => 'Fresh Milk', 'price' => 40, 'unit' => 'ltr'],
                    ['name' => 'Farm Eggs 15pcs', 'name_ar' => 'بيض مزارع ١٥ حبة', 'category' => 'Eggs', 'price' => 78],
                    ['name' => 'Rice 1kg', 'name_ar' => 'أرز ١ كجم', 'category' => 'Rice', 'price' => 38, 'unit' => 'kg'],
                    ['name' => 'Spaghetti 400g', 'name_ar' => 'اسباجتي ٤٠٠ جم', 'category' => 'Pasta & Noodles', 'price' => 22],
                    ['name' => 'Sunflower Oil 750ml', 'name_ar' => 'زيت عباد الشمس ٧٥٠ مل', 'category' => 'Cooking Oil', 'price' => 72],
                    ['name' => 'White Sugar 1kg', 'name_ar' => 'سكر أبيض ١ كجم', 'category' => 'Sugar & Sweeteners', 'price' => 36, 'unit' => 'kg'],
                    ['name' => 'Tuna Chunks 185g', 'name_ar' => 'تونة قطع ١٨٥ جم', 'category' => 'Canned Foods', 'price' => 55, 'discount' => 10],
                    ['name' => 'Potato Chips Salt 90g', 'name_ar' => 'شيبسي ملح ٩٠ جم', 'category' => 'Chips & Crisps', 'price' => 15],
                    ['name' => 'Mineral Water 600ml', 'name_ar' => 'مياه معدنية ٦٠٠ مل', 'category' => 'Water', 'price' => 7],
                    ['name' => 'Dishwashing Liquid 1L', 'name_ar' => 'سائل غسيل أطباق ١ لتر', 'category' => 'Cleaning Supplies', 'price' => 45],
                    ['name' => 'Toilet Paper 6 Rolls', 'name_ar' => 'ورق تواليت ٦ رول', 'category' => 'Paper Products', 'price' => 60],
                ],
            ],
            $this->base(2, 'road-9-supermarket', 29.9608, 31.2582, 'Road 9, Maadi') + [
                'name' => 'Road 9 Supermarket',
                'name_ar' => 'سوبر ماركت شارع ٩',
                'cuisines' => ['Supermarkets', 'Fresh produce'],
                'delivery_time' => '20-35 min',
                'shipping_charge' => 0,
                'free_delivery' => 1,
                'minimum_order' => 150,
                'store_discount' => 15,
                'items' => [
                    ['name' => 'Cucumbers 1kg', 'name_ar' => 'خيار ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 20, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Oranges 1kg', 'name_ar' => 'برتقال ١ كجم', 'category' => 'Fresh Fruits', 'price' => 30, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Laban Rayeb 1L', 'name_ar' => 'لبن رايب ١ لتر', 'category' => 'Yogurt & Laban', 'price' => 35, 'unit' => 'ltr'],
                    ['name' => 'Roumy Cheese 250g', 'name_ar' => 'جبنة رومي ٢٥٠ جم', 'category' => 'Egyptian Cheese', 'price' => 95],
                    ['name' => 'Toast Bread', 'name_ar' => 'عيش توست', 'category' => 'Bread', 'price' => 30],
                    ['name' => 'Orange Juice 1L', 'name_ar' => 'عصير برتقال ١ لتر', 'category' => 'Juices', 'price' => 45, 'unit' => 'ltr'],
                    ['name' => 'Cola 1L', 'name_ar' => 'كولا ١ لتر', 'category' => 'Soft Drinks', 'price' => 25, 'unit' => 'ltr'],
                    ['name' => 'Frozen Peas 400g', 'name_ar' => 'بسلة مجمدة ٤٠٠ جم', 'category' => 'Frozen Vegetables', 'price' => 40],
                    ['name' => 'Vanilla Ice Cream 1L', 'name_ar' => 'آيس كريم فانيليا ١ لتر', 'category' => 'Ice Cream', 'price' => 110],
                    ['name' => 'Baby Diapers Size 3', 'name_ar' => 'حفاضات أطفال مقاس ٣', 'category' => 'Diapers', 'price' => 320],
                    ['name' => 'Shampoo 400ml', 'name_ar' => 'شامبو ٤٠٠ مل', 'category' => 'Shampoo & Hair Care', 'price' => 125],
                ],
            ],

            // ── Fresh produce ──
            $this->base(3, 'maadi-fresh-market', 29.9585, 31.2630, 'Road 82, Maadi') + [
                'name' => 'Maadi Fresh Market',
                'name_ar' => 'سوق المعادي الطازج',
                'cuisines' => ['Fresh produce'],
                'delivery_time' => '15-25 min',
                'shipping_charge' => 15,
                'minimum_order' => 60,
                'items' => [
                    ['name' => 'Tomatoes 1kg', 'name_ar' => 'طماطم ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 22, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Potatoes 1kg', 'name_ar' => 'بطاطس ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 18, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Red Apples 1kg', 'name_ar' => 'تفاح أحمر ١ كجم', 'category' => 'Fresh Fruits', 'price' => 80, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Bananas 1kg', 'name_ar' => 'موز ١ كجم', 'category' => 'Fresh Fruits', 'price' => 42, 'unit' => 'kg', 'veg' => 1, 'discount' => 15],
                    ['name' => 'Parsley Bunch', 'name_ar' => 'حزمة بقدونس', 'category' => 'Herbs & Greens', 'price' => 5, 'veg' => 1],
                    ['name' => 'Fresh Mint Bunch', 'name_ar' => 'حزمة نعناع', 'category' => 'Herbs & Greens', 'price' => 5, 'veg' => 1],
                    ['name' => 'Mangoes 1kg', 'name_ar' => 'مانجو ١ كجم', 'category' => 'Egyptian Fruits', 'price' => 70, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Guava 1kg', 'name_ar' => 'جوافة ١ كجم', 'category' => 'Egyptian Fruits', 'price' => 35, 'unit' => 'kg', 'veg' => 1],
                ],
            ],

            // ── Butchers & seafood ──
            $this->base(4, 'el-sayed-butcher', 29.9631, 31.2701, 'Road 250, Degla, Maadi') + [
                'name' => 'El Sayed Butcher',
                'name_ar' => 'جزارة السيد',
                'cuisines' => ['Butchers & seafood'],
                'delivery_time' => '30-45 min',
                'shipping_charge' => 20,
                'minimum_order' => 200,
                'store_discount' => 10,
                'items' => [
                    ['name' => 'Beef Steak 1kg', 'name_ar' => 'ستيك بقري ١ كجم', 'category' => 'Fresh Beef', 'price' => 520, 'unit' => 'kg'],
                    ['name' => 'Beef Cubes 1kg', 'name_ar' => 'لحم بقري مكعبات ١ كجم', 'category' => 'Fresh Beef', 'price' => 450, 'unit' => 'kg'],
                    ['name' => 'Whole Chicken', 'name_ar' => 'فرخة كاملة', 'category' => 'Fresh Chicken', 'price' => 180],
                    ['name' => 'Chicken Breast 1kg', 'name_ar' => 'صدور فراخ ١ كجم', 'category' => 'Fresh Chicken', 'price' => 260, 'unit' => 'kg'],
                    ['name' => 'Lamb Chops 1kg', 'name_ar' => 'ريش ضاني ١ كجم', 'category' => 'Lamb & Goat', 'price' => 600, 'unit' => 'kg'],
                    ['name' => 'Kofta Mix 1kg', 'name_ar' => 'كفتة ١ كجم', 'category' => 'Kofta & Minced', 'price' => 420, 'unit' => 'kg'],
                    ['name' => 'Minced Beef 500g', 'name_ar' => 'لحم مفروم ٥٠٠ جم', 'category' => 'Kofta & Minced', 'price' => 230],
                ],
            ],
            $this->base(5, 'nile-catch-seafood', 29.9567, 31.2655, 'Road 18, Maadi') + [
                'name' => 'Nile Catch Seafood',
                'name_ar' => 'نايل كاتش للمأكولات البحرية',
                'cuisines' => ['Butchers & seafood'],
                'delivery_time' => '40-60 min',
                'shipping_charge' => 25,
                'minimum_order' => 250,
                'items' => [
                    ['name' => 'Fresh Sea Bass 1kg', 'name_ar' => 'قاروص طازج ١ كجم', 'category' => 'Fresh Fish', 'price' => 380, 'unit' => 'kg'],
                    ['name' => 'Fresh Mullet 1kg', 'name_ar' => 'بوري طازج ١ كجم', 'category' => 'Fresh Fish', 'price' => 220, 'unit' => 'kg'],
                    ['name' => 'Tilapia 1kg', 'name_ar' => 'بلطي ١ كجم', 'category' => 'Nile Fish', 'price' => 110, 'unit' => 'kg'],
                    ['name' => 'Jumbo Shrimp 1kg', 'name_ar' => 'جمبري جامبو ١ كجم', 'category' => 'Shrimp & Seafood', 'price' => 750, 'unit' => 'kg', 'discount' => 20],
                    ['name' => 'Calamari 1kg', 'name_ar' => 'كاليماري ١ كجم', 'category' => 'Shrimp & Seafood', 'price' => 420, 'unit' => 'kg'],
                    ['name' => 'Frozen Fish Fillet 1kg', 'name_ar' => 'فيليه سمك مجمد ١ كجم', 'category' => 'Frozen Fish', 'price' => 240, 'unit' => 'kg'],
                ],
            ],

            // ── Dairy ──
            $this->base(6, 'farm-dairy-degla', 29.9640, 31.2742, 'Road 206, Degla, Maadi') + [
                'name' => 'Farm Dairy Degla',
                'name_ar' => 'ألبان المزرعة دجلة',
                'cuisines' => ['Dairy'],
                'delivery_time' => '10-25 min',
                'shipping_charge' => 0,
                'free_delivery' => 1,
                'minimum_order' => 80,
                'items' => [
                    ['name' => 'Fresh Farm Milk 1L', 'name_ar' => 'لبن مزرعة طازج ١ لتر', 'category' => 'Fresh Milk', 'price' => 45, 'unit' => 'ltr'],
                    ['name' => 'Buffalo Milk 1L', 'name_ar' => 'لبن جاموسي ١ لتر', 'category' => 'Fresh Milk', 'price' => 55, 'unit' => 'ltr'],
                    ['name' => 'Natural Yogurt 4pcs', 'name_ar' => 'زبادي طبيعي ٤ علب', 'category' => 'Yogurt & Laban', 'price' => 40],
                    ['name' => 'Feta Cheese 500g', 'name_ar' => 'جبنة فيتا ٥٠٠ جم', 'category' => 'Cheese', 'price' => 75],
                    ['name' => 'Cheddar Slices 200g', 'name_ar' => 'شرائح شيدر ٢٠٠ جم', 'category' => 'Cheese', 'price' => 85],
                    ['name' => 'Areesh Cheese 500g', 'name_ar' => 'جبنة قريش ٥٠٠ جم', 'category' => 'Egyptian Cheese', 'price' => 50],
                    ['name' => 'Farm Butter 250g', 'name_ar' => 'زبدة فلاحي ٢٥٠ جم', 'category' => 'Butter & Cream', 'price' => 110],
                    ['name' => 'Eshta Cream 200g', 'name_ar' => 'قشطة ٢٠٠ جم', 'category' => 'Butter & Cream', 'price' => 60, 'discount' => 10],
                ],
            ],

            // ── Bakeries & sweets ──
            $this->base(7, 'baladi-bakery-maadi', 29.9593, 31.2598, 'Road 13, Maadi') + [
                'name' => 'Baladi Bakery Maadi',
                'name_ar' => 'مخبز بلدي المعادي',
                'cuisines' => ['Bakeries & sweets'],
                'delivery_time' => '10-20 min',
                'shipping_charge' => 10,
                'minimum_order' => 30,
                'items' => [
                    ['name' => 'Baladi Bread 10pcs', 'name_ar' => 'عيش بلدي ١٠ أرغفة', 'category' => 'Egyptian Baladi Bread', 'price' => 15, 'veg' => 1],
                    ['name' => 'Shami Bread 5pcs', 'name_ar' => 'عيش شامي ٥ أرغفة', 'category' => 'Bread', 'price' => 20, 'veg' => 1],
                    ['name' => 'Croissant', 'name_ar' => 'كرواسون', 'category' => 'Pastries', 'price' => 25],
                    ['name' => 'Cheese Pastry', 'name_ar' => 'فطيرة جبنة', 'category' => 'Pastries', 'price' => 30],
                    ['name' => 'Feteer Meshaltet', 'name_ar' => 'فطير مشلتت', 'category' => 'Feteer & Pies', 'price' => 120],
                ],
            ],
            $this->base(8, 'sweet-corner-patisserie', 29.9622, 31.2689, 'Road 199, Degla, Maadi') + [
                'name' => 'Sweet Corner Patisserie',
                'name_ar' => 'سويت كورنر للحلويات',
                'cuisines' => ['Bakeries & sweets'],
                'delivery_time' => '25-40 min',
                'shipping_charge' => 20,
                'minimum_order' => 100,
                'store_discount' => 20,
                'items' => [
                    ['name' => 'Chocolate Cake', 'name_ar' => 'تورتة شوكولاتة', 'category' => 'Cakes & Desserts', 'price' => 450],
                    ['name' => 'Basbousa Tray', 'name_ar' => 'صينية بسبوسة', 'category' => 'Cakes & Desserts', 'price' => 180],
                    ['name' => 'Mini Croissants 12pcs', 'name_ar' => 'ميني كرواسون ١٢ قطعة', 'category' => 'Pastries', 'price' => 150],
                    ['name' => 'Assorted Chocolates 250g', 'name_ar' => 'شوكولاتة مشكلة ٢٥٠ جم', 'category' => 'Chocolate & Candy', 'price' => 220],
                ],
            ],

            // ── Roasteries ──
            $this->base(9, 'bean-house-roastery', 29.9601, 31.2720, 'Road 212, Degla, Maadi') + [
                'name' => 'Bean House Roastery',
                'name_ar' => 'بين هاوس محمصة',
                'cuisines' => ['Roasteries'],
                'delivery_time' => '20-30 min',
                'shipping_charge' => 15,
                'minimum_order' => 100,
                'items' => [
                    ['name' => 'Turkish Coffee Medium Roast 250g', 'name_ar' => 'بن تركي وسط ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 160],
                    ['name' => 'Turkish Coffee with Cardamom 250g', 'name_ar' => 'بن تركي محوج ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 175, 'discount' => 10],
                    ['name' => 'Roasted Cashews 250g', 'name_ar' => 'كاجو محمص ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 240],
                    ['name' => 'Salted Pistachios 250g', 'name_ar' => 'فستق مملح ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 290],
                ],
            ],
            $this->base(10, 'maadi-roasters-nuts', 29.9575, 31.2611, 'Road 151, Maadi') + [
                'name' => 'Maadi Roasters & Nuts',
                'name_ar' => 'محمصة المعادي للمكسرات',
                'cuisines' => ['Roasteries'],
                'delivery_time' => '15-30 min',
                'shipping_charge' => 0,
                'free_delivery' => 1,
                'minimum_order' => 80,
                'items' => [
                    ['name' => 'Espresso Beans 500g', 'name_ar' => 'حبوب إسبريسو ٥٠٠ جم', 'category' => 'Tea & Coffee', 'price' => 380],
                    ['name' => 'Lebsy Black Tea 250g', 'name_ar' => 'شاي أسود ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 70],
                    ['name' => 'Lb Sunflower Seeds 250g', 'name_ar' => 'لب سوري ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 65],
                    ['name' => 'Mixed Nuts 500g', 'name_ar' => 'مكسرات مشكلة ٥٠٠ جم', 'category' => 'Nuts & Seeds', 'price' => 350],
                    ['name' => 'Dates Stuffed with Almonds 250g', 'name_ar' => 'بلح محشي لوز ٢٥٠ جم', 'category' => 'Egyptian Snacks', 'price' => 140],
                ],
            ],

            // ── Global & organic ──
            $this->base(11, 'green-basket-organic', 29.9654, 31.2773, 'Road 275, Degla, Maadi') + [
                'name' => 'Green Basket Organic',
                'name_ar' => 'جرين باسكت أورجانيك',
                'cuisines' => ['Global & organic', 'Fresh produce'],
                'delivery_time' => '35-50 min',
                'shipping_charge' => 25,
                'minimum_order' => 200,
                'store_discount' => 25,
                'items' => [
                    ['name' => 'Organic Baby Spinach 200g', 'name_ar' => 'سبانخ أورجانيك ٢٠٠ جم', 'category' => 'Organic Produce', 'price' => 65, 'veg' => 1],
                    ['name' => 'Organic Carrots 1kg', 'name_ar' => 'جزر أورجانيك ١ كجم', 'category' => 'Organic Produce', 'price' => 45, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Quinoa 500g', 'name_ar' => 'كينوا ٥٠٠ جم', 'category' => 'Legumes & Beans', 'price' => 190],
                    ['name' => 'Italian Penne 500g', 'name_ar' => 'بيني إيطالي ٥٠٠ جم', 'category' => 'Pasta & Noodles', 'price' => 85],
                    ['name' => 'Extra Virgin Olive Oil 500ml', 'name_ar' => 'زيت زيتون بكر ممتاز ٥٠٠ مل', 'category' => 'Cooking Oil', 'price' => 320],
                    ['name' => 'Almond Milk 1L', 'name_ar' => 'حليب لوز ١ لتر', 'category' => 'Fresh Milk', 'price' => 140, 'unit' => 'ltr'],
                    ['name' => 'Dark Chocolate 70% 100g', 'name_ar' => 'شوكولاتة داكنة ٧٠٪ ١٠٠ جم', 'category' => 'Chocolate & Candy', 'price' => 110],
                ],
            ],
        ];
    }
}
