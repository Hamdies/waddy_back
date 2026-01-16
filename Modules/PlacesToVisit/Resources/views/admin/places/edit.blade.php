@extends('layouts.admin.app')

@section('title', translate('messages.edit_place'))

@push('css_or_js')
<style>
    #map {
        height: 400px;
        width: 100%;
        border-radius: 8px;
    }
    .controls {
        margin-top: 10px;
        padding: 12px 16px;
        width: 320px;
        font-size: 15px;
        border: none;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0,0,0,.2);
        background-color: #fff;
    }
    .controls:focus {
        border-color: #4285f4;
        outline: none;
        box-shadow: 0 2px 6px rgba(66,133,244,.4);
    }
    .image-upload-wrapper {
        position: relative;
        display: inline-block;
    }
    .image-upload-wrapper img {
        width: 200px;
        height: 150px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px dashed #ddd;
    }
    .image-upload-wrapper:hover img {
        border-color: #4285f4;
    }
    .image-upload-wrapper input[type="file"] {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .image-upload-overlay {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(0,0,0,0.5);
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
    }
    .image-upload-wrapper:hover .image-upload-overlay {
        opacity: 1;
    }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-edit"></i> {{ translate('messages.edit_place') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.update', $place->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <!-- Category & Image -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.category') }} <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-control" required>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ $place->category_id == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.image') }}</label>
                            <div class="image-upload-wrapper">
                                @php
                                    $imageUrl = $place->image 
                                        ? asset('storage/app/public/places/' . $place->image) 
                                        : asset('public/assets/admin/img/upload-img.png');
                                @endphp
                                <img id="imagePreview" src="{{ $imageUrl }}" alt="Place Image">
                                <div class="image-upload-overlay">
                                    <i class="tio-edit"></i> {{ translate('messages.change') }}
                                </div>
                                <input type="file" name="image" id="imageInput" accept="image/*">
                            </div>
                            <small class="text-muted d-block mt-2">{{ translate('messages.click_to_upload_image') }}</small>
                        </div>
                    </div>
                </div>

                <!-- Titles (EN/AR) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.title') }} (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="title_en" class="form-control" 
                                   value="{{ $translations['en']->title ?? '' }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.title') }} (AR)</label>
                            <input type="text" name="title_ar" class="form-control" dir="rtl"
                                   value="{{ $translations['ar']->title ?? '' }}">
                        </div>
                    </div>
                </div>

                <!-- Descriptions (EN/AR) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.description') }} (EN)</label>
                            <textarea name="description_en" class="form-control" rows="4">{{ $translations['en']->description ?? '' }}</textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.description') }} (AR)</label>
                            <textarea name="description_ar" class="form-control" rows="4" dir="rtl">{{ $translations['ar']->description ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <!-- Location with Map -->
                <div class="card bg-light mb-3">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="tio-location-pin"></i> {{ translate('messages.location') }}
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="input-label" for="latitude">
                                        {{ translate('messages.latitude') }} <span class="text-danger">*</span>
                                        <span class="form-label-secondary" data-toggle="tooltip" data-placement="right"
                                              data-original-title="{{ translate('messages.click_on_map_to_select_location') }}">
                                            <img src="{{ asset('/public/assets/admin/img/info-circle.svg') }}" alt="info">
                                        </span>
                                    </label>
                                    <input type="text" name="latitude" id="latitude" class="form-control" 
                                           value="{{ $place->latitude }}" required readonly>
                                </div>
                                <div class="form-group">
                                    <label class="input-label" for="longitude">
                                        {{ translate('messages.longitude') }} <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" name="longitude" id="longitude" class="form-control" 
                                           value="{{ $place->longitude }}" required readonly>
                                </div>
                                <div class="form-group">
                                    <label class="input-label">{{ translate('messages.address') }}</label>
                                    <textarea name="address" id="address" class="form-control" rows="3">{{ $place->address }}</textarea>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <input id="pac-input" class="controls" type="text" 
                                       placeholder="{{ translate('messages.search_location') }}">
                                <div id="map"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label d-block">{{ translate('messages.status') }}</label>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="is_active" 
                                       {{ $place->is_active ? 'checked' : '' }}>
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                <span class="ml-2">{{ translate('messages.active') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label d-block">{{ translate('messages.featured') }}</label>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="is_featured"
                                       {{ $place->is_featured ? 'checked' : '' }}>
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                <span class="ml-2">{{ translate('messages.mark_as_featured') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group text-right">
                    <a href="{{ route('admin.places.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()?->value }}&libraries=places&callback=initMap&v=3.45.8" async defer></script>
<script>
    "use strict";
    
    // Image preview
    document.getElementById('imageInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('imagePreview').src = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    });

    // Google Maps
    let map;
    let marker;
    let infoWindow;
    
    function initMap() {
        const initialPosition = { 
            lat: {{ $place->latitude ?? 30.0444 }}, 
            lng: {{ $place->longitude ?? 31.2357 }} 
        };
        
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: initialPosition,
            mapTypeControl: true,
            streetViewControl: false,
            fullscreenControl: true,
        });
        
        // Add marker
        marker = new google.maps.Marker({
            position: initialPosition,
            map: map,
            draggable: true,
            animation: google.maps.Animation.DROP,
            title: "{{ $translations['en']->title ?? translate('messages.place_location') }}"
        });
        
        // Info window
        infoWindow = new google.maps.InfoWindow({
            content: "{{ translate('messages.drag_marker_or_click_map') }}"
        });
        infoWindow.open(map, marker);
        
        // Search box - add to map controls
        const input = document.getElementById("pac-input");
        const searchBox = new google.maps.places.SearchBox(input);
        map.controls[google.maps.ControlPosition.TOP_CENTER].push(input);
        
        // Bias SearchBox results towards current map's viewport
        map.addListener("bounds_changed", () => {
            searchBox.setBounds(map.getBounds());
        });
        
        // Listen for the event fired when the user selects a prediction
        searchBox.addListener("places_changed", () => {
            const places = searchBox.getPlaces();
            if (places.length === 0) return;
            
            const place = places[0];
            if (!place.geometry || !place.geometry.location) {
                console.log("Returned place contains no geometry");
                return;
            }
            
            // Update marker position
            marker.setPosition(place.geometry.location);
            map.setCenter(place.geometry.location);
            map.setZoom(15);
            
            // Update form fields
            document.getElementById('latitude').value = place.geometry.location.lat();
            document.getElementById('longitude').value = place.geometry.location.lng();
            
            // Update address if available
            if (place.formatted_address) {
                document.getElementById('address').value = place.formatted_address;
            }
            
            infoWindow.setContent(place.name || "{{ translate('messages.selected_location') }}");
            infoWindow.open(map, marker);
        });
        
        // Click on map to set location
        map.addListener("click", (e) => {
            updateMarkerPosition(e.latLng);
        });
        
        // Drag marker to set location
        marker.addListener("dragend", (e) => {
            updateMarkerPosition(e.latLng);
        });
    }
    
    function updateMarkerPosition(latLng) {
        marker.setPosition(latLng);
        document.getElementById('latitude').value = latLng.lat();
        document.getElementById('longitude').value = latLng.lng();
        
        // Reverse geocode to get address
        const geocoder = new google.maps.Geocoder();
        geocoder.geocode({ location: latLng }, (results, status) => {
            if (status === "OK" && results[0]) {
                document.getElementById('address').value = results[0].formatted_address;
                infoWindow.setContent(results[0].formatted_address);
            } else {
                infoWindow.setContent("Lat: " + latLng.lat().toFixed(6) + ", Lng: " + latLng.lng().toFixed(6));
            }
            infoWindow.open(map, marker);
        });
    }
</script>
@endpush
