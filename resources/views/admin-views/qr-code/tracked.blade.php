@extends('layouts.admin.app')

@section('title', translate('messages.qr_tab_tracked'))

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <i class="tio-qr-code"></i> {{ translate('messages.qr_code_generator') }}
        </h1>
        <p class="text-muted mb-0">{{ translate('messages.qr_tracked_hint') }}</p>
    </div>

    <ul class="nav nav-tabs mb-3 border-0">
        <li class="nav-item">
            <a class="nav-link" href="{{ route('admin.business-settings.qr-code') }}">{{ translate('messages.qr_tab_generator') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link active" href="{{ route('admin.business-settings.qr-code.tracked') }}">
                {{ translate('messages.qr_tab_tracked') }} <span class="badge badge-soft-dark ml-1">{{ $codes->total() }}</span>
            </a>
        </li>
    </ul>

    <div class="card">
        <div class="card-header border-0">
            <form class="search-form" method="GET">
                <div class="input-group input--group">
                    <input type="search" name="search" class="form-control" value="{{ request('search') }}"
                           placeholder="{{ translate('messages.qr_search') }}">
                    <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.name') }}</th>
                        <th>{{ translate('messages.qr_short_link') }}</th>
                        <th class="text-right">{{ translate('messages.qr_scans_total') }}</th>
                        <th class="text-right">{{ translate('messages.qr_scans_unique') }}</th>
                        <th class="text-right">{{ translate('messages.qr_scans_7d') }}</th>
                        <th>{{ translate('messages.qr_last_scan') }}</th>
                        <th>{{ translate('messages.status') }}</th>
                        <th class="text-center">{{ translate('messages.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($codes as $qr)
                    <tr>
                        <td>
                            <a href="{{ route('admin.business-settings.qr-code.show', $qr->id) }}" class="font-weight-bold text-dark">{{ $qr->name }}</a>
                            <div><small class="text-muted" title="{{ $qr->target_url }}">→ {{ \Illuminate\Support\Str::limit(preg_replace('#^https?://#', '', $qr->target_url), 45) }}</small></div>
                        </td>
                        <td><code>{{ preg_replace('#^https?://#', '', $qr->shortUrl()) }}</code></td>
                        <td class="text-right font-weight-bold">{{ number_format($qr->scans_count) }}</td>
                        <td class="text-right">{{ number_format($qr->unique_scans) }}</td>
                        <td class="text-right">
                            {{ number_format($qr->week_scans) }}
                            @if($qr->today_scans)<span class="badge badge-soft-success ml-1">+{{ $qr->today_scans }} {{ translate('messages.today') }}</span>@endif
                        </td>
                        <td>{{ $qr->last_scanned_at ? $qr->last_scanned_at->diffForHumans() : '—' }}</td>
                        <td>
                            <form action="{{ route('admin.business-settings.qr-code.toggle', $qr->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="badge border-0 badge-soft-{{ $qr->is_active ? 'success' : 'secondary' }}"
                                        title="{{ translate('messages.qr_pause_hint') }}">
                                    {{ $qr->is_active ? translate('messages.active') : translate('messages.qr_paused') }}
                                </button>
                            </form>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('admin.business-settings.qr-code.show', $qr->id) }}" class="btn btn-sm btn-white" title="{{ translate('messages.qr_view_stats') }}">
                                <i class="tio-chart-bar-4"></i>
                            </a>
                            <a href="{{ route('admin.business-settings.qr-code', ['edit' => $qr->id]) }}" class="btn btn-sm btn-white" title="{{ translate('messages.qr_edit_download') }}">
                                <i class="tio-edit"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <div class="text-muted mb-2">{{ translate('messages.qr_no_tracked') }}</div>
                            <a href="{{ route('admin.business-settings.qr-code') }}" class="btn btn-sm btn--primary">{{ translate('messages.qr_tab_generator') }}</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{!! $codes->withQueryString()->links() !!}</div>
    </div>
</div>
@endsection
