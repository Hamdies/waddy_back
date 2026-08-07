@extends('layouts.admin.app')

@section('title', translate('messages.voter_prizes'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-gift"></i> {{ translate('messages.voter_prizes') }}
                </h1>
                <p class="page-header-text">{{ translate('messages.voter_prizes_subtitle') }}</p>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link {{ !request('status') ? 'active' : '' }}"
                   href="{{ route('admin.places.prizes.index') }}">
                    {{ translate('messages.all') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'active' ? 'active' : '' }}"
                   href="{{ route('admin.places.prizes.index', ['status' => 'active']) }}">
                    <span class="badge badge-warning mr-1">●</span>
                    {{ translate('messages.active') }} ({{ $counts['active'] }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'redeemed' ? 'active' : '' }}"
                   href="{{ route('admin.places.prizes.index', ['status' => 'redeemed']) }}">
                    <span class="badge badge-success mr-1">●</span>
                    {{ translate('messages.redeemed') }} ({{ $counts['redeemed'] }})
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'expired' ? 'active' : '' }}"
                   href="{{ route('admin.places.prizes.index', ['status' => 'expired']) }}">
                    <span class="badge badge-secondary mr-1">●</span>
                    {{ translate('messages.expired') }} ({{ $counts['expired'] }})
                </a>
            </li>
        </ul>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2 align-items-center" action="{{ route('admin.places.prizes.index') }}" method="get">
                @if(request('status'))
                    <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="col-md-3 mb-2 mb-md-0">
                    <input type="text" name="search" class="form-control"
                           placeholder="{{ translate('messages.search_by_code') }}"
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3 mb-2 mb-md-0">
                    <select name="place_id" class="form-control">
                        <option value="">{{ translate('messages.all_places') }}</option>
                        @foreach($places as $place)
                            <option value="{{ $place->id }}" {{ request('place_id') == $place->id ? 'selected' : '' }}>
                                {{ $place->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2 mb-md-0">
                    <select name="period" class="form-control">
                        <option value="">{{ translate('messages.all') }}</option>
                        @foreach($periods as $period)
                            <option value="{{ $period }}" {{ request('period') == $period ? 'selected' : '' }}>
                                {{ $period }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ translate('messages.search') }}</button>
                    <a href="{{ route('admin.places.prizes.index') }}" class="btn btn-secondary">{{ translate('messages.reset') }}</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.code') }}</th>
                            <th>{{ translate('messages.user') }}</th>
                            <th>{{ translate('messages.place') }}</th>
                            <th>{{ translate('messages.period') }}</th>
                            <th>{{ translate('messages.expires') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th>{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse($prizes as $key => $prize)
                        <tr>
                            <td>{{ $prizes->firstItem() + $key }}</td>
                            <td>
                                <span class="badge badge-soft-dark" style="font-family:monospace;font-size:13px;letter-spacing:1px">
                                    {{ $prize->code }}
                                </span>
                            </td>
                            <td>
                                {{ trim(($prize->user->f_name ?? '') . ' ' . ($prize->user->l_name ?? '')) ?: translate('messages.guest') }}
                                @if($prize->user?->phone)
                                    <div class="text-muted small">{{ $prize->user->phone }}</div>
                                @endif
                            </td>
                            <td>{{ $prize->place?->title ?? '-' }}</td>
                            <td>{{ $prize->period }}</td>
                            <td>
                                {{ $prize->expires_at?->format('d M Y, H:i') ?? '-' }}
                                @if($prize->status === 'redeemed' && $prize->redeemed_at)
                                    <div class="text-success small">
                                        {{ translate('messages.redeemed_on') }} {{ $prize->redeemed_at->format('d M Y, H:i') }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($prize->status === 'active')
                                    <span class="badge badge-soft-warning">{{ translate('messages.active') }}</span>
                                @elseif($prize->status === 'redeemed')
                                    <span class="badge badge-soft-success">{{ translate('messages.redeemed') }}</span>
                                @else
                                    <span class="badge badge-soft-secondary">{{ translate('messages.expired') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($prize->status === 'active')
                                    <form action="{{ route('admin.places.prizes.redeem', $prize->id) }}" method="post"
                                          onsubmit="return confirm('{{ translate('messages.mark_prize_redeemed_confirm') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            {{ translate('messages.mark_redeemed') }}
                                        </button>
                                    </form>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                {{ translate('messages.no_prizes_found') }}
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($prizes->hasPages())
            <div class="card-footer">
                {!! $prizes->links() !!}
            </div>
        @endif
    </div>
</div>
@endsection
