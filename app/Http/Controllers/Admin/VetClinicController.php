<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\VetClinic;
use App\Traits\VetClinicInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin › Pets › Vet clinics. Clinics are the Pets module's own records,
 * apart from Spots (PET-04, revised 10-02).
 *
 * Applications from the public form (/vet-clinic/apply) land here as
 * `pending` clinics; approving one puts it in the app.
 */
class VetClinicController extends Controller
{
    use VetClinicInput;

    private const IMAGE_DIR = 'vet-clinic/';

    private const TABS = [VetClinic::STATUS_APPROVED, VetClinic::STATUS_PENDING, VetClinic::STATUS_REJECTED];

    public function index(Request $request): View
    {
        $tab = in_array($request->status, self::TABS, true) ? $request->status : VetClinic::STATUS_APPROVED;
        $counts = VetClinic::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $clinics = VetClinic::status($tab)
            ->when($request->search, function ($q, $search) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('name_ar', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%"));
            })
            // Applications: newest first. Live clinics: the app's order.
            ->when($tab === VetClinic::STATUS_APPROVED,
                fn ($q) => $q->orderByDesc('priority')->orderBy('name'),
                fn ($q) => $q->latest())
            ->paginate(config('default_pagination'));

        return view('admin-views.vet-clinic.index', compact('clinics', 'tab', 'counts'));
    }

    /** One application, read-only, with approve / reject. */
    public function show(VetClinic $clinic): View
    {
        return view('admin-views.vet-clinic.show', ['clinic' => $clinic, 'days' => VetClinic::DAYS]);
    }

    public function approve(VetClinic $clinic): RedirectResponse
    {
        $clinic->update([
            'status' => VetClinic::STATUS_APPROVED,
            'is_active' => true,
            'reviewed_at' => now(),
        ]);
        \Toastr::success(translate('messages.vet_application_approved'));
        return redirect()->route('admin.vet-clinic.index', ['status' => VetClinic::STATUS_PENDING]);
    }

    public function reject(Request $request, VetClinic $clinic): RedirectResponse
    {
        $request->validate(['review_note' => 'nullable|string|max:1000']);
        $clinic->update([
            'status' => VetClinic::STATUS_REJECTED,
            'is_active' => false,
            'review_note' => $request->review_note,
            'reviewed_at' => now(),
        ]);
        \Toastr::success(translate('messages.vet_application_rejected'));
        return redirect()->route('admin.vet-clinic.index', ['status' => VetClinic::STATUS_PENDING]);
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
        return $clinic->status === VetClinic::STATUS_APPROVED
            ? redirect()->route('admin.vet-clinic.index')
            : redirect()->route('admin.vet-clinic.show', $clinic->id);
    }

    public function toggleStatus(VetClinic $clinic): RedirectResponse
    {
        $clinic->update(['is_active' => !$clinic->is_active]);
        \Toastr::success(translate('messages.status_updated'));
        return back();
    }

    public function destroy(VetClinic $clinic): RedirectResponse
    {
        foreach (['logo', 'cover', 'license_document'] as $field) {
            $path = $clinic->{$field};
            if ($path && str_starts_with($path, self::IMAGE_DIR)) {
                Helpers::check_and_delete(dirname($path) . '/', basename($path));
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
        $request->validate($this->clinicRules() + [
            'priority' => 'nullable|integer|min:0|max:1000',
        ]);

        return $this->clinicData($request) + [
            'priority' => (int) $request->input('priority', 0),
            'is_active' => $request->has('is_active'),
        ];
    }

    private function upload($file): ?string
    {
        return $this->uploadClinicFile($file);
    }
}
