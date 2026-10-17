@extends('layouts.admin.app')

@section('title', $qr->name)

@push('css_or_js')
<style>
    .qr-bars { display: flex; align-items: flex-end; gap: 3px; height: 180px; padding-top: 18px; }
    .qr-bar { flex: 1; position: relative; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
    .qr-bar span { display: block; background: var(--primary, #107980); border-radius: 3px 3px 0 0; min-height: 2px; opacity: .85; }
    .qr-bar.is-zero span { background: #e7eaf3; }
    .qr-bar:hover span { opacity: 1; }
    .qr-bar b { position: absolute; top: -18px; left: 50%; transform: translateX(-50%); font-size: 11px; font-weight: 600;
                color: #334257; display: none; white-space: nowrap; }
    .qr-bar:hover b { display: block; }
    .qr-axis { display: flex; justify-content: space-between; font-size: 11px; color: #8a94a6; margin-top: 6px; }
    .qr-stat { font-size: 28px; font-weight: 700; line-height: 1.1; }
    .qr-device { height: 8px; border-radius: 4px; background: #eef1f6; overflow: hidden; }
    .qr-device span { display: block; height: 100%; background: var(--primary, #107980); }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <a href="{{ route('admin.business-settings.qr-code.tracked') }}" class="text-muted">
                    <i class="tio-chevron-left"></i> {{ translate('messages.qr_tab_tracked') }}
                </a>
                <h1 class="page-header-title mt-1">
                    <i class="tio-qr-code"></i> {{ $qr->name }}
                    @unless($qr->is_active)<span class="badge badge-soft-secondary ml-2">{{ translate('messages.qr_paused') }}</span>@endunless
                </h1>
                <p class="text-muted mb-0">
                    <code>{{ preg_replace('#^https?://#', '', $qr->shortUrl()) }}</code>
                    → <a href="{{ $qr->target_url }}" target="_blank" rel="noopener">{{ \Illuminate\Support\Str::limit($qr->target_url, 70) }}</a>
                </p>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.business-settings.qr-code', ['edit' => $qr->id]) }}" class="btn btn--primary">
                    <i class="tio-edit"></i> {{ translate('messages.qr_edit_download') }}
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        @foreach([
            ['qr_scans_total', $qr->scans_count],
            ['qr_scans_unique', $unique],
            ['qr_scans_7d', $week],
            ['today', $today],
        ] as [$label, $value])
            <div class="col-6 col-lg-3 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="text-muted mb-1">{{ translate('messages.' . $label) }}</div>
                        <div class="qr-stat">{{ number_format($value) }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row">
        <div class="col-lg-8 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.qr_last_30_days') }}</h5></div>
                <div class="card-body">
                    @php($max = max(1, max($days)))
                    <div class="qr-bars">
                        @foreach($days as $day => $count)
                            <div class="qr-bar {{ $count ? '' : 'is-zero' }}" title="{{ \Carbon\Carbon::parse($day)->format('D d M') }}: {{ $count }}">
                                <b>{{ $count }}</b>
                                <span style="height: {{ round($count / $max * 100, 1) }}%;"></span>
                            </div>
                        @endforeach
                    </div>
                    <div class="qr-axis">
                        <span>{{ \Carbon\Carbon::parse(array_key_first($days))->format('d M') }}</span>
                        <span>{{ translate('messages.today') }}</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-3">
            <div class="card h-100">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.qr_devices') }}</h5></div>
                <div class="card-body">
                    @php($deviceTotal = max(1, $devices->sum()))
                    @foreach(\App\Models\QrCodeScan::DEVICES as $device)
                        @php($n = (int) ($devices[$device] ?? 0))
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-1">
                                <span>{{ translate('messages.qr_device_' . $device) }}</span>
                                <span class="text-muted">{{ number_format($n) }} · {{ round($n / $deviceTotal * 100) }}%</span>
                            </div>
                            <div class="qr-device"><span style="width: {{ round($n / $deviceTotal * 100, 1) }}%;"></span></div>
                        </div>
                    @endforeach
                    <small class="text-muted">
                        {{ translate('messages.qr_last_scan') }}: {{ $qr->last_scanned_at ? $qr->last_scanned_at->format('d M Y, H:i') : '—' }}
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex flex-wrap justify-content-between align-items-center" style="gap: 12px;">
            <div>
                <div class="font-weight-bold">{{ $qr->is_active ? translate('messages.qr_pause') : translate('messages.qr_resume') }}</div>
                <small class="text-muted">{{ translate('messages.qr_pause_hint') }}</small>
            </div>
            <div class="d-flex" style="gap: 8px;">
                <form action="{{ route('admin.business-settings.qr-code.toggle', $qr->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-white">
                        <i class="tio-{{ $qr->is_active ? 'pause' : 'play' }}-circle"></i>
                        {{ $qr->is_active ? translate('messages.qr_pause') : translate('messages.qr_resume') }}
                    </button>
                </form>
                <form action="{{ route('admin.business-settings.qr-code.destroy', $qr->id) }}" method="POST"
                      onsubmit="return confirm('{{ translate('messages.qr_delete_confirm') }}')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger"><i class="tio-delete-outlined"></i> {{ translate('messages.delete') }}</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
