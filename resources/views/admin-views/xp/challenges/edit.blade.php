@extends('layouts.admin.app')

@section('title', translate('messages.edit_challenge'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-flag-outlined text-primary"></i>
            </span>
            <span>{{translate('messages.edit_challenge')}}</span>
        </h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form action="{{route('admin.users.customer.xp.challenges.update', $challenge->id)}}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.title')}} <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" required
                                   value="{{old('title', $challenge->title)}}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.frequency')}} <span class="text-danger">*</span></label>
                            <select name="frequency" class="form-control" required>
                                @foreach(['daily', 'weekly'] as $frequency)
                                    <option value="{{$frequency}}"
                                        {{old('frequency', $challenge->frequency) === $frequency ? 'selected' : ''}}>
                                        {{translate('messages.' . $frequency)}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.description')}} <span class="text-danger">*</span></label>
                            <textarea name="description" class="form-control" rows="2" required>{{old('description', $challenge->description)}}</textarea>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.challenge_type')}} <span class="text-danger">*</span></label>
                            <select name="challenge_type" class="form-control" required>
                                @php
                                    $types = [
                                        'complete_order' => 'complete_order',
                                        'min_order_amount' => 'minimum_order_amount',
                                        'multiple_orders' => 'multiple_orders',
                                        'new_store' => 'order_from_new_store',
                                    ];
                                @endphp
                                @foreach($types as $value => $label)
                                    <option value="{{$value}}"
                                        {{old('challenge_type', $challenge->challenge_type) === $value ? 'selected' : ''}}>
                                        {{translate('messages.' . $label)}}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.xp_reward')}} <span class="text-danger">*</span></label>
                            <input type="number" name="xp_reward" class="form-control" required min="1"
                                   value="{{old('xp_reward', $challenge->xp_reward)}}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.time_limit_hours')}} <span class="text-danger">*</span></label>
                            <input type="number" name="time_limit_hours" class="form-control" required min="1"
                                   value="{{old('time_limit_hours', $challenge->time_limit_hours)}}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.min_amount')}} ({{translate('messages.optional')}})</label>
                            <input type="number" name="min_amount" class="form-control" min="0"
                                   placeholder="For min_order_amount type"
                                   value="{{old('min_amount', $challenge->conditions['min_amount'] ?? '')}}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.order_count')}} ({{translate('messages.optional')}})</label>
                            <input type="number" name="order_count" class="form-control" min="1"
                                   placeholder="For multiple_orders type"
                                   value="{{old('order_count', $challenge->conditions['order_count'] ?? '')}}">
                        </div>
                    </div>
                    <div class="col-md-4 js-store-target">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.target_store')}} ({{translate('messages.optional')}})</label>
                            {{-- The picker loads over AJAX, so the currently pinned store is
                                 rendered as a preselected option or the form would silently
                                 unpin it on save. --}}
                            <select name="store_id" class="js-data-example-ajax form-control" data-placeholder="{{translate('messages.select_store')}}">
                                <option value="">---{{translate('messages.any_store')}}---</option>
                                @if($targetStore)
                                    <option value="{{$targetStore->id}}" selected>{{$targetStore->name}}</option>
                                @endif
                            </select>
                            <small class="form-text text-muted">{{translate('messages.target_store_hint')}}</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.status')}}</label>
                            <div class="form-check form-check-inline mt-2">
                                <input class="form-check-input" type="checkbox" name="status" value="1"
                                    {{old('status', $challenge->status) ? 'checked' : ''}}>
                                <label class="form-check-label">{{translate('messages.active')}}</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="btn--container justify-content-end">
                    <a href="{{route('admin.users.customer.xp.challenges')}}" class="btn btn--reset">{{translate('messages.back')}}</a>
                    <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script>
    $(document).on('ready', function () {
        let module_id = {{Config::get('module.current_module_id')}};

        // Same store picker the create form and the coupon page use.
        $('.js-data-example-ajax').select2({
            allowClear: true,
            placeholder: '{{translate('messages.any_store')}}',
            ajax: {
                url: '{{url('/')}}/admin/store/get-stores',
                data: function (params) {
                    return {
                        q: params.term,
                        page: params.page,
                        module_id: module_id
                    };
                },
                processResults: function (data) {
                    return {results: data};
                },
                __port: function (params, success, failure) {
                    var $request = $.ajax(params);
                    $request.then(success);
                    $request.fail(failure);
                    return $request;
                }
            }
        });

        // A target store only means something for types a specific store can
        // satisfy. `new_store` is satisfied by anywhere the user has NOT
        // ordered before, so pinning one would contradict the rule.
        function toggleStorePicker() {
            var type = $('select[name="challenge_type"]').val();
            var supportsStore = type === 'complete_order'
                || type === 'min_order_amount'
                || type === 'multiple_orders';
            $('.js-store-target').toggle(supportsStore);
            if (!supportsStore) {
                $('select[name="store_id"]').val('').trigger('change');
            }
        }

        $(document).on('change', 'select[name="challenge_type"]', toggleStorePicker);
        toggleStorePicker();
    });
</script>
@endpush
