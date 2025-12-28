@extends('layouts.admin.app')

@section('title', translate('messages.edit_level'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-star text-primary"></i>
            </span>
            <span>{{translate('messages.edit_level')}} - {{$level->name}}</span>
        </h1>
    </div>

    <!-- Content -->
    <div class="card">
        <div class="card-body">
            <form action="{{route('admin.users.customer.xp.levels.update', $level->id)}}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.level_number')}}</label>
                            <input type="text" class="form-control" value="Level {{$level->level_number}}" disabled>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.level_name')}} <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="{{$level->name}}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.xp_required')}} <span class="text-danger">*</span></label>
                            <input type="number" name="xp_required" class="form-control" value="{{$level->xp_required}}" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.status')}}</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="status" value="1" {{$level->status ? 'checked' : ''}}>
                                <label class="form-check-label">{{translate('messages.active')}}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.description')}}</label>
                            <textarea name="description" class="form-control" rows="3">{{$level->description}}</textarea>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end">
                    <a href="{{route('admin.users.customer.xp.levels')}}" class="btn btn--reset">{{translate('messages.back')}}</a>
                    <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
