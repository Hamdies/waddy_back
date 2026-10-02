{{-- Vet clinic details (PET-19). Only read for places in a "Vet clinics"
     category; leave empty for Spots places. No @php here on purpose: see the
     Blade @php trap. $place is null on the create form. --}}
<div class="card bg-light mb-3">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="tio-pet"></i> {{ translate('messages.vet_clinic_details') }}
        </h5>
        <small class="text-muted">{{ translate('messages.vet_clinic_details_hint') }}</small>
    </div>
    <div class="card-body">
        <label class="input-label">{{ translate('messages.clinic_treats') }}</label>
        <div class="d-flex flex-wrap mb-3">
            @foreach($clinicSpecies as $key)
                <label class="d-flex align-items-center mr-4 mb-2">
                    <input type="checkbox" name="clinic_species[]" value="{{ $key }}" class="mr-2"
                           {{ in_array($key, old('clinic_species', $place?->clinic_species ?? []), true) ? 'checked' : '' }}>
                    {{ translate('messages.clinic_species_' . $key) }}
                </label>
            @endforeach
        </div>

        <label class="input-label">{{ translate('messages.clinic_services') }}</label>
        <small class="text-muted d-block mb-2">{{ translate('messages.clinic_price_hint') }}</small>
        <div class="row">
            @foreach($clinicServices as $key)
                <div class="col-md-6 d-flex align-items-center mb-2">
                    <label class="d-flex align-items-center mb-0 mr-2" style="min-width: 170px;">
                        <input type="checkbox" name="clinic_services[]" value="{{ $key }}" class="mr-2"
                               {{ in_array($key, old('clinic_services', $place?->clinic_services ?? []), true) ? 'checked' : '' }}>
                        {{ translate('messages.clinic_service_' . $key) }}
                    </label>
                    <input type="number" min="0" step="1" name="clinic_service_prices[{{ $key }}]"
                           class="form-control form-control-sm" style="max-width: 140px;"
                           placeholder="{{ translate('messages.clinic_price_from') }}"
                           value="{{ old('clinic_service_prices.' . $key, $place?->clinic_service_prices[$key] ?? '') }}">
                </div>
            @endforeach
        </div>

        <label class="input-label mt-3">{{ translate('messages.clinic_vets') }}</label>
        @for($i = 0; $i < \Modules\PlacesToVisit\Entities\Place::CLINIC_MAX_VETS; $i++)
            <div class="row mb-2">
                <div class="col-md-5">
                    <input type="text" name="clinic_vets[{{ $i }}][name]" class="form-control form-control-sm"
                           placeholder="{{ translate('messages.clinic_vet_name') }}"
                           value="{{ old('clinic_vets.' . $i . '.name', $place?->clinic_vets[$i]['name'] ?? '') }}">
                </div>
                <div class="col-md-5">
                    <input type="text" name="clinic_vets[{{ $i }}][role]" class="form-control form-control-sm"
                           placeholder="{{ translate('messages.clinic_vet_role') }}"
                           value="{{ old('clinic_vets.' . $i . '.role', $place?->clinic_vets[$i]['role'] ?? '') }}">
                </div>
                <div class="col-md-2">
                    <input type="number" min="0" max="70" name="clinic_vets[{{ $i }}][years]" class="form-control form-control-sm"
                           placeholder="{{ translate('messages.clinic_vet_years') }}"
                           value="{{ old('clinic_vets.' . $i . '.years', $place?->clinic_vets[$i]['years'] ?? '') }}">
                </div>
            </div>
        @endfor
    </div>
</div>
