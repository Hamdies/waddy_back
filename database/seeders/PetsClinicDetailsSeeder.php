<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Test data for the clinic page (design 03): what each of the 5 clinics
 * treats, its services and starting prices, and its vets.
 *
 *   php artisan db:seed --class=PetsClinicDetailsSeeder --force
 *
 * Keyed on each clinic's English name in `vet_clinics` (they were renamed
 * after the demo seed, so ids say nothing). Re-running overwrites the same
 * fields, nothing else (names, photos, hours untouched).
 *
 * PLACEHOLDER CONTENT. The vets and prices are the design's sample data, not
 * facts about these clinics. Several are real businesses: replace these from
 * Admin › Places with what each clinic actually offers before customers rely
 * on it, or clear them all with:
 *
 *   php artisan tinker --execute='DB::table("vet_clinics")->update(["species"=>null,
 *     "services"=>null,"service_prices"=>null,"vets"=>null]);'
 */
class PetsClinicDetailsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->clinics() as $name => $data) {
            $updated = DB::table('vet_clinics')->where('name', $name)->update([
                'species' => json_encode($data['species']),
                'services' => json_encode(array_keys($data['services'])),
                'service_prices' => json_encode(array_filter($data['services'], fn ($p) => $p !== null)),
                'vets' => json_encode($data['vets']),
                'updated_at' => now(),
            ]);
            $this->command->line($updated ? "  {$name}: updated" : "  {$name}: not found, skipped");
        }
    }

    /** English name => species, services (key => starting price or null), vets */
    private function clinics(): array
    {
        $vet = fn (string $name, string $role, int $years) => compact('name', 'role', 'years');

        // Matched to each clinic's own description and hours as set in
        // admin (checked against the live API 10-02): ids follow the demo
        // seed, the names were changed afterwards.
        return [
            // I - Vet: "Cats and dogs. Vaccines, check-ups, grooming." 10–22.
            'I - Vet' => [
                'species' => ['cat', 'dog'],
                'services' => ['vaccination' => 450, 'grooming' => 350, 'lab' => 400, 'pharmacy' => null],
                'vets' => [$vet('Dr. Mona Saleh', 'General practice', 12), $vet('Dr. Omar Fathy', 'Surgery', 8)],
            ],
            // Animal Care Center: "Open 24 hours. Emergencies, surgery, X-ray. All pets."
            'Animal Care Center' => [
                'species' => ['cat', 'dog', 'bird', 'fish', 'small'],
                'services' => ['emergency_24h' => 600, 'surgery' => 2800, 'xray' => 700, 'lab' => 700, 'boarding' => 250],
                'vets' => [$vet('Dr. Yasmin Adel', 'Emergency care', 10), $vet('Dr. Tarek Nabil', 'Internal medicine', 14), $vet('Dr. Nour Hassan', 'Surgery', 7)],
            ],
            // Harmony: "Birds, fish, rabbits and hamsters." 16–23, closed Friday.
            'Harmony' => [
                'species' => ['bird', 'fish', 'small'],
                'services' => ['vaccination' => 300, 'grooming' => 150, 'lab' => 200],
                'vets' => [$vet('Dr. Hany Ibrahim', 'Exotics & birds', 15)],
            ],
            // Pets Lovers: "Cats and dogs. Home visits on request." 9–17, closed Friday.
            'Pets Lovers' => [
                'species' => ['cat', 'dog'],
                'services' => ['home_visit' => 600, 'vaccination' => 400, 'grooming' => 300],
                'vets' => [$vet('Dr. Sara Khaled', 'General practice', 9)],
            ],
            // Paw Stive: "Small animals and cats. Dental and vaccines." 11–21.
            'Paw Stive' => [
                'species' => ['cat', 'small'],
                'services' => ['dental' => 850, 'vaccination' => 420, 'pharmacy' => null],
                'vets' => [$vet('Dr. Hala Samir', 'Dentistry', 6), $vet('Dr. Karim Adel', 'Small animals', 11)],
            ],
        ];
    }
}
