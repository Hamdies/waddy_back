<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class EgyptianGroceryAttributesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds product variation attributes with Arabic translations.
     */
    public function run(): void
    {
        $attributes = [
            ['name' => 'Size', 'ar' => 'الحجم'],
            ['name' => 'Weight', 'ar' => 'الوزن'],
            ['name' => 'Flavor', 'ar' => 'النكهة'],
            ['name' => 'Quantity', 'ar' => 'الكمية'],
            ['name' => 'Type', 'ar' => 'النوع'],
            ['name' => 'Color', 'ar' => 'اللون'],
            ['name' => 'Brand', 'ar' => 'العلامة التجارية'],
            ['name' => 'Volume', 'ar' => 'الحجم'],
            ['name' => 'Packaging', 'ar' => 'التغليف'],
        ];

        foreach ($attributes as $attrData) {
            $attribute = Attribute::updateOrCreate(
                ['name' => $attrData['name']],
                ['name' => $attrData['name']]
            );

            // Add Arabic translation
            Translation::updateOrCreate(
                [
                    'translationable_type' => 'App\Models\Attribute',
                    'translationable_id' => $attribute->id,
                    'locale' => 'ar',
                    'key' => 'name',
                ],
                [
                    'value' => $attrData['ar'],
                ]
            );
        }

        $this->command->info('Egyptian grocery attributes seeded successfully!');
    }
}
