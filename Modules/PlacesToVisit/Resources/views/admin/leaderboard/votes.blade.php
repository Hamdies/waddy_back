@extends('layouts.admin.app')

@section('title', translate('messages.view_all_votes'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-format-list"></i> {{ translate('messages.view_all_votes') }}
                </h1>
            </div>
            <div class="col-sm-auto">
                <form method="GET" class="d-inline-flex align-items-center gap-2">
                    <select name="period" class="form-control" onchange="this.form.submit()">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $period == $p ? 'selected' : '' }}>{{ $p }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row gx-3 align-items-end">
                <div class="col-md-4">
                    <label class="input-label">{{ translate('messages.filter_by_flagged') }}</label>
                    <select name="flagged" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ translate('messages.all') }}</option>
                        <option value="1" {{ request('flagged') == '1' ? 'selected' : '' }}>{{ translate('messages.flagged_only') }}</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="input-label">{{ translate('messages.filter_by_place') }}</label>
                    <select name="place_id" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ translate('messages.all_places') }}</option>
                        @foreach($places as $place)
                            <option value="{{ $place->id }}" {{ request('place_id') == $place->id ? 'selected' : '' }}>
                                {{ $place->localized_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <a href="{{ route('admin.places.leaderboard.votes') }}" class="btn btn-sm btn-outline-secondary">
                        {{ translate('messages.reset') }}
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Votes Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.place') }}</th>
                            <th>{{ translate('messages.user') }}</th>
                            <th>{{ translate('messages.rating') }}</th>
                            <th>{{ translate('messages.comment') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($votes as $key => $vote)
                        <tr>
                            <td>{{ $votes->firstItem() + $key }}</td>
                            <td>
                                <a href="{{ route('admin.places.edit', $vote->place_id) }}">
                                    {{ $vote->place->localized_name ?? '-' }}
                                </a>
                            </td>
                            <td>
                                @if($vote->user)
                                    {{ $vote->user->name ?? $vote->user->phone ?? 'User #'.$vote->user_id }}
                                @else
                                    <span class="text-muted">{{ translate('messages.guest') }}</span>
                                @endif
                            </td>
                            <td>
                                @if($vote->rating)
                                    ⭐ {{ $vote->rating }}/5
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <small class="text-muted">{{ Str::limit($vote->comment, 50) }}</small>
                            </td>
                            <td>
                                @if($vote->is_flagged)
                                    <span class="badge badge-soft-danger">⚑ {{ translate('messages.flagged') }}</span>
                                @else
                                    <span class="badge badge-soft-success">{{ translate('messages.active') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.leaderboard.toggle-flag', $vote->id) }}"
                                   class="btn btn-sm btn-white"
                                   title="{{ translate('messages.toggle_flag') }}">
                                    <i class="tio-flag"></i>
                                </a>
                                <button class="btn btn-sm btn-white"
                                        onclick="if(confirm('{{ translate('messages.delete_vote_confirm') }}')){document.getElementById('vote-delete-{{ $vote->id }}').submit()}"
                                        title="{{ translate('messages.delete') }}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="vote-delete-{{ $vote->id }}"
                                      action="{{ route('admin.places.leaderboard.delete-vote', $vote->id) }}"
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-4">
                                {{ translate('messages.no_votes_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $votes->links() }}
        </div>
    </div>
</div>
@endsection
