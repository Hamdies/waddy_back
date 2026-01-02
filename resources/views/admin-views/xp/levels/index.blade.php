@extends('layouts.admin.app')

@section('title', translate('messages.xp_levels'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-star text-primary"></i>
            </span>
            <span>{{translate('messages.xp_levels')}}</span>
        </h1>
    </div>

    <!-- Content -->
    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <h5 class="card-title">{{translate('messages.levels_list')}} <span class="badge badge-soft-dark ml-2">{{count($levels)}}</span></h5>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{translate('messages.sl')}}</th>
                            <th>{{translate('messages.badge_image')}}</th>
                            <th>{{translate('messages.level')}}</th>
                            <th>{{translate('messages.name')}}</th>
                            <th>{{translate('messages.xp_required')}}</th>
                            <th>{{translate('messages.description')}}</th>
                            <th>{{translate('messages.status')}}</th>
                            <th class="text-center">{{translate('messages.action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($levels as $key => $level)
                        <tr>
                            <td>{{$key + 1}}</td>
                            <td>
                                @if($level->badge_image)
                                    <img src="{{asset('public/level/' . $level->badge_image)}}" 
                                         alt="{{$level->name}}" 
                                         class="rounded-circle"
                                         style="width: 40px; height: 40px; object-fit: cover;">
                                @else
                                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="tio-star text-muted"></i>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-primary">Level {{$level->level_number}}</span>
                            </td>
                            <td>
                                <span class="font-weight-bold">{{$level->name}}</span>
                            </td>
                            <td>
                                <span class="text-success font-weight-bold">{{number_format($level->xp_required)}} XP</span>
                            </td>
                            <td>
                                <span class="text-muted">{{Str::limit($level->description, 50)}}</span>
                            </td>
                            <td>
                                @if($level->status)
                                    <span class="badge badge-soft-success">{{translate('messages.active')}}</span>
                                @else
                                    <span class="badge badge-soft-danger">{{translate('messages.inactive')}}</span>
                                @endif
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                       href="{{route('admin.users.customer.xp.levels.edit', $level->id)}}"
                                       title="{{translate('messages.edit')}}">
                                        <i class="tio-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
