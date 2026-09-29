@extends('layouts.admin.app')

@section('title', translate('messages.catalog_merge_duplicate'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <a href="{{ route('admin.item.catalog.edit', $survivor->id) }}" class="page-header-icon text-body"><i class="tio-arrow-backward"></i></a>
                <span>{{ translate('messages.catalog_merge_into', ['name' => $survivor->name]) }}</span>
            </h1>
        </div>

        <p class="text-muted">{{ translate('messages.catalog_merge_hint') }}</p>

        <div class="card mb-3">
            <div class="card-body">
                <form method="get" class="row g-2 align-items-end">
                    <div class="col-md-9">
                        <label class="input-label">{{ translate('messages.catalog_duplicate_to_fold_in') }}</label>
                        <select name="duplicate" class="form-control js-select2-custom" required>
                            <option value="">{{ translate('messages.select') }}</option>
                            @foreach ($candidates as $candidate)
                                <option value="{{ $candidate->id }}" @selected($loser?->id === $candidate->id)>
                                    {{ $candidate->name }}{{ $candidate->barcode ? ' · ' . $candidate->barcode : '' }} ({{ $candidate->listings_count }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn--secondary w-100">{{ translate('messages.catalog_preview_merge') }}</button>
                    </div>
                </form>
            </div>
        </div>

        @if ($loser)
            <div class="card">
                <div class="card-header d-block">
                    <h5 class="card-title mb-1">{{ translate('messages.catalog_merge_preview_title', ['from' => $loser->name, 'to' => $survivor->name]) }}</h5>
                    <p class="text-muted mb-0">{{ translate('messages.catalog_merge_keeps_content', ['name' => $survivor->name]) }}</p>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-align-middle card-table mb-0">
                        <thead class="thead-light">
                            <tr>
                                <th>{{ translate('messages.store') }}</th>
                                <th>{{ translate('messages.catalog_what_happens') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($plan as $step)
                                <tr>
                                    <td>{{ $step['store'] }}</td>
                                    <td>
                                        @if (isset($step['move']))
                                            {{ translate('messages.catalog_merge_step_move', ['id' => $step['move']]) }}
                                        @else
                                            {{ translate('messages.catalog_merge_step_collide', ['keep' => $step['keep'], 'drop' => $step['drop']]) }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center p-4">{{ translate('messages.catalog_merge_no_listings') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer">
                    @foreach ($blockers as $blocker)
                        <div class="alert alert-soft-danger mb-2">{{ $blocker }}</div>
                    @endforeach
                    <form method="post" action="{{ route('admin.item.catalog.merge', $survivor->id) }}" class="text-right"
                          onsubmit="return confirm(@js(translate('messages.catalog_merge_confirm')))">
                        @csrf
                        <input type="hidden" name="duplicate" value="{{ $loser->id }}">
                        <button type="submit" class="btn btn-danger" @disabled($blockers)>
                            {{ translate('messages.catalog_merge_now') }}
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
@endsection
