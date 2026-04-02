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
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($zones as $key => $zone)
                        <tr>
                            <td>{{ $key + 1 }}</td>
                            <td>{{ $zone->name }}</td>
                            <td>{{ $zone->display_name }}</td>
                            <td>
                                <span class="badge badge-soft-primary">
                                    {{ \Modules\PlacesToVisit\Entities\Place::where('zone_id', $zone->id)->count() }}
                                </span>
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
                            <td colspan="5" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
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
