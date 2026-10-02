<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Models\StoreSchedule;
use App\Models\Translation;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Modules\PlacesToVisit\Entities\PlaceTranslation;
use Modules\PlacesToVisit\Entities\PlaceZone;
use Modules\PlacesToVisit\Entities\Scopes\SurfaceScope;

/**
 * Test content for the Pets module: 3 pet shops with products, 5 vet clinics.
 *
 *   php artisan db:seed --class=PetsDemoSeeder --force
 *
 * Runs PetsModuleSeeder first (module, category tree, "Vet clinics"), so it
 * works on a fresh deploy. Safe to re-run: shops are keyed on phone, items on
 * store + name, clinics on their English title.
 *
 * NOT real businesses. Names are invented and every phone number is a
 * deliberately undialable placeholder (+20 0000…), so testing Call can't ring
 * a stranger and WhatsApp stays hidden (the API only offers it for 01x
 * mobiles). To test Call/WhatsApp, put your own number on one clinic in
 * Admin › Places. Before launch, replace or delete these rows:
 *
 *   php artisan db:seed --class=PetsDemoSeeder --force   (re-run is harmless)
 *   delete: stores with phone LIKE '+2000000001%', places titled '* (demo)'
 *
 * The Pets module itself stays as it is (created switched off). Turn it on
 * and enable it for the test zone in Admin › Modules (PET-14).
 */
class PetsDemoSeeder extends Seeder
{
    private int $moduleId;

    /** @var array<string,Category> code => category */
    private array $categories = [];

    public function run(): void
    {
        $this->call(PetsModuleSeeder::class);

        $module = Module::where('variant', 'pets')->first();
        // The zone the grocery stores deliver in, so the shops are in the
        // same delivery area as everything else already live.
        $groceryId = Module::where('module_type', 'grocery')->whereNull('variant')->value('id');
        $zoneId = Store::where('module_id', $groceryId)->value('zone_id')
            ?? DB::table('zones')->value('id');
        if (!$module || !$zoneId) {
            $this->command->error('Need the Pets module and at least one zone.');
            return;
        }
        $this->moduleId = $module->id;
        $this->categories = Category::withoutGlobalScope('translate')
            ->where('module_id', $module->id)
            ->whereNotNull('code')
            ->get()
            ->keyBy('code')
            ->all();

        DB::transaction(function () use ($zoneId) {
            foreach ($this->shops() as $shop) {
                $store = $this->createStore($shop, $zoneId);
                $this->command->line("  shop {$store->id}: {$store->name} (" . count($shop['items']) . ' items)');
            }
            $this->createClinics();
        });

        $this->command->info('Pets demo content seeded.');
    }

    // ==================== Shops ====================

