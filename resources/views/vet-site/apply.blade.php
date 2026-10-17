@extends('vet-site.layout')

@section('title', translate('messages.vs_apply_title'))
@section('footer_class', 'is-apply')

@php
    $speciesIcons = ['cat' => 'cat', 'dog' => 'dog', 'bird' => 'bird', 'fish' => 'fish', 'small' => 'rabbit'];
    $serviceIcons = [
        'emergency_24h' => 'siren', 'home_visit' => 'house', 'vaccination' => 'syringe', 'surgery' => 'heart-pulse',
        'dental' => 'smile', 'xray' => 'scan-line', 'lab' => 'microscope', 'grooming' => 'scissors',
        'boarding' => 'bed-double', 'pharmacy' => 'pill',
    ];
    $oldVets = array_values(array_filter((array) old('vets', []), fn ($v) => trim((string) ($v['name'] ?? '')) !== ''));
    if (!$oldVets) $oldVets = [['name' => '', 'role' => '']];
    $linksOpen = old('website') || old('instagram') || $errors->has('website');
@endphp

@section('content')
@include('vet-site.partials._topbar')

<main class="wrap">
    <div class="apply-head">
        <h1>{{ translate('messages.vs_apply_title') }}</h1>
        <p>{{ translate('messages.vs_apply_lede') }}</p>
    </div>

    <div class="apply-grid">
        <form id="vcaForm" action="{{ route('vet-clinic.store') }}" method="POST" enctype="multipart/form-data" novalidate>
            @csrf
            <div class="hp" aria-hidden="true"><input type="text" name="website_url" tabindex="-1" autocomplete="off"></div>

            <div class="progress">
                <div class="progress-bar" aria-hidden="true">@for($i = 0; $i < 5; $i++)<span></span>@endfor</div>
                <div class="progress-meta"><span id="stepCount"></span><strong id="stepName"></strong></div>
            </div>

            @if($errors->any())
                <div class="alert" role="alert">@include('vet-site.partials._icon', ['name' => 'zap']) <span>{{ translate('messages.vs_errors_title') }} {{ translate('messages.vs_files_again') }}</span></div>
            @endif

            {{-- 1. Clinic --}}
            <section class="wstep" data-name="{{ translate('messages.vs_step1_title') }}">
                <h2>{{ translate('messages.vs_step1_title') }}</h2>
                <p class="wstep-sub">{{ translate('messages.vs_step1_sub') }}</p>

                <div class="field">
                    <label class="flabel" for="f_name">{{ translate('messages.vs_f_name_en') }} <span class="req">*</span></label>
                    <input class="input @error('name') is-invalid @enderror" id="f_name" name="name" required maxlength="200" dir="auto" autocomplete="organization" value="{{ old('name') }}" placeholder="{{ translate('messages.vs_ph_name_en') }}">
                    @error('name')<p class="err">{{ $message }}</p>@enderror
                </div>
                <div class="field">
                    <label class="flabel" for="f_name_ar">{{ translate('messages.vs_f_name_ar') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></label>
                    <input class="input" id="f_name_ar" name="name_ar" maxlength="200" dir="rtl" value="{{ old('name_ar') }}" placeholder="{{ translate('messages.vs_ph_name_ar') }}">
                </div>
                <div class="field">
                    <label class="flabel" for="f_phone">{{ translate('messages.vs_f_clinic_phone') }} <span class="req">*</span></label>
                    <div class="prefix">@include('vet-site.partials._icon', ['name' => 'phone'])
                        <input class="input @error('phone') is-invalid @enderror" id="f_phone" name="phone" type="tel" inputmode="tel" required maxlength="30" dir="ltr" value="{{ old('phone') }}" placeholder="01xxxxxxxxx">
                    </div>
                    <span class="hint">{{ translate('messages.vet_apply_whatsapp_hint') }}</span>
                    @error('phone')<p class="err">{{ $message }}</p>@enderror
                </div>

                <span class="flabel">{{ translate('messages.vs_photos') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></span>
                <div class="uploads">
                    <label class="drop">
                        <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" aria-label="{{ translate('messages.vs_f_logo') }}">
                        <img alt="">
                        <span class="drop-inner">@include('vet-site.partials._icon', ['name' => 'camera']){{ translate('messages.vs_f_logo') }}</span>
                        <button type="button" class="drop-clear" aria-label="{{ translate('messages.vs_remove') }}">@include('vet-site.partials._icon', ['name' => 'x'])</button>
                    </label>
                    <label class="drop">
                        <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" aria-label="{{ translate('messages.vs_f_cover') }}">
                        <img alt="">
                        <span class="drop-inner">@include('vet-site.partials._icon', ['name' => 'image']){{ translate('messages.vs_f_cover') }}</span>
                        <button type="button" class="drop-clear" aria-label="{{ translate('messages.vs_remove') }}">@include('vet-site.partials._icon', ['name' => 'x'])</button>
                    </label>
                </div>
                @error('logo')<p class="err">{{ $message }}</p>@enderror
                @error('cover')<p class="err">{{ $message }}</p>@enderror

                <div class="field">
                    <label class="flabel" for="f_desc">{{ translate('messages.vs_f_about') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></label>
                    <textarea class="textarea" id="f_desc" name="description" maxlength="2000" dir="auto" placeholder="{{ translate('messages.vs_ph_about') }}">{{ old('description') }}</textarea>
                </div>

                <details class="more" @if($linksOpen) open @endif>
                    <summary>@include('vet-site.partials._icon', ['name' => 'plus']) {{ translate('messages.vs_more_links') }}</summary>
                    <div class="more-body">
                        <div class="field">
                            <label class="flabel" for="f_web">{{ translate('messages.vs_f_website') }}</label>
                            <input class="input @error('website') is-invalid @enderror" id="f_web" name="website" type="url" inputmode="url" dir="ltr" value="{{ old('website') }}" placeholder="https://">
                            @error('website')<p class="err">{{ $message }}</p>@enderror
                        </div>
                        <div class="field">
                            <label class="flabel" for="f_ig">Instagram</label>
                            <input class="input" id="f_ig" name="instagram" dir="ltr" value="{{ old('instagram') }}" placeholder="@yourclinic">
                        </div>
                    </div>
                </details>
            </section>

            {{-- 2. Location --}}
            <section class="wstep" data-name="{{ translate('messages.vs_step2_title') }}">
                <h2>{{ translate('messages.vs_step2_title') }}</h2>
                <p class="wstep-sub">{{ translate('messages.vs_step2_sub') }}</p>
                <div class="map-tools">
                    <div class="prefix">@include('vet-site.partials._icon', ['name' => 'search'])
                        <input class="input" id="pac-input" type="text" placeholder="{{ translate('messages.vs_map_search') }}" autocomplete="off">
                    </div>
                    <button type="button" class="btn btn--outline" id="useLocation">
                        @include('vet-site.partials._icon', ['name' => 'navigation']) <span>{{ translate('messages.vs_map_here') }}</span>
                    </button>
                </div>
                <div class="map-wrap">
                    <div id="vcaMap"></div>
                    <div class="map-fallback" id="mapFallback">{{ translate('messages.vs_map_failed') }}</div>
                </div>
                <p class="map-pin-note" id="pinNote" data-set="{{ translate('messages.vs_map_pin_set') }}" data-unset="{{ translate('messages.vs_map_pin_unset') }}">
                    @include('vet-site.partials._icon', ['name' => 'map-pin']) <span></span>
                </p>
                <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}" data-required-msg="{{ translate('messages.vet_apply_pick_location') }}">
                <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                @error('latitude')<p class="err">{{ $message }}</p>@enderror
                <div class="field" style="margin-top: 18px;">
                    <label class="flabel" for="address">{{ translate('messages.vs_f_address') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></label>
                    <textarea class="textarea" id="address" name="address" rows="2" maxlength="500" style="min-height: 72px;" dir="auto" placeholder="{{ translate('messages.vs_ph_address') }}">{{ old('address') }}</textarea>
                </div>
            </section>

            {{-- 3. Hours --}}
            <section class="wstep" data-name="{{ translate('messages.vs_step3_title') }}">
                <h2>{{ translate('messages.vs_step3_title') }}</h2>
                <p class="wstep-sub">{{ translate('messages.vs_step3_sub') }}</p>
                <div class="presets">
                    <button type="button" class="pill" data-fill="10:00,22:00">{{ translate('messages.vs_hours_daily') }}</button>
                    <button type="button" class="pill" data-fill="00:00,23:59">{{ translate('messages.vet_apply_open_24h') }}</button>
                    <button type="button" class="pill" data-copy-first>{{ translate('messages.vs_hours_copy') }}</button>
                </div>
                <div class="hours">
                    @foreach($days as $day)
                        @php($closed = \App\Models\VetClinic::isClosedFlag(old('opening_hours.' . $day . '.closed')))
                        <div class="hrow {{ $closed ? 'is-closed' : '' }}">
                            <span class="hrow-day">{{ translate('messages.' . $day) }}</span>
                            <label class="switch">
                                <span class="switch-text" data-on="{{ translate('messages.vs_open') }}" data-off="{{ translate('messages.vet_apply_closed') }}">{{ $closed ? translate('messages.vet_apply_closed') : translate('messages.vs_open') }}</span>
                                <input type="checkbox" class="open-toggle" {{ $closed ? '' : 'checked' }} aria-label="{{ translate('messages.' . $day) }}: {{ translate('messages.vs_open') }}">
                                <span class="switch-track"></span>
                            </label>
                            <input type="hidden" name="opening_hours[{{ $day }}][closed]" value="{{ $closed ? '1' : '' }}" class="closed-flag">
                            <div class="hrow-times">
                                <input class="input" type="time" name="opening_hours[{{ $day }}][open]" value="{{ old('opening_hours.' . $day . '.open') }}" aria-label="{{ translate('messages.' . $day) }}: {{ translate('messages.vs_opens') }}">
                                <span aria-hidden="true">–</span>
                                <input class="input" type="time" name="opening_hours[{{ $day }}][close]" value="{{ old('opening_hours.' . $day . '.close') }}" aria-label="{{ translate('messages.' . $day) }}: {{ translate('messages.vs_closes') }}">
                            </div>
                        </div>
                    @endforeach
                </div>
                <span class="hint" style="margin-top: 10px;">{{ translate('messages.vet_clinic_hours_hint') }}</span>
            </section>

            {{-- 4. Pets and services --}}
            <section class="wstep" data-name="{{ translate('messages.vs_step4_title') }}">
                <h2>{{ translate('messages.vs_step4_title') }}</h2>
                <p class="wstep-sub">{{ translate('messages.vs_step4_sub') }}</p>
                <span class="flabel">{{ translate('messages.vet_apply_treats') }} <span class="req">*</span></span>
                <div class="choices" id="speciesGroup" data-required-msg="{{ translate('messages.vet_apply_pick_species') }}">
                    @foreach($speciesKeys as $key)
                        <label class="choice">
                            <input type="checkbox" name="species[]" value="{{ $key }}" {{ in_array($key, old('species', []), true) ? 'checked' : '' }}
                                   data-label="{{ translate('messages.clinic_species_' . $key) }}" data-icon="{{ $speciesIcons[$key] ?? 'paw-print' }}">
                            <span>@include('vet-site.partials._icon', ['name' => $speciesIcons[$key] ?? 'paw-print']){{ translate('messages.clinic_species_' . $key) }}</span>
                        </label>
                    @endforeach
                </div>
                @error('species')<p class="err">{{ $message }}</p>@enderror

                <span class="flabel" style="margin-top: 26px;">{{ translate('messages.vs_services') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></span>
                <div class="choices">
                    @foreach($serviceKeys as $key)
                        <label class="choice">
                            <input type="checkbox" name="services[]" value="{{ $key }}" {{ in_array($key, old('services', []), true) ? 'checked' : '' }}
                                   data-label="{{ translate('messages.clinic_service_' . $key) }}" data-icon="{{ $serviceIcons[$key] ?? 'sparkles' }}">
                            <span>@include('vet-site.partials._icon', ['name' => $serviceIcons[$key] ?? 'sparkles']){{ translate('messages.clinic_service_' . $key) }}</span>
                        </label>
                    @endforeach
                </div>
            </section>

            {{-- 5. Team and owner --}}
            <section class="wstep" data-name="{{ translate('messages.vs_step5_title') }}" id="lastStep">
                <h2>{{ translate('messages.vs_step5_title') }}</h2>
                <p class="wstep-sub">{{ translate('messages.vs_step5_sub') }}</p>

                <div class="subhead" style="margin-top: 0;">{{ translate('messages.vs_owner_title') }}</div>
                <p class="wstep-sub">{{ translate('messages.vs_owner_sub') }}</p>
                <div class="grid2">
                    <div class="field">
                        <label class="flabel" for="c_name">{{ translate('messages.vs_f_contact_name') }} <span class="req">*</span></label>
                        <input class="input @error('contact_name') is-invalid @enderror" id="c_name" name="contact_name" required maxlength="120" autocomplete="name" dir="auto" value="{{ old('contact_name') }}">
                        @error('contact_name')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label class="flabel" for="c_phone">{{ translate('messages.vs_f_contact_phone') }} <span class="req">*</span></label>
                        <div class="prefix">@include('vet-site.partials._icon', ['name' => 'phone'])
                            <input class="input @error('contact_phone') is-invalid @enderror" id="c_phone" name="contact_phone" type="tel" inputmode="tel" required minlength="10" maxlength="30" autocomplete="tel" dir="ltr" value="{{ old('contact_phone') }}" placeholder="01xxxxxxxxx">
                        </div>
                        @error('contact_phone')<p class="err">{{ $message }}</p>@enderror
                    </div>
                    <div class="field">
                        <label class="flabel" for="c_role">{{ translate('messages.vs_f_contact_role') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></label>
                        <input class="input" id="c_role" name="contact_role" maxlength="80" dir="auto" value="{{ old('contact_role') }}" placeholder="{{ translate('messages.vs_ph_role') }}">
                    </div>
                    <div class="field">
                        <label class="flabel" for="c_email">{{ translate('messages.vs_f_contact_email') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></label>
                        <input class="input @error('contact_email') is-invalid @enderror" id="c_email" name="contact_email" type="email" inputmode="email" maxlength="190" autocomplete="email" dir="ltr" value="{{ old('contact_email') }}">
                        @error('contact_email')<p class="err">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="subhead">{{ translate('messages.vs_team_title') }} <span class="opt">{{ translate('messages.vs_optional') }}</span></div>
                <p class="wstep-sub">{{ translate('messages.vs_team_sub') }}</p>
                <div class="vets-list" id="vetsList" data-max="{{ $maxVets }}">
                    @foreach($oldVets as $i => $vet)
                        @include('vet-site.partials._vet-row', ['i' => $i, 'vet' => $vet])
                    @endforeach
                </div>
                <button type="button" class="pill add-vet" id="addVet">@include('vet-site.partials._icon', ['name' => 'plus']) {{ translate('messages.vs_add_vet') }}</button>
                <template id="vetTemplate">@include('vet-site.partials._vet-row', ['i' => '__i__', 'vet' => []])</template>

                <div id="previewSlot" style="margin-top: 28px;"></div>

                <label class="agree" style="margin-top: 20px;">
                    <input type="checkbox" name="agree" value="1" required {{ old('agree') ? 'checked' : '' }} data-required-msg="{{ translate('messages.vs_err_agree') }}">
                    <span>{{ translate('messages.vet_apply_agree') }}</span>
                </label>
                @error('agree')<p class="err">{{ $message }}</p>@enderror
            </section>

            <div class="wnav">
                <button type="button" class="btn btn--outline" id="prevBtn" hidden aria-label="{{ translate('messages.vs_back') }}">
                    @include('vet-site.partials._icon', ['name' => 'arrow-left', 'class' => 'vi-flip']) <span>{{ translate('messages.vs_back') }}</span>
                </button>
                <span class="wnav-spacer"></span>
                <button type="button" class="btn" id="nextBtn">
                    {{ translate('messages.vs_next') }} @include('vet-site.partials._icon', ['name' => 'arrow-right', 'class' => 'vi-flip'])
                </button>
                <button type="submit" class="btn" id="submitBtn" hidden>{{ translate('messages.vet_apply_submit') }}</button>
            </div>
        </form>

        <aside id="previewHome">
            <div class="preview" id="preview">
                <p class="preview-title">@include('vet-site.partials._icon', ['name' => 'eye']) {{ translate('messages.vs_preview_label') }}</p>
                @include('vet-site.partials._phone', ['live' => true])
            </div>
        </aside>
    </div>
</main>

{{-- Icons the live preview's tags draw with. --}}
<div hidden id="pvIcons">
    @foreach(array_unique(array_merge(array_values($speciesIcons), array_values($serviceIcons))) as $icon)
        <span data-icon="{{ $icon }}">@include('vet-site.partials._icon', ['name' => $icon])</span>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
    window.VET = {
        sample: @json(translate('messages.vs_sample_clinic')),
        sampleAddr: @json(translate('messages.vs_sample_addr')),
        openNow: @json(translate('messages.vs_open_now')),
        closedNow: @json(translate('messages.vs_closed_now')),
        vetsMore: @json(translate('messages.vs_vets_count')),
        step: @json(translate('messages.vs_step_of')),
        required: @json(translate('messages.vs_err_required_short')),
        email: @json(translate('messages.vs_err_email_short')),
        url: @json(translate('messages.vs_err_url_short')),
        phone: @json(translate('messages.vs_err_phone_short')),
        tooBig: @json(translate('messages.vs_err_file_big')),
        locating: @json(translate('messages.vs_map_locating')),
        locateFailed: @json(translate('messages.vs_map_locate_failed')),
        lang: @json(app()->getLocale()),
        hasErrors: @json($errors->any()),
        timezone: 'Africa/Cairo',
    };
</script>
<script src="{{ asset('public/assets/vet/apply.js') }}?v={{ @filemtime(public_path('assets/vet/apply.js')) }}"></script>
@if($mapKey)
    <script src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}&libraries=places&callback=vetInitMap&language={{ app()->getLocale() }}&region=EG&v=3.55" async defer></script>
@else
    <script>document.addEventListener('DOMContentLoaded', () => window.vetMapUnavailable && window.vetMapUnavailable());</script>
@endif
@endpush
