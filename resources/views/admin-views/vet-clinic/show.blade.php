@extends('layouts.admin.app')

@section('title', translate('messages.vet_application'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <a href="{{ route('admin.vet-clinic.index', ['status' => $clinic->status]) }}" class="text-muted">
                    <i class="tio-chevron-left"></i> {{ translate('messages.vet_applications') }}
                </a>
                <h1 class="page-header-title mt-1">
                    <i class="tio-hospital"></i> {{ $clinic->name }}
                    @php $badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$clinic->status] ?? 'dark'; @endphp
                    <span class="badge badge-soft-{{ $badge }} ml-2">{{ translate('messages.' . $clinic->status) }}</span>
                </h1>
                <p class="text-muted mb-0">
                    {{ translate('messages.vet_submitted') }}: {{ $clinic->created_at?->format('d M Y, H:i') }}
                    @if($clinic->reviewed_at)
                        · {{ translate('messages.vet_reviewed') }}: {{ $clinic->reviewed_at->format('d M Y, H:i') }}
                    @endif
                </p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.vet-clinic.edit', $clinic->id) }}" class="btn btn-white">
                    <i class="tio-edit"></i> {{ translate('messages.edit') }}
                </a>
            </div>
        </div>
    </div>

    @if($clinic->status === 'rejected' && $clinic->review_note)
        <div class="alert alert-soft-danger">
            <strong>{{ translate('messages.vet_reject_note') }}:</strong> {{ $clinic->review_note }}
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">
            <div class="card mb-3">
                @if($clinic->coverUrl())
                    <img src="{{ $clinic->coverUrl() }}" class="card-img-top" style="max-height: 220px; object-fit: cover;" alt="">
                @endif
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <img src="{{ $clinic->logoUrl() ?? asset('public/assets/admin/img/160x160/img1.jpg') }}"
                             onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                             class="rounded mr-3" width="64" height="64" style="object-fit: cover;" alt="">
                        <div>
                            <h3 class="mb-0">{{ $clinic->name }}</h3>
                            @if($clinic->name_ar)<div class="text-muted" dir="rtl">{{ $clinic->name_ar }}</div>@endif
                        </div>
                    </div>
                    @if($clinic->description)<p class="mb-2">{{ $clinic->description }}</p>@endif
                    @if($clinic->description_ar)<p class="mb-2" dir="rtl">{{ $clinic->description_ar }}</p>@endif

                    <dl class="row mb-0 mt-3">
                        <dt class="col-sm-3">{{ translate('messages.phone') }}</dt>
                        <dd class="col-sm-9">{{ $clinic->phone ?? '—' }}</dd>
                        <dt class="col-sm-3">{{ translate('messages.address') }}</dt>
                        <dd class="col-sm-9">
                            {{ $clinic->address ?? '—' }}
                            <a href="https://www.google.com/maps?q={{ $clinic->latitude }},{{ $clinic->longitude }}" target="_blank" class="ml-1">
                                <i class="tio-map"></i> {{ translate('messages.vet_open_in_maps') }}
                            </a>
                        </dd>
                        @if($clinic->website)
                            <dt class="col-sm-3">{{ translate('messages.vet_apply_website') }}</dt>
                            <dd class="col-sm-9"><a href="{{ $clinic->website }}" target="_blank" rel="noopener nofollow">{{ $clinic->website }}</a></dd>
                        @endif
                        @if($clinic->instagram)
                            <dt class="col-sm-3">{{ translate('messages.vet_apply_instagram') }}</dt>
                            <dd class="col-sm-9">{{ $clinic->instagram }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0"><i class="tio-pet"></i> {{ translate('messages.vet_apply_step_care') }}</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        @forelse($clinic->species ?? [] as $s)
                            <span class="badge badge-soft-info mr-1">{{ translate('messages.clinic_species_' . $s) }}</span>
                        @empty
                            <span class="text-muted">—</span>
                        @endforelse
                    </div>
                    <table class="table table-sm table-borderless mb-0">
                        @forelse($clinic->services ?? [] as $s)
                            <tr>
                                <td>{{ translate('messages.clinic_service_' . $s) }}</td>
                                <td class="text-right text-muted">
                                    @isset($clinic->service_prices[$s]) {{ translate('messages.clinic_price_from') }} {{ number_format($clinic->service_prices[$s]) }} @endisset
                                </td>
                            </tr>
                        @empty
                            <tr><td class="text-muted">—</td></tr>
                        @endforelse
                    </table>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header"><h5 class="card-title mb-0"><i class="tio-time"></i> {{ translate('messages.vet_apply_step_hours') }}</h5></div>
                        <div class="card-body">
                            <table class="table table-sm table-borderless mb-0">
                                @foreach($days as $day)
                                    @php $h = $clinic->opening_hours[$day] ?? null; @endphp
                                    <tr>
                                        <td>{{ translate('messages.' . $day) }}</td>
                                        <td class="text-right">
                                            @if(!$h)
                                                <span class="text-muted">—</span>
                                            @elseif(\App\Models\VetClinic::isClosedFlag($h['closed'] ?? false))
                                                <span class="text-danger">{{ translate('messages.vet_apply_closed') }}</span>
                                            @else
                                                {{ $h['open'] ?? '?' }} – {{ $h['close'] ?? '?' }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header"><h5 class="card-title mb-0"><i class="tio-user"></i> {{ translate('messages.clinic_vets') }}</h5></div>
                        <div class="card-body">
                            @forelse($clinic->vets ?? [] as $vet)
                                <div class="mb-2">
                                    <div class="font-weight-bold">{{ $vet['name'] }}</div>
                                    <small class="text-muted">
                                        {{ $vet['role'] ?? '' }}{{ !empty($vet['role']) && isset($vet['years']) ? ' · ' : '' }}{{ isset($vet['years']) ? $vet['years'] . ' ' . translate('messages.clinic_vet_years') : '' }}
                                    </small>
                                </div>
                            @empty
                                <span class="text-muted">—</span>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0"><i class="tio-user-outlined"></i> {{ translate('messages.vet_contact_person') }}</h5></div>
                <div class="card-body">
                    <div class="font-weight-bold">{{ $clinic->contact_name ?? '—' }}</div>
                    @if($clinic->contact_role)<div class="text-muted mb-2">{{ $clinic->contact_role }}</div>@endif
                    @if($clinic->contact_phone)
                        <div><i class="tio-call"></i> <a href="tel:{{ $clinic->contact_phone }}">{{ $clinic->contact_phone }}</a></div>
                    @endif
                    @if($clinic->contact_email)
                        <div><i class="tio-email"></i> <a href="mailto:{{ $clinic->contact_email }}">{{ $clinic->contact_email }}</a></div>
                    @endif
                </div>
            </div>

            {{-- Older applications carry a license; the public form no longer asks for one. --}}
            @if($clinic->license_number || $clinic->license_document)
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0"><i class="tio-document-text"></i> {{ translate('messages.vet_license') }}</h5></div>
                <div class="card-body">
                    <div class="mb-2"><span class="text-muted">{{ translate('messages.vet_apply_license_number') }}:</span> <strong>{{ $clinic->license_number ?? '—' }}</strong></div>
                    @if($clinic->licenseUrl())
                        @if(str_ends_with($clinic->license_document, '.pdf'))
                            <a href="{{ $clinic->licenseUrl() }}" target="_blank" class="btn btn-sm btn-white"><i class="tio-file-text"></i> {{ translate('messages.vet_view_document') }}</a>
                        @else
                            <a href="{{ $clinic->licenseUrl() }}" target="_blank">
                                <img src="{{ $clinic->licenseUrl() }}" class="img-fluid rounded border" alt="">
                            </a>
                        @endif
                    @endif
                </div>
            </div>
            @endif

            @if($clinic->status !== 'approved')
                <div class="card mb-3">
                    <div class="card-body">
                        <form action="{{ route('admin.vet-clinic.approve', $clinic->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-success btn-block">
                                <i class="tio-checkmark-circle"></i> {{ translate('messages.vet_approve') }}
                            </button>
                        </form>
                        @if($clinic->status === 'pending')
                            <hr>
                            <form action="{{ route('admin.vet-clinic.reject', $clinic->id) }}" method="POST"
                                  onsubmit="return confirm('{{ translate('messages.vet_reject_confirm') }}')">
                                @csrf
                                <label class="input-label">{{ translate('messages.vet_reject_note') }}</label>
                                <textarea name="review_note" class="form-control mb-2" rows="2" maxlength="1000"></textarea>
                                <button type="submit" class="btn btn-outline-danger btn-block">
                                    <i class="tio-clear-circle"></i> {{ translate('messages.vet_reject') }}
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
