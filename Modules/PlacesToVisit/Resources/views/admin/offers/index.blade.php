@extends('layouts.admin.app')

@section('title', translate('messages.place_offers'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-gift"></i> {{ translate('messages.place_offers') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-primary" href="{{ route('admin.places.offers.create') }}">
                    <i class="tio-add"></i> {{ translate('messages.add_offer') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.offers.index') }}" method="get">
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control"
                           placeholder="{{ translate('messages.search_by_title') }}"
                           value="{{ request('search') }}">
                </div>
                <div class="col-md-3">
                    <select name="place_id" class="form-control">
                        <option value="">{{ translate('messages.all_places') }}</option>
                        @foreach($places as $place)
                        <option value="{{ $place->id }}" {{ request('place_id') == $place->id ? 'selected' : '' }}>
                            {{ $place->title }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-control">
                        <option value="">{{ translate('messages.all_status') }}</option>
                        <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>{{ translate('messages.active') }}</option>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ translate('messages.filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.title') }}</th>
                            <th>{{ translate('messages.place') }}</th>
                            <th>{{ translate('messages.discount') }}</th>
                            <th>{{ translate('messages.duration') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($offers as $key => $offer)
                        <tr>
                            <td>{{ $offers->firstItem() + $key }}</td>
                            <td>
                                <strong>{{ $offer->title }}</strong>
                                @if($offer->description)
                                <br><small class="text-muted">{{ Str::limit($offer->description, 40) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($offer->place)
                                    <a href="{{ route('admin.places.edit', $offer->place_id) }}" class="text-primary">
                                        {{ $offer->place->title ?? $offer->place_id }}
                                    </a>
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>
                                @if($offer->discount_percent)
                                    <span class="badge badge-soft-success">{{ $offer->discount_percent }}%</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>
                                @if($offer->start_date && $offer->end_date)
                                    <small>
                                        {{ \Carbon\Carbon::parse($offer->start_date)->format('M d') }}
                                        - {{ \Carbon\Carbon::parse($offer->end_date)->format('M d, Y') }}
                                    </small>
                                    @if($offer->isValid())
                                        <br><span class="badge badge-soft-success badge-sm">{{ translate('messages.running') }}</span>
                                    @else
                                        <br><span class="badge badge-soft-secondary badge-sm">{{ translate('messages.expired') }}</span>
                                    @endif
                                @else
                                    <small class="text-muted">{{ translate('messages.no_date_limit') }}</small>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.places.offers.toggle-status', $offer->id) }}"
                                   class="badge badge-soft-{{ $offer->is_active ? 'success' : 'danger' }}">
                                    {{ $offer->is_active ? translate('messages.active') : translate('messages.inactive') }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.offers.edit', $offer->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-white"
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-offer-{{ $offer->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-offer-{{ $offer->id }}"
                                      action="{{ route('admin.places.offers.destroy', $offer->id) }}"
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $offers->links() }}
        </div>
    </div>
</div>
@endsection
