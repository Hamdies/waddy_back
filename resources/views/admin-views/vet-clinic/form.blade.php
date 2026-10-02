@extends('layouts.admin.app')

@section('title', $clinic ? translate('messages.edit_vet_clinic') : translate('messages.add_vet_clinic'))

@push('css_or_js')
<style>
    #map { height: 360px; width: 100%; border-radius: 8px; }
    .controls { margin-top: 10px; padding: 10px 14px; width: 300px; border: none; border-radius: 8px;
                box-shadow: 0 2px 6px rgba(0,0,0,.2); background: #fff; }
    .vc-upload { position: relative; display: block; }
    .vc-upload img { width: 100%; object-fit: cover; border-radius: 8px; border: 2px dashed #ddd; }
    .vc-upload input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <i class="tio-hospital"></i>
            {{ $clinic ? translate('messages.edit_vet_clinic') : translate('messages.add_vet_clinic') }}
        </h1>
    </div>

    <form action="{{ $clinic ? route('admin.vet-clinic.update', $clinic->id) : route('admin.vet-clinic.store') }}"
          method="POST" enctype="multipart/form-data">
        @csrf
        @if($clinic)
            @method('PUT')
        @endif

        <div class="card mb-3">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <label class="input-label">{{ translate('messages.logo') }}</label>
                        <div class="vc-upload">
                            <img id="logoPreview" style="height: 150px;"
                                 src="{{ $clinic?->logoUrl() ?? asset('public/assets/admin/img/upload-img.png') }}">
                            <input type="file" name="logo" accept="image/*" onchange="vcPreview(this, 'logoPreview')">
                        </div>
                    </div>
                    <div class="col-md-9">
                        <label class="input-label">{{ translate('messages.cover_image') }}</label>
                        <div class="vc-upload">
                            <img id="coverPreview" style="height: 150px;"
                                 src="{{ $clinic?->coverUrl() ?? asset('public/assets/admin/img/upload-img.png') }}">
                            <input type="file" name="cover" accept="image/*" onchange="vcPreview(this, 'coverPreview')">
                        </div>
                    </div>
                </div>

                <div class="row mt-3">
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{ translate('messages.name') }} (EN) <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required value="{{ old('name', $clinic?->name) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{ translate('messages.name') }} (AR)</label>
                        <input type="text" name="name_ar" class="form-control" dir="rtl" value="{{ old('name_ar', $clinic?->name_ar) }}">
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{ translate('messages.description') }} (EN)</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $clinic?->description) }}</textarea>
                    </div>
                    <div class="col-md-6 form-group">
                        <label class="input-label">{{ translate('messages.description') }} (AR)</label>
                        <textarea name="description_ar" class="form-control" rows="3" dir="rtl">{{ old('description_ar', $clinic?->description_ar) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0"><i class="tio-location-pin"></i> {{ translate('messages.location') }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.latitude') }} <span class="text-danger">*</span></label>
                            <input type="text" name="latitude" id="latitude" class="form-control" required readonly
                                   value="{{ old('latitude', $clinic?->latitude) }}">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.longitude') }} <span class="text-danger">*</span></label>
                            <input type="text" name="longitude" id="longitude" class="form-control" required readonly
                                   value="{{ old('longitude', $clinic?->longitude) }}">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.address') }}</label>
                            <textarea name="address" id="address" class="form-control" rows="3">{{ old('address', $clinic?->address) }}</textarea>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <input id="pac-input" class="controls" type="text" placeholder="{{ translate('messages.search_location') }}">
                        <div id="map"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0"><i class="tio-call"></i> {{ translate('messages.contact_info') }}</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{ translate('messages.phone') }}</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $clinic?->phone) }}"
                               placeholder="01xxxxxxxxx">
                        <small class="text-muted">{{ translate('messages.vet_clinic_phone_hint') }}</small>
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{ translate('messages.website') }}</label>
                        <input type="url" name="website" class="form-control" value="{{ old('website', $clinic?->website) }}">
                    </div>
                    <div class="col-md-4 form-group">
                        <label class="input-label">{{ translate('messages.instagram') }}</label>
                        <input type="text" name="instagram" class="form-control" value="{{ old('instagram', $clinic?->instagram) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0"><i class="tio-time"></i> {{ translate('messages.opening_hours') }}</h5></div>
            <div class="card-body">
                @foreach($days as $day)
                <div class="row align-items-center mb-2">
                    <div class="col-md-2"><strong>{{ translate('messages.' . $day) }}</strong></div>
                    <div class="col-md-3">
                        <input type="time" name="opening_hours[{{ $day }}][open]" class="form-control form-control-sm"
                               value="{{ old('opening_hours.' . $day . '.open', $clinic?->opening_hours[$day]['open'] ?? '') }}">
                    </div>
                    <div class="col-md-1 text-center">–</div>
                    <div class="col-md-3">
                        <input type="time" name="opening_hours[{{ $day }}][close]" class="form-control form-control-sm"
                               value="{{ old('opening_hours.' . $day . '.close', $clinic?->opening_hours[$day]['close'] ?? '') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="d-flex align-items-center mb-0">
                            <input type="checkbox" name="opening_hours[{{ $day }}][closed]" value="1" class="mr-2"
                                   {{ \App\Models\VetClinic::isClosedFlag($clinic?->opening_hours[$day]['closed'] ?? false) ? 'checked' : '' }}>
                            {{ translate('messages.closed') }}
                        </label>
                    </div>
                </div>
                @endforeach
                <small class="text-muted">{{ translate('messages.vet_clinic_hours_hint') }}</small>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0"><i class="tio-pet"></i> {{ translate('messages.clinic_treats') }}</h5></div>
            <div class="card-body d-flex flex-wrap">
                @foreach($speciesKeys as $key)
                <label class="d-flex align-items-center mr-4 mb-2">
                    <input type="checkbox" name="species[]" value="{{ $key }}" class="mr-2"
                           {{ in_array($key, old('species', $clinic?->species ?? []), true) ? 'checked' : '' }}>
                    {{ translate('messages.clinic_species_' . $key) }}
                </label>
                @endforeach
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="tio-medical"></i> {{ translate('messages.clinic_services') }}</h5>
                <small class="text-muted">{{ translate('messages.clinic_price_hint') }}</small>
            </div>
            <div class="card-body">
                <div class="row">
                    @foreach($serviceKeys as $key)
                    <div class="col-md-6 d-flex align-items-center mb-2">
                        <label class="d-flex align-items-center mb-0 mr-2" style="min-width: 170px;">
                            <input type="checkbox" name="services[]" value="{{ $key }}" class="mr-2"
                                   {{ in_array($key, old('services', $clinic?->services ?? []), true) ? 'checked' : '' }}>
                            {{ translate('messages.clinic_service_' . $key) }}
                        </label>
                        <input type="number" min="0" step="1" name="service_prices[{{ $key }}]"
                               class="form-control form-control-sm" style="max-width: 140px;"
                               placeholder="{{ translate('messages.clinic_price_from') }}"
                               value="{{ old('service_prices.' . $key, $clinic?->service_prices[$key] ?? '') }}">
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h5 class="card-title mb-0"><i class="tio-user"></i> {{ translate('messages.clinic_vets') }}</h5></div>
            <div class="card-body">
                @for($i = 0; $i < $maxVets; $i++)
                <div class="row mb-2">
                    <div class="col-md-5">
                        <input type="text" name="vets[{{ $i }}][name]" class="form-control form-control-sm"
                               placeholder="{{ translate('messages.clinic_vet_name') }}"
                               value="{{ old('vets.' . $i . '.name', $clinic?->vets[$i]['name'] ?? '') }}">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="vets[{{ $i }}][role]" class="form-control form-control-sm"
                               placeholder="{{ translate('messages.clinic_vet_role') }}"
                               value="{{ old('vets.' . $i . '.role', $clinic?->vets[$i]['role'] ?? '') }}">
                    </div>
                    <div class="col-md-2">
                        <input type="number" min="0" max="70" name="vets[{{ $i }}][years]" class="form-control form-control-sm"
                               placeholder="{{ translate('messages.clinic_vet_years') }}"
                               value="{{ old('vets.' . $i . '.years', $clinic?->vets[$i]['years'] ?? '') }}">
                    </div>
                </div>
                @endfor
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body row">
                <div class="col-md-6">
                    <label class="input-label d-block">{{ translate('messages.status') }}</label>
                    <label class="toggle-switch toggle-switch-sm">
                        <input type="checkbox" class="toggle-switch-input" name="is_active"
                               {{ ($clinic?->is_active ?? true) ? 'checked' : '' }}>
                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                        <span class="ml-2">{{ translate('messages.active') }}</span>
                    </label>
                </div>
                <div class="col-md-6 form-group">
                    <label class="input-label">{{ translate('messages.priority') }}</label>
                    <input type="number" min="0" max="1000" name="priority" class="form-control"
                           value="{{ old('priority', $clinic?->priority ?? 0) }}">
                </div>
            </div>
        </div>

        <div class="text-right mb-5">
            <a href="{{ route('admin.vet-clinic.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
            <button type="submit" class="btn btn--primary">{{ translate('messages.save') }}</button>
        </div>
    </form>
</div>
@endsection

@push('script_2')
<script src="https://maps.googleapis.com/maps/api/js?key={{ $mapKey }}&libraries=places&callback=initMap&v=3.45.8" async defer></script>
<script>
    "use strict";

    function vcPreview(input, id) {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = e => document.getElementById(id).src = e.target.result;
        reader.readAsDataURL(file);
    }

    let map, marker, infoWindow;

    function initMap() {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const hasPoint = latInput.value && lngInput.value;
        // Maadi when adding a clinic; the clinic itself when editing.
        const start = hasPoint
            ? { lat: parseFloat(latInput.value), lng: parseFloat(lngInput.value) }
            : { lat: 29.9602, lng: 31.2569 };

        map = new google.maps.Map(document.getElementById('map'), {
            zoom: hasPoint ? 16 : 13, center: start, mapTypeControl: false, streetViewControl: false,
        });
        marker = new google.maps.Marker({ position: start, map: map, draggable: true, visible: !!hasPoint });
        infoWindow = new google.maps.InfoWindow();

        const input = document.getElementById('pac-input');
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        map.addListener('bounds_changed', () => searchBox.setBounds(map.getBounds()));
        searchBox.addListener('places_changed', () => {
            const place = (searchBox.getPlaces() || [])[0];
            if (!place || !place.geometry || !place.geometry.location) return;
            setPoint(place.geometry.location);
            map.setCenter(place.geometry.location);
            map.setZoom(16);
            if (place.formatted_address) document.getElementById('address').value = place.formatted_address;
        });
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
@endpush
