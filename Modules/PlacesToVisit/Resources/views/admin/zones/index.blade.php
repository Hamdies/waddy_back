@extends('layouts.admin.app')

@section('title', translate('messages.zones'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-map"></i> {{ translate('messages.zones') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <a class="btn btn-primary" href="{{ route('admin.places.zones.create') }}">
                    <i class="tio-add"></i> {{ translate('messages.add_zone') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.zones.index') }}" method="get">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control" 
                           placeholder="{{ translate('messages.search_by_name') }}" 
                           value="{{ request('search') }}">
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
                            <th>{{ translate('messages.name') }}</th>
                            <th>{{ translate('messages.display_name') }}</th>
                            <th>{{ translate('messages.places_count') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zones as $key => $zone)
                        <tr>
                            <td>{{ $zones->firstItem() + $key }}</td>
                            <td>{{ $zone->localized_name }}</td>
                            <td>{{ $zone->localized_display_name }}</td>
                            <td>
                                <span class="badge badge-soft-primary">
                                    {{ $zone->places_count }}
                                </span>
                            </td>
                            <td>
                                <a href="{{ route('admin.places.zones.toggle-status', $zone->id) }}"
                                   class="badge badge-soft-{{ $zone->is_active ? 'success' : 'danger' }}">
                                    {{ $zone->is_active ? translate('messages.active') : translate('messages.inactive') }}
                                </a>
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.zones.edit', $zone->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-edit"></i>
                                </a>
                                <button class="btn btn-sm btn-white" 
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-{{ $zone->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-{{ $zone->id }}" 
                                      action="{{ route('admin.places.zones.destroy', $zone->id) }}" 
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $zones->links() }}
        </div>
    </div>
</div>
@endsection
