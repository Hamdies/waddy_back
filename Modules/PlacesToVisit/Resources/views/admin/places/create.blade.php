@extends('layouts.admin.app')

@section('title', translate('messages.add_place'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-add-circle"></i> {{ translate('messages.add_place') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <!-- Category & Image -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.category') }} <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-control" required>
                                <option value="">{{ translate('messages.select_category') }}</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.image') }}</label>
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
                                   placeholder="{{ translate('messages.enter_title_english') }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.title') }} (AR)</label>
                            <input type="text" name="title_ar" class="form-control" dir="rtl"
                                   placeholder="{{ translate('messages.enter_title_arabic') }}">
                        </div>
                    </div>
                </div>

                <!-- Descriptions (EN/AR) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.description') }} (EN)</label>
                            <textarea name="description_en" class="form-control" rows="4"
                                      placeholder="{{ translate('messages.enter_description_english') }}"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.description') }} (AR)</label>
                            <textarea name="description_ar" class="form-control" rows="4" dir="rtl"
                                      placeholder="{{ translate('messages.enter_description_arabic') }}"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Location -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.latitude') }} <span class="text-danger">*</span></label>
                            <input type="number" name="latitude" class="form-control" step="any"
                                   placeholder="e.g. 24.7136" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.longitude') }} <span class="text-danger">*</span></label>
                            <input type="number" name="longitude" class="form-control" step="any"
                                   placeholder="e.g. 46.6753" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.address') }}</label>
                            <input type="text" name="address" class="form-control"
                                   placeholder="{{ translate('messages.enter_address') }}">
                        </div>
                    </div>
                </div>

                <!-- Status -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label d-block">{{ translate('messages.status') }}</label>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="is_active" checked>
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                <span class="ml-2">{{ translate('messages.active') }}</span>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label d-block">{{ translate('messages.featured') }}</label>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="is_featured">
                                <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                <span class="ml-2">{{ translate('messages.mark_as_featured') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group text-right">
                    <a href="{{ route('admin.places.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
