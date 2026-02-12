@extends('layouts.admin.app')

@section('title', translate('messages.edit_offer'))

@push('css_or_js')
<style>
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
        top: 0; left: 0;
        width: 100%; height: 100%;
        opacity: 0;
        cursor: pointer;
    }
    .image-upload-overlay {
        position: absolute;
        top: 50%; left: 50%;
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
                    <i class="tio-edit"></i> {{ translate('messages.edit_offer') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.offers.update', $offer->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <!-- Place & Discount -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.place') }} <span class="text-danger">*</span></label>
                            <select name="place_id" class="form-control" required>
                                @foreach($places as $place)
                                <option value="{{ $place->id }}" {{ $offer->place_id == $place->id ? 'selected' : '' }}>
                                    {{ $place->title }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.discount_percent') }}</label>
                            <div class="input-group">
                                <input type="number" name="discount_percent" class="form-control"
                                       min="0" max="100" step="0.01"
                                       value="{{ $offer->discount_percent }}">
                                <div class="input-group-append">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Title & Description -->
                <div class="form-group">
                    <label class="input-label">{{ translate('messages.title') }} <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" value="{{ $offer->title }}" required>
                </div>
                <div class="form-group">
                    <label class="input-label">{{ translate('messages.description') }}</label>
                    <textarea name="description" class="form-control" rows="3">{{ $offer->description }}</textarea>
                </div>

                <!-- Date Range -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.start_date') }}</label>
                            <input type="date" name="start_date" class="form-control"
                                   value="{{ $offer->start_date ? \Carbon\Carbon::parse($offer->start_date)->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.end_date') }}</label>
                            <input type="date" name="end_date" class="form-control"
                                   value="{{ $offer->end_date ? \Carbon\Carbon::parse($offer->end_date)->format('Y-m-d') : '' }}">
                        </div>
                    </div>
                </div>

                <!-- Image -->
                <div class="form-group">
                    <label class="input-label">{{ translate('messages.image') }}</label>
                    <div class="image-upload-wrapper">
                        @php
                            $imageUrl = $offer->image
                                ? asset('storage/place_offers/' . $offer->image)
                                : asset('public/assets/admin/img/upload-img.png');
                        @endphp
                        <img id="imagePreview" src="{{ $imageUrl }}" alt="Offer Image">
                        <div class="image-upload-overlay">
                            <i class="tio-edit"></i> {{ translate('messages.change') }}
                        </div>
                        <input type="file" name="image" id="imageInput" accept="image/*">
                    </div>
                </div>

                <!-- Status -->
                <div class="form-group">
                    <label class="input-label d-block">{{ translate('messages.status') }}</label>
                    <label class="toggle-switch toggle-switch-sm">
                        <input type="checkbox" class="toggle-switch-input" name="is_active"
                               {{ $offer->is_active ? 'checked' : '' }}>
                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                        <span class="ml-2">{{ translate('messages.active') }}</span>
                    </label>
                </div>

                <div class="form-group text-right">
                    <a href="{{ route('admin.places.offers.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script>
    "use strict";
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
</script>
@endpush
