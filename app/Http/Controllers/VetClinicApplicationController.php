<?php

namespace App\Http\Controllers;

use App\Models\BusinessSetting;
use App\Models\VetClinic;
use App\Traits\VetClinicInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public form where vets submit their clinic for the Pets app. Each
 * application is a hidden `pending` clinic until an admin approves it in
 * Admin › Vet clinics › Applications.
 */
class VetClinicApplicationController extends Controller
{
    use VetClinicInput;

    public function create(): View
    {
        return view('vet-clinic-application', [
            'days' => VetClinic::DAYS,
            'speciesKeys' => VetClinic::SPECIES,
            'serviceKeys' => VetClinic::SERVICES,
            'maxVets' => VetClinic::MAX_VETS,
            'mapKey' => BusinessSetting::where('key', 'map_api_key')->value('value'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Honeypot: real visitors never see or fill `website_url`.
        if ($request->filled('website_url')) {
            return redirect()->route('vet-clinic.thanks');
        }

        $request->validate($this->clinicRules() + [
            'phone' => 'required|string|max:30',
            'species' => 'required|array|min:1',
            'contact_name' => 'required|string|max:120',
            'contact_phone' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:10|max:30',
            'contact_email' => 'nullable|email|max:190',
            'contact_role' => 'nullable|string|max:80',
            'license_number' => 'required|string|max:80',
            'license_document' => 'required|file|mimes:jpeg,png,jpg,webp,pdf|max:5120',
            'agree' => 'accepted',
        ], [
            'latitude.required' => translate('messages.vet_apply_pick_location'),
            'species.required' => translate('messages.vet_apply_pick_species'),
            'license_document.required' => translate('messages.vet_apply_license_required'),
        ]);

        $data = $this->clinicData($request) + [
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'contact_role' => $request->contact_role,
            'license_number' => $request->license_number,
            'status' => VetClinic::STATUS_PENDING,
            'is_active' => false,
            'priority' => 0,
        ];
        $data['logo'] = $request->hasFile('logo') ? $this->uploadClinicFile($request->file('logo')) : null;
        $data['cover'] = $request->hasFile('cover') ? $this->uploadClinicFile($request->file('cover')) : null;
        $data['license_document'] = $this->uploadClinicFile($request->file('license_document'), 'license/');

        VetClinic::create($data);

        return redirect()->route('vet-clinic.thanks');
    }

    public function thanks(): View
    {
        return view('vet-clinic-application-thanks');
    }
}