    private function createStore(array $data, int $zoneId): Store
    {
        $vendor = Vendor::updateOrCreate(
            ['phone' => $data['phone'] . '9'],
            [
                'f_name' => $data['name'],
                'l_name' => 'Demo',
                'email' => $data['email'],
                // Placeholder: the account exists so the shop has an owner.
                'password' => Hash::make(Str::random(32)),
                'status' => 1,
            ],
        );

        $store = Store::updateOrCreate(
            ['phone' => $data['phone']],
            [
                'name' => $data['name'],
                'email' => $data['email'],
                'latitude' => (string) $data['lat'],
                'longitude' => (string) $data['lng'],
                'address' => $data['address'],
                'vendor_id' => $vendor->id,
                'module_id' => $this->moduleId,
                'zone_id' => $zoneId,
                'minimum_order' => $data['minimum_order'],
                'delivery_time' => $data['delivery_time'],
                'minimum_shipping_charge' => $data['shipping_charge'],
                'per_km_shipping_charge' => 5,
                'maximum_shipping_charge' => 60,
                'status' => 1,
                'active' => 1,
                'delivery' => 1,
                'take_away' => 1,
                'free_delivery' => $data['free_delivery'] ?? 0,
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

        Translation::updateOrCreate(
            ['translationable_type' => Store::class, 'translationable_id' => $store->id, 'locale' => 'ar', 'key' => 'name'],
            ['value' => $data['name_ar']],
        );

        // Open all day: the store open flag can't express ranges past midnight
        // (see MaadiContentSeeder::setSchedule).
        foreach (range(0, 6) as $day) {
            StoreSchedule::updateOrCreate(
                ['store_id' => $store->id, 'day' => $day],
                ['opening_time' => '00:00:00', 'closing_time' => '23:59:00'],
            );
        }

        foreach ($data['items'] as $item) {
            $this->createItem($store, $item);
        }

        return $store;
    }

    /** @param array{0:string,1:string,2:string,3:float,4?:float} $row [code, name, name_ar, price, discount%] */
    private function createItem(Store $store, array $row): void
    {
        [$code, $name, $nameAr, $price] = $row;
        $discount = $row[4] ?? 0;
        $sub = $this->categories[$code] ?? null;
        $main = $sub ? collect($this->categories)->firstWhere('id', $sub->parent_id) : null;
        if (!$sub || !$main) {
            $this->command->warn("Category {$code} not found, skipping {$name}.");
            return;
        }

        $item = Item::updateOrCreate(
            ['store_id' => $store->id, 'name' => $name],
            [
                'description' => '',
                // category_id is the SUB; the main (species) rides category_ids
                // position 1, the sub position 2 (CAT-08 convention).
                'category_id' => $sub->id,
                'category_ids' => json_encode([
                    ['id' => (string) $main->id, 'position' => 1],
                    ['id' => (string) $sub->id, 'position' => 2],
                ]),
                'price' => $price,
                'discount' => $discount,
                'discount_type' => 'percent',
                'module_id' => $this->moduleId,
                'store_id' => $store->id,
                'stock' => 50,
                'veg' => 0,
                'status' => 1,
                'is_approved' => 1,
                'slug' => Str::slug($name) . '-' . $store->id,
                'variations' => json_encode([]),
                'food_variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'images' => [],
            ],
        );

        Translation::updateOrCreate(
            ['translationable_type' => Item::class, 'translationable_id' => $item->id, 'locale' => 'ar', 'key' => 'name'],
            ['value' => $nameAr],
        );
    }

    private function shops(): array
    {
        return [
            [
                'name' => 'Pet Corner Degla',
                'name_ar' => 'بت كورنر دجلة',
                'phone' => '+200000000101',
                'email' => 'petcorner.demo@waddyapp.com',
                'lat' => 29.9604, 'lng' => 31.2770,
                'address' => 'Street 200, Degla, Maadi',
                'minimum_order' => 100, 'delivery_time' => '20-30', 'shipping_charge' => 15,
                'items' => [
                    ['cat.food', 'Royal Canin Indoor 27, 2 kg', 'رويال كانين إندور ٢٧، ٢ كجم', 1150, 15],
                    ['cat.food', 'Whiskas tuna pouches, 12 × 85 g', 'ويسكاس تونة أكياس، ١٢ × ٨٥ جم', 290, 15],
                    ['cat.food', 'Felix chicken in jelly, 4 × 85 g', 'فيليكس فراخ في جيلي، ٤ × ٨٥ جم', 110],
                    ['cat.treats', 'Dreamies salmon treats, 60 g', 'دريميز حلويات سلمون، ٦٠ جم', 85, 18],
                    ['cat.treats', 'Churu tuna lick treats, 4 × 14 g', 'تشورو تونة، ٤ × ١٤ جم', 95],
                    ['cat.litter', 'Clumping litter, unscented, 10 L', 'رمل متكتل بدون ريحة، ١٠ لتر', 320],
                    ['cat.litter', 'Litter liners, 10 bags', 'أكياس صندوق الرمل، ١٠ أكياس', 75],
                    ['cat.toys', 'Feather wand teaser', 'عصاية ريش للعب', 140],
                    ['cat.grooming', 'Hairball paste, 50 g', 'معجون كرات الشعر، ٥٠ جم', 210],
                    ['dog.food', 'Josera Adult Medium, 4 kg', 'جوزيرا أدلت ميديم، ٤ كجم', 1480, 10],
                    ['dog.food', 'Pedigree beef wet food, 400 g', 'بيديجري لحمة أكل رطب، ٤٠٠ جم', 95],
                    ['dog.treats', 'Pedigree Dentastix, 7 sticks', 'بيديجري دنتاستكس، ٧ أصابع', 120, 17],
                    ['dog.toys', 'Rope chew toy, medium', 'لعبة حبل للعض، وسط', 95],
                    ['all.bowls', 'Stainless steel bowl, 500 ml', 'طبق ستانلس، ٥٠٠ مل', 120],
                ],
            ],
            [
                'name' => 'Paws & Claws Maadi',
                'name_ar' => 'باوز آند كلوز المعادي',
                'phone' => '+200000000102',
                'email' => 'pawsclaws.demo@waddyapp.com',
                'lat' => 29.9625, 'lng' => 31.2545,
                'address' => 'Road 9, Maadi Sarayat',
                'minimum_order' => 80, 'delivery_time' => '25-35', 'shipping_charge' => 0, 'free_delivery' => 1,
                'items' => [
                    ['dog.food', 'Puppy starter kibble, 2 kg', 'دراي فود جراوي، ٢ كجم', 690],
                    ['dog.food', 'Josera Adult Medium, 4 kg', 'جوزيرا أدلت ميديم، ٤ كجم', 1495],
                    ['dog.treats', 'Chicken jerky strips, 100 g', 'شرائح فراخ مجففة، ١٠٠ جم', 135],
                    ['dog.toys', 'Squeaky tennis balls, 3 pcs', 'كور تنس بصوت، ٣ قطع', 150],
                    ['dog.walk', 'Nylon lead, 1.5 m', 'مقود نايلون، ١.٥ متر', 180],
                    ['dog.grooming', 'Oatmeal dog shampoo, 250 ml', 'شامبو شوفان للكلاب، ٢٥٠ مل', 180],
                    ['cat.food', 'Royal Canin Indoor 27, 2 kg', 'رويال كانين إندور ٢٧، ٢ كجم', 1165],
                    ['cat.treats', 'Catnip mouse, set of 3', 'فار كاتنيب، ٣ قطع', 120],
                    ['all.travel', 'Pet carrier, medium', 'شنطة نقل، وسط', 650, 12],
                    ['all.cleaning', 'Poop bags with dispenser, 60 bags', 'أكياس فضلات مع علبة، ٦٠ كيس', 85],
                ],
            ],
            [
                'name' => 'Aqua & Wings',
                'name_ar' => 'أكوا آند وينجز',
                'phone' => '+200000000103',
                'email' => 'aquawings.demo@waddyapp.com',
                'lat' => 29.9530, 'lng' => 31.2650,
                'address' => 'Laselky St, New Maadi',
                'minimum_order' => 60, 'delivery_time' => '35-50', 'shipping_charge' => 20,
                'items' => [
                    ['bird.food', 'Budgie seed mix, 1 kg', 'خليط حبوب بادجي، ١ كجم', 220],
                    ['bird.food', 'Parrot fruit blend, 750 g', 'خليط فواكه للببغاء، ٧٥٠ جم', 340, 10],
                    ['bird.treats', 'Honey seed stick, 2 pcs', 'عصاية حبوب بالعسل، ٢ قطعة', 60],
                    ['bird.cages', 'Wooden swing perch', 'مرجيحة خشب للعصافير', 110],
                    ['bird.care', 'Cage sand sheets, 10 sheets', 'ورق رمل للقفص، ١٠ ورقات', 70],
                    ['fish.food', 'Tetra Min flakes, 100 g', 'تترا مين رقائق، ١٠٠ جم', 260],
                    ['fish.food', 'Goldfish pellets, 200 g', 'حبيبات سمك ذهبي، ٢٠٠ جم', 150],
                    ['fish.tanks', 'Aquarium ornament cave', 'كهف ديكور للحوض', 180],
                    ['fish.care', 'Water conditioner, 250 ml', 'منظم مياه، ٢٥٠ مل', 160],
                    ['small.food', 'Vitakraft hamster mix, 1 kg', 'فيتاكرافت أكل هامستر، ١ كجم', 180],
                    ['small.food', 'Timothy hay, 500 g', 'تبن تيموثي، ٥٠٠ جم', 140],
                    ['small.treats', 'Wooden chew blocks, 4 pcs', 'مكعبات خشب للقرض، ٤ قطع', 75],
                    ['small.cages', 'Exercise wheel, 18 cm', 'عجلة تمرين، ١٨ سم', 260],
                    ['small.bedding', 'Paper bedding, 1 kg', 'فرشة ورق، ١ كجم', 150],
                ],
            ],
        ];
    }

    // ==================== Clinics ====================

    private function createClinics(): void
    {
        $categoryId = DB::table('place_categories')
            ->where('surface', SurfaceScope::PETS)
            ->where('name', 'Vet clinics')
            ->value('id');
        if (!$categoryId) {
            $this->command->warn('No "Vet clinics" category; skipping clinics.');
            return;
        }
        $zoneId = PlaceZone::value('id');

        foreach ($this->clinics() as $data) {
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
                'phone' => $data['phone'],
                'opening_hours' => json_encode($data['hours']),
                'is_active' => 1,
                'is_featured' => 0,
                'updated_at' => now(),
            ];

            if ($existingId) {
                DB::table('places')->where('id', $existingId)->update($attributes);
                $placeId = $existingId;
            } else {
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
            $this->command->line("  clinic {$placeId}: {$data['title']}");
        }
    }

    /** Every day [open, close]; `closed` days listed by name. */
    private function hours(string $open, string $close, array $closed = []): array
    {
        $out = [];
        foreach (['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day) {
            $out[$day] = ['open' => $open, 'close' => $close, 'closed' => in_array($day, $closed, true)];
        }
        return $out;
    }

    private function clinics(): array
    {
        return [
            [
                'title' => 'Maadi Vet Care (demo)', 'title_ar' => 'معادي فيت كير (تجريبي)',
                'description' => 'Cats and dogs. Vaccines, check-ups, grooming.',
                'description_ar' => 'قطط وكلاب. تطعيمات وكشف وتنظيف.',
                'lat' => 29.9590, 'lng' => 31.2580, 'address' => 'Road 9, Maadi',
                'phone' => '+200000000201', 'hours' => $this->hours('10:00', '22:00'),
            ],
            [
                'title' => 'Degla Animal Hospital (demo)', 'title_ar' => 'مستشفى دجلة للحيوانات (تجريبي)',
                'description' => 'Open 24 hours. Emergencies, surgery, X-ray. All pets.',
                'description_ar' => 'مفتوح ٢٤ ساعة. طوارئ وعمليات وأشعة. كل الحيوانات.',
                'lat' => 29.9612, 'lng' => 31.2795, 'address' => 'Street 200, Degla',
                'phone' => '+200000000202', 'hours' => $this->hours('00:00', '23:59'),
            ],
            [
                'title' => 'New Maadi Exotic Vet (demo)', 'title_ar' => 'عيادة نيو معادي للحيوانات النادرة (تجريبي)',
                'description' => 'Birds, fish, rabbits and hamsters.',
                'description_ar' => 'طيور وأسماك وأرانب وهامستر.',
                'lat' => 29.9525, 'lng' => 31.2662, 'address' => 'Laselky St, New Maadi',
                'phone' => '+200000000203', 'hours' => $this->hours('16:00', '23:00', ['friday']),
            ],
            [
                'title' => 'Zahraa Pet Clinic (demo)', 'title_ar' => 'عيادة الزهراء البيطرية (تجريبي)',
                'description' => 'Cats and dogs. Home visits on request.',
                'description_ar' => 'قطط وكلاب. زيارات منزلية بالطلب.',
                'lat' => 29.9680, 'lng' => 31.2860, 'address' => 'Zahraa El Maadi',
                'phone' => '+200000000204', 'hours' => $this->hours('09:00', '17:00', ['friday']),
            ],
            [
                'title' => 'Sarayat Small Animal Clinic (demo)', 'title_ar' => 'عيادة السرايات للحيوانات الصغيرة (تجريبي)',
                'description' => 'Small animals and cats. Dental and vaccines.',
                'description_ar' => 'حيوانات صغيرة وقطط. أسنان وتطعيمات.',
                'lat' => 29.9575, 'lng' => 31.2520, 'address' => 'Road 77, Maadi Sarayat',
                // No number on file: the sheet shows no Call button.
                'phone' => null, 'hours' => $this->hours('11:00', '21:00'),
            ],
        ];
    }
}
