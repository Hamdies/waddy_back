<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Cuisine;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Models\StoreSchedule;
use App\Models\Translation;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\Zone;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\Place;
use Modules\PlacesToVisit\Entities\PlaceTranslation;
use Modules\PlacesToVisit\Entities\PlaceZone;

/**
 * Real Maadi and Degla venues: grocery stores, restaurants and cafes.
 *
 * Every store is keyed on its phone number and every place on its English
 * title, so a re-run corrects the row rather than adding a second one. That
 * matters more than usual here: the data is hand-checked against real
 * businesses and will be corrected in passes.
 *
 * Coordinates all sit inside the Degla delivery zone polygon (roughly
 * 31.21-31.36 E, 29.92-30.00 N). A store outside it is invisible to the app
 * no matter how correct the rest of the row is.
 *
 * Images are deliberately left null. The filenames are predictable from each
 * store's slug, so artwork can be dropped into storage and attached later
 * without touching this file; a missing image renders as empty rather than
 * as a broken path.
 */
class MaadiContentSeeder extends Seeder
{
    public function run(): void
    {
        $grocery = Module::where('module_type', 'grocery')->first();
        $food = Module::where('module_type', 'food')->first();
        $zone = Zone::first();

        if (!$grocery || !$food || !$zone) {
            $this->command->error('Expected a grocery module, a food module and at least one zone.');

            return;
        }

        DB::transaction(function () use ($grocery, $food, $zone) {
            foreach ($this->groceryStores() as $data) {
                $this->createStore($data, $grocery->id, $zone->id);
            }

            foreach ($this->restaurants() as $data) {
                $this->createStore($data, $food->id, $zone->id);
            }

            $this->createPlaces();
        });

        $this->command->info('Maadi content seeded.');
    }

    // ==================== Stores ====================

    private function createStore(array $data, int $moduleId, int $zoneId): void
    {
        $vendor = Vendor::updateOrCreate(
            ['phone' => $data['vendor_phone']],
            [
                'f_name' => $data['vendor_f_name'],
                'l_name' => $data['vendor_l_name'],
                'email' => $data['vendor_email'],
                // Placeholder credentials: these accounts exist so the stores
                // have an owner, not so anyone signs in as them yet.
                'password' => Hash::make(Str::random(32)),
                'status' => 1,
            ],
        );

        $store = Store::updateOrCreate(
            ['phone' => $data['phone']],
            [
                'name' => $data['name'],
                'email' => $data['vendor_email'],
                'latitude' => (string) $data['lat'],
                'longitude' => (string) $data['lng'],
                'address' => $data['address'],
                'vendor_id' => $vendor->id,
                'module_id' => $moduleId,
                'zone_id' => $zoneId,
                'minimum_order' => $data['minimum_order'] ?? 50,
                'delivery_time' => $data['delivery_time'] ?? '30-45',
                'minimum_shipping_charge' => 15,
                'per_km_shipping_charge' => 5,
                'maximum_shipping_charge' => 60,
                'status' => 1,
                'active' => 1,
                'delivery' => 1,
                'take_away' => 1,
                'free_delivery' => 0,
                'schedule_order' => 1,
                'item_section' => 1,
                'reviews_section' => 1,
                'self_delivery_system' => 0,
                'store_business_model' => 'commission',
                'comission' => 15,
                'slug' => Str::slug($data['name']),
                'off_day' => '',
            ],
        );

        $this->setStoreTranslation($store, $data['name_ar']);
        $this->setSchedule($store, $data['opens'] ?? '09:00:00', $data['closes'] ?? '23:59:00');

        if (!empty($data['cuisines'])) {
            $cuisineIds = Cuisine::withoutGlobalScope('translate')
                ->whereIn('name', $data['cuisines'])
                ->pluck('id')
                ->all();

            $store->cuisines()->sync($cuisineIds);
        }

        foreach ($data['items'] as $item) {
            $this->createItem($store, $item, $moduleId);
        }
    }

