@extends('layouts.admin.app')

@section('title', translate('messages.add_category'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-add-circle"></i> {{ translate('messages.add_place_category') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.categories.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.name') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" 
                                   placeholder="{{ translate('messages.enter_category_name') }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.priority') }}</label>
                            <input type="number" name="priority" class="form-control" 
                                   placeholder="0" value="0" min="0">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.image') }}</label>
                            <div class="custom-file">
                                <input type="file" name="image" class="custom-file-input" 
                                       accept="image/*" id="categoryImage">
                                <label class="custom-file-label" for="categoryImage">
                                    {{ translate('messages.choose_file') }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label d-block">{{ translate('messages.status') }}</label>
                            <label class="toggle-switch toggle-switch-sm">
                                <input type="checkbox" class="toggle-switch-input" name="is_active" checked>
                                <span class="toggle-switch-label">
                                    <span class="toggle-switch-indicator"></span>
                                </span>
                                <span class="ml-2">{{ translate('messages.active') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-group text-right">
                    <a href="{{ route('admin.places.categories.index') }}" class="btn btn-secondary">
                        {{ translate('messages.cancel') }}
                    </a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
