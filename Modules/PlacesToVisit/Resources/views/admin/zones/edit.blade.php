@extends('layouts.admin.app')

@section('title', translate('messages.edit_zone'))

@section('content')
<div class="content container-fluid">
    <!-- Header -->
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col-sm mb-2 mb-sm-0">
                <h1 class="page-header-title">
                    <i class="tio-edit"></i> {{ translate('messages.edit_zone') }}
                </h1>
            </div>
        </div>
    </div>

    <!-- Card -->
    <div class="card">
        <div class="card-body">
            <form action="{{ route('admin.places.zones.update', $zone->id) }}" method="POST">
                @csrf
                @method('PUT')

                @php
                    $defaultLang = str_replace('_', '-', app()->getLocale());
                    $languages = $language ?? [];
                    $translations = $zone->translations->groupBy('locale');
                @endphp

                <!-- Language Tabs -->
                @if(count($languages) > 1)
                <ul class="nav nav-tabs mb-4">
                    @foreach($languages as $lang)
                        @php $langCode = $lang['code'] ?? $lang; @endphp
                        <li class="nav-item">
                            <a class="nav-link lang_link {{ $langCode == $defaultLang ? 'active' : '' }}"
                               href="javascript:void(0)" id="{{ $langCode }}-link">
                                {{ strtoupper($langCode) }}
                            </a>
                        </li>
                    @endforeach
                </ul>
                @endif

                <!-- Name fields per language -->
                @foreach($languages as $lang)
                    @php
                        $langCode = $lang['code'] ?? $lang;
                        $langTranslations = $translations[$langCode] ?? collect();
                        $nameTranslation = $langTranslations->firstWhere('key', 'name');
                        $displayNameTranslation = $langTranslations->firstWhere('key', 'display_name');
                    @endphp
                    <div class="lang_form {{ $langCode != $defaultLang ? 'd-none' : '' }}" id="{{ $langCode }}-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">
                                        {{ translate('messages.name') }} ({{ strtoupper($langCode) }})
                                        @if($langCode == $defaultLang) <span class="text-danger">*</span> @endif
                                    </label>
                                    <input type="text" 
                                           name="{{ $langCode == $defaultLang ? 'name' : 'name_'.$langCode }}" 
                                           class="form-control" 
                                           value="{{ $langCode == $defaultLang ? $zone->getRawOriginal('name') : ($nameTranslation->value ?? '') }}"
                                           placeholder="{{ translate('messages.enter_zone_name') }}"
                                           {{ $langCode == $defaultLang ? 'required' : '' }}
                                           {{ $langCode == 'ar' ? 'dir=rtl' : '' }}>
                                    <small class="text-muted">{{ translate('messages.internal_name_for_reference') }}</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label class="input-label">
                                        {{ translate('messages.display_name') }} ({{ strtoupper($langCode) }})
                                        @if($langCode == $defaultLang) <span class="text-danger">*</span> @endif
                                    </label>
                                    <input type="text" 
                                           name="{{ $langCode == $defaultLang ? 'display_name' : 'display_name_'.$langCode }}" 
                                           class="form-control" 
                                           value="{{ $langCode == $defaultLang ? $zone->getRawOriginal('display_name') : ($displayNameTranslation->value ?? '') }}"
                                           placeholder="{{ translate('messages.enter_display_name') }}"
                                           {{ $langCode == $defaultLang ? 'required' : '' }}
                                           {{ $langCode == 'ar' ? 'dir=rtl' : '' }}>
                                    <small class="text-muted">{{ translate('messages.shown_to_users_in_app') }}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="form-group text-right mt-3">
                    <a href="{{ route('admin.places.zones.index') }}" class="btn btn-secondary">{{ translate('messages.cancel') }}</a>
                    <button type="submit" class="btn btn-primary">{{ translate('messages.update') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script>
    "use strict";
    document.querySelectorAll('.lang_link').forEach(function(link) {
        link.addEventListener('click', function() {
            document.querySelectorAll('.lang_link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.lang_form').forEach(f => f.classList.add('d-none'));
            this.classList.add('active');
            let lang = this.id.replace('-link', '');
            document.getElementById(lang + '-form').classList.remove('d-none');
        });
    });
</script>
@endpush
