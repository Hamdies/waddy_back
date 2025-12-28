@extends('layouts.admin.app')

@section('title', translate('messages.xp_users'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-user text-primary"></i>
            </span>
            <span>{{translate('messages.customer_xp_report')}}</span>
        </h1>
    </div>

    <!-- Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form action="" method="GET">
                <div class="row align-items-end">
                    <div class="col-md-8">
                        <label class="input-label">{{translate('messages.search_customer')}}</label>
                        <input type="text" name="search" class="form-control" placeholder="{{translate('messages.search_by_name_phone_email')}}" value="{{$search ?? ''}}">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn--primary w-100">{{translate('messages.search')}}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Content -->
    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <h5 class="card-title">{{translate('messages.customers_with_xp')}} <span class="badge badge-soft-dark ml-2">{{$users->total()}}</span></h5>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{translate('messages.sl')}}</th>
                            <th>{{translate('messages.customer')}}</th>
                            <th>{{translate('messages.phone')}}</th>
                            <th>{{translate('messages.level')}}</th>
                            <th>{{translate('messages.total_xp')}}</th>
                            <th>{{translate('messages.orders')}}</th>
                            <th class="text-center">{{translate('messages.action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $key => $user)
                        <tr>
                            <td>{{$users->firstItem() + $key}}</td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="{{$user->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg')}}" class="avatar avatar-sm mr-3" alt="">
                                    <div>
                                        <span class="d-block font-weight-bold">{{$user->f_name}} {{$user->l_name}}</span>
                                        <span class="text-muted">{{$user->email}}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{$user->phone}}</td>
                            <td>
                                <span class="badge badge-primary">Level {{$user->level}}</span>
                            </td>
                            <td>
                                <span class="text-success font-weight-bold">{{number_format($user->total_xp)}} XP</span>
                            </td>
                            <td>{{$user->order_count}}</td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                       href="{{route('admin.users.customer.xp.users.detail', $user->id)}}"
                                       title="{{translate('messages.view')}}">
                                        <i class="tio-invisible"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="page-area mt-3">
                {!! $users->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection
