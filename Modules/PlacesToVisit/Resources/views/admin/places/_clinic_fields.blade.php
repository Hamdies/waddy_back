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
        <div class="d-flex flex-wrap">
            @foreach($clinicServices as $key)
                <label class="d-flex align-items-center mr-4 mb-2">
                    <input type="checkbox" name="clinic_services[]" value="{{ $key }}" class="mr-2"
                           {{ in_array($key, old('clinic_services', $place?->clinic_services ?? []), true) ? 'checked' : '' }}>
                    {{ translate('messages.clinic_service_' . $key) }}
                </label>
            @endforeach
        </div>
    </div>
</div>
