<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use App\Models\Module;
use App\Models\Store;
use App\Models\Translation;
use App\Models\Zone;
use Illuminate\Support\Facades\DB;

/**
 * Grocery store types, plus real Maadi/Degla shops to fill each one.
 *
 * Store types (Supermarkets, Roasteries, …) are what a store IS — they live in
 * the cuisines table scoped to the grocery module (migration
 * 2026_09_28_000001) and drive the grocery home's strip. Item categories
 * (Fresh Milk, Frozen) are the aisles inside a store and are untouched here.
 *
 * The stores are REAL businesses. Name, street address and phone are taken
 * from their FindInEgypt listings (checked 2026-09-28). Two things are NOT
 * from a source and should be corrected in admin:
 *   - coordinates are street-level estimates, not pinned locations;
 *   - the product lists are plausible for each kind of shop, not their actual
 *     stock or prices.
 * No discounts or free delivery are seeded: on a live catalogue those read as
 * a promotion the shop is offering, and none of these shops has agreed to one.
 * Delivery time and fee are Waddy's own settings and vary between stores.
 *
 * Idempotent: types key on name + module, stores on phone, items on
 * store + name. Images are left null — upload type/store art in admin.
 *
 * Run after `php artisan migrate`:
 *   php artisan db:seed --class=GroceryStoreTypesSeeder --force
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

    /**
     * Real stores already in the catalogue, matched by name PREFIX: the
     * seeder names them "Metro Market Degla", but admins rename them ("Metro
     * Market"), and every branch of a chain should carry the chain's types.
     */
    private const EXISTING_STORE_TYPES = [
        'Seoudi Market' => ['Supermarkets', 'Fresh produce'],
        'Metro Market' => ['Supermarkets'],
        'Gourmet Egypt' => ['Supermarkets', 'Global & organic'],
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
                $this->createStore($data, $grocery->id, $zone->id);
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
        foreach (self::EXISTING_STORE_TYPES as $prefix => $types) {
            $stores = Store::withoutGlobalScope('translate')
                ->where('module_id', $moduleId)
                ->where('name', 'like', $prefix . '%')
                ->get();

            if ($stores->isEmpty()) {
                $this->command->warn("No store named '{$prefix}…' found, not tagged.");

                continue;
            }

            $ids = Cuisine::withoutGlobalScope('translate')
                ->where('module_id', $moduleId)
                ->whereIn('name', $types)
                ->pluck('id')
                ->all();

            foreach ($stores as $store) {
                // syncWithoutDetaching: an admin may already have tagged these.
                $store->cuisines()->syncWithoutDetaching($ids);
                $this->command->line("Tagged {$store->name}: " . implode(', ', $types));
            }
        }
    }

    // ==================== Data ====================

    /**
     * Contact fields. The vendor account shares the store's listed phone —
     * these accounts exist so each store has an owner, not so anyone signs in
     * as them yet (MaadiContentSeeder gives them a random password).
     */
    private function contact(string $phone, string $slug, float $lat, float $lng, string $address): array
    {
        return [
            'phone' => $phone,
            'vendor_phone' => $phone,
            'vendor_f_name' => 'Store',
            'vendor_l_name' => 'Owner',
            'vendor_email' => "{$slug}@waddyapp.com",
            'lat' => $lat,
            'lng' => $lng,
            'address' => $address,
        ];
    }

    private function stores(): array
    {
        return [
            // ── Supermarkets ──
            $this->contact('+20225161788', 'kimo-market-degla', 29.9627, 31.2803, '1 Road 210, Victoria Square, Degla, Maadi') + [
                'name' => 'Kimo Market',
                'name_ar' => 'كيمو ماركت',
                'cuisines' => ['Supermarkets'],
                'delivery_time' => '15-25 min',
                'shipping_charge' => 15,
                'minimum_order' => 75,
                'items' => [
                    ['name' => 'Juhayna Skimmed Milk 1L', 'name_ar' => 'جهينة لبن خالي الدسم ١ لتر', 'category' => 'Fresh Milk', 'price' => 40, 'unit' => 'ltr'],
                    ['name' => 'Farm Eggs 15pcs', 'name_ar' => 'بيض مزارع ١٥ حبة', 'category' => 'Eggs', 'price' => 78],
                    ['name' => 'Egyptian Rice 1kg', 'name_ar' => 'أرز مصري ١ كجم', 'category' => 'Rice', 'price' => 38, 'unit' => 'kg'],
                    ['name' => 'Spaghetti 400g', 'name_ar' => 'اسباجتي ٤٠٠ جم', 'category' => 'Pasta & Noodles', 'price' => 22],
                    ['name' => 'Sunflower Oil 750ml', 'name_ar' => 'زيت عباد الشمس ٧٥٠ مل', 'category' => 'Cooking Oil', 'price' => 72],
                    ['name' => 'White Sugar 1kg', 'name_ar' => 'سكر أبيض ١ كجم', 'category' => 'Sugar & Sweeteners', 'price' => 36, 'unit' => 'kg'],
                    ['name' => 'Tuna Chunks 185g', 'name_ar' => 'تونة قطع ١٨٥ جم', 'category' => 'Canned Foods', 'price' => 55],
                    ['name' => 'Potato Chips Salt 90g', 'name_ar' => 'شيبسي ملح ٩٠ جم', 'category' => 'Chips & Crisps', 'price' => 15],
                    ['name' => 'Mineral Water 600ml', 'name_ar' => 'مياه معدنية ٦٠٠ مل', 'category' => 'Water', 'price' => 7],
                    ['name' => 'Dishwashing Liquid 1L', 'name_ar' => 'سائل غسيل أطباق ١ لتر', 'category' => 'Cleaning Supplies', 'price' => 45],
                    ['name' => 'Toilet Paper 6 Rolls', 'name_ar' => 'ورق تواليت ٦ رول', 'category' => 'Paper Products', 'price' => 60],
                ],
            ],
            $this->contact('+20225210180', 'adam-supermarket-degla', 29.9556, 31.2762, '8 Road 231, off Road 213, Degla, Maadi') + [
                'name' => 'Adam Supermarket',
                'name_ar' => 'آدم سوبر ماركت',
                'cuisines' => ['Supermarkets'],
                'delivery_time' => '20-35 min',
                'shipping_charge' => 10,
                'minimum_order' => 100,
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
            $this->contact('+201004806961', 'natural-garden-degla', 29.9577, 31.2788, '15 El Shorta Buildings, Road 233, Degla, Maadi') + [
                'name' => 'Natural Garden',
                'name_ar' => 'ناتشورال جاردن',
                'cuisines' => ['Fresh produce'],
                'delivery_time' => '15-25 min',
                'shipping_charge' => 10,
                'minimum_order' => 60,
                'items' => [
                    ['name' => 'Tomatoes 1kg', 'name_ar' => 'طماطم ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 22, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Potatoes 1kg', 'name_ar' => 'بطاطس ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 18, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Red Apples 1kg', 'name_ar' => 'تفاح أحمر ١ كجم', 'category' => 'Fresh Fruits', 'price' => 80, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Bananas 1kg', 'name_ar' => 'موز ١ كجم', 'category' => 'Fresh Fruits', 'price' => 42, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Parsley Bunch', 'name_ar' => 'حزمة بقدونس', 'category' => 'Herbs & Greens', 'price' => 5, 'veg' => 1],
                    ['name' => 'Fresh Mint Bunch', 'name_ar' => 'حزمة نعناع', 'category' => 'Herbs & Greens', 'price' => 5, 'veg' => 1],
                ],
            ],
            $this->contact('+201221693274', 'el-baraka-vegetables-degla', 29.9574, 31.2792, '3 El Shorta Buildings, Road 233, Degla, Maadi') + [
                'name' => 'El Baraka For Vegetables',
                'name_ar' => 'البركة للخضروات',
                'cuisines' => ['Fresh produce'],
                'delivery_time' => '10-20 min',
                'shipping_charge' => 10,
                'minimum_order' => 50,
                'items' => [
                    ['name' => 'Onions 1kg', 'name_ar' => 'بصل ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 15, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Bell Peppers 1kg', 'name_ar' => 'فلفل رومي ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 35, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Mangoes 1kg', 'name_ar' => 'مانجو ١ كجم', 'category' => 'Egyptian Fruits', 'price' => 70, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Guava 1kg', 'name_ar' => 'جوافة ١ كجم', 'category' => 'Egyptian Fruits', 'price' => 35, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Arugula Bunch', 'name_ar' => 'حزمة جرجير', 'category' => 'Herbs & Greens', 'price' => 5, 'veg' => 1],
                ],
            ],

            // ── Butchers & seafood ──
            $this->contact('+201142278888', 'degla-meat', 29.9614, 31.2748, '24 Road 200, Degla, Maadi (beside the Philippines Embassy)') + [
                'name' => 'Degla Meat',
                'name_ar' => 'دجلة للحوم',
                'cuisines' => ['Butchers & seafood'],
                'delivery_time' => '30-45 min',
                'shipping_charge' => 20,
                'minimum_order' => 200,
                'items' => [
                    ['name' => 'Beef Steak 1kg', 'name_ar' => 'ستيك بقري ١ كجم', 'category' => 'Fresh Beef', 'price' => 520, 'unit' => 'kg'],
                    ['name' => 'Beef Cubes 1kg', 'name_ar' => 'لحم بقري مكعبات ١ كجم', 'category' => 'Fresh Beef', 'price' => 450, 'unit' => 'kg'],
                    ['name' => 'Lamb Chops 1kg', 'name_ar' => 'ريش ضاني ١ كجم', 'category' => 'Lamb & Goat', 'price' => 600, 'unit' => 'kg'],
                    ['name' => 'Kofta Mix 1kg', 'name_ar' => 'كفتة ١ كجم', 'category' => 'Kofta & Minced', 'price' => 420, 'unit' => 'kg'],
                    ['name' => 'Minced Beef 500g', 'name_ar' => 'لحم مفروم ٥٠٠ جم', 'category' => 'Kofta & Minced', 'price' => 230],
                ],
            ],
            $this->contact('+201011246127', 'andria-butchery-degla', 29.9579, 31.2785, '18 El Shorta Buildings, Road 233, Degla, Maadi') + [
                'name' => 'Andria Butchery',
                'name_ar' => 'جزارة أندريا',
                'cuisines' => ['Butchers & seafood'],
                'delivery_time' => '25-40 min',
                'shipping_charge' => 15,
                'minimum_order' => 150,
                'items' => [
                    ['name' => 'Whole Chicken', 'name_ar' => 'فرخة كاملة', 'category' => 'Fresh Chicken', 'price' => 180],
                    ['name' => 'Chicken Breast 1kg', 'name_ar' => 'صدور فراخ ١ كجم', 'category' => 'Fresh Chicken', 'price' => 260, 'unit' => 'kg'],
                    ['name' => 'Beef Tenderloin 1kg', 'name_ar' => 'فيليه بقري ١ كجم', 'category' => 'Fresh Beef', 'price' => 650, 'unit' => 'kg'],
                    ['name' => 'Beef Sausage 500g', 'name_ar' => 'سجق بقري ٥٠٠ جم', 'category' => 'Processed Meat', 'price' => 190],
                ],
            ],
            $this->contact('+20225237446', 'el-bahrain-fish-maadi', 29.9703, 31.2507, '8 El Dandarawy St., off Road 9, Hadayek El Maadi') + [
                'name' => 'El Bahrain Fish',
                'name_ar' => 'أسماك البحرين',
                'cuisines' => ['Butchers & seafood'],
                'delivery_time' => '40-60 min',
                'shipping_charge' => 25,
                'minimum_order' => 200,
                'items' => [
                    ['name' => 'Fresh Sea Bass 1kg', 'name_ar' => 'قاروص طازج ١ كجم', 'category' => 'Fresh Fish', 'price' => 380, 'unit' => 'kg'],
                    ['name' => 'Fresh Mullet 1kg', 'name_ar' => 'بوري طازج ١ كجم', 'category' => 'Fresh Fish', 'price' => 220, 'unit' => 'kg'],
                    ['name' => 'Tilapia 1kg', 'name_ar' => 'بلطي ١ كجم', 'category' => 'Nile Fish', 'price' => 110, 'unit' => 'kg'],
                    ['name' => 'Jumbo Shrimp 1kg', 'name_ar' => 'جمبري جامبو ١ كجم', 'category' => 'Shrimp & Seafood', 'price' => 750, 'unit' => 'kg'],
                    ['name' => 'Calamari 1kg', 'name_ar' => 'كاليماري ١ كجم', 'category' => 'Shrimp & Seafood', 'price' => 420, 'unit' => 'kg'],
                ],
            ],

            // ── Dairy ──
            $this->contact('+201272805011', 'dina-farms-degla', 29.9602, 31.2829, 'Road 206, Degla, Maadi (beside Shell)') + [
                'name' => 'Dina Farms',
                'name_ar' => 'مزارع دينا',
                'cuisines' => ['Dairy'],
                'delivery_time' => '10-25 min',
                'shipping_charge' => 15,
                'minimum_order' => 80,
                'items' => [
                    ['name' => 'Full Cream Milk 1L', 'name_ar' => 'لبن كامل الدسم ١ لتر', 'category' => 'Fresh Milk', 'price' => 45, 'unit' => 'ltr'],
                    ['name' => 'Low Fat Milk 1L', 'name_ar' => 'لبن قليل الدسم ١ لتر', 'category' => 'Fresh Milk', 'price' => 43, 'unit' => 'ltr'],
                    ['name' => 'Plain Yogurt 4pcs', 'name_ar' => 'زبادي سادة ٤ علب', 'category' => 'Yogurt & Laban', 'price' => 40],
                    ['name' => 'Feta Cheese 500g', 'name_ar' => 'جبنة فيتا ٥٠٠ جم', 'category' => 'Cheese', 'price' => 75],
                    ['name' => 'Areesh Cheese 500g', 'name_ar' => 'جبنة قريش ٥٠٠ جم', 'category' => 'Egyptian Cheese', 'price' => 50],
                    ['name' => 'Butter 250g', 'name_ar' => 'زبدة ٢٥٠ جم', 'category' => 'Butter & Cream', 'price' => 110],
                    ['name' => 'Eshta Cream 200g', 'name_ar' => 'قشطة ٢٠٠ جم', 'category' => 'Butter & Cream', 'price' => 60],
                ],
            ],

            // ── Bakeries & sweets ──
            $this->contact('+20225200909', 'la-poire-degla', 29.9582, 31.2796, '24 Road 233, Degla, Maadi') + [
                'name' => 'La Poire',
                'name_ar' => 'لابوار',
                'cuisines' => ['Bakeries & sweets'],
                'delivery_time' => '25-40 min',
                'shipping_charge' => 20,
                'minimum_order' => 100,
                'items' => [
                    ['name' => 'Chocolate Cake', 'name_ar' => 'تورتة شوكولاتة', 'category' => 'Cakes & Desserts', 'price' => 450],
                    ['name' => 'Mixed Gateaux Box', 'name_ar' => 'علبة جاتوه مشكل', 'category' => 'Cakes & Desserts', 'price' => 280],
                    ['name' => 'Croissant', 'name_ar' => 'كرواسون', 'category' => 'Pastries', 'price' => 30],
                    ['name' => 'Petit Four 500g', 'name_ar' => 'بيتي فور ٥٠٠ جم', 'category' => 'Biscuits & Cookies', 'price' => 220],
                ],
            ],
            $this->contact('+20225169009', 'capricci-degla', 29.9591, 31.2771, '9B Road 216, Degla, Maadi (near Victory College)') + [
                'name' => 'Capricci',
                'name_ar' => 'كابريتشي',
                'cuisines' => ['Bakeries & sweets'],
                'delivery_time' => '20-30 min',
                'shipping_charge' => 15,
                'minimum_order' => 80,
                'items' => [
                    ['name' => 'Ciabatta Bread', 'name_ar' => 'عيش شاباتا', 'category' => 'Bread', 'price' => 35],
                    ['name' => 'Focaccia', 'name_ar' => 'فوكاتشا', 'category' => 'Bread', 'price' => 45],
                    ['name' => 'Tiramisu', 'name_ar' => 'تيراميسو', 'category' => 'Cakes & Desserts', 'price' => 95],
                    ['name' => 'Cheese Pastry', 'name_ar' => 'فطيرة جبنة', 'category' => 'Pastries', 'price' => 35],
                ],
            ],

            // ── Roasteries ──
            $this->contact('+201156597020', 'sphinx-roastery-degla', 29.9580, 31.2790, '14 Road 233, Degla, Maadi') + [
                'name' => 'Sphinx Roastery',
                'name_ar' => 'محمصة سفنكس',
                'cuisines' => ['Roasteries'],
                'delivery_time' => '15-30 min',
                'shipping_charge' => 10,
                'minimum_order' => 80,
                'items' => [
                    ['name' => 'Turkish Coffee Medium Roast 250g', 'name_ar' => 'بن تركي وسط ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 160],
                    ['name' => 'Turkish Coffee with Cardamom 250g', 'name_ar' => 'بن تركي محوج ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 175],
                    ['name' => 'Roasted Cashews 250g', 'name_ar' => 'كاجو محمص ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 240],
                    ['name' => 'Salted Pistachios 250g', 'name_ar' => 'فستق مملح ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 290],
                ],
            ],
            $this->contact('+201224889359', 'abou-rayan-roastery-maadi', 29.9658, 31.2952, '12 El Gazaer St., 10th Sector, New Maadi') + [
                'name' => 'Abou Rayan Roastery',
                'name_ar' => 'محمصة أبو ريان',
                'cuisines' => ['Roasteries'],
                'delivery_time' => '30-45 min',
                'shipping_charge' => 20,
                'minimum_order' => 100,
                'items' => [
                    ['name' => 'Espresso Beans 500g', 'name_ar' => 'حبوب إسبريسو ٥٠٠ جم', 'category' => 'Tea & Coffee', 'price' => 380],
                    ['name' => 'Black Tea 250g', 'name_ar' => 'شاي أسود ٢٥٠ جم', 'category' => 'Tea & Coffee', 'price' => 70],
                    ['name' => 'Sunflower Seeds 250g', 'name_ar' => 'لب سوري ٢٥٠ جم', 'category' => 'Nuts & Seeds', 'price' => 65],
                    ['name' => 'Mixed Nuts 500g', 'name_ar' => 'مكسرات مشكلة ٥٠٠ جم', 'category' => 'Nuts & Seeds', 'price' => 350],
                    ['name' => 'Dates Stuffed with Almonds 250g', 'name_ar' => 'بلح محشي لوز ٢٥٠ جم', 'category' => 'Egyptian Snacks', 'price' => 140],
                ],
            ],

            // ── Global & organic ──
            $this->contact('+201020155599', 'el-market-degla', 29.9610, 31.2742, '36 Road 200, Degla, Maadi') + [
                'name' => 'El Market',
                'name_ar' => 'الماركت',
                'cuisines' => ['Global & organic'],
                'delivery_time' => '35-50 min',
                'shipping_charge' => 25,
                'minimum_order' => 200,
                'items' => [
                    ['name' => 'Organic Baby Spinach 200g', 'name_ar' => 'سبانخ أورجانيك ٢٠٠ جم', 'category' => 'Organic Produce', 'price' => 65, 'veg' => 1],
                    ['name' => 'Organic Carrots 1kg', 'name_ar' => 'جزر أورجانيك ١ كجم', 'category' => 'Organic Produce', 'price' => 45, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Quinoa 500g', 'name_ar' => 'كينوا ٥٠٠ جم', 'category' => 'Legumes & Beans', 'price' => 190],
                    ['name' => 'Italian Penne 500g', 'name_ar' => 'بيني إيطالي ٥٠٠ جم', 'category' => 'Pasta & Noodles', 'price' => 85],
                    ['name' => 'Extra Virgin Olive Oil 500ml', 'name_ar' => 'زيت زيتون بكر ممتاز ٥٠٠ مل', 'category' => 'Cooking Oil', 'price' => 320],
                    ['name' => 'Dark Chocolate 70% 100g', 'name_ar' => 'شوكولاتة داكنة ٧٠٪ ١٠٠ جم', 'category' => 'Chocolate & Candy', 'price' => 110],
                ],
            ],
        ];
    }
}
