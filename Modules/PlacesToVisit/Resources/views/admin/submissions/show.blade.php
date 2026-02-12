@extends('layouts.admin.app')

@section('title', translate('messages.submission_details'))

@push('css_or_js')
<style>
    #submissionMap {
        height: 300px;
        width: 100%;
        border-radius: 8px;
    }
    .detail-label {
        font-weight: 600;
        color: #677788;
        font-size: 0.875rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .detail-value {
        font-size: 1rem;
        color: #1e2022;
    }
    .submission-image {
        max-width: 300px;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .status-banner {
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .status-banner.pending { background: #fff8e1; border-left: 4px solid #ffc107; }
    .status-banner.approved { background: #e8f5e9; border-left: 4px solid #28a745; }
    .status-banner.rejected { background: #fce4ec; border-left: 4px solid #dc3545; }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-visible"></i> {{ translate('messages.submission_details') }}
                </h1>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb breadcrumb-no-gutter">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.places.submissions.index') }}">{{ translate('messages.submissions') }}</a>
                        </li>
                        <li class="breadcrumb-item active">#{{ $submission->id }}</li>
                    </ol>
                </nav>
            </div>
            <div class="col-sm-auto">
                <a href="{{ route('admin.places.submissions.index') }}" class="btn btn-secondary">
                    <i class="tio-chevron-left"></i> {{ translate('messages.back') }}
                </a>
            </div>
        </div>
    </div>

    <!-- Status Banner -->
    <div class="status-banner {{ $submission->status }}">
        @if($submission->status === 'pending')
            <i class="tio-time mr-1"></i> <strong>{{ translate('messages.pending_review') }}</strong>
            — {{ translate('messages.this_submission_awaits_review') }}
        @elseif($submission->status === 'approved')
            <i class="tio-checkmark-circle mr-1"></i> <strong>{{ translate('messages.approved') }}</strong>
            @if($submission->approved_place_id)
                — <a href="{{ route('admin.places.edit', $submission->approved_place_id) }}">{{ translate('messages.view_created_place') }}</a>
            @endif
        @else
            <i class="tio-clear-circle mr-1"></i> <strong>{{ translate('messages.rejected') }}</strong>
            @if($submission->admin_note)
                — {{ $submission->admin_note }}
            @endif
        @endif
    </div>

    <div class="row">
        <!-- Submission Details -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('messages.place_info') }}</h5>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <span class="detail-label">{{ translate('messages.title') }}</span>
                                <div class="detail-value">{{ $submission->title }}</div>
                            </div>
                            <div class="mb-3">
                                <span class="detail-label">{{ translate('messages.category') }}</span>
                                <div class="detail-value">{{ $submission->category?->name ?? translate('messages.not_specified') }}</div>
                            </div>
                            @if($submission->phone)
                            <div class="mb-3">
                                <span class="detail-label">{{ translate('messages.phone') }}</span>
                                <div class="detail-value">{{ $submission->phone }}</div>
                            </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            @if($submission->image)
                            <img src="{{ $submission->image_url }}" class="submission-image"
                                 onerror="this.src='{{ asset('public/assets/admin/img/160x160/img1.jpg') }}'">
                            @endif
                        </div>
                    </div>

                    @if($submission->description)
                    <div class="mb-3">
                        <span class="detail-label">{{ translate('messages.description') }}</span>
                        <div class="detail-value">{{ $submission->description }}</div>
                    </div>
                    @endif

                    @if($submission->address)
                    <div class="mb-3">
                        <span class="detail-label">{{ translate('messages.address') }}</span>
                        <div class="detail-value">{{ $submission->address }}</div>
                    </div>
                    @endif

                    <div class="mb-3">
                        <span class="detail-label">{{ translate('messages.coordinates') }}</span>
                        <div class="detail-value">{{ $submission->latitude }}, {{ $submission->longitude }}</div>
                    </div>

                    <!-- Map -->
                    <div id="submissionMap"></div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Submitted By -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('messages.submitted_by') }}</h5>
                </div>
                <div class="card-body">
                    @if($submission->user)
                    <div class="d-flex align-items-center mb-2">
                        <div class="avatar avatar-sm mr-3">
                            <img class="avatar-img rounded-circle"
                                 src="{{ $submission->user->image ? asset('storage/app/public/profile/' . $submission->user->image) : asset('public/assets/admin/img/160x160/img1.jpg') }}"
                                 alt="{{ $submission->user->f_name }}">
                        </div>
                        <div>
                            <strong>{{ $submission->user->f_name }} {{ $submission->user->l_name }}</strong>
                            <br><small class="text-muted">{{ $submission->user->email }}</small>
                            @if($submission->user->phone)
                            <br><small class="text-muted">{{ $submission->user->phone }}</small>
                            @endif
                        </div>
                    </div>
                    @else
                    <p class="text-muted">{{ translate('messages.user_deleted') }}</p>
                    @endif
                    <hr>
                    <small class="text-muted">
                        <i class="tio-time mr-1"></i> {{ translate('messages.submitted_on') }}: {{ $submission->created_at->format('M d, Y H:i') }}
                    </small>
                </div>
            </div>

            <!-- Actions -->
            @if($submission->isPending())
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">{{ translate('messages.actions') }}</h5>
                </div>
                <div class="card-body">
                    <!-- Approve Form -->
                    <form action="{{ route('admin.places.submissions.approve', $submission->id) }}" method="POST" class="mb-3">
                        @csrf
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.category') }} <span class="text-danger">*</span></label>
                            <select name="category_id" class="form-control" required>
                                @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ $submission->category_id == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.admin_note') }}</label>
                            <textarea name="admin_note" class="form-control" rows="2"
                                      placeholder="{{ translate('messages.optional_note') }}"></textarea>
                        </div>
                        <button type="submit" class="btn btn-success btn-block"
                                onclick="return confirm('{{ translate('messages.approve_submission_confirm') }}')">
                            <i class="tio-checkmark-circle mr-1"></i> {{ translate('messages.approve_create_place') }}
                        </button>
                    </form>

                    <hr>

                    <!-- Reject Form -->
                    <form action="{{ route('admin.places.submissions.reject', $submission->id) }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.rejection_reason') }}</label>
                            <textarea name="admin_note" class="form-control" rows="2"
                                      placeholder="{{ translate('messages.reason_for_rejection') }}"></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger btn-block"
                                onclick="return confirm('{{ translate('messages.reject_submission_confirm') }}')">
                            <i class="tio-clear-circle mr-1"></i> {{ translate('messages.reject') }}
                        </button>
                    </form>
                </div>
            </div>
            @endif

            <!-- Delete -->
            <div class="card">
                <div class="card-body text-center">
                    <form action="{{ route('admin.places.submissions.destroy', $submission->id) }}" method="POST">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                onclick="return confirm('{{ translate('messages.are_you_sure') }}')">
                            <i class="tio-delete"></i> {{ translate('messages.delete_submission') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script src="https://maps.googleapis.com/maps/api/js?key={{ \App\Models\BusinessSetting::where('key', 'map_api_key')->first()?->value }}&callback=initSubmissionMap&v=3.45.8" async defer></script>
<script>
    "use strict";
    function initSubmissionMap() {
        const position = {
            lat: {{ $submission->latitude ?? 30.0444 }},
            lng: {{ $submission->longitude ?? 31.2357 }}
        };

        const map = new google.maps.Map(document.getElementById("submissionMap"), {
            zoom: 15,
            center: position,
            mapTypeControl: false,
            streetViewControl: false,
        });

        new google.maps.Marker({
            position: position,
            map: map,
            title: "{{ $submission->title }}",
            animation: google.maps.Animation.DROP,
        });
    }
</script>
@endpush
