<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\VetClinic;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin › Pets › Vet clinics. Clinics are the Pets module's own records,
 * apart from Spots (PET-04, revised 10-02).
 */
class VetClinicController extends Controller
{
    private const IMAGE_DIR = 'vet-clinic/';

    public function index(Request $request): View
    {
        $clinics = VetClinic::query()
            ->when($request->search, function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"));
            })
            ->orderByDesc('priority')
            ->orderBy('name')
            ->paginate(config('default_pagination'));

        return view('admin-views.vet-clinic.index', compact('clinics'));
    }

    public function create(): View
    {
        return view('admin-views.vet-clinic.form', $this->formData(null));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['logo'] = $request->hasFile('logo') ? $this->upload($request->file('logo')) : null;
        $data['cover'] = $request->hasFile('cover') ? $this->upload($request->file('cover')) : null;
        VetClinic::create($data);

        \Toastr::success(translate('messages.vet_clinic_saved'));
        return redirect()->route('admin.vet-clinic.index');
    }

    public function edit(VetClinic $clinic): View
    {
        return view('admin-views.vet-clinic.form', $this->formData($clinic));
    }

    public function update(Request $request, VetClinic $clinic): RedirectResponse
    {
        $data = $this->validated($request);
        foreach (['logo', 'cover'] as $field) {
            if ($request->hasFile($field)) {
                $old = $clinic->{$field};
                $data[$field] = $this->upload($request->file($field));
                // Only delete files this screen uploaded; migrated clinics
                // still point at Spots' `places/` files.
                if ($old && str_starts_with($old, self::IMAGE_DIR)) {
                    Helpers::check_and_delete(self::IMAGE_DIR, substr($old, strlen(self::IMAGE_DIR)));
                }
            }
        }
        $clinic->update($data);

        \Toastr::success(translate('messages.vet_clinic_saved'));
        return redirect()->route('admin.vet-clinic.index');
    }

    public function toggleStatus(VetClinic $clinic): RedirectResponse
    {
        $clinic->update(['is_active' => !$clinic->is_active]);
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function destroy(VetClinic $clinic): RedirectResponse
    {
        foreach (['logo', 'cover'] as $field) {
            $path = $clinic->{$field};
            if ($path && str_starts_with($path, self::IMAGE_DIR)) {
                Helpers::check_and_delete(self::IMAGE_DIR, substr($path, strlen(self::IMAGE_DIR)));
            }
        }
        $clinic->delete();
        \Toastr::success(translate('messages.vet_clinic_deleted'));
        return redirect()->route('admin.vet-clinic.index');
    }

    // ==================== Helpers ====================

    private function formData(?VetClinic $clinic): array
    {
        return [
            'clinic' => $clinic,
            'days' => VetClinic::DAYS,
            'speciesKeys' => VetClinic::SPECIES,
            'serviceKeys' => VetClinic::SERVICES,
            'maxVets' => VetClinic::MAX_VETS,
            'mapKey' => \App\Models\BusinessSetting::where('key', 'map_api_key')->value('value'),
        ];
    }

    private function validated(Request $request): array
    {
        $request->validate([
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
            'priority' => 'nullable|integer|min:0|max:1000',
        ]);

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
            'opening_hours' => $request->input('opening_hours') ?: null,
            'species' => $request->input('species', []) ?: null,
            'services' => $services ?: null,
            'service_prices' => $prices ?: null,
            'vets' => $vets ?: null,
            'priority' => (int) $request->input('priority', 0),
            'is_active' => $request->has('is_active'),
        ];
    }

    private function upload($file): ?string
    {
        $name = Helpers::upload(self::IMAGE_DIR, 'png', $file);
        return $name === 'def.png' ? null : self::IMAGE_DIR . $name;
    }
}
