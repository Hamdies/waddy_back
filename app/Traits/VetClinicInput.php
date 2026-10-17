<?php

namespace App\Traits;

use App\CentralLogics\Helpers;
use App\Models\VetClinic;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The clinic fields shared by Admin › Vet clinics and the public
 * application form (/vet-clinic/apply): rules, and the cleaned-up data.
 */
trait VetClinicInput
{
    private function clinicRules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'name_ar' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:2000',
            'description_ar' => 'nullable|string|max:2000',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:30',
            'website' => 'nullable|url|max:255',
            'instagram' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'cover' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'opening_hours' => 'nullable|array',
            'species' => 'nullable|array',
            'species.*' => Rule::in(VetClinic::SPECIES),
            'services' => 'nullable|array',
            'services.*' => Rule::in(VetClinic::SERVICES),
            'service_prices' => 'nullable|array',
            'service_prices.*' => 'nullable|numeric|min:0|max:1000000',
            'vets' => 'nullable|array|max:' . VetClinic::MAX_VETS,
            'vets.*.name' => 'nullable|string|max:80',
            'vets.*.role' => 'nullable|string|max:80',
            'vets.*.years' => 'nullable|integer|min:0|max:70',
        ];
    }

    /** The clinic's own fields from a validated request; no images, status or priority. */
    private function clinicData(Request $request): array
    {
        $services = $request->input('services', []);
        $prices = [];
        foreach ((array) $request->input('service_prices', []) as $key => $price) {
            if (in_array($key, $services, true) && $price !== null && $price !== '') {
                $prices[$key] = (float) $price;
            }
        }
        $vets = [];
        foreach ((array) $request->input('vets', []) as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name !== '') {
                $vets[] = [
                    'name' => $name,
                    'role' => trim((string) ($row['role'] ?? '')) ?: null,
                    'years' => isset($row['years']) && $row['years'] !== '' ? (int) $row['years'] : null,
                ];
            }
        }

        return [
            'name' => $request->name,
            'name_ar' => $request->name_ar,
            'description' => $request->description,
            'description_ar' => $request->description_ar,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'address' => $request->address,
            'phone' => $request->phone,
            'website' => $request->website,
            'instagram' => $request->instagram,
            'opening_hours' => array_intersect_key(
                (array) $request->input('opening_hours', []), array_flip(VetClinic::DAYS)
            ) ?: null,
            'species' => $request->input('species', []) ?: null,
            'services' => $services ?: null,
            'service_prices' => $prices ?: null,
            'vets' => $vets ?: null,
        ];
    }

    /** Stores an upload under `vet-clinic/…`; returns the public-disk path. */
    private function uploadClinicFile($file, string $subdir = ''): ?string
    {
        $dir = 'vet-clinic/' . $subdir;
        $ext = strtolower($file->getClientOriginalExtension()) === 'pdf' ? 'pdf' : 'png';
        $name = Helpers::upload($dir, $ext, $file);
        return $name === 'def.png' ? null : $dir . $name;
    }
}
