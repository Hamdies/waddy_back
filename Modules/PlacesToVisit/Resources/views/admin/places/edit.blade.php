@extends('layouts.admin.app')

@section('title', translate('messages.edit_place'))

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
                            @if($place->image)
                            <div class="mb-2">
                                <img src="{{ asset('storage/app/public/places/' . $place->image) }}" 
                                     class="rounded" width="100">
                            </div>
                            @endif
                            <div class="custom-file">
                                <input type="file" name="image" class="custom-file-input" accept="image/*">
                                <label class="custom-file-label">{{ translate('messages.choose_file') }}</label>
                            </div>
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

                <!-- Location -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.latitude') }} <span class="text-danger">*</span></label>
                            <input type="number" name="latitude" class="form-control" step="any"
                                   value="{{ $place->latitude }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.longitude') }} <span class="text-danger">*</span></label>
                            <input type="number" name="longitude" class="form-control" step="any"
                                   value="{{ $place->longitude }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.address') }}</label>
                            <input type="text" name="address" class="form-control"
                                   value="{{ $place->address }}">
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
