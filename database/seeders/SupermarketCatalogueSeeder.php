<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The supermarket aisle tree (talabat-mart style) and a starter catalogue.
 *
 * 1. Creates 25 main categories, each with its subcategories, in the grocery
 *    module — ordered by `priority` exactly as listed (the API sorts desc).
 * 2. Repairs a defect left by EgyptianGroceryCategoriesSeeder: it stored each
 *    subcategory's list index in `position`, so every FIRST subcategory
 *    ("Fresh Milk", "Fresh Fruits", …) got position 0 — which is how the API
 *    identifies a MAIN category — and surfaced in the app as one. Any row with
 *    a parent is a subcategory, so it is set to position 1.
 * 3. Stocks the three supermarkets (Seoudi, Metro, Gourmet — by owner email,
 *    so admin renames don't lose them) with 2 products per MAIN category:
 *    one each in its first two subcategories. The other subcategories stay
 *    empty on purpose — a starter shelf, not a full catalogue.
 * 4. Removes the products an earlier run of this seeder added beyond that
 *    (it used to put 2 in every subcategory, and stocked Adam Supermarket
 *    too). Only rows this seeder created (from its first run, found via
 *    FIRST_RUN_MARKER) with a catalogue product name are touched, and never
 *    an item that has been ordered.
 *
 * Products are generic Egyptian-supermarket staples at typical prices, not a
 * copy of any store's real stock. Images are left null — upload in admin.
 *
 * The subcategories mirror talabat mart's aisles (checked 2026-09-29). Not
 * copied: the "Hot Deals" main tile and each aisle's "Deals" / "All" tiles —
 * those are live views (discounted items; everything in the parent), and a
 * static category would go stale the day a promo ends. talabat's third level
 * (Herbs & Leafy Greens › Lettuce) is also skipped: categories here are two
 * levels deep.
 *
 * Old top-level categories that are not in this tree are LISTED, not deleted:
 * items may still live in them. Subcategories under a tree parent that are no
 * longer in the tree are deleted, but only once empty.
 *
 * Idempotent: categories key on module + parent + name, items on store + name.
 * Re-running also re-prunes, so the catalogue converges on the kept set.
 *   php artisan db:seed --class=SupermarketCatalogueSeeder --force
 */
class SupermarketCatalogueSeeder extends Seeder
{
    /** Owner emails of the stores that carry this catalogue. */
    private const SUPERMARKET_EMAILS = [
        'seoudi.maadi@waddyapp.com',
        'metro.degla@waddyapp.com',
        'gourmet.maadi@waddyapp.com',
    ];

    /** Subcategories per main category that get a product. */
    private const STOCKED_SUBS = 2;

    /**
     * A product only this seeder creates, in its FIRST category — so the
     * earliest row with this name marks when the catalogue first ran.
     *
     * The cutoff used to be a hard-coded date (2026-09-29 00:00). The live
     * server runs on CEST and the first run landed late on the 28th by its
     * clock, so every catalogue row looked older than the cutoff and the
     * prune removed nothing. A marker row can't disagree with the server
     * about what time it was.
     */
    private const FIRST_RUN_MARKER = 'Siwa Dates 500g';

    /** Slack before the marker: its insert is a few rows into the run. */
    private const FIRST_RUN_SLACK_MINUTES = 5;

    public function run(): void
    {
        $moduleId = Module::where('module_type', 'grocery')->value('id');
        if (!$moduleId) {
            $this->command->error('Grocery module not found.');

            return;
        }

        $stores = $this->supermarkets($moduleId);
        if ($stores->count() < count(self::SUPERMARKET_EMAILS)) {
            $this->command->warn('Found ' . $stores->count() . ' of ' . count(self::SUPERMARKET_EMAILS) . ' supermarkets: ' . $stores->map(fn ($s) => $s->getRawOriginal('name'))->join(', '));
        }

        DB::transaction(function () use ($moduleId, $stores) {
            $fixed = Category::where('module_id', $moduleId)
                ->where('parent_id', '!=', 0)
                ->where('position', '!=', 1)
                ->update(['position' => 1]);
            $this->command->line("Repaired {$fixed} subcategories that were posing as main categories.");

            $tree = $this->tree();
            $priority = count($tree);
            $itemCount = 0;

            $removed = [];
            $keepNames = [];
            $allNames = [];

            foreach ($tree as $name => [$ar, $subs]) {
                $parent = $this->category($moduleId, 0, $name, $ar, 0, $priority--);

                $subPriority = count($subs);
                $subIndex = 0;
                foreach ($subs as $subName => [$subAr, $products]) {
                    $sub = $this->category($moduleId, $parent->id, $subName, $subAr, 1, $subPriority--);

                    foreach ($products as [$pName]) {
                        $allNames[] = $pName;
                    }

                    // One product, and only in the first STOCKED_SUBS subs.
                    if ($subIndex++ < self::STOCKED_SUBS) {
                        [$pName, $pAr, $price] = $products[0];
                        $keepNames[] = $pName;
                        foreach ($stores as $store) {
                            $this->item($store, $parent, $sub, $pName, $pAr, $price, $moduleId);
                            $itemCount++;
                        }
                    }
                }

                // Subcategories an earlier version of this tree created under
                // the same parent ("Fruits", "Herbs & Salad", …). Removed only
                // once empty — the item upserts above have already moved any
                // same-named product into its new subcategory.
                $orphans = Category::withoutGlobalScope('translate')
                    ->where('parent_id', $parent->id)
                    ->whereNotIn('name', array_keys($subs))
                    ->get();
                foreach ($orphans as $orphan) {
                    if (Item::withoutGlobalScopes()->where('category_id', $orphan->id)->exists()) {
                        continue;
                    }
                    Translation::where('translationable_type', Category::class)
                        ->where('translationable_id', $orphan->id)
                        ->delete();
                    $orphan->delete();
                    $removed[] = "{$name} › {$orphan->getRawOriginal('name')}";
                }
            }

            if ($removed) {
                $this->command->line('Removed empty old subcategories: ' . implode(', ', $removed));
            }

            $this->pruneExtraProducts($moduleId, $stores, $allNames, $keepNames);

            $this->command->info('Seeded ' . count($tree) . " categories and {$itemCount} products across {$stores->count()} supermarkets.");

            $stale = Category::withoutGlobalScope('translate')
                ->where('module_id', $moduleId)
                ->where('parent_id', 0)
                ->whereNull('store_id') // specialty stores' own lists are not stale
                ->whereNotIn('name', array_keys($tree))
                ->pluck('name');
            if ($stale->isNotEmpty()) {
                $this->command->warn('Older main categories still active (not touched): ' . $stale->join(', '));
            }
        });
    }

    private function supermarkets(int $moduleId)
    {
        return Store::withoutGlobalScope('translate')
            ->where('module_id', $moduleId)
            ->whereHas('vendor', fn ($q) => $q->whereIn('email', self::SUPERMARKET_EMAILS))
            ->get();
    }

    /**
     * Deletes this seeder's own earlier products that are no longer wanted:
     * every catalogue item at a store outside the three supermarkets, and at
     * the three, every catalogue item beyond the kept 2-per-category set.
     */
    private function pruneExtraProducts(int $moduleId, $stores, array $allNames, array $keepNames): void
    {
        $keepAt = $stores->pluck('id')->all();

        $firstRun = Item::withoutGlobalScopes()
            ->where('module_id', $moduleId)
            ->where('name', self::FIRST_RUN_MARKER)
            ->min('created_at');
        if (!$firstRun) {
            $this->command->warn('No "' . self::FIRST_RUN_MARKER . '" row found — nothing to prune (already pruned: the marker itself is one of the extras).');

            return;
        }
        $cutoff = \Illuminate\Support\Carbon::parse($firstRun)->subMinutes(self::FIRST_RUN_SLACK_MINUTES);
        $this->command->line("Catalogue first ran at {$firstRun}; pruning its rows from {$cutoff}.");

        $candidates = Item::withoutGlobalScopes()
            ->where('module_id', $moduleId)
            ->whereIn('name', array_unique($allNames))
            ->where('created_at', '>=', $cutoff)
            ->get(['id', 'store_id', 'name']);

        $deleted = 0;
        $ordered = 0;
        foreach ($candidates as $item) {
            if (in_array((int) $item->store_id, $keepAt) && in_array($item->getRawOriginal('name'), $keepNames, true)) {
                continue;
            }
            if (DB::table('order_details')->where('item_id', $item->id)->exists()) {
                $ordered++;

                continue;
            }
            Translation::where('translationable_type', Item::class)
                ->where('translationable_id', $item->id)
                ->delete();
            Item::withoutGlobalScopes()->where('id', $item->id)->delete();
            $deleted++;
        }

        $this->command->line("Removed {$deleted} extra catalogue products" . ($ordered ? " (kept {$ordered} that have orders)" : '') . '.');
    }

    private function category(int $moduleId, int $parentId, string $name, string $ar, int $position, int $priority): Category
    {
        $category = Category::withoutGlobalScope('translate')->updateOrCreate(
            ['module_id' => $moduleId, 'store_id' => null, 'parent_id' => $parentId, 'name' => $name],
            ['position' => $position, 'priority' => $priority, 'status' => 1],
        );

        Translation::updateOrCreate(
            [
                'translationable_type' => Category::class,
                'translationable_id' => $category->id,
                'locale' => 'ar',
                'key' => 'name',
            ],
            ['value' => $ar],
        );

        return $category;
    }

    private function item(Store $store, Category $parent, Category $sub, string $name, string $ar, float $price, int $moduleId): void
    {
        $item = Item::withoutGlobalScopes()->updateOrCreate(
            ['store_id' => $store->id, 'name' => $name],
            [
                'description' => '',
                'category_id' => $sub->id,
                'category_ids' => json_encode([
                    ['id' => (string) $parent->id, 'position' => 0],
                    ['id' => (string) $sub->id, 'position' => 1],
                ]),
                'price' => $price,
                'module_id' => $moduleId,
                'stock' => 100,
                'veg' => 0,
                'status' => 1,
                'is_approved' => 1,
                'slug' => Str::slug($name) . '-' . $store->id,
                'variations' => json_encode([]),
                'food_variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                // Cast to array on the model — see MaadiContentSeeder::createItem.
                'images' => [],
            ],
        );

        Translation::updateOrCreate(
            [
                'translationable_type' => Item::class,
                'translationable_id' => $item->id,
                'locale' => 'ar',
                'key' => 'name',
            ],
            ['value' => $ar],
        );
    }

    /**
     * main => [arabic, [sub => [arabic, [[product, arabic, price EGP], …]]]]
     * Listed in display order.
     */
    private function tree(): array
    {
        return [
            'Fruit & Veg' => ['فاكهة وخضار', [
                'Fresh Fruit' => ['فاكهة طازجة', [['Bananas 1kg', 'موز ١ كجم', 42], ['Red Apples 1kg', 'تفاح أحمر ١ كجم', 80]]],
                'Fresh Vegetables' => ['خضروات طازجة', [['Tomatoes 1kg', 'طماطم ١ كجم', 22], ['Cucumbers 1kg', 'خيار ١ كجم', 20]]],
                'Herbs & Leafy Greens' => ['أعشاب وورقيات', [['Iceberg Lettuce 1pc', 'خس آيسبرج قطعة', 20], ['White Cabbage 1pc', 'كرنب أبيض قطعة', 30]]],
                'Dates & Dried Fruit' => ['بلح وفواكه مجففة', [['Siwa Dates 500g', 'بلح سيوي ٥٠٠ جم', 60], ['Golden Raisins 250g', 'زبيب ذهبي ٢٥٠ جم', 55]]],
            ]],
            'Bakery' => ['مخبوزات', [
                'Flatbread' => ['عيش بلدي وشامي', [['Baladi Bread 10pcs', 'عيش بلدي ١٠ أرغفة', 15], ['Shami Bread 5pcs', 'عيش شامي ٥ أرغفة', 20]]],
                'Buns & Rolls' => ['خبز صغير ورول', [['Burger Buns 6pcs', 'عيش برجر ٦ قطع', 35], ['Hot Dog Rolls 6pcs', 'عيش هوت دوج ٦ قطع', 35]]],
                'Fresh Bakes & Cakes' => ['مخبوزات طازجة وكيك', [['HOHOs King Chocolate Cake 60g', 'هوهوز كينج شوكولاتة ٦٠ جم', 10], ['Twinkies Original Cake 50g', 'تونكيز أوريجينال ٥٠ جم', 10]]],
                'Pastries' => ['معجنات', [['Molto XXL Chocolate Croissant 60g', 'مولتو XXL كرواسون شوكولاتة ٦٠ جم', 10], ['Cheese Pastry', 'فطيرة جبنة', 35]]],
                'Crispbread & Rusk' => ['بقسماط وتوست ناشف', [['Rusk Plain 250g', 'بقسماط سادة ٢٥٠ جم', 35], ['Breadsticks 150g', 'أصابع عيش ١٥٠ جم', 30]]],
            ]],
            'Poultry, Meat & Seafood' => ['دواجن ولحوم وأسماك', [
                'Chicken & Poultry' => ['دجاج ودواجن', [['Whole Chicken 1.2kg', 'فرخة كاملة ١.٢ كجم', 180], ['Chicken Breast 1kg', 'صدور فراخ ١ كجم', 260]]],
                'Beef & Veal' => ['لحم بقري وبتلو', [['Minced Beef Balady 500g', 'لحم مفروم بلدي ٥٠٠ جم', 230], ['Beef Cubes Balady 500g', 'لحم مكعبات بلدي ٥٠٠ جم', 225]]],
                'Lamb & Goat' => ['ضاني وماعز', [['Lamb Chops 500g', 'ريش ضاني ٥٠٠ جم', 300], ['Lamb Leg 1kg', 'فخدة ضاني ١ كجم', 560]]],
                'Fish & Seafood' => ['أسماك ومأكولات بحرية', [['Tilapia 1kg', 'بلطي ١ كجم', 110], ['Shrimp Medium 500g', 'جمبري وسط ٥٠٠ جم', 350]]],
            ]],
            'Ready To Eat' => ['جاهز للأكل', [
                'Salads' => ['سلطات', [['Italian Salad 1kg', 'سلطة إيطالي ١ كجم', 200], ['Greek Salad', 'سلطة يوناني', 90]]],
                'Sandwiches & Wraps' => ['ساندويتشات ورابس', [['Smoked Turkey Cheddar Sandwich', 'ساندويتش تركي مدخن وشيدر', 60], ['Chicken Caesar Wrap', 'راب دجاج سيزر', 95]]],
                'Starters & Sides' => ['مقبلات وأطباق جانبية', [['Cheese Sambousek 750g', 'سمبوسك جبنة ٧٥٠ جم', 220], ['Hummus 250g', 'حمص بالطحينة ٢٥٠ جم', 45]]],
            ]],
            'Breakfast Food' => ['فطار', [
                'Cereals' => ['حبوب الإفطار', [['Corn Flakes 375g', 'كورن فليكس ٣٧٥ جم', 95], ['Rolled Oats 500g', 'شوفان ٥٠٠ جم', 70]]],
                'Spreads' => ['أنواع الدهن', [['Abu Auf Peanut Butter 330g', 'أبو عوف زبدة فول سوداني ٣٣٠ جم', 180], ['El Bawadi Chocolate Halawa Spread 300g', 'البوادي حلاوة شوكولاتة سبريد ٣٠٠ جم', 70]]],
                'Honey & Jams' => ['عسل ومربى', [['Bee Honey 500g', 'عسل نحل ٥٠٠ جم', 150], ['Strawberry Jam 450g', 'مربى فراولة ٤٥٠ جم', 60]]],
            ]],
            'Canned & Jarred' => ['معلبات', [
                'Canned Seafood' => ['مأكولات بحرية معلبة', [['Sunshine Tuna Chunks 185g', 'صن شاين تونة قطع ١٨٥ جم', 71], ['Sardines in Oil 125g', 'سردين بالزيت ١٢٥ جم', 35]]],
                'Canned Vegetables' => ['خضروات معلبة', [['Sweet Corn 340g', 'ذرة حلوة ٣٤٠ جم', 40], ['Green Peas 400g', 'بسلة خضراء ٤٠٠ جم', 30]]],
                'Canned Fruit' => ['فاكهة معلبة', [['Pineapple Slices 565g', 'أناناس شرائح ٥٦٥ جم', 75], ['Peach Halves 820g', 'خوخ أنصاف ٨٢٠ جم', 95]]],
                'Canned Meat' => ['لحوم معلبة', [['Luncheon Meat 200g', 'لانشون لحم ٢٠٠ جم', 55], ['Corned Beef 340g', 'بولوبيف ٣٤٠ جم', 120]]],
            ]],
            'Disposables' => ['أدوات الاستخدام الواحد', [
                'Tissues & Paper Rolls' => ['مناديل ولفائف ورق', [['Facial Tissues 500 Sheets', 'مناديل وجه ٥٠٠ منديل', 40], ['Toilet Paper 12 Rolls', 'ورق تواليت ١٢ رول', 135]]],
                'Disposable Tableware' => ['أطباق وأكواب للاستخدام الواحد', [['Paper Plates 20pcs', 'أطباق ورق ٢٠ قطعة', 35], ['Plastic Cups 50pcs', 'أكواب بلاستيك ٥٠ قطعة', 30]]],
                'Garbage Bags' => ['أكياس قمامة', [['Garbage Bags Large 30pcs', 'أكياس قمامة كبيرة ٣٠ قطعة', 40], ['Garbage Bags Medium 50pcs', 'أكياس قمامة وسط ٥٠ قطعة', 35]]],
                'Food Storage & Wraps' => ['حفظ الطعام والتغليف', [['Aluminum Foil 10m', 'ورق ألومنيوم ١٠ م', 45], ['Cling Film 30m', 'رول تغليف ٣٠ م', 40]]],
            ]],
            'Household Essentials' => ['مستلزمات منزلية', [
                'Outdoor & Travel Gear' => ['مستلزمات الرحلات والسفر', [['Travel Neck Pillow', 'مخدة رقبة للسفر', 150], ['Insulated Water Bottle 500ml', 'زمزمية معزولة ٥٠٠ مل', 180]]],
                'Home Maintenance' => ['صيانة المنزل', [['AA Batteries 4pcs', 'بطاريات AA ٤ قطع', 60], ['LED Bulb 12W', 'لمبة ليد ١٢ وات', 50]]],
                'Home Supplies' => ['مستلزمات البيت', [['Frida Air Freshener 460ml', 'فريدا معطر جو ٤٦٠ مل', 70], ['Insect Killer Spray 300ml', 'مبيد حشرات سبراي ٣٠٠ مل', 85]]],
                'Kitchen & Dining' => ['المطبخ والسفرة', [['Dish Sponges 3pcs', 'سفنج مطبخ ٣ قطع', 20], ['Glass Food Container 1L', 'علبة حفظ زجاج ١ لتر', 120]]],
            ]],
            'Stationery & Games' => ['أدوات مكتبية وألعاب', [
                'Stationery' => ['أدوات مكتبية', [['Spiral Notebook 100 Sheets', 'كشكول سلك ١٠٠ ورقة', 85], ['Ballpoint Pens 10pcs', 'أقلام جاف ١٠ قطع', 40]]],
                'Games' => ['ألعاب', [['Playing Cards', 'كوتشينة', 25], ['Balloons 25pcs', 'بالونات ٢٥ قطعة', 45]]],
            ]],
            'Dairy & Eggs' => ['ألبان وبيض', [
                'Eggs' => ['بيض', [['White Eggs 30pcs', 'بيض أبيض ٣٠ حبة', 145], ['Baladi Eggs 10pcs', 'بيض بلدي ١٠ حبات', 55]]],
                'Cheese' => ['جبن', [['Domty White Cheese 500g', 'دومتي جبنة بيضاء ٥٠٠ جم', 68], ['Cheddar Slices 200g', 'شرائح شيدر ٢٠٠ جم', 85]]],
                'Yoghurts & Labneh' => ['زبادي ولبنة', [['Juhayna Greek Yoghurt 180g', 'جهينة زبادي يوناني ١٨٠ جم', 43], ['Labneh 250g', 'لبنة ٢٥٠ جم', 50]]],
                'Butter' => ['زبدة', [['Butter 200g', 'زبدة ٢٠٠ جم', 110], ['Lurpak Butter 200g', 'لورباك زبدة ٢٠٠ جم', 160]]],
                'Cream' => ['كريمة وقشطة', [['Almarai Cooking Cream 200ml', 'المراعي كريمة طبخ ٢٠٠ مل', 60], ['Almarai Whipping Cream 200ml', 'المراعي كريمة خفق ٢٠٠ مل', 65]]],
                'Chilled Desserts' => ['حلويات مبردة', [['Danette Caramel 70g', 'دانيت كراميل ٧٠ جم', 15], ['Rice Pudding 150g', 'أرز باللبن ١٥٠ جم', 20]]],
            ]],
            'Deli' => ['ديلي', [
                'Cheese & Labneh' => ['جبن ولبنة', [['Romy Cheese with Pepper 250g', 'جبنة رومي بالفلفل ٢٥٠ جم', 84], ['Batarekh Cheese 250g', 'جبنة بطارخ ٢٥٠ جم', 84]]],
                'Deli Cuts' => ['لحوم مقطعة', [['Smoked Turkey Breast 250g', 'صدور رومي مدخن ٢٥٠ جم', 120], ['Beef Pastrami 250g', 'بسطرمة ٢٥٠ جم', 130]]],
                'Pickles & Olives' => ['مخللات وزيتون', [['Green Olives 500g', 'زيتون أخضر ٥٠٠ جم', 55], ['Mixed Pickles 500g', 'مخلل مشكل ٥٠٠ جم', 40]]],
                'Jam & Halawa' => ['مربى وحلاوة', [['Plain Halawa 250g', 'حلاوة سادة ٢٥٠ جم', 45], ['Apricot Jam 250g', 'مربى مشمش ٢٥٠ جم', 40]]],
                'Zaatar' => ['زعتر', [['Zaatar Mix 250g', 'زعتر مخلوط ٢٥٠ جم', 60], ['Dukkah 250g', 'دقة ٢٥٠ جم', 50]]],
            ]],
            'Beverages' => ['مشروبات', [
                'Water' => ['مياه', [['Elano Water 6 x 1.5L', 'إيلانو مياه ٦ × ١.٥ لتر', 60], ['Mineral Water 600ml', 'مياه معدنية ٦٠٠ مل', 7]]],
                'Soft Drinks' => ['مشروبات غازية', [['Pepsi Can 355ml', 'بيبسي كانز ٣٥٥ مل', 15], ['Pepsi Diet Can 355ml', 'بيبسي دايت كانز ٣٥٥ مل', 15]]],
                'Sports & Energy Drinks' => ['مشروبات رياضية وطاقة', [['Red Bull 250ml', 'ريد بول ٢٥٠ مل', 55], ['Sports Drink 500ml', 'مشروب رياضي ٥٠٠ مل', 30]]],
                'Juices' => ['عصائر', [['Orange Juice 1L', 'عصير برتقال ١ لتر', 45], ['Mango Juice 1L', 'عصير مانجو ١ لتر', 45]]],
                'Specialty Drinks' => ['مشروبات مميزة', [['Iced Tea Peach 330ml', 'شاي مثلج خوخ ٣٣٠ مل', 25], ['Coconut Water 330ml', 'ماء جوز الهند ٣٣٠ مل', 55]]],
                'Syrups' => ['شراب مركز', [['Rose Syrup 700ml', 'شراب ورد ٧٠٠ مل', 60], ['Mango Syrup 700ml', 'شراب مانجو ٧٠٠ مل', 60]]],
                'Powdered Drinks' => ['مشروبات بودرة', [['Orange Powder Drink 750g', 'مشروب برتقال بودرة ٧٥٠ جم', 95], ['Sahlab Powder 200g', 'سحلب بودرة ٢٠٠ جم', 45]]],
            ]],
            'Milk' => ['لبن', [
                'Fresh Milk' => ['لبن طازج', [['Full Cream Fresh Milk 1L', 'لبن طازج كامل الدسم ١ لتر', 45], ['Skimmed Fresh Milk 1L', 'لبن طازج خالي الدسم ١ لتر', 43]]],
                'Long Life Milk' => ['لبن طويل الأجل', [['Juhayna Full Cream UHT Milk 1L', 'جهينة لبن كامل الدسم ١ لتر', 55], ['Bekhero Full Cream Milk 1L', 'بخيره لبن كامل الدسم ١ لتر', 42]]],
                'Milk Alternatives' => ['بدائل اللبن', [['Almond Milk 1L', 'حليب لوز ١ لتر', 140], ['Oat Milk 1L', 'حليب شوفان ١ لتر', 150]]],
                'Powdered Milk' => ['لبن بودرة', [['Full Cream Milk Powder 400g', 'لبن بودرة كامل الدسم ٤٠٠ جم', 190], ['Skimmed Milk Powder 400g', 'لبن بودرة خالي الدسم ٤٠٠ جم', 180]]],
                'Evaporated Milk' => ['لبن مبخر', [['Evaporated Milk 170g', 'لبن مبخر ١٧٠ جم', 30], ['Sweetened Condensed Milk 397g', 'لبن مكثف محلى ٣٩٧ جم', 60]]],
            ]],
            'Condiments' => ['صوصات وتوابل', [
                'Salad Dressings' => ['تتبيلات السلطة', [['Caesar Dressing 250ml', 'دريسنج سيزر ٢٥٠ مل', 60], ['Thousand Island Dressing 250ml', 'دريسنج ثاوزند آيلاند ٢٥٠ مل', 60]]],
                'Sauces' => ['صوصات', [['Heinz Ketchup 125g', 'هاينز كاتشب ١٢٥ جم', 22], ['Mayonnaise 480g', 'مايونيز ٤٨٠ جم', 70]]],
                'Spices & Seasonings' => ['بهارات وتوابل', [['Knorr Potato Seasoning 6g', 'كنور تتبيلة بطاطس ٦ جم', 3], ['Ground Cumin 50g', 'كمون مطحون ٥٠ جم', 20]]],
                'Salt' => ['ملح', [["Cook's Table Salt 700g", 'كوكس ملح طعام ٧٠٠ جم', 16], ['Sea Salt 500g', 'ملح بحري ٥٠٠ جم', 25]]],
            ]],
            'Protein & Special Diet' => ['بروتين وأنظمة غذائية', [
                'Protein' => ['بروتين', [['Abu Auf Smoked BBQ Protein Snacks 60g', 'أبو عوف سناكس بروتين باربيكيو ٦٠ جم', 23], ['Chocolate Protein Bar 60g', 'لوح بروتين شوكولاتة ٦٠ جم', 70]]],
                'Special Diet' => ['أنظمة غذائية خاصة', [['Gluten Free Pasta 400g', 'مكرونة خالية من الجلوتين ٤٠٠ جم', 120], ['Sugar Free Chocolate 80g', 'شوكولاتة بدون سكر ٨٠ جم', 85]]],
            ]],
            'Personal Care' => ['عناية شخصية', [
                'Hair Care' => ['عناية بالشعر', [['OGX Argan Oil Shampoo 385ml', 'أو جي إكس شامبو زيت الأرجان ٣٨٥ مل', 350], ['Conditioner 360ml', 'بلسم ٣٦٠ مل', 120]]],
                'Face Care' => ['عناية بالوجه', [['Face Wash 150ml', 'غسول وجه ١٥٠ مل', 120], ['Face Moisturizer 50ml', 'مرطب وجه ٥٠ مل', 160]]],
                'Skin & Body Care' => ['عناية بالبشرة والجسم', [['NIVEA Body Cream 50ml', 'نيفيا كريم جسم ٥٠ مل', 43], ['Shower Gel 500ml', 'شاور جل ٥٠٠ مل', 110]]],
                'Deodorants' => ['مزيلات عرق', [['Deodorant Spray 150ml', 'مزيل عرق سبراي ١٥٠ مل', 110], ['Roll-On Deodorant 50ml', 'مزيل عرق رول أون ٥٠ مل', 85]]],
                'Oral Care' => ['عناية بالفم', [['Listerine Cool Mint 250ml', 'ليسترين كول مينت ٢٥٠ مل', 125], ['Signal Toothbrush Medium', 'سيجنال فرشاة أسنان متوسطة', 35]]],
                'Feminine Care' => ['عناية نسائية', [['Sanitary Pads 10pcs', 'فوط صحية ١٠ قطع', 45], ['Panty Liners 20pcs', 'فوط يومية ٢٠ قطعة', 40]]],
                'Shaving & Hair Removal' => ['حلاقة وإزالة شعر', [['Disposable Razors 5pcs', 'أمواس حلاقة ٥ قطع', 80], ['Shaving Foam 200ml', 'رغوة حلاقة ٢٠٠ مل', 90]]],
            ]],
            'Baby Corner' => ['ركن الأطفال', [
                'Diapers' => ['حفاضات', [['Baby Diapers Size 3', 'حفاضات أطفال مقاس ٣', 320], ['Baby Diapers Size 4', 'حفاضات أطفال مقاس ٤', 340]]],
                'Baby Formula' => ['لبن أطفال', [['Infant Formula Stage 1 400g', 'لبن أطفال مرحلة ١ ٤٠٠ جم', 250], ['Infant Formula Stage 2 400g', 'لبن أطفال مرحلة ٢ ٤٠٠ جم', 250]]],
                'Baby Food' => ['أكل أطفال', [['Cerelac Wheat with Milk 125g', 'سيريلاك قمح باللبن ١٢٥ جم', 60], ['Fruit Puree 120g', 'بيوريه فاكهة ١٢٠ جم', 45]]],
                'Baby Hygiene' => ['نظافة الطفل', [["Johnson's Baby Shampoo 200ml", 'جونسون شامبو أطفال ٢٠٠ مل', 92], ['Baby Wipes 72pcs', 'مناديل مبللة للأطفال ٧٢ قطعة', 60]]],
            ]],
            'Snacks & Chocolate' => ['سناكس وشوكولاتة', [
                'Chocolate' => ['شوكولاتة', [['Galaxy Flutes 4 Fingers 45g', 'جالاكسي فلوتس ٤ أصابع ٤٥ جم', 19], ['Maltesers 37g', 'مالتيزرز ٣٧ جم', 30]]],
                'Biscuits' => ['بسكويت', [['Tea Biscuits 12pcs', 'بسكويت شاي ١٢ قطعة', 30], ['Chocolate Chip Cookies 200g', 'كوكيز شوكولاتة ٢٠٠ جم', 40]]],
                'Chips & Dips' => ['شيبسي وصوصات', [['Bigy Chips Lime 90g', 'بيج شيبس ليمون ٩٠ جم', 15], ['Salsa Dip 300g', 'صوص سالسا ٣٠٠ جم', 60]]],
                'Seeds & Nuts' => ['لب ومكسرات', [['Salted Peanuts 250g', 'فول سوداني مملح ٢٥٠ جم', 50], ['Sunflower Seeds 250g', 'لب سوري ٢٥٠ جم', 65]]],
                'Popcorn' => ['فشار', [['Microwave Popcorn Butter 90g', 'فشار ميكروويف بالزبدة ٩٠ جم', 30], ['Caramel Popcorn 100g', 'فشار كراميل ١٠٠ جم', 35]]],
                'Candy & Gums' => ['حلوى ولبان', [['Chewing Gum Mint 10pcs', 'لبان نعناع ١٠ قطع', 15], ['Marshmallows 150g', 'مارشميلو ١٥٠ جم', 35]]],
                'Crackers & Pretzels' => ['كراكرز وبريتزل', [['Salty Crackers 100g', 'كراكرز مملح ١٠٠ جم', 15], ['Pretzels 150g', 'بريتزل ١٥٠ جم', 40]]],
                'Rice Cakes & Others' => ['كيك أرز وأخرى', [['Rice Cakes 100g', 'كيك أرز ١٠٠ جم', 45], ['Balance Protein Crackers 78g', 'بالانس كراكرز بروتين ٧٨ جم', 15]]],
            ]],
            'Ice Cream' => ['آيس كريم', [
                'Bars, Cones & Sticks' => ['أصابع وكونز', [['Nestle Mega Strawberry 85ml', 'نستله ميجا فراولة ٨٥ مل', 25], ['Vanilla Cone', 'كون فانيليا', 25]]],
                'Cups & Tubs' => ['أكواب وعلب', [['Vanilla Ice Cream 1L', 'آيس كريم فانيليا ١ لتر', 110], ['Chocolate Ice Cream Cup 200ml', 'آيس كريم شوكولاتة كوب ٢٠٠ مل', 45]]],
                'Protein Ice Cream' => ['آيس كريم بروتين', [['Wonderfit Greek Yogurt Ice Cream 200ml', 'وندرفت آيس كريم زبادي يوناني ٢٠٠ مل', 60], ['Wonderfit French Vanilla 200ml', 'وندرفت فانيليا فرنسية ٢٠٠ مل', 60]]],
            ]],
            'Frozen Food' => ['مجمدات', [
                'Fries' => ['بطاطس مجمدة', [['Farm Frites Steakhouse Fries 750g', 'فارم فريتس ستيك هاوس ٧٥٠ جم', 69], ['Farm Frites Potato Wedges 750g', 'فارم فريتس ودجز ٧٥٠ جم', 74]]],
                'Ready Meals' => ['وجبات جاهزة', [['Frozen Beef Lasagna 400g', 'لازانيا لحم مجمدة ٤٠٠ جم', 150], ['Frozen Mini Pizzas 4pcs', 'ميني بيتزا مجمدة ٤ قطع', 90]]],
                'Fruit And Veg' => ['فاكهة وخضار مجمدة', [['Frozen Mixed Vegetables 400g', 'خضار مشكل مجمد ٤٠٠ جم', 45], ['Frozen Strawberries 400g', 'فراولة مجمدة ٤٠٠ جم', 70]]],
                'Poultry' => ['دواجن مجمدة', [['Chicken Nuggets 400g', 'ناجتس دجاج ٤٠٠ جم', 110], ['Frozen Chicken Breasts 1kg', 'صدور دجاج مجمدة ١ كجم', 220]]],
                'Seafood' => ['مأكولات بحرية مجمدة', [['Frozen Fish Fillet 1kg', 'فيليه سمك مجمد ١ كجم', 240], ['Frozen Shrimp 500g', 'جمبري مجمد ٥٠٠ جم', 280]]],
                'Meat' => ['لحوم مجمدة', [['Beef Burgers 8pcs', 'برجر لحم ٨ قطع', 160], ['Frozen Kofta 1kg', 'كفتة مجمدة ١ كجم', 280]]],
                'Bakery & Desserts' => ['مخبوزات وحلويات مجمدة', [['Frozen Puff Pastry 400g', 'عجينة بف باستري مجمدة ٤٠٠ جم', 55], ['Frozen Sambousek 500g', 'سمبوسك مجمد ٥٠٠ جم', 90]]],
            ]],
            'Coffee & Tea' => ['قهوة وشاي', [
                'Coffee' => ['قهوة', [['Nescafe Classic Jar 95g', 'نسكافيه كلاسيك برطمان ٩٥ جم', 150], ['Turkish Coffee 250g', 'بن تركي ٢٥٠ جم', 160]]],
                'Tea' => ['شاي', [['Black Tea 100 Bags', 'شاي أسود ١٠٠ فتلة', 95], ['Green Tea 25 Bags', 'شاي أخضر ٢٥ فتلة', 45]]],
                'Creamers' => ['مبيضات القهوة', [['Coffee Creamer 400g', 'مبيض قهوة ٤٠٠ جم', 110], ['Condensed Milk Tube 170g', 'لبن مكثف أنبوب ١٧٠ جم', 40]]],
                'Ready To Drink' => ['جاهز للشرب', [['Iced Latte Can 240ml', 'آيس لاتيه كانز ٢٤٠ مل', 35], ['Cold Brew Coffee 250ml', 'قهوة كولد برو ٢٥٠ مل', 55]]],
            ]],
            'Cooking & Baking' => ['طبخ وخبيز', [
                'Baking Ingredients' => ['مستلزمات الخبيز', [['Baking Powder 50g', 'بيكنج بودر ٥٠ جم', 10], ['Aldoha Flour 1kg', 'الضحى دقيق ١ كجم', 30]]],
                'Frying Oil' => ['زيت قلي', [['Sunflower Oil 1L', 'زيت عباد الشمس ١ لتر', 95], ['Corn Oil 1L', 'زيت ذرة ١ لتر', 110]]],
                'Olive Oil' => ['زيت زيتون', [['Extra Virgin Olive Oil 500ml', 'زيت زيتون بكر ممتاز ٥٠٠ مل', 320], ['Pomace Olive Oil 1L', 'زيت زيتون ثفل ١ لتر', 250]]],
                'Ghee' => ['سمن', [['Ghee 800g', 'سمن ٨٠٠ جم', 180], ['Vegetable Ghee 700g', 'سمن نباتي ٧٠٠ جم', 95]]],
                'Sugar & Sweeteners' => ['سكر ومحليات', [['White Sugar 1kg', 'سكر أبيض ١ كجم', 36], ['Stevia 100 Sachets', 'ستيفيا ١٠٠ كيس', 110]]],
                'Pastas' => ['مكرونة', [['Spaghetti 400g', 'اسباجتي ٤٠٠ جم', 22], ['Penne 400g', 'قلم ٤٠٠ جم', 22]]],
                'Noodles & Soups' => ['نودلز وشوربة', [['Indomie Jumbo Beef Noodles 100g', 'إندومي جامبو لحم ١٠٠ جم', 15], ['Chicken Soup Cubes 8pcs', 'مكعبات مرقة دجاج ٨ قطع', 20]]],
                'Rice' => ['أرز', [['Aldoha Egyptian White Rice 1kg', 'الضحى أرز مصري ١ كجم', 43], ['Basmati Rice 1kg', 'أرز بسمتي ١ كجم', 110]]],
                'Pulses & Grains' => ['بقوليات وحبوب', [['Yellow Lentils 1kg', 'عدس أصفر ١ كجم', 60], ['Dried Fava Beans 1kg', 'فول ناشف ١ كجم', 45]]],
                'Pizza & Pasta Sauces' => ['صوصات البيتزا والمكرونة', [['Heinz Tomato Paste 370g', 'هاينز صلصة طماطم ٣٧٠ جم', 36], ['Pasta Sauce 500g', 'صوص مكرونة ٥٠٠ جم', 65]]],
            ]],
            'Cleaning & Laundry' => ['تنظيف وغسيل', [
                'Dishwashing' => ['غسيل الأطباق', [['Oxi Dishwashing Liquid Green Lemon 600ml', 'أوكسي سائل أطباق ليمون أخضر ٦٠٠ مل', 38], ['Dishwasher Tablets 30pcs', 'أقراص غسالة أطباق ٣٠ قطعة', 280]]],
                'Cleaning Supplies' => ['منظفات', [['Clorox for Colors 950ml', 'كلوروكس للألوان ٩٥٠ مل', 68], ['Floor Cleaner 1L', 'منظف أرضيات ١ لتر', 50]]],
                'Laundry' => ['غسيل الملابس', [['Laundry Powder 2.5kg', 'مسحوق غسيل ٢.٥ كجم', 190], ['Liquid Detergent 3L', 'منظف غسيل سائل ٣ لتر', 230]]],
            ]],
            'Health & Beauty' => ['صحة وجمال', [
                'Perfumes' => ['عطور', [['Mood Power Body Splash 220ml', 'مود باور بادي سبلاش ٢٢٠ مل', 100], ['Eau de Toilette 100ml', 'أو دو تواليت ١٠٠ مل', 450]]],
                'Makeup' => ['مكياج', [['Garnier Micellar Water 100ml', 'جارنييه مياه ميسيلار ١٠٠ مل', 110], ['Mascara Black', 'ماسكارا سوداء', 180]]],
                'Pharmacy' => ['صيدلية', [['Sanita Charm Cotton Buds 100pcs', 'سانيتا شارم أعواد قطن ١٠٠ قطعة', 30], ['Pectol Vitamin C Candy 19.2g', 'بكتول حلوى فيتامين سي ١٩.٢ جم', 24]]],
            ]],
            'Pet Care' => ['عناية بالحيوانات الأليفة', [
                'Cat Food' => ['أكل قطط', [['Dry Cat Food 1kg', 'أكل قطط جاف ١ كجم', 180], ['Wet Cat Food 85g', 'أكل قطط رطب ٨٥ جم', 30]]],
                'Dog Food' => ['أكل كلاب', [['Dry Dog Food 2kg', 'أكل كلاب جاف ٢ كجم', 320], ['Dog Treats 100g', 'مكافآت كلاب ١٠٠ جم', 90]]],
                'Pet Supplies' => ['مستلزمات الحيوانات', [['Cat Litter 5kg', 'رمل قطط ٥ كجم', 150], ['Pet Shampoo 250ml', 'شامبو حيوانات ٢٥٠ مل', 120]]],
            ]],
        ];
    }
}
