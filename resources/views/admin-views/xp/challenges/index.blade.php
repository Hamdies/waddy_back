@extends('layouts.admin.app')

@section('title', translate('messages.xp_challenges'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-flag-outlined text-primary"></i>
            </span>
            <span>{{translate('messages.xp_challenges')}}</span>
        </h1>
    </div>

    <!-- Add Challenge Form -->
    <div class="card mb-3">
        <div class="card-header">
            <h5>{{translate('messages.add_new_challenge')}}</h5>
        </div>
        <div class="card-body">
            <form action="{{route('admin.users.customer.xp.challenges.store')}}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.title')}} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required placeholder="e.g. Order Lunch Today">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.frequency')}} <span class="text-danger">*</span></label>
                            <select name="frequency" class="form-control" required>
                                <option value="daily">{{translate('messages.daily')}}</option>
                                <option value="weekly">{{translate('messages.weekly')}}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.description')}} <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="2" required placeholder="e.g. Complete any order today"></textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.challenge_type')}} <span class="text-danger">*</span></label>
                            <select name="challenge_type" class="form-control" required>
                                <option value="complete_order">{{translate('messages.complete_order')}}</option>
                                <option value="min_order_amount">{{translate('messages.minimum_order_amount')}}</option>
                                <option value="multiple_orders">{{translate('messages.multiple_orders')}}</option>
                                <option value="new_store">{{translate('messages.order_from_new_store')}}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.xp_reward')}} <span class="text-danger">*</span></label>
                            <input type="number" name="xp_reward" class="form-control" required min="1" placeholder="e.g. 20">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.time_limit_hours')}} <span class="text-danger">*</span></label>
                            <input type="number" name="time_limit_hours" class="form-control" required min="1" value="24" placeholder="e.g. 24">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.min_amount')}} ({{translate('messages.optional')}})</label>
                            <input type="number" name="min_amount" class="form-control" min="0" placeholder="For min_order_amount type">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.order_count')}} ({{translate('messages.optional')}})</label>
                            <input type="number" name="order_count" class="form-control" min="1" placeholder="For multiple_orders type">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.status')}}</label>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="checkbox" name="status" value="1" checked>
                                <label class="form-check-label">{{translate('messages.active')}}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end">
                    <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                    <button type="submit" class="btn btn--primary">{{translate('messages.add_challenge')}}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Challenges List -->
    <div class="card">
        <div class="card-header border-0 py-2">
            <div class="search--button-wrapper">
                <h5 class="card-title">{{translate('messages.challenges_list')}} <span class="badge badge-soft-dark ml-2">{{$challenges->total()}}</span></h5>
            </div>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>{{translate('messages.sl')}}</th>
                            <th>{{translate('messages.title')}}</th>
                            <th>{{translate('messages.frequency')}}</th>
                            <th>{{translate('messages.type')}}</th>
                            <th>{{translate('messages.xp_reward')}}</th>
                            <th>{{translate('messages.status')}}</th>
                            <th class="text-center">{{translate('messages.action')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($challenges as $key => $challenge)
                        <tr>
                            <td>{{$challenges->firstItem() + $key}}</td>
                            <td>
                                <div>
                                    <span class="font-weight-bold">{{$challenge->title}}</span>
                                    <br>
                                    <small class="text-muted">{{Str::limit($challenge->description, 40)}}</small>
                                </div>
                            </td>
                            <td>
                                @if($challenge->frequency == 'daily')
                                    <span class="badge badge-soft-info">{{translate('messages.daily')}}</span>
                                @else
                                    <span class="badge badge-soft-primary">{{translate('messages.weekly')}}</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-soft-secondary">{{ucfirst(str_replace('_', ' ', $challenge->challenge_type))}}</span>
                            </td>
                            <td>
                                <span class="text-success font-weight-bold">+{{$challenge->xp_reward}} XP</span>
                            </td>
                            <td>
                                <label class="toggle-switch toggle-switch-sm">
                                    <input type="checkbox" 
                                           class="toggle-switch-input" 
                                           onclick="location.href='{{route('admin.users.customer.xp.challenges.status', ['id' => $challenge->id])}}'"
                                           {{$challenge->status ? 'checked' : ''}}>
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </td>
                            <td>
                                <div class="btn--container justify-content-center">
                                    <a class="btn action-btn btn--primary btn-outline-primary"
                                       href="{{route('admin.users.customer.xp.challenges.edit', $challenge->id)}}"
                                       title="{{translate('messages.edit')}}">
                                        <i class="tio-edit"></i>
                                    </a>
                                    <a class="btn action-btn btn--danger btn-outline-danger"
                                       href="javascript:"
                                       onclick="form_alert('challenge-{{$challenge->id}}','{{translate('messages.want_to_delete_this_challenge')}}')"
                                       title="{{translate('messages.delete')}}">
                                        <i class="tio-delete-outlined"></i>
                                    </a>
                                    <form action="{{route('admin.users.customer.xp.challenges.delete', $challenge->id)}}" method="POST" id="challenge-{{$challenge->id}}">
                                        @csrf @method('DELETE')
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="page-area mt-3">
                {!! $challenges->links() !!}
            </div>
        </div>
    </div>
</div>
@endsection
