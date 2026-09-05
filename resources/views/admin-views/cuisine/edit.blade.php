@extends('layouts.admin.app')

@section('title',translate('messages.cuisine_update'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-restaurant"></i>
                </span>
                <span>{{translate('messages.cuisine_update')}}</span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.cuisine.update',[$cuisine->id])}}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        @php($language=\App\Models\BusinessSetting::where('key','language')->first())
                        @php($language = $language->value ?? null)
                        @php($defaultLang = str_replace('_', '-', app()->getLocale()))
                        @if($language)
                            <div class="col-12">
                                <ul class="nav nav-tabs mb-3 border-0">
                                    <li class="nav-item">
                                        <a class="nav-link lang_link active" href="#" id="default-link">{{translate('messages.default')}}</a>
                                    </li>
                                    @foreach (json_decode($language) as $lang)
                                        <li class="nav-item">
                                            <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">{{ \App\CentralLogics\Helpers::get_language_name($lang) . '(' . strtoupper($lang) . ')' }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="col-md-6">
                            @if($language)
                                <div class="lang_form" id="default-form">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                        <input type="text" name="name[]" value="{{$cuisine->getRawOriginal('name')}}" class="form-control">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach(json_decode($language) as $lang)
                                    @php($translate = [])
                                    @foreach($cuisine->translations as $t)
                                        @if($t->locale == $lang && $t->key == "name")
                                            @php($translate[$lang]['name'] = $t->value)
                                        @endif
                                    @endforeach
                                    <div class="d-none lang_form" id="{{$lang}}-form">
                                        <div class="form-group">
                                            <label class="input-label">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                            <input type="text" name="name[]" value="{{$translate[$lang]['name']??''}}" class="form-control">
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                        <input type="text" name="name[]" value="{{$cuisine->getRawOriginal('name')}}" class="form-control" required>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif
                            <div class="form-group">
                                <label class="input-label">{{translate('messages.priority')}}</label>
                                <input type="number" min="0" name="priority" value="{{$cuisine->priority}}" class="form-control">
                                <small class="text-muted">{{translate('messages.higher_priority_shows_first')}}</small>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="h-100 d-flex flex-column">
                                <label class="text-center d-block mt-auto">
                                    {{translate('messages.image')}}
                                    <small class="text-danger">( {{translate('messages.ratio')}} 200x200)</small>
                                </label>
                                <div class="text-center py-3 my-auto">
                                    <img class="img--120" id="viewer"
                                         src="{{$cuisine->image_full_url ?? asset('public/assets/admin/img/900x400/img1.jpg')}}"
                                         onerror="this.src='{{asset('public/assets/admin/img/900x400/img1.jpg')}}'" alt="image"/>
                                </div>
                                <div class="custom-file">
                                    <input type="file" name="image" id="customFileEg1" class="custom-file-input"
                                           accept=".webp, .jpg, .png, .jpeg">
                                    <label class="custom-file-label" for="customFileEg1">{{translate('messages.choose_file')}}</label>
                                </div>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="btn--container justify-content-end">
                                <a href="{{route('admin.cuisine.index')}}" class="btn btn--reset">{{translate('messages.back')}}</a>
                                <button type="submit" class="btn btn--primary">{{translate('messages.update')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        function readURL(input) {
            if (input.files && input.files[0]) {
                let reader = new FileReader();
                reader.onload = function (e) {
                    $('#viewer').attr('src', e.target.result);
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        $("#customFileEg1").change(function () {
            readURL(this);
        });

        $(".lang_link").click(function (e) {
            e.preventDefault();
            $(".lang_link").removeClass('active');
            $(".lang_form").addClass('d-none');
            $(this).addClass('active');
            let form_id = this.id;
            let lang = form_id.substring(0, form_id.length - 5);
            $("#" + lang + "-form").removeClass('d-none');
        });
    </script>
@endpush
