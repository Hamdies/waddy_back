@extends('layouts.admin.app')

@section('title', translate('messages.xp_abuse_monitoring'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-warning text-warning"></i>
            </span>
            <span>{{ translate('messages.xp_abuse_monitoring') }}</span>
        </h1>
        <p class="text-muted mb-0">{{ translate('messages.read_only_signals_review_before_acting') }}</p>
    </div>

    <!-- Top earners this week -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="tio-chart-bar-1 text-primary"></i>
                {{ translate('messages.top_earners_this_week') }}
                <small class="text-muted">({{ $period }})</small>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-align-middle mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>{{ translate('messages.customer') }}</th>
                        <th>{{ translate('messages.phone') }}</th>
                        <th class="text-right">{{ translate('messages.xp_this_week') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topEarners as $i => $row)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td>
                                @if($row->user)
                                    <a href="{{ route('admin.users.customer.xp.users.detail', $row->user_id) }}">
                                        {{ $row->user->f_name }} {{ $row->user->l_name }}
                                    </a>
                                @else
                                    #{{ $row->user_id }}
                                @endif
                            </td>
                            <td>{{ $row->user->phone ?? '-' }}</td>
                            <td class="text-right"><b>{{ $row->xp_earned }}</b> XP</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('messages.no_data_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- XP-per-order ratio outliers -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="tio-shopping-cart text-danger"></i>
                {{ translate('messages.xp_per_order_outliers') }}
            </h5>
            <small class="text-muted">{{ translate('messages.high_xp_few_orders_hint') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table table-align-middle mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.customer') }}</th>
                        <th>{{ translate('messages.phone') }}</th>
                        <th class="text-right">{{ translate('messages.total_xp') }}</th>
                        <th class="text-right">{{ translate('messages.delivered_orders') }}</th>
                        <th class="text-right">{{ translate('messages.xp_per_order') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ratioOutliers as $u)
                        <tr>
                            <td>
                                <a href="{{ route('admin.users.customer.xp.users.detail', $u->id) }}">
                                    {{ $u->f_name }} {{ $u->l_name }}
                                </a>
                            </td>
                            <td>{{ $u->phone ?? '-' }}</td>
                            <td class="text-right">{{ $u->total_xp }}</td>
                            <td class="text-right">{{ $u->delivered_count }}</td>
                            <td class="text-right"><span class="badge badge-soft-danger">{{ $u->xp_per_order }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">{{ translate('messages.no_outliers_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Refund-after-delivery offenders -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="tio-undo text-warning"></i>
                {{ translate('messages.refund_offenders') }}
            </h5>
            <small class="text-muted">{{ translate('messages.repeated_refunds_hint') }}</small>
        </div>
        <div class="table-responsive">
            <table class="table table-align-middle mb-0">
                <thead class="thead-light">
                    <tr>
                        <th>{{ translate('messages.customer') }}</th>
                        <th>{{ translate('messages.phone') }}</th>
                        <th class="text-right">{{ translate('messages.total_xp') }}</th>
                        <th class="text-right">{{ translate('messages.refunded_orders') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($refundOffenders as $row)
                        @php($u = $refundUsers[$row->user_id] ?? null)
                        <tr>
                            <td>
                                @if($u)
                                    <a href="{{ route('admin.users.customer.xp.users.detail', $u->id) }}">
                                        {{ $u->f_name }} {{ $u->l_name }}
                                    </a>
                                @else
                                    #{{ $row->user_id }}
                                @endif
                            </td>
                            <td>{{ $u->phone ?? '-' }}</td>
                            <td class="text-right">{{ $u->total_xp ?? '-' }}</td>
                            <td class="text-right"><span class="badge badge-soft-warning">{{ $row->refund_count }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ translate('messages.no_data_found') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
