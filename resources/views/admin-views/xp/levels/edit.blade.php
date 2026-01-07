@extends('layouts.admin.app')

@section('title', translate('messages.edit_level'))

@section('content')
<div class="content container-fluid">
    <!-- Page Header -->
    <div class="page-header">
        <h1 class="page-header-title mr-3">
            <span class="page-header-icon">
                <i class="tio-star text-primary"></i>
            </span>
            <span>{{translate('messages.edit_level')}} - {{$level->getRawOriginal('name')}}</span>
        </h1>
    </div>

    <!-- Content -->
    <div class="card">
        <div class="card-body">
            <form action="{{route('admin.users.customer.xp.levels.update', $level->id)}}" method="POST" enctype="multipart/form-data">
                @csrf

                @php($language = json_decode($language))
                
                @if($language)
                <ul class="nav nav-tabs mb-4 border-0">
                    <li class="nav-item">
                        <a class="nav-link lang_link active" href="#" id="default-link">{{translate('messages.default')}}</a>
                    </li>
                    @foreach($language as $lang)
                    <li class="nav-item">
                        <a class="nav-link lang_link" href="#" id="{{$lang}}-link">{{\App\CentralLogics\Helpers::get_language_name($lang).'('.strtoupper($lang).')'}}</a>
                    </li>
                    @endforeach
                </ul>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.level_number')}}</label>
                            <input type="text" class="form-control" value="Level {{$level->level_number}}" disabled>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        @if($language)
                            <div class="form-group lang_form" id="default-form">
                                <label class="input-label">{{translate('messages.level_name')}} ({{translate('messages.default')}}) <span class="text-danger">*</span></label>
                                <input type="text" name="name[]" class="form-control" value="{{$level->getRawOriginal('name')}}" required placeholder="{{translate('messages.level_name')}}">
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                            @foreach($language as $lang)
                                @php($translate = \App\Models\Translation::where(['translationable_type' => 'App\Models\Level', 'translationable_id' => $level->id, 'locale' => $lang, 'key' => 'name'])->first())
                                <div class="form-group d-none lang_form" id="{{$lang}}-form">
                                    <label class="input-label">{{translate('messages.level_name')}} ({{strtoupper($lang)}})</label>
                                    <input type="text" name="name[]" class="form-control" value="{{$translate?->value ?? ''}}" placeholder="{{translate('messages.level_name')}}">
                                </div>
                                <input type="hidden" name="lang[]" value="{{$lang}}">
                            @endforeach
                        @else
                            <div class="form-group">
                                <label class="input-label">{{translate('messages.level_name')}} <span class="text-danger">*</span></label>
                                <input type="text" name="name[]" class="form-control" value="{{$level->name}}" required>
                            </div>
                            <input type="hidden" name="lang[]" value="default">
                        @endif
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.xp_required')}} <span class="text-danger">*</span></label>
                            <input type="number" name="xp_required" class="form-control" value="{{$level->xp_required}}" min="0" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.status')}}</label>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="status" value="1" {{$level->status ? 'checked' : ''}}>
                                <label class="form-check-label">{{translate('messages.active')}}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        @if($language)
                            <div class="form-group lang_form" id="default-form-desc">
                                <label class="input-label">{{translate('messages.description')}} ({{translate('messages.default')}})</label>
                                <textarea name="description[]" class="form-control" rows="3" placeholder="{{translate('messages.description')}}">{{$level->getRawOriginal('description')}}</textarea>
                            </div>
                            @foreach($language as $lang)
                                @php($descTranslate = \App\Models\Translation::where(['translationable_type' => 'App\Models\Level', 'translationable_id' => $level->id, 'locale' => $lang, 'key' => 'description'])->first())
                                <div class="form-group d-none lang_form" id="{{$lang}}-form-desc">
                                    <label class="input-label">{{translate('messages.description')}} ({{strtoupper($lang)}})</label>
                                    <textarea name="description[]" class="form-control" rows="3" placeholder="{{translate('messages.description')}}">{{$descTranslate?->value ?? ''}}</textarea>
                                </div>
                            @endforeach
                        @else
                            <div class="form-group">
                                <label class="input-label">{{translate('messages.description')}}</label>
                                <textarea name="description[]" class="form-control" rows="3">{{$level->description}}</textarea>
                            </div>
                        @endif
                    </div>

                    <!-- Badge Image Upload -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.badge_image')}} <small class="text-muted">({{translate('messages.ratio')}} 1:1)</small></label>
                            <div class="custom-file">
                                <input type="file" name="badge_image" id="badge_image" class="custom-file-input" accept="image/png, image/jpeg, image/gif" onchange="previewImage(this)">
                                <label class="custom-file-label" for="badge_image">{{translate('messages.choose_file')}}</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.image_preview')}}</label>
                            <div class="text-center border rounded p-3" style="min-height: 120px;">
                                @if($level->badge_image)
                                    <img id="image_preview" src="{{asset('public/level/' . $level->badge_image)}}" alt="Badge" style="max-height: 100px; max-width: 100%;">
                                @else
                                    <img id="image_preview" src="{{asset('public/assets/admin/img/upload-img.png')}}" alt="Preview" style="max-height: 100px; max-width: 100%;">
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Level Prizes Section -->
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">{{translate('messages.level_prizes')}}</h5>
                        <button type="button" class="btn btn-sm btn--primary" id="add-prize-btn">
                            <i class="tio-add"></i> {{translate('messages.add_prize')}}
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered" id="prizes-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>{{translate('messages.title')}} *</th>
                                        <th>{{translate('messages.prize_type')}}</th>
                                        <th>{{translate('messages.value')}}</th>
                                        <th>{{translate('messages.min_order')}}</th>
                                        <th>{{translate('messages.period')}}</th>
                                        <th>{{translate('messages.validity_days')}}</th>
                                        <th>{{translate('messages.status')}}</th>
                                        <th width="80">{{translate('messages.action')}}</th>
                                    </tr>
                                </thead>
                                <tbody id="prizes-body">
                                    @forelse($level->prizes as $index => $prize)
                                    <tr class="prize-row">
                                        <td>
                                            <input type="hidden" name="prizes[{{$index}}][id]" value="{{$prize->id}}">
                                            <input type="text" name="prizes[{{$index}}][title][]" class="form-control form-control-sm mb-1" value="{{$prize->getRawOriginal('title')}}" required placeholder="Title (Default)">
                                            @if($language)
                                                @foreach($language as $lang)
                                                    @php($prizeTranslate = \App\Models\Translation::where(['translationable_type' => 'App\Models\LevelPrize', 'translationable_id' => $prize->id, 'locale' => $lang, 'key' => 'title'])->first())
                                                    <input type="text" name="prizes[{{$index}}][title][]" class="form-control form-control-sm mt-1" value="{{$prizeTranslate?->value ?? ''}}" placeholder="Title ({{strtoupper($lang)}})">
                                                @endforeach
                                            @endif
                                        </td>
                                        <td>
                                            <select name="prizes[{{$index}}][prize_type]" class="form-control form-control-sm prize-type-select" onchange="toggleValueField(this)">
                                                @foreach($prizeTypes as $type)
                                                    <option value="{{$type}}" {{$prize->prize_type == $type ? 'selected' : ''}}>{{ucfirst(str_replace('_', ' ', $type))}}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="value-cell">
                                            <input type="number" name="prizes[{{$index}}][value]" class="form-control form-control-sm value-input" value="{{$prize->value}}" step="0.01" min="0" {{in_array($prize->prize_type, ['free_delivery', 'badge', 'free_item']) ? 'disabled placeholder=N/A' : ''}}>
                                        </td>
                                        <td>
                                            <input type="number" name="prizes[{{$index}}][min_order_amount]" class="form-control form-control-sm" value="{{$prize->min_order_amount}}" placeholder="{{translate('messages.min_order')}}" min="0" step="0.01">
                                        </td>
                                        <td>
                                            <select name="prizes[{{$index}}][period_type]" class="form-control form-control-sm">
                                                <option value="">{{translate('messages.no_limit')}}</option>
                                                @foreach($periodTypes as $key => $label)
                                                    <option value="{{$key}}" {{($prize->period_type ?? '') == $key ? 'selected' : ''}}>{{$label}}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td>
                                            <input type="number" name="prizes[{{$index}}][validity_days]" class="form-control form-control-sm" value="{{$prize->validity_days ?? 30}}" min="1">
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" name="prizes[{{$index}}][status]" value="1" {{$prize->status ? 'checked' : ''}}>
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-danger remove-prize-btn" title="{{translate('messages.delete')}}">
                                                <i class="tio-delete-outlined"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr id="no-prizes-row">
                                        <td colspan="8" class="text-center text-muted">{{translate('messages.no_prizes_added')}}</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <small class="text-muted">{{translate('messages.prize_types_hint')}}: Badge, Free Item, Free Delivery, Discount %, Wallet Credit, Custom</small>
                    </div>
                </div>

                <div class="btn--container justify-content-end mt-4">

                    <a href="{{route('admin.users.customer.xp.levels')}}" class="btn btn--reset">{{translate('messages.back')}}</a>
                    <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script>
    function previewImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            reader.onload = function(e) {
                $('#image_preview').attr('src', e.target.result);
            }
            reader.readAsDataURL(input.files[0]);
            $(input).next('.custom-file-label').html(input.files[0].name);
        }
    }

    // Language tab switching
    $(".lang_link").click(function(e){
        e.preventDefault();
        $(".lang_link").removeClass('active');
        $(".lang_form").addClass('d-none');
        $(this).addClass('active');

        let form_id = this.id.replace("-link", "-form");
        let form_desc_id = this.id.replace("-link", "-form-desc");
        $("#"+form_id).removeClass('d-none');
        $("#"+form_desc_id).removeClass('d-none');
    });

    // Prize management
    let prizeIndex = {{$level->prizes->count()}};
    const prizeTypes = @json($prizeTypes);
    const languages = @json($language ?? []);

    $('#add-prize-btn').click(function() {
        // Remove "no prizes" row if exists
        $('#no-prizes-row').remove();

        let typeOptions = prizeTypes.map(type => 
            `<option value="${type}">${type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</option>`
        ).join('');

        // Build language inputs for title
        let titleInputs = `<input type="text" name="prizes[${prizeIndex}][title][]" class="form-control form-control-sm mb-1" placeholder="{{translate('messages.prize_title')}} (Default)" required>`;
        if (languages && languages.length > 0) {
            languages.forEach(lang => {
                titleInputs += `<input type="text" name="prizes[${prizeIndex}][title][]" class="form-control form-control-sm mt-1" placeholder="{{translate('messages.prize_title')}} (${lang.toUpperCase()})">`;
            });
        }

        let periodOptions = `
            <option value="">{{translate('messages.no_limit')}}</option>
            <option value="once">One Time</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
            <option value="monthly">Monthly</option>
        `;

        let newRow = `
            <tr class="prize-row">
                <td>
                    <input type="hidden" name="prizes[${prizeIndex}][id]" value="">
                    ${titleInputs}
                </td>
                <td>
                    <select name="prizes[${prizeIndex}][prize_type]" class="form-control form-control-sm prize-type-select" onchange="toggleValueField(this)">
                        ${typeOptions}
                    </select>
                </td>
                <td class="value-cell">
                    <input type="number" name="prizes[${prizeIndex}][value]" class="form-control form-control-sm value-input" placeholder="0" step="0.01" min="0">
                </td>
                <td>
                    <input type="number" name="prizes[${prizeIndex}][min_order_amount]" class="form-control form-control-sm" placeholder="{{translate('messages.min_order')}}" min="0" step="0.01">
                </td>
                <td>
                    <select name="prizes[${prizeIndex}][period_type]" class="form-control form-control-sm">
                        ${periodOptions}
                    </select>
                </td>
                <td>
                    <input type="number" name="prizes[${prizeIndex}][validity_days]" class="form-control form-control-sm" value="30" min="1">
                </td>
                <td class="text-center">
                    <input type="checkbox" name="prizes[${prizeIndex}][status]" value="1" checked>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger remove-prize-btn" title="{{translate('messages.delete')}}">
                        <i class="tio-delete-outlined"></i>
                    </button>
                </td>
            </tr>
        `;

        $('#prizes-body').append(newRow);
        prizeIndex++;
    });

    // Remove prize row
    $(document).on('click', '.remove-prize-btn', function() {
        $(this).closest('tr').remove();
        
        // Show "no prizes" row if table is empty
        if ($('#prizes-body .prize-row').length === 0) {
            $('#prizes-body').append(`
                <tr id="no-prizes-row">
                    <td colspan="7" class="text-center text-muted">{{translate('messages.no_prizes_added')}}</td>
                </tr>
            `);
        }
    });

    // Toggle value field based on prize type
    function toggleValueField(selectElement) {
        const row = $(selectElement).closest('tr');
        const valueInput = row.find('.value-input');
        const prizeType = $(selectElement).val();
        const noValueTypes = ['free_delivery', 'badge', 'free_item'];
        
        if (noValueTypes.includes(prizeType)) {
            valueInput.prop('disabled', true).val('').attr('placeholder', 'N/A');
        } else {
            valueInput.prop('disabled', false).attr('placeholder', '0');
        }
    }

    // Initialize value fields on page load
    $(document).ready(function() {
        $('.prize-type-select').each(function() {
            toggleValueField(this);
        });
    });
</script>
@endpush
