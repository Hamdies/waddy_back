<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\VetClinic;
use App\Traits\VetClinicInput;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Waddy Vets: the standalone site where vets list their clinic for the
 * Pets app (/vets, /vets/apply). Each application is a hidden `pending`
 * clinic until an admin approves it in Admin › Vet clinics › Applications.
 *
 * The site keeps its own language (?lang=ar|en, remembered in the session)
 * so switching it does not change the main site's.
 */
class VetClinicApplicationController extends Controller
{
    use VetClinicInput;

    private const LOCALES = ['en', 'ar'];

    public function __construct()
    {
        $this->middleware(function (Request $request, $next) {
            if (in_array($request->query('lang'), self::LOCALES, true)) {
                session(['vet_locale' => $request->query('lang')]);
            }
            $locale = session('vet_locale')
                ?? (str_contains(strtolower((string) $request->header('Accept-Language')), 'ar') ? 'ar' : null)
                ?? (in_array(App::getLocale(), self::LOCALES, true) ? App::getLocale() : 'en');
            App::setLocale($locale);
            return $next($request);
        });
    }

    public function landing(): View
    {
        return view('vet-site.landing', [
            'supportPhone' => Helpers::get_settings('phone'),
            'supportEmail' => Helpers::get_settings('email_address'),
        ]);
    }

    public function create(): View
    {
        return view('vet-site.apply', [
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
            'agree' => 'accepted',
        ], $this->messages(), $this->attributes());

        // Prices are set by admins, not on the public form.
        $data = array_merge($this->clinicData($request), ['service_prices' => null]) + [
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'contact_role' => $request->contact_role,
            'status' => VetClinic::STATUS_PENDING,
            'is_active' => false,
            'priority' => 0,
        ];
        $data['logo'] = $request->hasFile('logo') ? $this->uploadClinicFile($request->file('logo')) : null;
        $data['cover'] = $request->hasFile('cover') ? $this->uploadClinicFile($request->file('cover')) : null;

        VetClinic::create($data);

        return redirect()->route('vet-clinic.thanks')->with('vet_clinic_name', $data['name']);
    }

    public function thanks(): View
    {
        return view('vet-site.thanks', ['clinicName' => session('vet_clinic_name')]);
    }

    /** There is no Arabic validation.php, so this form brings its own messages. */
    private function messages(): array
    {
        return [
            'required' => translate('messages.vs_err_required'),
            'accepted' => translate('messages.vs_err_agree'),
            'email' => translate('messages.vs_err_email'),
            'url' => translate('messages.vs_err_url'),
            'image' => translate('messages.vs_err_image'),
            'mimes' => translate('messages.vs_err_file_type'),
            'uploaded' => translate('messages.vs_err_upload'),
            'max' => translate('messages.vs_err_max'),
            'min' => translate('messages.vs_err_min'),
            'regex' => translate('messages.vs_err_phone'),
            'numeric' => translate('messages.vs_err_number'),
            'integer' => translate('messages.vs_err_number'),
            'between' => translate('messages.vs_err_location'),
            'species.required' => translate('messages.vet_apply_pick_species'),
            'latitude.required' => translate('messages.vet_apply_pick_location'),
            'longitude.required' => translate('messages.vet_apply_pick_location'),
        ];
    }

    private function attributes(): array
    {
        return [
            'name' => translate('messages.vs_f_name_en'),
            'name_ar' => translate('messages.vs_f_name_ar'),
            'description' => translate('messages.vs_f_about'),
            'phone' => translate('messages.vs_f_clinic_phone'),
            'website' => translate('messages.vs_f_website'),
            'logo' => translate('messages.vs_f_logo'),
            'cover' => translate('messages.vs_f_cover'),
            'contact_name' => translate('messages.vs_f_contact_name'),
            'contact_phone' => translate('messages.vs_f_contact_phone'),
            'contact_email' => translate('messages.vs_f_contact_email'),
            'contact_role' => translate('messages.vs_f_contact_role'),
            'agree' => translate('messages.vs_f_agree_short'),
        ];
    }
}
