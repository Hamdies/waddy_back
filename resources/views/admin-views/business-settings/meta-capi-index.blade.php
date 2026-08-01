@extends('layouts.admin.app')

@section('title', translate('messages.Meta Conversions API'))


@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/facebook.png')}}" class="w--26" alt="" onerror="this.style.display='none'">
                </span>
                <span>
                    {{translate('messages.Meta_Conversions_API_setup')}}
                </span>
            </h1>
            @include('admin-views.business-settings.partials.third-party-links')
        </div>
        <!-- End Page Header -->

        <div class="card">
            <div class="card-header">
                <h4 class="m-0">
                    {{translate('Meta (Facebook) Ads Purchase Tracking')}}
                </h4>
                <button type="button" class="btn btn--primary btn-outline-primary btn-sm px-3" data-toggle="modal" data-target="#setup-information">
                    {{translate('Credential Setup Information')}} <i class="tio-info"></i>
                </button>
            </div>
            <div class="card-body">
                <div class="alert alert-soft-secondary">
                    <div class="d-flex gap-2">
                        <div class="w-0 flex-grow-1">
                            <h4 class="m-0">{{ translate('Server-side Purchase events for Meta ads') }}</h4>
                            <div>{{ translate('When enabled, every placed order is reported to Meta as a Purchase with hashed customer contact info. This recovers ad attribution for iPhone users who decline tracking, and lets Meta optimize campaigns on real orders instead of installs.') }}</div>
                        </div>
                        <div>
                            <button type="button" class="btn p-0 text-danger" data-dismiss="alert">
                                <i class="tio-clear"></i>
                            </button>
                        </div>
                    </div>
                </div>
                @php($config=\App\CentralLogics\Helpers::get_business_settings('meta_capi'))
                <form action="{{env('APP_MODE')!='demo'?route('admin.business-settings.third-party.meta_capi_update'):'javascript:'}}" method="post">
                    @csrf
                    <label class="toggle-switch h--45px toggle-switch-sm d-flex justify-content-between border rounded px-3 py-0 form-control mb-4">
                        <span class="pr-1 d-flex align-items-center switch--label">
                            <span class="line--limit-1">
                                @if (isset($config) && $config['status'] == 1)
                                {{translate('Meta Conversions API Turn OFF')}}
                                @else
                                {{translate('Meta Conversions API Turn ON')}}
                                @endif
                            </span>
                        </span>
                        <input type="checkbox"
                                class="status toggle-switch-input"
                                name="status" id="meta_capi_status" value="1" {{isset($config) && $config['status'] == 1 ? 'checked':''}}>
                        <span class="toggle-switch-label text p-0">
                            <span class="toggle-switch-indicator"></span>
                        </span>
                    </label>
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="form-group">
                                <label for="dataset_id" class="form-label">{{translate('messages.Dataset ID (Meta App ID)')}}</label><br>
                                <input id="dataset_id" type="text" class="form-control" name="dataset_id"
                                        placeholder="380903914182154"
                                        value="{{env('APP_MODE')!='demo'?$config['dataset_id']??"":''}}">
                            </div>
                        </div>
                        <div class="col-sm-8">
                            <div class="form-group">
                                <label for="access_token" class="form-label">{{translate('messages.Conversions API Access Token')}}</label><br>
                                <textarea id="access_token" class="form-control" name="access_token" rows="3"
                                        placeholder="EAAG...">{{env('APP_MODE')!='demo'?$config['access_token']??"":''}}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="btn--container justify-content-end">
                        <button type="reset" class="btn btn--reset">{{translate('messages.reset')}}</button>
                        <button type="{{env('APP_MODE')!='demo'?'submit':'button'}}" class="btn btn--primary call-demo">{{translate('messages.save')}}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="setup-information" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <h4 class="modal-title">{{translate('Instructions')}}</h4>
                    <ol class="list-gap-5 fs-13 mt-3">
                        <li>{{translate('Open Meta Events Manager')}}
                            ({{translate('messages.Click')}} <a
                                href="https://business.facebook.com/events_manager2"
                                target="_blank">{{translate('messages.here')}}</a>)
                        </li>
                        <li>{{translate('Select the app dataset for')}} <b>Waddy</b> ({{translate('the Dataset ID shown there is the Meta App ID')}})</li>
                        <li>{{translate('Go to')}} <b>{{translate('Settings')}}</b> {{translate('and scroll to the')}} <b>{{translate('Conversions API')}}</b> {{translate('section')}}</li>
                        <li>{{translate('Press')}} <b>{{translate('Generate access token')}}</b> {{translate('and copy it')}}</li>
                        <li>{{translate('Paste both values below, turn the toggle ON and')}} <b>{{ translate('Save') }}</b></li>
                        <li>{{translate('After the next orders, verify events arrive in Events Manager with')}} <b>{{translate('Server')}}</b> {{translate('as the source')}}</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>


@endsection
