@extends('layouts.admin.app')

@section('title', translate('messages.vet_clinics'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-hospital"></i> {{ translate('messages.vet_clinics') }}
                    <span class="badge badge-soft-dark ml-2">{{ $clinics->total() }}</span>
                </h1>
                <p class="text-muted mb-0">{{ translate('messages.vet_clinics_hint') }}</p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.vet-clinic.create') }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('messages.add_vet_clinic') }}
                </a>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0">
            <form class="search-form" method="GET">
                <div class="input-group input--group">
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}"
                           placeholder="{{ translate('messages.search_vet_clinics') }}">
                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.sl') }}</th>
                        <th>{{ translate('messages.vet_clinic') }}</th>
                        <th>{{ translate('messages.clinic_treats') }}</th>
                        <th>{{ translate('messages.clinic_services') }}</th>
                        <th>{{ translate('messages.clinic_vets') }}</th>
                        <th>{{ translate('messages.status') }}</th>
                        <th class="text-center">{{ translate('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($clinics as $key => $clinic)
                    <tr>
                        <td>{{ $clinics->firstItem() + $key }}</td>
                        <td>
                            <div class="d-flex align-items-center">
                                <img src="{{ $clinic->logoUrl() ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                     onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                                     class="rounded mr-2" width="44" height="44" style="object-fit: cover;">
                                <div>
                                    <div class="font-weight-bold">{{ $clinic->name }}</div>
                                    <small class="text-muted">{{ $clinic->name_ar }}</small>
                                    <div><small class="text-muted">{{ \Illuminate\Support\Str::limit($clinic->address, 40) }}</small></div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @foreach($clinic->species ?? [] as $s)
                                <span class="badge badge-soft-info">{{ translate('messages.clinic_species_' . $s) }}</span>
                            @endforeach
                        </td>
                        <td>
                            @foreach($clinic->services ?? [] as $s)
                                <span class="badge badge-soft-success">{{ translate('messages.clinic_service_' . $s) }}</span>
                            @endforeach
                        </td>
                        <td>{{ count($clinic->vets ?? []) }}</td>
                        <td>
                            <a href="{{ route('admin.vet-clinic.toggle-status', $clinic->id) }}"
                               class="badge badge-soft-{{ $clinic->is_active ? 'success' : 'danger' }}">
                                {{ $clinic->is_active ? translate('messages.active') : translate('messages.inactive') }}
                            </a>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.vet-clinic.edit', $clinic->id) }}" class="btn btn-sm btn-white" title="{{ translate('messages.edit') }}">
                                <i class="tio-edit"></i>
                            </a>
                            <form action="{{ route('admin.vet-clinic.destroy', $clinic->id) }}" method="POST" class="d-inline"
                                  onsubmit="return confirm('{{ translate('messages.vet_clinic_delete_confirm') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-white text-danger" title="{{ translate('messages.delete') }}">
                                    <i class="tio-delete-outlined"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">{{ translate('messages.no_vet_clinics_yet') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{!! $clinics->withQueryString()->links() !!}</div>
    </div>
</div>
@endsection
