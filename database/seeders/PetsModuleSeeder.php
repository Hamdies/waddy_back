<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Module;
use App\Models\Translation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The Pets module: module row, shared category tree, vet clinic category.
 *
 *   php artisan db:seed --class=PetsModuleSeeder --force
 *
 * Safe to re-run: everything is matched on a stable key (module variant,
 * category code, place category surface + name) and updated in place.
 *
 * - The module is `module_type = grocery`, `variant = pets` (PET-01), so
 *   orders, order-status pushes, admin forms, POS and the store app treat pet
 *   shops like grocery stores. It is created SWITCHED OFF (`status = 0`):
 *   app builds that predate `variant` read it as a second grocery module, so
 *   it stays off until the build that understands it is the minimum version
 *   (PET-14). An admin turns it on and assigns zones.
 * - Categories: species as main categories, needs as their subs, one shared
 *   tree (`store_id` NULL) so every shop browses the same way (PET-02).
 *   "All pets" holds what several species share (bowls, carriers; D3).
 *   `code` is the stable key the app reads; names and images are free to edit.
 * - Vet clinics are their own records (`vet_clinics`), managed from
 *   Admin › Vet clinics in the Pets module; nothing to seed here.
 */
class PetsModuleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $module = $this->module();
            $this->command->info("Pets module id {$module->id} (status {$module->status}).");

            foreach ($this->tree() as $priority => $species) {
                $main = $this->category($module->id, 0, $species, 0, count($this->tree()) - $priority);
                foreach ($species['subs'] as $sub) {
                    $this->category($module->id, $main->id, $sub, 1, 0);
                }
                $this->command->line("  {$species['name']}: " . count($species['subs']) . ' sub-categories');
            }

        });
    }

    private function module(): Module
    {
        $module = Module::where('variant', 'pets')->first();
        if (!$module) {
            $module = Module::create([
                'module_name' => 'Pets',
                'module_type' => 'grocery',
                'variant' => 'pets',
                'thumbnail' => null,
                'icon' => null,
                'status' => 0,
                'stores_count' => 0,
                'theme_id' => 1,
                'description' => 'Pet food, treats and supplies from pet shops near you',
                'all_zone_service' => 0,
            ]);
        }

        $this->translate(Module::class, $module->id, 'module_name', 'Pets', 'الحيوانات الأليفة');
        $this->translate(Module::class, $module->id, 'description',
            'Pet food, treats and supplies from pet shops near you',
            'أكل وحلويات ومستلزمات الحيوانات الأليفة من محلات قريبة منك');

        return $module;
    }

    private function category(int $moduleId, int $parentId, array $data, int $position, int $priority): Category
    {
        $category = Category::where('module_id', $moduleId)->where('code', $data['code'])->first()
            ?? new Category();

        $category->forceFill([
            'module_id' => $moduleId,
            'parent_id' => $parentId,
            'store_id' => null,
            'code' => $data['code'],
            // position is the main(0)/sub(1) flag the API filters on.
            'position' => $position,
            'priority' => $priority,
            'status' => 1,
            'featured' => 0,
        ]);
        if (!$category->exists) {
            $category->name = $data['name'];
            $category->image = '';
        }
        $category->save();

        $this->translate(Category::class, $category->id, 'name', $data['name'], $data['ar']);

        return $category;
    }

    private function translate(string $type, int $id, string $key, string $en, string $ar): void
    {
        foreach (['en' => $en, 'ar' => $ar] as $locale => $value) {
            Translation::updateOrCreate(
                ['translationable_type' => $type, 'translationable_id' => $id, 'locale' => $locale, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    /** Species → needs. Codes are `<species>` and `<species>.<need>`. */
    private function tree(): array
    {
        $sub = fn (string $species, string $need, string $name, string $ar) =>
            ['code' => "{$species}.{$need}", 'name' => $name, 'ar' => $ar];

        return [
            ['code' => 'cat', 'name' => 'Cats', 'ar' => 'قطط', 'subs' => [
                $sub('cat', 'food', 'Food', 'أكل'),
                $sub('cat', 'treats', 'Treats', 'حلويات'),
                $sub('cat', 'litter', 'Litter & care', 'رمل وعناية'),
                $sub('cat', 'toys', 'Toys', 'ألعاب'),
                $sub('cat', 'beds', 'Beds & scratchers', 'سراير وخدّاشات'),
                $sub('cat', 'grooming', 'Grooming', 'تنظيف وتجميل'),
                $sub('cat', 'health', 'Health', 'صحة'),
            ]],
            ['code' => 'dog', 'name' => 'Dogs', 'ar' => 'كلاب', 'subs' => [
                $sub('dog', 'food', 'Food', 'أكل'),
                $sub('dog', 'treats', 'Treats & chews', 'حلويات وعضّاضات'),
                $sub('dog', 'toys', 'Toys', 'ألعاب'),
                $sub('dog', 'walk', 'Leads & collars', 'أطواق ومقاود'),
                $sub('dog', 'beds', 'Beds', 'سراير'),
                $sub('dog', 'grooming', 'Grooming', 'تنظيف وتجميل'),
                $sub('dog', 'health', 'Health', 'صحة'),
            ]],
            ['code' => 'bird', 'name' => 'Birds', 'ar' => 'طيور', 'subs' => [
                $sub('bird', 'food', 'Seeds & food', 'حبوب وأكل'),
                $sub('bird', 'treats', 'Treats', 'حلويات'),
                $sub('bird', 'cages', 'Cages & perches', 'أقفاص ومجاثم'),
                $sub('bird', 'care', 'Cage care', 'نظافة القفص'),
                $sub('bird', 'health', 'Health', 'صحة'),
            ]],
            ['code' => 'fish', 'name' => 'Fish', 'ar' => 'أسماك', 'subs' => [
                $sub('fish', 'food', 'Food', 'أكل'),
                $sub('fish', 'tanks', 'Tanks & decor', 'أحواض وديكور'),
                $sub('fish', 'care', 'Water & tank care', 'مياه ونظافة الحوض'),
                $sub('fish', 'health', 'Health', 'صحة'),
            ]],
            ['code' => 'small', 'name' => 'Small pets', 'ar' => 'حيوانات صغيرة', 'subs' => [
                $sub('small', 'food', 'Food & hay', 'أكل وتبن'),
                $sub('small', 'treats', 'Treats & chews', 'حلويات وعضّاضات'),
                $sub('small', 'bedding', 'Bedding', 'فرشة'),
                $sub('small', 'cages', 'Cages & wheels', 'أقفاص وعجلات'),
                $sub('small', 'health', 'Health', 'صحة'),
            ]],
            ['code' => 'all', 'name' => 'All pets', 'ar' => 'لكل الحيوانات', 'subs' => [
                $sub('all', 'bowls', 'Bowls & feeders', 'أطباق ومعالف'),
                $sub('all', 'travel', 'Carriers & travel', 'شنط وسفر'),
                $sub('all', 'cleaning', 'Cleaning', 'تنظيف'),
                $sub('all', 'accessories', 'Accessories', 'إكسسوارات'),
            ]],
        ];
    }
}
