@extends('layouts.admin.app')

@section('title', translate('messages.vet_clinics'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-hospital"></i> {{ translate('messages.vet_clinics') }}
                </h1>
                <p class="text-muted mb-0">{{ translate('messages.vet_clinics_hint') }}</p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('vet-clinic.create') }}" target="_blank" class="btn btn--secondary mr-1"
                   title="{{ translate('messages.vet_apply_public_link_hint') }}">
                    <i class="tio-open-in-new"></i> {{ translate('messages.vet_apply_public_link') }}
                </a>
                <a href="{{ route('admin.vet-clinic.create') }}" class="btn btn--primary">
                    <i class="tio-add"></i> {{ translate('messages.add_vet_clinic') }}
                </a>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs mb-3 border-0">
        @foreach([
            \App\Models\VetClinic::STATUS_APPROVED => 'vet_clinics_live',
            \App\Models\VetClinic::STATUS_PENDING => 'vet_applications',
            \App\Models\VetClinic::STATUS_REJECTED => 'vet_rejected',
        ] as $status => $label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $status ? 'active' : '' }}" href="{{ route('admin.vet-clinic.index', ['status' => $status]) }}">
                    {{ translate('messages.' . $label) }}
                    <span class="badge badge-soft-{{ $status === 'pending' && ($counts[$status] ?? 0) ? 'warning' : 'dark' }} ml-1">{{ $counts[$status] ?? 0 }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    <div class="card">
        <div class="card-header border-0">
            <form class="search-form" method="GET">
                <input type="hidden" name="status" value="{{ $tab }}">
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
                        @if($tab === 'approved')
                        <th>{{ translate('messages.clinic_vets') }}</th>
                        <th>{{ translate('messages.status') }}</th>
                        @else
                        <th>{{ translate('messages.vet_contact_person') }}</th>
                        <th>{{ translate('messages.vet_submitted') }}</th>
                        @endif
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
                        @if($tab === 'approved')
                        <td>{{ count($clinic->vets ?? []) }}</td>
                        <td>
                            <a href="{{ route('admin.vet-clinic.toggle-status', $clinic->id) }}"
                               class="badge badge-soft-{{ $clinic->is_active ? 'success' : 'danger' }}">
                                {{ $clinic->is_active ? translate('messages.active') : translate('messages.inactive') }}
                            </a>
                        </td>
                        @else
                        <td>
                            <div class="font-weight-bold">{{ $clinic->contact_name ?? '—' }}</div>
                            <small class="text-muted">{{ $clinic->contact_phone }}</small>
                        </td>
                        <td>
                            <div>{{ $clinic->created_at?->format('d M Y') }}</div>
                            <small class="text-muted">{{ $clinic->created_at?->diffForHumans() }}</small>
                        </td>
                        @endif
                        <td class="text-center">
                            @if($tab !== 'approved')
                            <a href="{{ route('admin.vet-clinic.show', $clinic->id) }}" class="btn btn-sm btn--primary" title="{{ translate('messages.review') }}">
                                <i class="tio-visible-outlined"></i> {{ translate('messages.review') }}
                            </a>
                            @endif
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
                        <td colspan="7" class="text-center py-5 text-muted">{{ translate($tab === 'approved' ? 'messages.no_vet_clinics_yet' : 'messages.vet_no_applications') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{!! $clinics->withQueryString()->links() !!}</div>
    </div>
</div>
@endsection
