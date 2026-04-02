@extends('layouts.admin.app')

@section('title', translate('messages.places_leaderboard'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio trophy"></i> {{ translate('messages.places_leaderboard') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <form method="GET" class="d-inline-flex align-items-center gap-2">
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $period == $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1">{{ number_format($stats['total_votes']) }}</h3>
                    <p class="text-muted mb-0">{{ translate('messages.total_votes') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1">{{ number_format($stats['participating_places']) }}</h3>
                    <p class="text-muted mb-0">{{ translate('messages.participating_places') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1">{{ number_format($stats['average_rating'] ?? 0, 1) }}</h3>
                    <p class="text-muted mb-0">{{ translate('messages.average_rating') }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-1">{{ number_format($stats['total_places']) }}</h3>
                    <p class="text-muted mb-0">{{ translate('messages.total_places') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Top 10 Places -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0">{{ translate('messages.top_10_places') }}</h5>
                <a href="{{ route('admin.places.leaderboard.votes') }}" class="btn btn-sm btn-outline-primary ml-auto">
                    <i class="tio-list"></i> {{ translate('messages.view_all_votes') }}
                </a>
                <a href="{{ route('admin.places.leaderboard.clear-cache') }}" class="btn btn-sm btn-outline-secondary">
                    <i class="tio-refresh"></i> {{ translate('messages.clear_cache') }}
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            @if($topPlaces->isEmpty())
                <div class="text-center py-5">
                    <i class="tio-sad text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-2">{{ translate('messages.no_places_qualified_yet') }}</p>
                    <small>{{ translate('messages.min_votes_required', ['count' => config('default_min_votes', 10)]) }}</small>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.rank') }}</th>
                                <th>{{ translate('messages.place') }}</th>
                                <th class="text-center">{{ translate('messages.votes') }}</th>
                                <th class="text-center">{{ translate('messages.rating') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($topPlaces as $index => $entry)
                            <tr>
                                <td>
                                    @if($index == 0)
                                        <span class="badge badge-warning">🥇 1</span>
                                    @elseif($index == 1)
                                        <span class="badge badge-secondary">🥈 2</span>
                                    @elseif($index == 2)
                                        <span class="badge badge-soft-warning">🥉 3</span>
                                    @else
                                        <span class="badge badge-soft-dark">#{{ $index + 1 }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if($entry['place']->image)
                                            <img src="{{ asset('storage/app/public/places/' . $entry['place']->image) }}" 
                                                 class="rounded" style="width:40px;height:40px;object-fit:cover">
                                        @else
                                            <div class="rounded bg-soft-secondary d-flex align-items-center justify-content-center" 
                                                 style="width:40px;height:40px;">
                                                <i class="tio-place"></i>
                                            </div>
                                        @endif
                                        <div>
                                            <a href="{{ route('admin.places.edit', $entry['place']->id) }}" class="text-body">
                                                {{ $entry['place']->localized_name }}
                                            </a>
                                            @if($entry['place']->category)
                                                <br><small class="text-muted">{{ $entry['place']->category->localized_name }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge badge-soft-primary">{{ $entry['votes_count'] }}</span>
                                </td>
                                <td class="text-center">
                                    @if($entry['rating'])
                                        <span class="badge badge-soft-success">
                                            ⭐ {{ number_format($entry['rating'], 1) }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
