@extends('layouts.admin.app')

@section('title', translate('messages.place_submissions'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-user-outlined"></i> {{ translate('messages.place_submissions') }}
                </h1>
                <p class="page-header-text">{{ translate('messages.user_submitted_hidden_gems') }}</p>
            </div>
        </div>
    </div>

    <!-- Status Tabs -->
    <div class="mb-3">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a class="nav-link {{ !request('status') ? 'active' : '' }}" 
                   href="{{ route('admin.places.submissions.index') }}">
                    {{ translate('messages.all') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'pending' ? 'active' : '' }}"
                   href="{{ route('admin.places.submissions.index', ['status' => 'pending']) }}">
                    <span class="badge badge-warning mr-1">●</span> {{ translate('messages.pending') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'approved' ? 'active' : '' }}"
                   href="{{ route('admin.places.submissions.index', ['status' => 'approved']) }}">
                    <span class="badge badge-success mr-1">●</span> {{ translate('messages.approved') }}
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request('status') == 'rejected' ? 'active' : '' }}"
                   href="{{ route('admin.places.submissions.index', ['status' => 'rejected']) }}">
                    <span class="badge badge-danger mr-1">●</span> {{ translate('messages.rejected') }}
                </a>
            </li>
        </ul>
    </div>

    <!-- Search -->
    <div class="card mb-3">
        <div class="card-body">
            <form class="row gx-2" action="{{ route('admin.places.submissions.index') }}" method="get">
                @if(request('status'))
                <input type="hidden" name="status" value="{{ request('status') }}">
                @endif
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control"
                           placeholder="{{ translate('messages.search_by_title_or_user') }}"
                           value="{{ request('search') }}">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">{{ translate('messages.search') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-borderless table-thead-bordered table-align-middle">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ translate('messages.sl') }}</th>
                            <th>{{ translate('messages.image') }}</th>
                            <th>{{ translate('messages.title') }}</th>
                            <th>{{ translate('messages.submitted_by') }}</th>
                            <th>{{ translate('messages.category') }}</th>
                            <th>{{ translate('messages.date') }}</th>
                            <th>{{ translate('messages.status') }}</th>
                            <th class="text-center">{{ translate('messages.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($submissions as $key => $submission)
                        <tr>
                            <td>{{ $submissions->firstItem() + $key }}</td>
                            <td>
                                @if($submission->image)
                                <img src="{{ $submission->image_url }}"
                                     onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'"
                                     class="rounded" width="60">
                                @else
                                <img src="{{ asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                     class="rounded" width="60">
                                @endif
                            </td>
                            <td>
                                <strong>{{ $submission->title }}</strong>
                                @if($submission->description)
                                <br><small class="text-muted">{{ Str::limit($submission->description, 40) }}</small>
                                @endif
                            </td>
                            <td>
                                @if($submission->user)
                                <span>{{ $submission->user->f_name }} {{ $submission->user->l_name }}</span>
                                <br><small class="text-muted">{{ $submission->user->email }}</small>
                                @else
                                <span class="text-muted">-</span>
                                @endif
                            </td>
                            <td>{{ $submission->category?->name ?? '-' }}</td>
                            <td><small>{{ $submission->created_at->format('M d, Y H:i') }}</small></td>
                            <td>
                                @if($submission->status === 'pending')
                                    <span class="badge badge-soft-warning">{{ translate('messages.pending') }}</span>
                                @elseif($submission->status === 'approved')
                                    <span class="badge badge-soft-success">{{ translate('messages.approved') }}</span>
                                @else
                                    <span class="badge badge-soft-danger">{{ translate('messages.rejected') }}</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <a href="{{ route('admin.places.submissions.show', $submission->id) }}"
                                   class="btn btn-sm btn-white">
                                    <i class="tio-visible"></i>
                                </a>
                                @if($submission->status === 'pending')
                                <button class="btn btn-sm btn-white"
                                        onclick="if(confirm('{{ translate('messages.are_you_sure') }}')){document.getElementById('delete-sub-{{ $submission->id }}').submit()}">
                                    <i class="tio-delete text-danger"></i>
                                </button>
                                <form id="delete-sub-{{ $submission->id }}"
                                      action="{{ route('admin.places.submissions.destroy', $submission->id) }}"
                                      method="POST" style="display:none">
                                    @csrf @method('DELETE')
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4">
                                {{ translate('messages.no_data_found') }}
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer">
            {{ $submissions->links() }}
        </div>
    </div>
</div>
@endsection
