@extends('layouts.admin.app')

@section('title', translate('messages.edit_zone'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-edit"></i> {{ translate('messages.edit_zone') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.zones.update', $zone->id) }}" method="POST">
                @csrf
                @method('PUT')

                <!-- English -->
                <h5 class="mb-3">English</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.name') }} (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   value="{{ $zone->name }}"
                                   placeholder="{{ translate('messages.enter_zone_name') }}" required>
                            <small class="text-muted">{{ translate('messages.internal_name_for_reference') }}</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.display_name') }} (EN) <span class="text-danger">*</span></label>
                            <input type="text" name="display_name" class="form-control" 
                                   value="{{ $zone->display_name }}"
                                   placeholder="{{ translate('messages.enter_display_name') }}" required>
                            <small class="text-muted">{{ translate('messages.shown_to_users_in_app') }}</small>
                        </div>
                    </div>
                </div>

                <hr>

                <!-- Arabic -->
                <h5 class="mb-3">العربية</h5>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.name') }} (AR)</label>
                            <input type="text" name="name_ar" class="form-control" dir="rtl"
                                   value="{{ $zone->name_ar }}"
                                   placeholder="{{ translate('messages.enter_zone_name_arabic') }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.display_name') }} (AR)</label>
                            <input type="text" name="display_name_ar" class="form-control" dir="rtl"
                                   value="{{ $zone->display_name_ar }}"
                                   placeholder="{{ translate('messages.enter_display_name_arabic') }}">
                        </div>
                    </div>
                </div>

                <div class="form-group text-right mt-3">
                    <a href="{{ route('admin.places.zones.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
