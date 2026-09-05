@extends('layouts.admin.app')

@section('title',translate('messages.cuisine'))

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-restaurant"></i>
                </span>
                <span>
                    {{translate('messages.cuisine')}}
                </span>
            </h1>
        </div>

        <div class="card">
            <div class="card-body">
                <form action="{{route('admin.cuisine.store')}}" method="post" enctype="multipart/form-data">
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
                            @if ($language)
                                <div class="lang_form" id="default-form">
                                    <div class="form-group">
                                        <label class="input-label" for="default_name">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                        <input type="text" name="name[]" id="default_name" class="form-control" placeholder="{{translate('messages.new_item')}}">
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                                @foreach(json_decode($language) as $lang)
                                    <div class="d-none lang_form" id="{{$lang}}-form">
                                        <div class="form-group">
                                            <label class="input-label" for="{{$lang}}_name">{{translate('messages.name')}} ({{strtoupper($lang)}})</label>
                                            <input type="text" name="name[]" id="{{$lang}}_name" class="form-control" placeholder="{{translate('messages.new_item')}}">
                                        </div>
                                        <input type="hidden" name="lang[]" value="{{$lang}}">
                                    </div>
                                @endforeach
                            @else
                                <div id="default-form">
                                    <div class="form-group">
                                        <label class="input-label">{{translate('messages.name')}} ({{ translate('messages.default') }})</label>
                                        <input type="text" name="name[]" class="form-control" placeholder="{{translate('messages.new_item')}}" required>
                                    </div>
                                    <input type="hidden" name="lang[]" value="default">
                                </div>
                            @endif
                            <div class="form-group">
                                <label class="input-label">{{translate('messages.priority')}}</label>
                                <input type="number" min="0" name="priority" value="0" class="form-control">
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
                                        src="{{asset('public/assets/admin/img/900x400/img1.jpg')}}" alt="image"/>
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
                                <button type="reset" id="reset_btn" class="btn btn--reset">{{translate('messages.reset')}}</button>
                                <button type="submit" class="btn btn--primary">{{translate('messages.add_cuisine')}}</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header py-2 border-0">
                <div class="search--button-wrapper">
                    <h5 class="card-title">
                        {{translate('messages.cuisine_list')}}
                        <span class="badge badge-soft-dark ml-2">{{$cuisines->total()}}</span>
                    </h5>
                    <form action="{{route('admin.cuisine.index')}}" method="GET" class="search-form">
                        <div class="input-group input--group">
                            <input type="search" name="search" class="form-control" placeholder="{{translate('messages.search_by_name')}}" value="{{$search ?? ''}}">
                            <button type="submit" class="btn btn--secondary">
                                <i class="tio-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-borderless table-thead-bordered table-align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('messages.SL') }}</th>
                                <th class="border-0">{{translate('messages.id')}}</th>
                                <th class="border-0">{{translate('messages.image')}}</th>
                                <th class="border-0">{{translate('messages.name')}}</th>
                                <th class="border-0 text-center">{{translate('messages.priority')}}</th>
                                <th class="border-0 text-center">{{translate('messages.restaurants')}}</th>
                                <th class="border-0">{{translate('messages.status')}}</th>
                                <th class="border-0 text-center">{{translate('messages.action')}}</th>
                            </tr>
                        </thead>
                        <tbody>
                        @foreach($cuisines as $key=>$cuisine)
                            <tr>
                                <td>{{$key+$cuisines->firstItem()}}</td>
                                <td>{{$cuisine->id}}</td>
                                <td>
                                    <img class="img--60 rounded" src="{{$cuisine->image_full_url ?? asset('public/assets/admin/img/160x160/img2.jpg')}}"
                                         onerror="this.src='{{asset('public/assets/admin/img/160x160/img2.jpg')}}'" alt="{{$cuisine->name}}">
                                </td>
                                <td>
                                    <span class="d-block font-size-sm text-body">
                                        {{Str::limit($cuisine->getRawOriginal('name'), 25,'...')}}
                                    </span>
                                </td>
                                <td class="text-center">{{$cuisine->priority}}</td>
                                <td class="text-center">{{$cuisine->stores_count ?? $cuisine->stores()->count()}}</td>
                                <td>
                                    <label class="toggle-switch toggle-switch-sm" for="statusCheckbox{{$cuisine->id}}">
                                        <input type="checkbox" data-url="{{route('admin.cuisine.status',[$cuisine->id,$cuisine->status?0:1])}}"
                                               class="toggle-switch-input redirect-url" id="statusCheckbox{{$cuisine->id}}" {{$cuisine->status?'checked':''}}>
                                        <span class="toggle-switch-label">
                                            <span class="toggle-switch-indicator"></span>
                                        </span>
                                    </label>
                                </td>
                                <td>
                                    <div class="btn--container justify-content-center">
                                        <a class="btn action-btn btn--primary btn-outline-primary"
                                           href="{{route('admin.cuisine.edit',[$cuisine->id])}}" title="{{translate('messages.edit')}}"><i class="tio-edit"></i>
                                        </a>
                                        <a class="btn action-btn btn--danger btn-outline-danger form-alert" href="javascript:"
                                           data-id="cuisine-{{$cuisine->id}}" data-message="{{ translate('Want to delete this cuisine') }}" title="{{translate('messages.delete')}}"><i class="tio-delete-outlined"></i>
                                        </a>
                                        <form action="{{route('admin.cuisine.delete',[$cuisine->id])}}" method="post" id="cuisine-{{$cuisine->id}}">
                                            @csrf @method('delete')
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if(count($cuisines) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $cuisines->links() !!}
            </div>
            @if(count($cuisines) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>{{translate('no_data_found')}}</h5>
                </div>
            @endif
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

        $('#reset_btn').click(function () {
            $('#viewer').attr('src', '{{asset('public/assets/admin/img/900x400/img1.jpg')}}');
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
