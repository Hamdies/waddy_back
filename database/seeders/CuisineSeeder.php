<?php

namespace Database\Seeders;

use App\Models\Cuisine;
use App\Models\Translation;
use Illuminate\Database\Seeder;

/**
 * The cuisine filter list, as the app presents it.
 *
 * Keyed on name so re-running updates rather than duplicates.
 */
class CuisineSeeder extends Seeder
{
    /** name => [arabic, priority]. Higher priority sorts first. */
    private const CUISINES = [
        'Egyptian' => ['مصري', 100],
        'Burgers' => ['برجر', 95],
        'Pizza' => ['بيتزا', 95],
        'Shawerma' => ['شاورما', 90],
        'Grills' => ['مشويات', 90],
        'Seafood' => ['مأكولات بحرية', 85],
        'Koshary' => ['كشري', 85],
        'Chicken' => ['فراخ', 80],
        'Sandwiches' => ['ساندويتشات', 80],
        'Breakfast' => ['فطار', 75],
        'Desserts' => ['حلويات', 75],
        'Bakery & Pastries' => ['مخبوزات وحلويات', 70],
        'Coffee & Tea' => ['قهوة وشاي', 70],
        'Juices' => ['عصائر', 65],
        'Beverages' => ['مشروبات', 65],
        'Ice Cream' => ['آيس كريم', 60],
        'Crepes' => ['كريب', 60],
        'Waffles' => ['وافل', 55],
        'Cakes' => ['كيك', 55],
        'Chocolate' => ['شوكولاتة', 50],
        'Italian' => ['إيطالي', 50],
        'Pasta' => ['مكرونة', 50],
        'Asian' => ['آسيوي', 45],
        'Chinese' => ['صيني', 45],
        'Japanese' => ['ياباني', 45],
        'Sushi' => ['سوشي', 45],
        'Indian' => ['هندي', 40],
        'Mexican' => ['مكسيكي', 40],
        'American' => ['أمريكي', 40],
        'Lebanese' => ['لبناني', 35],
        'Syrian' => ['سوري', 35],
        'Arabic' => ['عربي', 35],
        'Arabic Sweets' => ['حلويات شرقية', 30],
        'Manaqeesh' => ['مناقيش', 30],
        'Mandi' => ['مندي', 30],
        'Seyami' => ['صيامي', 25],
        'Salad' => ['سلطات', 25],
        'Healthy' => ['صحي', 25],
        'Vegetarian' => ['نباتي', 20],
        'Pies' => ['فطائر', 20],
        'International' => ['عالمي', 15],
    ];

    public function run(): void
    {
        foreach (self::CUISINES as $name => [$arabic, $priority]) {
            $cuisine = Cuisine::withoutGlobalScope('translate')
                ->firstWhere('name', $name);

            if ($cuisine) {
                $cuisine->forceFill(['priority' => $priority, 'status' => 1])->save();
            } else {
                $cuisine = Cuisine::create([
                    'name' => $name,
                    'status' => 1,
                    'priority' => $priority,
                ]);
            }

            Translation::updateOrCreate(
                [
                    'translationable_type' => Cuisine::class,
                    'translationable_id' => $cuisine->id,
                    'locale' => 'ar',
                    'key' => 'name',
                ],
                ['value' => $arabic],
            );
        }

        $this->command->info('Seeded ' . count(self::CUISINES) . ' cuisines.');
    }
}
