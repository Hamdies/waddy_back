@extends('layouts.landing.app')
@section('title', translate('messages.vet_apply_title'))

@push('css_or_js')
<style>
    .vca { --vca-ink: #1f2a37; --vca-muted: #6b7280; --vca-line: #e5e7eb; --vca-soft: #f6f7f9;
           --vca-accent: #0e9f6e; --vca-accent-soft: #e7f6f0; --vca-danger: #dc2626;
           color: var(--vca-ink); padding: 48px 0 72px; background: var(--vca-soft); }
    .vca-wrap { max-width: 880px; margin: 0 auto; padding: 0 16px; }
    .vca-hero { text-align: center; margin-bottom: 32px; }
    .vca-hero .vca-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 999px;
                           background: var(--vca-accent-soft); color: var(--vca-accent); font-weight: 600; font-size: 13px; }
    .vca-hero h1 { font-size: clamp(26px, 4vw, 36px); font-weight: 700; margin: 14px 0 10px; }
    .vca-hero p { color: var(--vca-muted); max-width: 620px; margin: 0 auto; font-size: 16px; line-height: 1.6; }

    .vca-card { background: #fff; border: 1px solid var(--vca-line); border-radius: 14px; padding: 24px; margin-bottom: 18px; }
    .vca-card-head { display: flex; align-items: center; gap: 12px; margin-bottom: 18px; }
    .vca-step { flex: none; width: 30px; height: 30px; border-radius: 50%; background: var(--vca-accent); color: #fff;
                display: grid; place-items: center; font-weight: 700; font-size: 14px; }
    .vca-card-head h2 { font-size: 18px; font-weight: 700; margin: 0; }
    .vca-card-head small { display: block; color: var(--vca-muted); font-size: 13px; font-weight: 400; margin-top: 2px; }

    .vca label.vca-label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 6px; }
    .vca .vca-req { color: var(--vca-danger); }
    .vca .vca-opt { color: var(--vca-muted); font-weight: 400; font-size: 12px; }
    .vca .form-control { border-radius: 10px; border-color: var(--vca-line); min-height: 44px; }
    .vca .form-control:focus { border-color: var(--vca-accent); box-shadow: 0 0 0 3px var(--vca-accent-soft); }
    .vca .is-invalid { border-color: var(--vca-danger) !important; }
    .vca .vca-hint { color: var(--vca-muted); font-size: 12px; margin-top: 4px; display: block; }
    .vca .vca-err { color: var(--vca-danger); font-size: 13px; margin-top: 4px; display: block; }

    .vca-upload { position: relative; border: 2px dashed var(--vca-line); border-radius: 12px; overflow: hidden;
                  background: var(--vca-soft); display: grid; place-items: center; cursor: pointer; transition: border-color .15s; }
    .vca-upload:hover { border-color: var(--vca-accent); }
    .vca-upload img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; display: none; }
    .vca-upload.has-file img { display: block; }
    .vca-upload span { color: var(--vca-muted); font-size: 13px; text-align: center; padding: 8px; }
    .vca-upload input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

    #vcaMap { height: 340px; width: 100%; border-radius: 12px; border: 1px solid var(--vca-line); }
    #pac-input { margin-top: 10px; padding: 10px 14px; width: min(320px, 80%); border: none; border-radius: 10px;
                 box-shadow: 0 2px 8px rgba(0,0,0,.18); font-size: 14px; }

    .vca-hours-row { display: grid; grid-template-columns: 110px 1fr 1fr auto; gap: 10px; align-items: center;
                     padding: 8px 0; border-bottom: 1px solid var(--vca-soft); }
    .vca-hours-row:last-child { border-bottom: 0; }
    .vca-hours-row.is-closed input[type="time"] { opacity: .35; pointer-events: none; }
    .vca-quick { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
    .vca-quick button { border: 1px solid var(--vca-line); background: #fff; border-radius: 999px; padding: 6px 14px; font-size: 13px; }
    .vca-quick button:hover { border-color: var(--vca-accent); color: var(--vca-accent); }

    .vca-chips { display: flex; flex-wrap: wrap; gap: 10px; }
    .vca-chip input { position: absolute; opacity: 0; }
    .vca-chip span { display: inline-block; padding: 9px 18px; border: 1px solid var(--vca-line); border-radius: 999px;
                     cursor: pointer; font-weight: 500; user-select: none; transition: all .15s; }
    .vca-chip input:checked + span { background: var(--vca-accent); border-color: var(--vca-accent); color: #fff; }
    .vca-chip input:focus-visible + span { box-shadow: 0 0 0 3px var(--vca-accent-soft); }

    .vca-service { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--vca-line);
                   border-radius: 10px; margin-bottom: 10px; }
    .vca-service label { flex: 1; display: flex; align-items: center; gap: 10px; margin: 0; cursor: pointer; font-weight: 500; }
    .vca-service input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--vca-accent); }
    .vca-service input[type="number"] { max-width: 130px; min-height: 38px; }
    .vca-service:not(.is-on) input[type="number"] { opacity: .4; }

    .vca-vet { display: grid; grid-template-columns: 2fr 2fr 1fr; gap: 10px; margin-bottom: 10px; }

    .vca-agree { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; }
    .vca-agree input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--vca-accent); flex: none; }
    .vca-submit { width: 100%; min-height: 52px; border: 0; border-radius: 12px; background: var(--vca-accent); color: #fff;
                  font-weight: 700; font-size: 16px; margin-top: 18px; transition: filter .15s; }
    .vca-submit:hover { filter: brightness(.95); }
    .vca-submit:disabled { opacity: .6; }
    .vca-alert { background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: 12px; padding: 14px 18px; margin-bottom: 18px; font-size: 14px; }
    .vca-alert ul { margin: 6px 0 0; padding-inline-start: 18px; }
    .vca-hp { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }

    @media (max-width: 575px) {
        .vca { padding-top: 28px; }
        .vca-card { padding: 18px 16px; }
        .vca-hours-row { grid-template-columns: 1fr 1fr; }
        .vca-hours-row strong { grid-column: 1 / -1; }
        .vca-vet { grid-template-columns: 1fr 1fr; }
        .vca-vet input:first-child { grid-column: 1 / -1; }
        .vca-service { flex-wrap: wrap; }
        .vca-service input[type="number"] { max-width: none; flex: 1 0 100%; }
    }