    private function setStoreTranslation(Store $store, string $arabic): void
    {
        Translation::updateOrCreate(
            [
                'translationable_type' => Store::class,
                'translationable_id' => $store->id,
                'locale' => 'ar',
                'key' => 'name',
            ],
            ['value' => $arabic],
        );
    }

    /**
     * A store with no schedule rows reads as permanently closed, because the
     * open flag is computed from them.
     */
    private function setSchedule(Store $store, string $opens, string $closes): void
    {
        foreach (range(0, 6) as $day) {
            StoreSchedule::updateOrCreate(
                ['store_id' => $store->id, 'day' => $day],
                ['opening_time' => $opens, 'closing_time' => $closes],
            );
        }
    }

    private function createItem(Store $store, array $data, int $moduleId): void
    {
        $category = Category::withoutGlobalScope('translate')
            ->where('module_id', $moduleId)
            ->where('name', $data['category'])
            ->where('status', 1)
            ->first();

        if (!$category) {
            $this->command->warn("Category '{$data['category']}' not found, skipping {$data['name']}.");

            return;
        }

        $unitId = null;
        if (!empty($data['unit'])) {
            $unitId = Unit::withoutGlobalScope('translate')
                ->where('unit', $data['unit'])
                ->value('id');
        }

        $item = Item::updateOrCreate(
            ['store_id' => $store->id, 'name' => $data['name']],
            [
                'description' => $data['description'] ?? '',
                'category_id' => $category->id,
                'category_ids' => json_encode([
                    ['id' => (string) ($category->parent_id ?: $category->id), 'position' => 0],
                    ['id' => (string) $category->id, 'position' => 1],
                ]),
                'price' => $data['price'],
                'module_id' => $moduleId,
                'store_id' => $store->id,
                'unit_id' => $unitId,
                'stock' => $data['stock'] ?? 100,
                'veg' => $data['veg'] ?? 0,
                'status' => 1,
                'is_approved' => 1,
                'slug' => Str::slug($data['name']) . '-' . $store->id,
                'variations' => json_encode([]),
                'food_variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'images' => json_encode([]),
            ],
        );

        Translation::updateOrCreate(
            [
                'translationable_type' => Item::class,
                'translationable_id' => $item->id,
                'locale' => 'ar',
                'key' => 'name',
            ],
            ['value' => $data['name_ar']],
        );
    }

    // ==================== Places ====================

    private function createPlaces(): void
    {
        $categoryId = DB::table('place_categories')->where('name', 'Cafes')->value('id')
            ?? DB::table('place_categories')->value('id');

        if (!$categoryId) {
            $this->command->warn('No place category found, skipping cafes.');

            return;
        }

        foreach ($this->cafes() as $data) {
            $zoneId = PlaceZone::where('name', $data['zone'])->value('id');

            $existingId = DB::table('places')
                ->join('place_translations', 'places.id', '=', 'place_translations.place_id')
                ->where('place_translations.locale', 'en')
                ->where('place_translations.title', $data['title'])
                ->value('places.id');

            $attributes = [
                'category_id' => $categoryId,
                'zone_id' => $zoneId,
                'latitude' => $data['lat'],
                'longitude' => $data['lng'],
                'address' => $data['address'],
                'phone' => $data['phone'] ?? null,
                'instagram' => $data['instagram'] ?? null,
                'opening_hours' => json_encode($this->openingHours($data['opens'], $data['closes'])),
                'is_active' => 1,
                'is_featured' => $data['featured'] ?? 0,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('places')->where('id', $existingId)->update($attributes);
                $placeId = $existingId;
            } else {
                // The token is the cashier's only credential on the redemption
                // page, so it is generated the same way the admin panel does.
                $attributes['redeem_token'] = Str::random(32);
                $attributes['created_at'] = now();
                $placeId = DB::table('places')->insertGetId($attributes);
            }

            foreach ([['en', $data['title'], $data['description']], ['ar', $data['title_ar'], $data['description_ar']]] as [$locale, $title, $description]) {
                PlaceTranslation::updateOrCreate(
                    ['place_id' => $placeId, 'locale' => $locale],
                    ['title' => $title, 'description' => $description],
                );
            }
        }
    }

