@extends('layouts.admin.app')

@section('title', 'Scratch cards · ' . $batch->name)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-gift text-primary"></i>
            </span>
            <span>Batch {{ $batch->name }}</span>
            @if($batch->active)
                <span class="badge badge-soft-success ml-2">on</span>
            @else
                <span class="badge badge-soft-danger ml-2">off</span>
            @endif
        </h1>
        <a href="{{ route('admin.users.customer.scratch.index') }}" class="btn btn--reset">← All batches</a>
    </div>

    <!-- Summary + actions -->
    <div class="row mb-3">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col"><h3 class="mb-0">{{ $batch->quantity }}</h3><small class="text-muted">cards</small></div>
                        <div class="col"><h3 class="mb-0">{{ $winners }}</h3><small class="text-muted">winners ({{ $batch->quantity ? round($winners / $batch->quantity * 100) : 0 }}%)</small></div>
                        <div class="col"><h3 class="mb-0">{{ $reached }}</h3><small class="text-muted">typed in</small></div>
                        <div class="col"><h3 class="mb-0">{{ $used }}</h3><small class="text-muted">used on an order</small></div>
                    </div>
                    <hr>
                    <div class="text-muted">
                        Free delivery winners: {{ $batch->outcome_mix['free_delivery'] ?? 0 }} ·
                        Discount winners: {{ $batch->outcome_mix['discount'] ?? 0 }}
                        @if(($batch->outcome_mix['discount'] ?? 0) > 0)
                            (EGP {{ $batch->outcome_mix['discount_value'] ?? 0 }} off, min EGP {{ $batch->outcome_mix['discount_min_order'] ?? 0 }})
                        @endif
                        · Zone: {{ $batch->zone->name ?? 'any' }}
                        · Use before: <strong>{{ $batch->use_before->format('d M Y') }}</strong>
                        @if($batch->isExpired())<span class="badge badge-soft-secondary">ended</span>@endif
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <a href="{{ route('admin.users.customer.scratch.export', $batch->id) }}" class="btn btn--primary w-100 mb-2">Download printer file (CSV)</a>
                    <form action="{{ route('admin.users.customer.scratch.toggle', $batch->id) }}" method="POST" class="mb-3">
                        @csrf
                        @if($batch->active)
                            <button type="submit" class="btn btn-outline-danger w-100">Switch batch off</button>
                            <small class="text-muted">Only for a lost or stolen box: codes not yet typed in stop working at once.</small>
                        @else
                            <button type="submit" class="btn btn-outline-success w-100">Switch batch on</button>
                            <small class="text-muted">Switch on when the box goes out to riders or stores.</small>
                        @endif
                    </form>
                    <form action="{{ route('admin.users.customer.scratch.extend', $batch->id) }}" method="POST" class="d-flex">
                        @csrf
                        <input type="date" name="use_before" class="form-control mr-2" min="{{ $batch->use_before->toDateString() }}" value="{{ $batch->use_before->toDateString() }}" required>
                        <button type="submit" class="btn btn--reset">Extend</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Log a range (SC-15) -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Log a hand-out</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">Which card numbers went to whom. This log is the whole custody control: without it the report below can't point anywhere.</p>
            <form action="{{ route('admin.users.customer.scratch.ranges.store', $batch->id) }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">From #</label>
                            <input type="number" name="from_no" class="form-control" min="1" max="{{ $batch->quantity }}" required value="{{ old('from_no') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">To #</label>
                            <input type="number" name="to_no" class="form-control" min="1" max="{{ $batch->quantity }}" required value="{{ old('to_no') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">Given to</label>
                            <select name="holder_type" class="form-control">
                                <option value="rider">Rider</option>
                                <option value="store">Store</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">Name</label>
                            <input type="text" name="holder_name" class="form-control" maxlength="100" required value="{{ old('holder_name') }}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">Zone</label>
                            <select name="zone_id" class="form-control">
                                <option value="">{{ $batch->zone->name ?? '— none —' }}</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label class="input-label">Date</label>
                            <input type="date" name="handed_at" class="form-control" required value="{{ old('handed_at', now()->toDateString()) }}">
                        </div>
                    </div>
                    <div class="col-md-10">
                        <input type="text" name="notes" class="form-control" maxlength="255" placeholder="Notes (optional)">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn--primary w-100">Log</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Custody report (SC-15) -->
    <div class="card">
        <div class="card-header border-0 py-2">
            <h5 class="card-title">Hand-outs and redemption</h5>
        </div>
        <div class="card-body pt-0">
            <p class="text-muted">
                "Typed in" rate is compared with the same zone in this batch, not a fixed number: a slow zone is slow for everyone.
                A holder is judged on rate only once their ranges hold {{ $minHolderWinners }}+ winners.
                A flag means look closer, not proof.
            </p>
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Cards</th>
                            <th>Holder</th>
                            <th>Zone</th>
                            <th>Winners</th>
                            <th>Typed in</th>
                            <th>Rate / zone</th>
                            <th>Used</th>
                            <th>Accounts</th>
                            <th>Flag</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($report as $row)
                            @php($range = $row['range'])
                            <tr>
                                <td>#{{ $range->from_no }}–{{ $range->to_no }}<br><small class="text-muted">{{ $range->handed_at->format('d M') }}</small></td>
                                <td>
                                    {{ $range->holder_name }} <small class="text-muted">({{ $range->holder_type }})</small>
                                    <br><small class="text-muted">{{ $row['holder_winners'] }} winners overall</small>
                                    @if($range->notes)<br><small class="text-muted">{{ $range->notes }}</small>@endif
                                </td>
                                <td>{{ $range->zone->name ?? '—' }}</td>
                                <td>{{ $row['winners'] }}</td>
                                <td>{{ $row['reached'] }}</td>
                                <td>{{ round($row['rate'] * 100) }}% / {{ round($row['zone_rate'] * 100) }}%</td>
                                <td>{{ $row['used'] }}</td>
                                <td>{{ $row['accounts'] }}</td>
                                <td>
                                    @if(in_array('low_vs_zone', $row['flags']))
                                        <span class="badge badge-soft-warning">low vs zone</span>
                                    @endif
                                    @if(in_array('few_accounts', $row['flags']))
                                        <span class="badge badge-soft-danger">few accounts</span>
                                    @endif
                                </td>
                                <td>
                                    <form action="{{ route('admin.users.customer.scratch.ranges.delete', [$batch->id, $range->id]) }}" method="POST" onsubmit="return confirm('Remove this hand-out?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="tio-delete-outlined"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center text-muted py-4">No hand-outs logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