</style>
@endpush

@section('content')
<section class="vca">
    <div class="vca-wrap">
        <div class="vca-hero">
            <span class="vca-badge">🐾 {{ translate('messages.vet_apply_badge') }}</span>
            <h1>{{ translate('messages.vet_apply_title') }}</h1>
            <p>{{ translate('messages.vet_apply_subtitle') }}</p>
        </div>

        @if ($errors->any())
            <div class="vca-alert" role="alert">
                <strong>{{ translate('messages.vet_apply_fix_errors') }}</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <div class="mt-2">{{ translate('messages.vet_apply_files_again') }}</div>
            </div>
        @endif

        <form action="{{ route('vet-clinic.store') }}" method="POST" enctype="multipart/form-data" id="vcaForm" novalidate>
            @csrf
            <div class="vca-hp" aria-hidden="true">
                <input type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            {{-- 1. Clinic --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">1</span>
                    <h2>{{ translate('messages.vet_apply_step_clinic') }}</h2>
                </div>
                <div class="row">
                    <div class="col-sm-4 mb-3">
                        <label class="vca-label">{{ translate('messages.logo') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <div class="vca-upload" style="aspect-ratio: 1;">
                            <img alt="">
                            <span>{{ translate('messages.vet_apply_tap_upload') }}</span>
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                    <div class="col-sm-8 mb-3">
                        <label class="vca-label">{{ translate('messages.cover_image') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <div class="vca-upload" style="aspect-ratio: 2 / 1;">
                            <img alt="">
                            <span>{{ translate('messages.vet_apply_tap_upload') }}<br>{{ translate('messages.vet_apply_upload_hint') }}</span>
                            <input type="file" name="cover" accept="image/jpeg,image/png,image/webp">
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_clinic_name_en') }} <span class="vca-req">*</span></label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required maxlength="200" value="{{ old('name') }}">
                        @error('name')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_clinic_name_ar') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <input type="text" name="name_ar" class="form-control" dir="rtl" maxlength="200" value="{{ old('name_ar') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_about_en') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <textarea name="description" class="form-control" rows="3" maxlength="2000">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_about_ar') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <textarea name="description_ar" class="form-control" rows="3" dir="rtl" maxlength="2000">{{ old('description_ar') }}</textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_clinic_phone') }} <span class="vca-req">*</span></label>
                        <input type="tel" name="phone" class="form-control @error('phone') is-invalid @enderror" required maxlength="30" placeholder="01xxxxxxxxx" value="{{ old('phone') }}">
                        <span class="vca-hint">{{ translate('messages.vet_apply_whatsapp_hint') }}</span>
                        @error('phone')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_website') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <input type="url" name="website" class="form-control @error('website') is-invalid @enderror" placeholder="https://" value="{{ old('website') }}">
                        @error('website')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_instagram') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <input type="text" name="instagram" class="form-control" placeholder="@yourclinic" value="{{ old('instagram') }}">
                    </div>
                </div>
            </div>

            {{-- 2. Location --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">2</span>
                    <h2>{{ translate('messages.vet_apply_step_location') }} <small>{{ translate('messages.vet_apply_location_hint') }}</small></h2>
                </div>
                <input id="pac-input" type="text" placeholder="{{ translate('messages.search_location') }}">
                <div id="vcaMap"></div>
                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                @error('latitude')<span class="vca-err">{{ $message }}</span>@enderror
                <div class="mt-3">
                    <label class="vca-label">{{ translate('messages.address') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                    <textarea name="address" id="address" class="form-control" rows="2" maxlength="500">{{ old('address') }}</textarea>
                </div>
            </div>

            {{-- 3. Hours --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">3</span>
                    <h2>{{ translate('messages.vet_apply_step_hours') }} <small>{{ translate('messages.vet_clinic_hours_hint') }}</small></h2>
                </div>
                <div class="vca-quick">
                    <button type="button" data-fill="10:00,22:00">{{ translate('messages.vet_apply_fill_every_day') }} 10:00 – 22:00</button>
                    <button type="button" data-fill="00:00,23:59">{{ translate('messages.vet_apply_open_24h') }}</button>
                </div>
                @foreach($days as $day)
                    @php $closed = \App\Models\VetClinic::isClosedFlag(old('opening_hours.' . $day . '.closed')); @endphp
                    <div class="vca-hours-row {{ $closed ? 'is-closed' : '' }}">
                        <strong>{{ translate('messages.' . $day) }}</strong>
                        <input type="time" name="opening_hours[{{ $day }}][open]" class="form-control" value="{{ old('opening_hours.' . $day . '.open') }}" aria-label="{{ translate('messages.' . $day) }} open">
                        <input type="time" name="opening_hours[{{ $day }}][close]" class="form-control" value="{{ old('opening_hours.' . $day . '.close') }}" aria-label="{{ translate('messages.' . $day) }} close">
                        <label class="vca-chip mb-0">
                            <input type="checkbox" name="opening_hours[{{ $day }}][closed]" value="1" {{ $closed ? 'checked' : '' }}>
                            <span>{{ translate('messages.vet_apply_closed') }}</span>
                        </label>
                    </div>
                @endforeach
            </div>

            {{-- 4. Pets and services --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">4</span>
                    <h2>{{ translate('messages.vet_apply_step_care') }}</h2>
                </div>
                <label class="vca-label">{{ translate('messages.vet_apply_treats') }} <span class="vca-req">*</span></label>
                <div class="vca-chips mb-1">
                    @foreach($speciesKeys as $key)
                        <label class="vca-chip mb-0">
                            <input type="checkbox" name="species[]" value="{{ $key }}" {{ in_array($key, old('species', []), true) ? 'checked' : '' }}>
                            <span>{{ translate('messages.clinic_species_' . $key) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('species')<span class="vca-err">{{ $message }}</span>@enderror

                <label class="vca-label mt-4">{{ translate('messages.vet_apply_services') }}</label>
                <span class="vca-hint mb-2" style="margin-top: -2px;">{{ translate('messages.vet_apply_services_hint') }}</span>
                <div class="row">
                    @foreach($serviceKeys as $key)
                        @php $on = in_array($key, old('services', []), true); @endphp
                        <div class="col-md-6">
                            <div class="vca-service {{ $on ? 'is-on' : '' }}">
                                <label>
                                    <input type="checkbox" name="services[]" value="{{ $key }}" {{ $on ? 'checked' : '' }}>
                                    {{ translate('messages.clinic_service_' . $key) }}
                                </label>
                                <input type="number" min="0" step="1" name="service_prices[{{ $key }}]" class="form-control"
                                       placeholder="{{ translate('messages.clinic_price_from') }}" value="{{ old('service_prices.' . $key) }}">
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- 5. Vets --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">5</span>
                    <h2>{{ translate('messages.vet_apply_step_team') }} <small>{{ translate('messages.vet_apply_team_hint') }}</small></h2>
                </div>
                @for($i = 0; $i < $maxVets; $i++)
                    <div class="vca-vet">
                        <input type="text" name="vets[{{ $i }}][name]" class="form-control" maxlength="80"
                               placeholder="{{ translate('messages.clinic_vet_name') }}" value="{{ old('vets.' . $i . '.name') }}">
                        <input type="text" name="vets[{{ $i }}][role]" class="form-control" maxlength="80"
                               placeholder="{{ translate('messages.clinic_vet_role') }}" value="{{ old('vets.' . $i . '.role') }}">
                        <input type="number" min="0" max="70" name="vets[{{ $i }}][years]" class="form-control"
                               placeholder="{{ translate('messages.clinic_vet_years') }}" value="{{ old('vets.' . $i . '.years') }}">
                    </div>
                @endfor
            </div>

            {{-- 6. Contact and license --}}
            <div class="vca-card">
                <div class="vca-card-head">
                    <span class="vca-step">6</span>
                    <h2>{{ translate('messages.vet_apply_step_contact') }} <small>{{ translate('messages.vet_apply_contact_hint') }}</small></h2>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_contact_name') }} <span class="vca-req">*</span></label>
                        <input type="text" name="contact_name" class="form-control @error('contact_name') is-invalid @enderror" required maxlength="120" value="{{ old('contact_name') }}">
                        @error('contact_name')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_contact_role') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <input type="text" name="contact_role" class="form-control" maxlength="80" value="{{ old('contact_role') }}">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_contact_phone') }} <span class="vca-req">*</span></label>
                        <input type="tel" name="contact_phone" class="form-control @error('contact_phone') is-invalid @enderror" required maxlength="30" placeholder="01xxxxxxxxx" value="{{ old('contact_phone') }}">
                        @error('contact_phone')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_contact_email') }} <span class="vca-opt">({{ translate('messages.optional') }})</span></label>
                        <input type="email" name="contact_email" class="form-control @error('contact_email') is-invalid @enderror" maxlength="190" value="{{ old('contact_email') }}">
                        @error('contact_email')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_license_number') }} <span class="vca-req">*</span></label>
                        <input type="text" name="license_number" class="form-control @error('license_number') is-invalid @enderror" required maxlength="80" value="{{ old('license_number') }}">
                        @error('license_number')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="vca-label">{{ translate('messages.vet_apply_license_document') }} <span class="vca-req">*</span></label>
                        <input type="file" name="license_document" class="form-control @error('license_document') is-invalid @enderror" required accept="image/jpeg,image/png,image/webp,application/pdf" style="padding-top: 9px;">
                        <span class="vca-hint">{{ translate('messages.vet_apply_license_hint') }}</span>
                        @error('license_document')<span class="vca-err">{{ $message }}</span>@enderror
                    </div>
                </div>

                <label class="vca-agree mt-2">
                    <input type="checkbox" name="agree" value="1" required {{ old('agree') ? 'checked' : '' }}>
                    <span>{{ translate('messages.vet_apply_agree') }}</span>
                </label>
                @error('agree')<span class="vca-err">{{ $message }}</span>@enderror

                <button type="submit" class="vca-submit" id="vcaSubmit">{{ translate('messages.vet_apply_submit') }}</button>
            </div>
        </form>
    </div>
</section>
@endsection

@push('script_2')
<script>
    "use strict";

    // Image previews inside the dashed boxes.
    document.querySelectorAll('.vca-upload input[type="file"]').forEach(input => {
        input.addEventListener('change', () => {
            const box = input.closest('.vca-upload');
            const file = input.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => { box.querySelector('img').src = e.target.result; box.classList.add('has-file'); };
            reader.readAsDataURL(file);
        });
    });

    // Hours: "Closed" greys out the row; quick-fill buttons set every open day.
    document.querySelectorAll('.vca-hours-row input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', () => cb.closest('.vca-hours-row').classList.toggle('is-closed', cb.checked));
    });
    document.querySelectorAll('.vca-quick button').forEach(btn => {
        btn.addEventListener('click', () => {
            const [open, close] = btn.dataset.fill.split(',');
            document.querySelectorAll('.vca-hours-row').forEach(row => {
                const times = row.querySelectorAll('input[type="time"]');
                const cb = row.querySelector('input[type="checkbox"]');
                times[0].value = open; times[1].value = close;
                cb.checked = false; row.classList.remove('is-closed');
            });
        });
    });

    // Services: typing a price ticks the service.
    document.querySelectorAll('.vca-service').forEach(row => {
        const cb = row.querySelector('input[type="checkbox"]');
        const price = row.querySelector('input[type="number"]');
        cb.addEventListener('change', () => row.classList.toggle('is-on', cb.checked));
        price.addEventListener('input', () => { if (price.value) { cb.checked = true; row.classList.add('is-on'); } });
    });

    document.getElementById('vcaForm').addEventListener('submit', e => {
        if (!document.getElementById('latitude').value) {
            e.preventDefault();
            toastr.error(@json(translate('messages.vet_apply_pick_location')));
            document.getElementById('vcaMap').scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        if (!document.querySelector('input[name="species[]"]:checked')) {
            e.preventDefault();
            toastr.error(@json(translate('messages.vet_apply_pick_species')));
            return;
        }
        const form = e.target;
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        document.getElementById('vcaSubmit').disabled = true;
    });

    let map, marker;

    function initMap() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const hasPoint = latInput.value && lngInput.value;
        const start = hasPoint
            ? { lat: parseFloat(latInput.value), lng: parseFloat(lngInput.value) }
            : { lat: 30.0444, lng: 31.2357 };

        map = new google.maps.Map(document.getElementById('vcaMap'), {
            zoom: hasPoint ? 16 : 12, center: start, mapTypeControl: false, streetViewControl: false,
        });
        marker = new google.maps.Marker({ position: start, map: map, draggable: true, visible: !!hasPoint });

        const input = document.getElementById('pac-input');
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        map.addListener('bounds_changed', () => searchBox.setBounds(map.getBounds()));
        searchBox.addListener('places_changed', () => {
            const place = (searchBox.getPlaces() || [])[0];
            if (!place || !place.geometry || !place.geometry.location) return;
            setPoint(place.geometry.location);
            map.setCenter(place.geometry.location);
            map.setZoom(17);
            if (place.formatted_address) document.getElementById('address').value = place.formatted_address;
        });
        // Enter in the search box must not submit the whole form.
        input.addEventListener('keydown', e => { if (e.key === 'Enter') e.preventDefault(); });
        map.addListener('click', e => setPoint(e.latLng, true));
        marker.addListener('dragend', e => setPoint(e.latLng, true));
    }

    function setPoint(latLng, geocode) {
        marker.setVisible(true);
        marker.setPosition(latLng);
        document.getElementById('latitude').value = latLng.lat();
        document.getElementById('longitude').value = latLng.lng();
        if (!geocode) return;
        new google.maps.Geocoder().geocode({ location: latLng }, (results, status) => {
            if (status === 'OK' && results[0]) document.getElementById('address').value = results[0].formatted_address;
        });
    }
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}&libraries=places&callback=initMap&v=3.45.8" async defer></script>
@endpush