    /** @return array<string,array{open:string,close:string,closed:bool}> */
    private function openingHours(string $opens, string $closes): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];

        foreach ($days as $day) {
            $hours[$day] = ['open' => $opens, 'close' => $closes, 'closed' => false];
        }

        return $hours;
    }

    // ==================== Data ====================

    private function groceryStores(): array
    {
        return [
            [
                'name' => 'Seoudi Market Maadi',
                'name_ar' => 'سعودي ماركت المعادي',
                'phone' => '+20225169100',
                'vendor_phone' => '+20225169101',
                'vendor_f_name' => 'Seoudi',
                'vendor_l_name' => 'Market',
                'vendor_email' => 'seoudi.maadi@waddyapp.com',
                'lat' => 29.9599,
                'lng' => 31.2571,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'minimum_order' => 100,
                'delivery_time' => '30-45',
                'opens' => '08:00:00',
                'closes' => '23:59:00',
                'items' => [
                    ['name' => 'Fresh Tomatoes 1kg', 'name_ar' => 'طماطم طازجة ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 25, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Bananas 1kg', 'name_ar' => 'موز ١ كجم', 'category' => 'Fresh Fruits', 'price' => 45, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Juhayna Full Cream Milk 1L', 'name_ar' => 'جهينة لبن كامل الدسم ١ لتر', 'category' => 'Fresh Milk', 'price' => 42, 'unit' => 'ltr'],
                    ['name' => 'Domty White Cheese 500g', 'name_ar' => 'دومتي جبنة بيضاء ٥٠٠ جم', 'category' => 'Cheese', 'price' => 68],
                    ['name' => 'Baladi Eggs 30pcs', 'name_ar' => 'بيض بلدي ٣٠ حبة', 'category' => 'Eggs', 'price' => 145],
                    ['name' => 'Egyptian Baladi Bread 10pcs', 'name_ar' => 'عيش بلدي ١٠ أرغفة', 'category' => 'Egyptian Baladi Bread', 'price' => 15, 'veg' => 1],
                    ['name' => 'Crystal Sunflower Oil 1L', 'name_ar' => 'كريستال زيت عباد الشمس ١ لتر', 'category' => 'Cooking Oil', 'price' => 95],
                    ['name' => 'Egyptian Rice 5kg', 'name_ar' => 'أرز مصري ٥ كجم', 'category' => 'Rice', 'price' => 175, 'unit' => 'kg'],
                    ['name' => 'Nescafe Classic 200g', 'name_ar' => 'نسكافيه كلاسيك ٢٠٠ جم', 'category' => 'Tea & Coffee', 'price' => 210],
                    ['name' => 'Baraka Natural Water 1.5L', 'name_ar' => 'بركة مياه طبيعية ١.٥ لتر', 'category' => 'Water', 'price' => 12, 'unit' => 'ltr'],
                ],
            ],
            [
                'name' => 'Metro Market Degla',
                'name_ar' => 'مترو ماركت دجلة',
                'phone' => '+20225218400',
                'vendor_phone' => '+20225218401',
                'vendor_f_name' => 'Metro',
                'vendor_l_name' => 'Market',
                'vendor_email' => 'metro.degla@waddyapp.com',
                'lat' => 29.9603,
                'lng' => 31.2489,
                'address' => 'Road 231, Degla, Maadi, Cairo Governorate',
                'minimum_order' => 75,
                'delivery_time' => '25-40',
                'opens' => '08:00:00',
                'closes' => '23:00:00',
                'items' => [
                    ['name' => 'Fresh Cucumbers 1kg', 'name_ar' => 'خيار طازج ١ كجم', 'category' => 'Fresh Vegetables', 'price' => 20, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Egyptian Oranges 2kg', 'name_ar' => 'برتقال مصري ٢ كجم', 'category' => 'Egyptian Fruits', 'price' => 55, 'unit' => 'kg', 'veg' => 1],
                    ['name' => 'Almarai Laban 1L', 'name_ar' => 'المراعي لبن ١ لتر', 'category' => 'Yogurt & Laban', 'price' => 48, 'unit' => 'ltr'],
                    ['name' => 'Fresh Chicken Breast 1kg', 'name_ar' => 'صدور فراخ طازجة ١ كجم', 'category' => 'Fresh Chicken', 'price' => 210, 'unit' => 'kg'],
                    ['name' => 'Egyptian White Cheese 250g', 'name_ar' => 'جبنة بيضاء مصرية ٢٥٠ جم', 'category' => 'Egyptian Cheese', 'price' => 40],
                    ['name' => 'Lipton Yellow Label Tea 100 Bags', 'name_ar' => 'ليبتون شاي ١٠٠ فتلة', 'category' => 'Tea & Coffee', 'price' => 130],
                    ['name' => 'Chipsy Salt 170g', 'name_ar' => 'شيبسي ملح ١٧٠ جم', 'category' => 'Chips & Crisps', 'price' => 35],
                    ['name' => 'Molto Croissant 6pcs', 'name_ar' => 'مولتو كرواسون ٦ حبات', 'category' => 'Pastries', 'price' => 60],
                    ['name' => 'Persil Detergent 3kg', 'name_ar' => 'برسيل مسحوق غسيل ٣ كجم', 'category' => 'Laundry', 'price' => 285, 'unit' => 'kg'],
                    ['name' => 'Fine Tissues 550 Sheets', 'name_ar' => 'فاين مناديل ٥٥٠ ورقة', 'category' => 'Paper Products', 'price' => 75],
                ],
            ],
            [
                'name' => 'Gourmet Egypt Maadi',
                'name_ar' => 'جورميه إيجيبت المعادي',
                'phone' => '+20216082',
                'vendor_phone' => '+20216083',
                'vendor_f_name' => 'Gourmet',
                'vendor_l_name' => 'Egypt',
                'vendor_email' => 'gourmet.maadi@waddyapp.com',
                'lat' => 29.9556,
                'lng' => 31.2599,
                'address' => 'Road 216, Degla, Maadi, Cairo Governorate',
                'minimum_order' => 150,
                'delivery_time' => '35-50',
                'opens' => '09:00:00',
                'closes' => '22:00:00',
                'items' => [
                    ['name' => 'Imported Beef Ribeye 500g', 'name_ar' => 'ريب آي بقري مستورد ٥٠٠ جم', 'category' => 'Fresh Beef', 'price' => 520],
                    ['name' => 'Fresh Salmon Fillet 500g', 'name_ar' => 'فيليه سلمون طازج ٥٠٠ جم', 'category' => 'Fresh Fish', 'price' => 480],
                    ['name' => 'Organic Baby Spinach 200g', 'name_ar' => 'سبانخ بيبي عضوية ٢٠٠ جم', 'category' => 'Organic Produce', 'price' => 65, 'veg' => 1],
                    ['name' => 'French Brie Cheese 200g', 'name_ar' => 'جبنة بري فرنسية ٢٠٠ جم', 'category' => 'Cheese', 'price' => 240],
                    ['name' => 'Extra Virgin Olive Oil 500ml', 'name_ar' => 'زيت زيتون بكر ممتاز ٥٠٠ مل', 'category' => 'Cooking Oil', 'price' => 320],
                    ['name' => 'Sourdough Bread Loaf', 'name_ar' => 'خبز ساوردو', 'category' => 'Bread', 'price' => 85, 'veg' => 1],
                    ['name' => 'Jumbo Shrimp 500g', 'name_ar' => 'جمبري جامبو ٥٠٠ جم', 'category' => 'Shrimp & Seafood', 'price' => 395],
                    ['name' => 'Belgian Dark Chocolate 100g', 'name_ar' => 'شوكولاتة بلجيكية داكنة ١٠٠ جم', 'category' => 'Chocolate & Candy', 'price' => 120],
                    ['name' => 'Mixed Roasted Nuts 500g', 'name_ar' => 'مكسرات مشكلة محمصة ٥٠٠ جم', 'category' => 'Nuts & Seeds', 'price' => 340],
                    ['name' => 'Greek Yogurt 500g', 'name_ar' => 'زبادي يوناني ٥٠٠ جم', 'category' => 'Yogurt & Laban', 'price' => 95],
                ],
            ],
        ];
    }

    private function restaurants(): array
    {
        return [
            [
                'name' => 'Fish Market Maadi',
                'name_ar' => 'فيش ماركت المعادي',
                'phone' => '+20227501111',
                'vendor_phone' => '+20227501112',
                'vendor_f_name' => 'Fish',
                'vendor_l_name' => 'Market',
                'vendor_email' => 'fishmarket.maadi@waddyapp.com',
                'lat' => 29.9628,
                'lng' => 31.2556,
                'address' => 'Corniche El Nil, Maadi, Cairo Governorate',
                'minimum_order' => 200,
                'delivery_time' => '45-60',
                'opens' => '12:00:00',
                'closes' => '01:00:00',
                'cuisines' => ['Seafood', 'Egyptian', 'Grills'],
                'items' => [
                    ['name' => 'Grilled Sea Bass', 'name_ar' => 'قاروص مشوي', 'category' => 'Seafood', 'price' => 420],
                    ['name' => 'Fried Calamari', 'name_ar' => 'كاليماري مقلي', 'category' => 'Seafood', 'price' => 285],
                    ['name' => 'Grilled Jumbo Shrimp', 'name_ar' => 'جمبري جامبو مشوي', 'category' => 'Seafood', 'price' => 490],
                    ['name' => 'Seafood Tagine', 'name_ar' => 'طاجن سيفود', 'category' => 'Seafood', 'price' => 380],
                    ['name' => 'Grilled Red Mullet', 'name_ar' => 'بربوني مشوي', 'category' => 'Seafood', 'price' => 350],
                    ['name' => 'Sayadeya Rice', 'name_ar' => 'أرز صيادية', 'category' => 'Seafood', 'price' => 85],
                    ['name' => 'Tahina Salad', 'name_ar' => 'سلطة طحينة', 'category' => 'Salad', 'price' => 45, 'veg' => 1],
                    ['name' => 'Fresh Lemon Juice', 'name_ar' => 'عصير ليمون طازج', 'category' => 'Juices', 'price' => 55, 'veg' => 1],
                ],
            ],
            [
                'name' => 'Butcher\'s Burger Maadi',
                'name_ar' => 'بوتشرز برجر المعادي',
                'phone' => '+20219914',
                'vendor_phone' => '+20219915',
                'vendor_f_name' => 'Butchers',
                'vendor_l_name' => 'Burger',
                'vendor_email' => 'butchers.maadi@waddyapp.com',
                'lat' => 29.9591,
                'lng' => 31.2583,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'minimum_order' => 120,
                'delivery_time' => '30-45',
                'opens' => '12:00:00',
                'closes' => '02:00:00',
                'cuisines' => ['Burgers', 'American', 'Sandwiches'],
                'items' => [
                    ['name' => 'Classic Cheeseburger', 'name_ar' => 'تشيز برجر كلاسيك', 'category' => 'Burgers', 'price' => 185],
                    ['name' => 'Double Bacon Burger', 'name_ar' => 'دبل بيكون برجر', 'category' => 'Burgers', 'price' => 265],
                    ['name' => 'Mushroom Swiss Burger', 'name_ar' => 'مشروم سويس برجر', 'category' => 'Burgers', 'price' => 225],
                    ['name' => 'Crispy Chicken Burger', 'name_ar' => 'كريسبي تشيكن برجر', 'category' => 'Burgers', 'price' => 175],
                    ['name' => 'Truffle Fries', 'name_ar' => 'بطاطس ترافل', 'category' => 'Sandwiches', 'price' => 95, 'veg' => 1],
                    ['name' => 'Buffalo Chicken Wings', 'name_ar' => 'أجنحة دجاج بافلو', 'category' => 'Chicken', 'price' => 155],
                    ['name' => 'Caesar Salad', 'name_ar' => 'سلطة سيزر', 'category' => 'Salad', 'price' => 120],
                    ['name' => 'Oreo Milkshake', 'name_ar' => 'ميلك شيك أوريو', 'category' => 'Desserts', 'price' => 85, 'veg' => 1],
                ],
            ],
            [
                'name' => 'Zooba Maadi',
                'name_ar' => 'زوبا المعادي',
                'phone' => '+20216667',
                'vendor_phone' => '+20216668',
                'vendor_f_name' => 'Zooba',
                'vendor_l_name' => 'Maadi',
                'vendor_email' => 'zooba.maadi@waddyapp.com',
                'lat' => 29.9605,
                'lng' => 31.2578,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'minimum_order' => 90,
                'delivery_time' => '25-40',
                'opens' => '09:00:00',
                'closes' => '02:00:00',
                'cuisines' => ['Egyptian', 'Koshary', 'Sandwiches', 'Breakfast'],
                'items' => [
                    ['name' => 'Koshary Classic', 'name_ar' => 'كشري كلاسيك', 'category' => 'Koshary', 'price' => 75, 'veg' => 1],
                    ['name' => 'Taameya Sandwich', 'name_ar' => 'ساندويتش طعمية', 'category' => 'Sandwiches', 'price' => 45, 'veg' => 1],
                    ['name' => 'Foul Iskandarani', 'name_ar' => 'فول إسكندراني', 'category' => 'Breakfast', 'price' => 55, 'veg' => 1],
                    ['name' => 'Hawawshi Beef', 'name_ar' => 'حواوشي لحمة', 'category' => 'Sandwiches', 'price' => 110],
                    ['name' => 'Feteer Meshaltet', 'name_ar' => 'فطير مشلتت', 'category' => 'Pies', 'price' => 95, 'veg' => 1],
                    ['name' => 'Egyptian Mixed Grill', 'name_ar' => 'مشويات مصرية مشكلة', 'category' => 'Grills', 'price' => 240],
                    ['name' => 'Sobia Drink', 'name_ar' => 'سوبيا', 'category' => 'Beverages', 'price' => 40, 'veg' => 1],
                    ['name' => 'Om Ali', 'name_ar' => 'أم علي', 'category' => 'Desserts', 'price' => 70, 'veg' => 1],
                ],
            ],
            [
                'name' => 'Lucca Pizza Degla',
                'name_ar' => 'لوكا بيتزا دجلة',
                'phone' => '+20225203344',
                'vendor_phone' => '+20225203345',
                'vendor_f_name' => 'Lucca',
                'vendor_l_name' => 'Pizza',
                'vendor_email' => 'lucca.degla@waddyapp.com',
                'lat' => 29.9576,
                'lng' => 31.2521,
                'address' => 'Road 199, Degla, Maadi, Cairo Governorate',
                'minimum_order' => 130,
                'delivery_time' => '35-50',
                'opens' => '12:00:00',
                'closes' => '00:30:00',
                'cuisines' => ['Pizza', 'Italian', 'Pasta'],
                'items' => [
                    ['name' => 'Margherita Pizza', 'name_ar' => 'بيتزا مارجريتا', 'category' => 'Pizza', 'price' => 195, 'veg' => 1],
                    ['name' => 'Pepperoni Pizza', 'name_ar' => 'بيتزا بيبروني', 'category' => 'Pizza', 'price' => 245],
                    ['name' => 'Quattro Formaggi Pizza', 'name_ar' => 'بيتزا أربع أجبان', 'category' => 'Pizza', 'price' => 265, 'veg' => 1],
                    ['name' => 'Penne Arrabbiata', 'name_ar' => 'بيني أرابياتا', 'category' => 'Pasta', 'price' => 175, 'veg' => 1],
                    ['name' => 'Fettuccine Alfredo', 'name_ar' => 'فيتوتشيني ألفريدو', 'category' => 'Pasta', 'price' => 195],
                    ['name' => 'Lasagna Bolognese', 'name_ar' => 'لازانيا بولونيز', 'category' => 'Pasta', 'price' => 225],
                    ['name' => 'Garlic Bread', 'name_ar' => 'خبز بالثوم', 'category' => 'Bakery & Pastries', 'price' => 65, 'veg' => 1],
                    ['name' => 'Tiramisu', 'name_ar' => 'تيراميسو', 'category' => 'Desserts', 'price' => 105, 'veg' => 1],
                ],
            ],
            [
                'name' => 'Abou El Sid Maadi',
                'name_ar' => 'أبو السيد المعادي',
                'phone' => '+20227359640',
                'vendor_phone' => '+20227359641',
                'vendor_f_name' => 'Abou',
                'vendor_l_name' => 'El Sid',
                'vendor_email' => 'abouelsid.maadi@waddyapp.com',
                'lat' => 29.9612,
                'lng' => 31.2564,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'minimum_order' => 180,
                'delivery_time' => '40-55',
                'opens' => '13:00:00',
                'closes' => '01:00:00',
                'cuisines' => ['Egyptian', 'Grills', 'Arabic'],
                'items' => [
                    ['name' => 'Molokheya with Rabbit', 'name_ar' => 'ملوخية بالأرانب', 'category' => 'Egyptian', 'price' => 320],
                    ['name' => 'Stuffed Pigeon', 'name_ar' => 'حمام محشي', 'category' => 'Egyptian', 'price' => 285],
                    ['name' => 'Mixed Grill Platter', 'name_ar' => 'مشويات مشكلة', 'category' => 'Grills', 'price' => 395],
                    ['name' => 'Kofta Kebab', 'name_ar' => 'كفتة كباب', 'category' => 'Grills', 'price' => 265],
                    ['name' => 'Stuffed Vine Leaves', 'name_ar' => 'ورق عنب محشي', 'category' => 'Egyptian', 'price' => 145, 'veg' => 1],
                    ['name' => 'Egyptian Mezze Platter', 'name_ar' => 'مقبلات مصرية مشكلة', 'category' => 'Arabic', 'price' => 165, 'veg' => 1],
                    ['name' => 'Rice with Vermicelli', 'name_ar' => 'أرز بالشعرية', 'category' => 'Egyptian', 'price' => 55, 'veg' => 1],
                    ['name' => 'Rice Pudding', 'name_ar' => 'أرز باللبن', 'category' => 'Desserts', 'price' => 75, 'veg' => 1],
                ],
            ],
        ];
    }

    private function cafes(): array
    {
        return [
            [
                'title' => 'Beano\'s Cafe Degla',
                'title_ar' => 'بينوز كافيه دجلة',
                'description' => 'A Degla mainstay for specialty coffee and all-day breakfast, with a shaded terrace on Road 231.',
                'description_ar' => 'من أشهر كافيهات دجلة، قهوة مختصة وفطار طوال اليوم مع تراس مظلل في شارع ٢٣١.',
                'zone' => 'Degla',
                'lat' => 29.96010000,
                'lng' => 31.24950000,
                'address' => 'Road 231, Degla, Maadi, Cairo Governorate',
                'opens' => '07:00',
                'closes' => '01:00',
                'featured' => 1,
            ],
            [
                'title' => 'Cilantro Degla',
                'title_ar' => 'سيلانترو دجلة',
                'description' => 'Reliable espresso, quiet corners and steady wifi — the default Degla spot for working through an afternoon.',
                'description_ar' => 'إسبريسو ممتاز وأركان هادئة وإنترنت ثابت، المكان المفضل في دجلة للعمل بعد الظهر.',
                'zone' => 'Degla',
                'lat' => 29.95870000,
                'lng' => 31.25120000,
                'address' => 'Road 233, Degla, Maadi, Cairo Governorate',
                'opens' => '07:00',
                'closes' => '00:00',
            ],
            [
                'title' => 'Costa Coffee Degla',
                'title_ar' => 'كوستا كوفي دجلة',
                'description' => 'Familiar flat whites and pastries just off Degla Square, busy from the morning rush onwards.',
                'description_ar' => 'فلات وايت ومعجنات بجوار ميدان دجلة، مزدحم من الصباح الباكر.',
                'zone' => 'Degla',
                'lat' => 29.95940000,
                'lng' => 31.25230000,
                'address' => 'Degla Square, Maadi, Cairo Governorate',
                'opens' => '07:00',
                'closes' => '01:00',
            ],
            [
                'title' => 'Bakery Shop Degla',
                'title_ar' => 'بيكري شوب دجلة',
                'description' => 'Fresh croissants and Egyptian pastries from early morning, with coffee to match.',
                'description_ar' => 'كرواسون طازج ومعجنات مصرية من الصباح الباكر مع قهوة ممتازة.',
                'zone' => 'Degla',
                'lat' => 29.95780000,
                'lng' => 31.25050000,
                'address' => 'Road 206, Degla, Maadi, Cairo Governorate',
                'opens' => '06:00',
                'closes' => '23:00',
            ],
            [
                'title' => 'Coffee Corner Degla',
                'title_ar' => 'كوفي كورنر دجلة',
                'description' => 'A small neighbourhood roastery pouring single-origin filter alongside proper Turkish coffee.',
                'description_ar' => 'محمصة صغيرة في الحي تقدم قهوة فلتر أحادية المصدر بجانب القهوة التركي.',
                'zone' => 'Degla',
                'lat' => 29.96080000,
                'lng' => 31.25340000,
                'address' => 'Road 214, Degla, Maadi, Cairo Governorate',
                'opens' => '08:00',
                'closes' => '23:00',
                'featured' => 1,
            ],
            [
                'title' => 'Second Cup Road 9',
                'title_ar' => 'سيكند كب شارع ٩',
                'description' => 'Street-facing seating on the Road 9 strip, good for people-watching over an iced latte.',
                'description_ar' => 'جلسات على شارع ٩، مثالية لمراقبة الشارع مع لاتيه مثلج.',
                'zone' => 'Road 9',
                'lat' => 29.95960000,
                'lng' => 31.25790000,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'opens' => '08:00',
                'closes' => '00:00',
            ],
            [
                'title' => 'Cafe Greco Road 9',
                'title_ar' => 'كافيه جريكو شارع ٩',
                'description' => 'Long-running Road 9 cafe with an unhurried atmosphere and dependable breakfast plates.',
                'description_ar' => 'كافيه عريق في شارع ٩ بأجواء هادئة وأطباق فطار ممتازة.',
                'zone' => 'Road 9',
                'lat' => 29.95890000,
                'lng' => 31.25830000,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'opens' => '07:30',
                'closes' => '01:00',
            ],
            [
                'title' => 'Eish + Malh Maadi',
                'title_ar' => 'عيش وملح المعادي',
                'description' => 'Bakery-cafe turning out sourdough and pastries, with a long weekend brunch.',
                'description_ar' => 'مخبز وكافيه يقدم خبز الساوردو والمعجنات مع برانش في نهاية الأسبوع.',
                'zone' => 'Road 9',
                'lat' => 29.96050000,
                'lng' => 31.25710000,
                'address' => 'Road 9, Maadi Sarayat, Cairo Governorate',
                'opens' => '08:00',
                'closes' => '23:30',
                'featured' => 1,
            ],
            [
                'title' => 'Tabali Degla',
                'title_ar' => 'تبالي دجلة',
                'description' => 'Casual Degla cafe pairing Levantine plates with strong coffee, busy in the evenings.',
                'description_ar' => 'كافيه في دجلة يقدم أطباق شامية مع قهوة قوية، مزدحم في المساء.',
                'zone' => 'Degla',
                'lat' => 29.95700000,
                'lng' => 31.24880000,
                'address' => 'Road 210, Degla, Maadi, Cairo Governorate',
                'opens' => '09:00',
                'closes' => '00:00',
            ],
            [
                'title' => 'Kaldi Coffee Degla',
                'title_ar' => 'كالدي كوفي دجلة',
                'description' => 'Compact roastery cafe with beans sold by the bag and a short, well-made drinks list.',
                'description_ar' => 'محمصة صغيرة تبيع البن بالكيلو مع قائمة مشروبات قصيرة ومتقنة.',
                'zone' => 'Degla',
                'lat' => 29.95820000,
                'lng' => 31.25410000,
                'address' => 'Road 218, Degla, Maadi, Cairo Governorate',
                'opens' => '08:00',
                'closes' => '23:00',
            ],
        ];
    }
}
