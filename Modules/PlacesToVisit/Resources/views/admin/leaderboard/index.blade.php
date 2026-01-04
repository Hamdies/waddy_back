@extends('layouts.admin.app')

@section('title', translate('messages.leaderboard'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-award"></i> {{ translate('messages.places_leaderboard') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <form action="{{ route('admin.places.leaderboard.clear-cache') }}" method="POST" class="d-inline">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="tio-refresh"></i> {{ translate('messages.clear_cache') }}
                    </button>
                </form>
                <a class="btn btn-primary ml-2" href="{{ route('admin.places.leaderboard.votes') }}">
                    <i class="tio-message"></i> {{ translate('messages.view_all_votes') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Period Filter -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.leaderboard.index') }}" method="get">
                <div class="col-md-3">
                    <label class="input-label">{{ translate('messages.period') }}</label>
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        @foreach($availablePeriods as $p)
                        <option value="{{ $p }}" {{ $period == $p ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($p . '-01')->format('F Y') }}
                            @if($p == now()->format('Y-m')) ({{ translate('messages.current') }}) @endif
                        </option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Stats -->
    <div class="row gx-2 mb-3">
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <span class="d-block font-size-sm mb-1">{{ translate('messages.total_votes') }}</span>
                            <span class="h3">{{ $stats['total_votes'] }}</span>
                        </div>
                        <div class="col-auto">
                            <span class="icon icon-soft-primary icon-circle">
                                <i class="tio-heart"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <span class="d-block font-size-sm mb-1">{{ translate('messages.participating_places') }}</span>
                            <span class="h3">{{ $stats['participating_places'] }} / {{ $stats['total_places'] }}</span>
                        </div>
                        <div class="col-auto">
                            <span class="icon icon-soft-success icon-circle">
                                <i class="tio-poi"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3 mb-3">
            <div class="card card-sm">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <span class="d-block font-size-sm mb-1">{{ translate('messages.average_rating') }}</span>
                            <span class="h3">
                                @if($stats['average_rating'])
                                    <i class="tio-star text-warning"></i> {{ number_format($stats['average_rating'], 1) }}
                                @else
                                    -
                                @endif
                            </span>
                        </div>
                        <div class="col-auto">
                            <span class="icon icon-soft-warning icon-circle">
                                <i class="tio-star"></i>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Leaderboard -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="tio-award text-warning"></i> 
                {{ translate('messages.top_10_places') }} - {{ \Carbon\Carbon::parse($period . '-01')->format('F Y') }}
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th style="width: 60px">{{ translate('messages.rank') }}</th>
                            <th>{{ translate('messages.place') }}</th>
                            <th>{{ translate('messages.category') }}</th>
                            <th class="text-center">{{ translate('messages.votes') }}</th>
                            <th class="text-center">{{ translate('messages.rating') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($topPlaces as $index => $place)
                        <tr>
                            <td>
                                @if($index == 0)
                                    <span class="badge badge-warning badge-lg">🥇 1</span>
                                @elseif($index == 1)
                                    <span class="badge badge-secondary badge-lg">🥈 2</span>
                                @elseif($index == 2)
                                    <span class="badge badge-info badge-lg">🥉 3</span>
                                @else
                                    <span class="badge badge-soft-dark">{{ $index + 1 }}</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('storage/app/public/places/' . $place['image']) }}" 
                                         onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                                         class="rounded mr-3" width="50">
                                    <div>
                                        <strong>{{ $place['title'] }}</strong>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $place['category'] ?? '-' }}</td>
                            <td class="text-center">
                                <span class="badge badge-soft-primary badge-lg">
                                    {{ $place['votes_count'] }}
                                </span>
                            </td>
                            <td class="text-center">
                                @if($place['avg_rating'] > 0)
                                    <i class="tio-star text-warning"></i> {{ $place['avg_rating'] }}
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-4">
                                <div class="text-muted">
                                    <i class="tio-award" style="font-size: 48px;"></i>
                                    <p class="mt-2">{{ translate('messages.no_places_qualified_yet') }}</p>
                                    <small>{{ translate('messages.min_votes_required', ['count' => config('placestovisit.min_votes_for_leaderboard', 5)]) }}</small>
                                </div>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
