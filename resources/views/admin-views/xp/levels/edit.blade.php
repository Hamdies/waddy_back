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
                        <div class="form-group">
                            <label class="input-label">{{translate('messages.description')}}</label>
                            <textarea name="description" class="form-control" rows="3">{{$level->description}}</textarea>
                        </div>
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
                <div class="btn--container justify-content-end">
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
        $("#"+form_id).removeClass('d-none');
    });
</script>
@endpush
