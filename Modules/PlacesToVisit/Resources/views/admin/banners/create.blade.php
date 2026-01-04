@extends('layouts.admin.app')

@section('title', translate('messages.add_banner'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-add-circle"></i> {{ translate('messages.add_banner') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.banners.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <!-- Titles (EN/AR) -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.title') }} (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" 
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
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="{{ translate('messages.enter_description_english') }}"></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.description') }} (AR)</label>
                            <textarea name="description_ar" class="form-control" rows="3" dir="rtl"
                                      placeholder="{{ translate('messages.enter_description_arabic') }}"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Image & Type -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.image') }} <span class="text-danger">*</span></label>
                            <div class="custom-file">
                                <input type="file" name="image" class="custom-file-input" accept="image/*" required>
                                <label class="custom-file-label">{{ translate('messages.choose_file') }}</label>
                            </div>
                            <small class="text-muted">{{ translate('messages.recommended_size') }}: 1920x600px</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.banner_type') }} <span class="text-danger">*</span></label>
                            <select name="type" class="form-control" id="banner_type" required>
                                <option value="default">{{ translate('messages.default') }} ({{ translate('messages.no_link') }})</option>
                                <option value="category">{{ translate('messages.category') }}</option>
                                <option value="place">{{ translate('messages.place') }}</option>
                                <option value="external">{{ translate('messages.external_link') }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Dynamic Data/Link -->
                <div class="row">
                    <div class="col-md-6" id="category_select" style="display: none;">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.select_category') }}</label>
                            <select name="data" class="form-control category-data">
                                <option value="">{{ translate('messages.select') }}</option>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6" id="place_select" style="display: none;">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.select_place') }}</label>
                            <select name="data" class="form-control place-data">
                                <option value="">{{ translate('messages.select') }}</option>
                                @foreach($places as $place)
                                <option value="{{ $place->id }}">{{ $place->title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6" id="external_link" style="display: none;">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.external_url') }}</label>
                            <input type="url" name="external_link" class="form-control" 
                                   placeholder="https://example.com">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.zone') }}</label>
                            <select name="zone_id" class="form-control">
                                <option value="">{{ translate('messages.all_zones') }}</option>
                                @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Priority & Dates -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.priority') }}</label>
                            <input type="number" name="priority" class="form-control" value="0" min="0">
                            <small class="text-muted">{{ translate('messages.higher_priority_shown_first') }}</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.start_date') }}</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.end_date') }}</label>
                            <input type="date" name="end_date" class="form-control">
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
                    <a href="{{ route('admin.places.banners.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script')
<script>
    $(document).ready(function() {
        function toggleDataFields() {
            var type = $('#banner_type').val();
            $('#category_select, #place_select, #external_link').hide();
            $('.category-data, .place-data').prop('disabled', true);
            
            if (type === 'category') {
                $('#category_select').show();
                $('.category-data').prop('disabled', false);
            } else if (type === 'place') {
                $('#place_select').show();
                $('.place-data').prop('disabled', false);
            } else if (type === 'external') {
                $('#external_link').show();
            }
        }
        
        $('#banner_type').on('change', toggleDataFields);
        toggleDataFields();
    });
</script>
@endpush
