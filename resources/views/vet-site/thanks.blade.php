@extends('vet-site.layout')

@section('title', translate('messages.vet_apply_thanks_title'))
@section('footer_class', 'is-apply')

@section('content')
@include('vet-site.partials._topbar')

<main class="wrap thanks">
    <div class="thanks-icon">@include('vet-site.partials._icon', ['name' => 'check'])</div>
    <h1>{{ translate('messages.vs_thanks_title') }}</h1>
    <p>
        @if($clinicName)
            {{ str_replace(':name', $clinicName, translate('messages.vs_thanks_body_named')) }}
        @else
            {{ translate('messages.vs_thanks_body') }}
        @endif
    </p>
    <div class="actions">
        <a class="btn btn--block" href="{{ route('vet-clinic.landing') }}">{{ translate('messages.vet_apply_back_home') }}</a>
        <a class="text-link" href="{{ route('vet-clinic.create') }}">{{ translate('messages.vet_apply_another') }}</a>
    </div>
</main>
@endsection
