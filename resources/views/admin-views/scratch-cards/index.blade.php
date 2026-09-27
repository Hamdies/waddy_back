@extends('layouts.admin.app')

@section('title', 'Scratch cards')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-gift text-primary"></i>
            </span>
            <span>Scratch cards</span>
        </h1>
        <p class="text-muted mb-0">Printed cards in the order bag. Winners type the code into the promo field on their next order.</p>
    </div>

    <!-- Program settings (SC-14, SC-15) -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">Program</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('admin.users.customer.scratch.settings') }}" method="POST">
                @csrf
                <div class="row align-items-end">
                    <div class="col-md-4">
                        <div class="form-group">
                            <div class="d-flex align-items-center">
                                <label class="toggle-switch mr-3">
                                    <input type="checkbox" name="status" value="1" {{ $enabled ? 'checked' : '' }}>
                                    <span class="toggle-switch-slider"></span>
                                </label>
                                <span>Program on</span>
                            </div>
                            <small class="text-muted d-block mt-2">Off = no new batches and no batch can be switched on. Cards already handed out keep working until their date.</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Max cards per account</label>
                            <input type="number" name="account_cap" class="form-control" min="1" max="100" value="{{ $cap }}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">…within (days)</label>
                            <input type="number" name="cap_days" class="form-control" min="1" max="365" value="{{ $capDays }}" required>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <button type="submit" class="btn btn--primary w-100">{{ translate('messages.save') }}</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- New batch (SC-01, SC-12, SC-13) -->
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">New batch (one printed box)</h5>
        </div>
        <div class="card-body">
            @if(!$enabled)
                <div class="alert alert-soft-warning">The program is off, so new batches can't be created.</div>
            @endif
            <form action="{{ route('admin.users.customer.scratch.store') }}" method="POST" id="scratch-batch-form">
                @csrf
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Batch label <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" maxlength="50" required placeholder="e.g. W1" value="{{ old('name') }}">
                            <small class="text-muted">Printed on the box and the card back.</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Cards in the box <span class="text-danger">*</span></label>
                            <input type="number" name="quantity" class="form-control js-mix" min="1" max="20000" required value="{{ old('quantity', 500) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Free delivery winners <span class="text-danger">*</span></label>
                            <input type="number" name="free_delivery_winners" class="form-control js-mix" min="0" required value="{{ old('free_delivery_winners', 500) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Discount winners <span class="text-danger">*</span></label>
                            <input type="number" name="discount_winners" class="form-control js-mix" min="0" required value="{{ old('discount_winners', 0) }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Discount value (EGP)</label>
                            <input type="number" name="discount_value" class="form-control" min="0" step="0.01" value="{{ old('discount_value') }}" placeholder="e.g. 20">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Discount minimum order (EGP)</label>
                            <input type="number" name="discount_min_order" class="form-control" min="0" step="0.01" value="{{ old('discount_min_order') }}" placeholder="e.g. 150">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Use before <span class="text-danger">*</span></label>
                            <input type="date" name="use_before" class="form-control" required value="{{ old('use_before') }}">
                            <small class="text-muted">Printed on the cards. Can be extended later, never shortened.</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label class="input-label">Zone ({{ translate('messages.optional') }})</label>
                            <select name="zone_id" class="form-control">
                                <option value="">— any —</option>
                                @foreach($zones as $zone)
                                    <option value="{{ $zone->id }}" {{ old('zone_id') == $zone->id ? 'selected' : '' }}>{{ $zone->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <span class="font-weight-bold" id="scratch-mix-summary"></span>
                    <button type="submit" class="btn btn--primary" {{ $enabled ? '' : 'disabled' }}>Create batch</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Batches -->
    <div class="card">
        <div class="card-header border-0 py-2">
            <h5 class="card-title">Batches <span class="badge badge-soft-dark ml-2">{{ $batches->total() }}</span></h5>
        </div>
        <div class="card-body pt-0">
            <div class="table-responsive datatable-custom">
                <table class="table table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Batch</th>
                            <th>Cards</th>
                            <th>Winners</th>
                            <th>Win rate</th>
                            <th>Typed in</th>
                            <th>Used on an order</th>
                            <th>Use before</th>
                            <th>Status</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($batches as $batch)
                            <tr>
                                <td>
                                    <span class="font-weight-bold">{{ $batch->name }}</span>
                                    @if($batch->zone)<br><small class="text-muted">{{ $batch->zone->name }}</small>@endif
                                </td>
                                <td>{{ $batch->quantity }}</td>
                                <td>{{ $batch->winners_count }}</td>
                                <td>{{ $batch->quantity > 0 ? round($batch->winners_count / $batch->quantity * 100) : 0 }}%</td>
                                <td>{{ $batch->reached_count }}</td>
                                <td>{{ $batch->used_count }}</td>
                                <td>
                                    {{ $batch->use_before->format('d M Y') }}
                                    @if($batch->isExpired())<br><span class="badge badge-soft-secondary">ended</span>@endif
                                </td>
                                <td>
                                    @if($batch->active)
                                        <span class="badge badge-soft-success">on</span>
                                    @else
                                        <span class="badge badge-soft-danger">off</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.users.customer.scratch.show', $batch->id) }}" class="btn btn-sm btn--primary btn-outline-primary">Open</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">No batches yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {!! $batches->links() !!}
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script>
    (function () {
        const form = document.getElementById('scratch-batch-form');
        const out = document.getElementById('scratch-mix-summary');
        function num(name) { return parseInt(form.querySelector('[name="' + name + '"]').value, 10) || 0; }
        function render() {
            const q = num('quantity'), fd = num('free_delivery_winners'), d = num('discount_winners');
            const winners = fd + d;
            if (winners > q) {
                out.textContent = 'Winners (' + winners + ') are more than the cards (' + q + ').';
                out.className = 'font-weight-bold text-danger';
                return;
            }
            const rate = q > 0 ? Math.round(winners / q * 100) : 0;
            out.textContent = winners + ' winners, ' + (q - winners) + ' no-prize cards: exactly ' + rate + '% win rate.';
            out.className = 'font-weight-bold';
        }
        form.querySelectorAll('.js-mix').forEach(function (el) { el.addEventListener('input', render); });
        render();
    })();
</script>
@endpush
