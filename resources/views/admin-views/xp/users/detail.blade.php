@extends('layouts.admin.app')

@section('title', translate('messages.user_xp_details'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-user text-primary"></i>
            </span>
            <span>{{$user->f_name}} {{$user->l_name}} - {{translate('messages.xp_details')}}</span>
        </h1>
    </div>

    <!-- User Info Card -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 text-center">
                    <img src="{{$user->image_full_url ?? asset('public/assets/admin/img/160x160/img1.jpg')}}" class="avatar avatar-xxl mb-3" alt="">
                    <h4>{{$user->f_name}} {{$user->l_name}}</h4>
                    <p class="text-muted">{{$user->email}}</p>
                </div>
                <div class="col-md-9">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h2>{{number_format($user->total_xp)}}</h2>
                                    <p class="mb-0">{{translate('messages.total_xp')}}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h2>Level {{$user->level}}</h2>
                                    <p class="mb-0">{{$currentLevel->name ?? 'N/A'}}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    @if($nextLevel)
                                        <h2>{{number_format($nextLevel->xp_required - $user->total_xp)}}</h2>
                                        <p class="mb-0">{{translate('messages.xp_to_next_level')}}</p>
                                    @else
                                        <h2>MAX</h2>
                                        <p class="mb-0">{{translate('messages.max_level_reached')}}</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- XP Transactions -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title">{{translate('messages.recent_xp_transactions')}}</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{translate('messages.date')}}</th>
                            <th>{{translate('messages.source')}}</th>
                            <th>{{translate('messages.xp')}}</th>
                            <th>{{translate('messages.description')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($user->xpTransactions as $transaction)
                        <tr>
                            <td>{{$transaction->created_at->format('d M Y, H:i')}}</td>
                            <td>
                                <span class="badge badge-soft-primary">{{ucfirst(str_replace('_', ' ', $transaction->xp_source))}}</span>
                            </td>
                            <td>
                                <span class="text-success font-weight-bold">+{{$transaction->xp_amount}}</span>
                            </td>
                            <td>{{$transaction->description ?? '-'}}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="text-center">{{translate('messages.no_transactions_found')}}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Level Prizes -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title">{{translate('messages.level_prizes')}}</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>{{translate('messages.prize')}}</th>
                            <th>{{translate('messages.level')}}</th>
                            <th>{{translate('messages.status')}}</th>
                            <th>{{translate('messages.unlocked_at')}}</th>
                            <th>{{translate('messages.expires_at')}}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($user->levelPrizes as $userPrize)
                        <tr>
                            <td>{{$userPrize->prize->title ?? 'N/A'}}</td>
                            <td>Level {{$userPrize->prize->level->level_number ?? 'N/A'}}</td>
                            <td>
                                @if($userPrize->status == 'unlocked')
                                    <span class="badge badge-warning">{{translate('messages.unlocked')}}</span>
                                @elseif($userPrize->status == 'claimed')
                                    <span class="badge badge-info">{{translate('messages.claimed')}}</span>
                                @elseif($userPrize->status == 'used')
                                    <span class="badge badge-success">{{translate('messages.used')}}</span>
                                @else
                                    <span class="badge badge-danger">{{translate('messages.expired')}}</span>
                                @endif
                            </td>
                            <td>{{$userPrize->unlocked_at ? $userPrize->unlocked_at->format('d M Y') : '-'}}</td>
                            <td>{{$userPrize->expires_at ? $userPrize->expires_at->format('d M Y') : '-'}}</td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center">{{translate('messages.no_prizes_found')}}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="{{route('admin.users.customer.xp.users')}}" class="btn btn--reset">{{translate('messages.back')}}</a>
    </div>
</div>
@endsection
