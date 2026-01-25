<?php

namespace Database\Seeders;

use App\Models\Unit;
use App\Models\Translation;
use Illuminate\Database\Seeder;

class EgyptianGroceryUnitsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Seeds common grocery units with Arabic translations.
     */
    public function run(): void
    {
        $units = [
            ['unit' => 'Kilogram', 'ar' => 'كيلوجرام'],
            ['unit' => 'Gram', 'ar' => 'جرام'],
            ['unit' => 'Liter', 'ar' => 'لتر'],
            ['unit' => 'Milliliter', 'ar' => 'مللي لتر'],
            ['unit' => 'Piece', 'ar' => 'قطعة'],
            ['unit' => 'Pack', 'ar' => 'عبوة'],
            ['unit' => 'Dozen', 'ar' => 'درزن'],
            ['unit' => 'Box', 'ar' => 'علبة'],
            ['unit' => 'Bag', 'ar' => 'كيس'],
            ['unit' => 'Bundle', 'ar' => 'حزمة'],
            ['unit' => 'Bottle', 'ar' => 'زجاجة'],
            ['unit' => 'Can', 'ar' => 'علبة معدنية'],
            ['unit' => 'Jar', 'ar' => 'برطمان'],
            ['unit' => 'Carton', 'ar' => 'كرتونة'],
            ['unit' => 'Sachet', 'ar' => 'كيس صغير'],
        ];

        foreach ($units as $unitData) {
            $unit = Unit::updateOrCreate(
                ['unit' => $unitData['unit']],
                ['unit' => $unitData['unit']]
            );

            // Add Arabic translation
            Translation::updateOrCreate(
                [
                    'translationable_type' => 'App\Models\Unit',
                    'translationable_id' => $unit->id,
                    'locale' => 'ar',
                    'key' => 'unit',
                ],
                [
                    'value' => $unitData['ar'],
                ]
            );
        }

        $this->command->info('Egyptian grocery units seeded successfully!');
    }
}
