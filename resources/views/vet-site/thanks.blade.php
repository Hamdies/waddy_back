@extends('vet-site.layout')

@section('title', translate('messages.vet_apply_thanks_title'))

@section('content')
<div class="hero-shell">
    <section class="hero">
        @include('vet-site.partials._topbar')
        <div class="wrap thanks">
            <div>
                <div style="position: relative; display: inline-block;">
                    @include('vet-site.partials._sparkle', ['style' => 'width:40px;height:40px;top:0;inset-inline-start:-36px;'])
                    @include('vet-site.partials._sparkle', ['style' => 'width:24px;height:24px;top:40px;inset-inline-end:-30px;animation-delay:.9s;'])
                    @include('vet-site.partials._mascot')
                </div>
                <h1>{{ translate('messages.vs_thanks_title') }}</h1>
                <p>
                    @if($clinicName)
                        {{ str_replace(':name', $clinicName, translate('messages.vs_thanks_body_named')) }}
                    @else
                        {{ translate('messages.vs_thanks_body') }}
                    @endif
                </p>
                <div class="thanks-next">
                    @foreach([['vs_s2_title', 'vs_s2_body'], ['vs_s3_title', 'vs_s3_body'], ['vs_thanks_next3', 'vs_thanks_next3_body']] as [$t, $b])
                        <div><b>{{ translate('messages.' . $t) }}</b><span>{{ translate('messages.' . $b) }}</span></div>
                    @endforeach
                </div>
                <div class="hero-cta" style="justify-content: center;">
                    <a class="btn btn--teal btn--lg" href="{{ route('vet-clinic.landing') }}">{{ translate('messages.vet_apply_back_home') }}</a>
                    <a class="link-btn" href="{{ route('vet-clinic.create') }}">{{ translate('messages.vet_apply_another') }}</a>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
