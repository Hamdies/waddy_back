@extends('layouts.admin.app')

@section('title', translate('Expansion requests'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span>{{ translate('messages.Expansion_requests') }}</span>
            </h1>
            <p class="m-0 fs-12">
                {{ translate('People who asked us to deliver where we do not operate yet, grouped by the closest zone.') }}
            </p>
        </div>

        {{-- Demand by area: the launch-order shortlist. --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title">{{ translate('messages.Demand_by_area') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.SL') }}</th>
                            <th>{{ translate('messages.Closest_zone') }}</th>
                            <th>{{ translate('messages.Requests') }}</th>
                            {{-- Reachable = has a push token. An area we cannot notify at
                                 launch converts far worse than its raw count suggests. --}}
                            <th>{{ translate('messages.Reachable') }}</th>
                            <th>{{ translate('messages.Guests') }}</th>
                            <th>{{ translate('messages.Latest') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($byZone as $key => $row)
                            <tr>
                                <td>{{ $byZone->firstItem() + $key }}</td>
                                <td>{{ $row->zone->name ?? translate('messages.Unknown') }}</td>
                                <td><strong>{{ $row->total }}</strong></td>
                                <td>
                                    {{ $row->reachable }}
                                    <small class="text-muted">
                                        ({{ $row->total > 0 ? round(($row->reachable / $row->total) * 100) : 0 }}%)
                                    </small>
                                </td>
                                <td>{{ $row->guests }}</td>
                                <td>{{ $row->latest }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">{{ translate('messages.no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="page-area px-3">
                {!! $byZone->links() !!}
            </div>
        </div>

        <div class="row">
            {{-- Where in the funnel they hit the wall. --}}
            <div class="col-md-5">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('messages.Where_they_stopped') }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-align-middle">
                            <tbody>
                                @forelse($bySource as $source => $total)
                                    <tr>
                                        <td>{{ translate($source) }}</td>
                                        <td class="text-right"><strong>{{ $total }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center">{{ translate('messages.no_data_found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- The merchant sign-up shortlist. --}}
            <div class="col-md-7">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="card-title">{{ translate('messages.Most_wanted_stores') }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-align-middle">
                            <tbody>
                                @forelse($byStore as $row)
                                    <tr>
                                        <td>{{ $row->store->name ?? ('#' . $row->store_id) }}</td>
                                        <td class="text-right"><strong>{{ $row->total }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td class="text-center">{{ translate('messages.no_data_found') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Audit tail: these numbers can steer real expansion spend, so recent
             rows stay inspectable instead of being trusted blindly. --}}
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">{{ translate('messages.Recent_requests') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.Date') }}</th>
                            <th>{{ translate('messages.Address') }}</th>
                            <th>{{ translate('messages.Closest_zone') }}</th>
                            <th>{{ translate('messages.Source') }}</th>
                            <th>{{ translate('messages.Type') }}</th>
                            <th>{{ translate('messages.IP') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recent as $row)
                            <tr>
                                <td>{{ $row->created_at }}</td>
                                <td>{{ \Illuminate\Support\Str::limit($row->address ?? '-', 48) }}</td>
                                <td>{{ $row->zone->name ?? '-' }}</td>
                                <td>{{ translate($row->source) }}</td>
                                <td>{{ $row->is_guest ? translate('messages.Guest') : translate('messages.User') }}</td>
                                <td>{{ $row->ip_address ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">{{ translate('messages.no_data_found') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
