<?php

namespace Modules\PlacesToVisit\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Module;

class PlacesModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if Places module already exists
        $existingModule = Module::where('module_type', 'places')->first();
        
        if (!$existingModule) {
            $module = Module::create([
                'module_name' => 'Places to Visit',
                'module_type' => 'places',
                'thumbnail' => null,
                'status' => 1,
                'stores_count' => 0,
                'icon' => null,
                'theme_id' => 1,
                'description' => 'Discover and vote for the best local places to visit',
                'all_zone_service' => 1,
            ]);

            // Add translations
            DB::table('translations')->insert([
                [
                    'translationable_type' => 'App\Models\Module',
                    'translationable_id' => $module->id,
                    'locale' => 'en',
                    'key' => 'module_name',
                    'value' => 'Places to Visit',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'translationable_type' => 'App\Models\Module',
                    'translationable_id' => $module->id,
                    'locale' => 'ar',
                    'key' => 'module_name',
                    'value' => 'أماكن للزيارة',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'translationable_type' => 'App\Models\Module',
                    'translationable_id' => $module->id,
                    'locale' => 'en',
                    'key' => 'description',
                    'value' => 'Discover and vote for the best local places to visit',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'translationable_type' => 'App\Models\Module',
                    'translationable_id' => $module->id,
                    'locale' => 'ar',
                    'key' => 'description',
                    'value' => 'اكتشف وصوت لأفضل الأماكن المحلية للزيارة',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $this->command->info('Places module created successfully!');
        } else {
            $this->command->info('Places module already exists.');
        }
    }
}
