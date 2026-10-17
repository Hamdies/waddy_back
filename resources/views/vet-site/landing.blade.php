@extends('vet-site.layout')

@section('title', translate('messages.vs_landing_title'))

@section('content')
<div class="hero-shell">
    <section class="hero">
        @include('vet-site.partials._topbar', ['cta' => true])

        <div class="wrap hero-grid">
            <div>
                <span class="eyebrow">@include('vet-site.partials._icon', ['name' => 'stethoscope']) {{ translate('messages.vs_eyebrow') }}</span>
                <h1>{!! translate('messages.vs_hero_title') !!}</h1>
                <p class="hero-lede">{{ translate('messages.vs_hero_lede') }}</p>
                <div class="hero-cta">
                    <a class="btn btn--teal btn--lg" href="{{ route('vet-clinic.create') }}">
                        {{ translate('messages.vs_cta_apply') }}
                        @include('vet-site.partials._icon', ['name' => 'arrow-right', 'class' => 'vi-flip'])
                    </a>
                    <a class="link-btn" href="#how">{{ translate('messages.vs_cta_how') }}</a>
                </div>
                <ul class="facts">
                    @foreach(['vs_fact_free', 'vs_fact_direct', 'vs_fact_reviewed'] as $fact)
                        <li><span class="tick">@include('vet-site.partials._icon', ['name' => 'check'])</span>{{ translate('messages.' . $fact) }}</li>
                    @endforeach
                </ul>
            </div>

            <div class="hero-visual">
                <div class="paws" aria-hidden="true">
                    @foreach([[4, 8, -20], [78, 4, 15], [88, 62, -10], [10, 70, 25], [60, 90, -30]] as [$x, $y, $r])
                        <span style="position:absolute; left: {{ $x }}%; top: {{ $y }}%; transform: rotate({{ $r }}deg);">@include('vet-site.partials._icon', ['name' => 'paw-print'])</span>
                    @endforeach
                </div>
                @include('vet-site.partials._sparkle', ['style' => 'width:46px;height:46px;top:6%;inset-inline-start:12%;'])
                @include('vet-site.partials._sparkle', ['style' => 'width:26px;height:26px;top:16%;inset-inline-start:22%;animation-delay:.8s;'])
                @include('vet-site.partials._sparkle', ['style' => 'width:34px;height:34px;bottom:14%;inset-inline-end:6%;animation-delay:1.4s;'])
                <div class="sticker">{{ translate('messages.vs_sticker') }}<small>{{ translate('messages.vs_sticker_sub') }}</small></div>
                @include('vet-site.partials._phone')
                @include('vet-site.partials._mascot')
            </div>
        </div>
    </section>
</div>

<section class="section" id="why">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">{{ translate('messages.vs_why_kicker') }}</span>
            <h2 class="h2">{{ translate('messages.vs_why_title') }}</h2>
            <p class="lede">{{ translate('messages.vs_why_lede') }}</p>
        </div>
        <div class="benefits">
            @foreach([
                ['navigation', 'vs_b1_title', 'vs_b1_body'],
                ['phone', 'vs_b2_title', 'vs_b2_body'],
                ['paw-print', 'vs_b3_title', 'vs_b3_body'],
            ] as [$icon, $title, $body])
                <article class="benefit">
                    <div class="benefit-icon">@include('vet-site.partials._icon', ['name' => $icon])</div>
                    <h3>{{ translate('messages.' . $title) }}</h3>
                    <p>{{ translate('messages.' . $body) }}</p>
                </article>
            @endforeach
        </div>
    </div>
</section>

<section class="section" id="how" style="padding-top: 0;">
    <div class="wrap">
        <div class="section-head">
            <span class="kicker">{{ translate('messages.vs_how_kicker') }}</span>
            <h2 class="h2">{{ translate('messages.vs_how_title') }}</h2>
        </div>
        <ol class="steps">
            @foreach([['vs_s1_title', 'vs_s1_body'], ['vs_s2_title', 'vs_s2_body'], ['vs_s3_title', 'vs_s3_body']] as $i => [$title, $body])
                <li class="step">
                    <div class="step-num">{{ $i + 1 }}</div>
                    <h3>{{ translate('messages.' . $title) }}</h3>
                    <p>{{ translate('messages.' . $body) }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>

<section class="section" style="padding-top: 0;">
    <div class="wrap split">
        <div class="need">
            <h3>{{ translate('messages.vs_need_title') }}</h3>
            <p>{{ translate('messages.vs_need_lede') }}</p>
            <ul>
                @foreach([
                    ['store', 'vs_need_1', 'vs_need_1_sub'],
                    ['map-pin', 'vs_need_2', 'vs_need_2_sub'],
                    ['clock', 'vs_need_3', 'vs_need_3_sub'],
                    ['file-check', 'vs_need_4', 'vs_need_4_sub'],
                ] as [$icon, $title, $sub])
                    <li>
                        @include('vet-site.partials._icon', ['name' => $icon])
                        <div><b>{{ translate('messages.' . $title) }}</b><span>{{ translate('messages.' . $sub) }}</span></div>
                    </li>
                @endforeach
            </ul>
        </div>

        <div>
            <span class="kicker">{{ translate('messages.vs_faq_kicker') }}</span>
            <h2 class="h2" style="font-size: clamp(28px, 3.4vw, 42px);">{{ translate('messages.vs_faq_title') }}</h2>
            <div class="faq">
                @foreach(['1', '2', '3', '4'] as $n)
                    <details @if($n === '1') open @endif>
                        <summary>{{ translate('messages.vs_faq_q' . $n) }} @include('vet-site.partials._icon', ['name' => 'chevron-down'])</summary>
                        <p>{{ translate('messages.vs_faq_a' . $n) }}</p>
                    </details>
                @endforeach
                @if($supportPhone || $supportEmail)
                    <details>
                        <summary>{{ translate('messages.vs_faq_q5') }} @include('vet-site.partials._icon', ['name' => 'chevron-down'])</summary>
                        <p>
                            {{ translate('messages.vs_faq_a5') }}
                            @if($supportPhone)<a href="tel:{{ $supportPhone }}" dir="ltr">{{ $supportPhone }}</a>@endif
                            @if($supportPhone && $supportEmail) · @endif
                            @if($supportEmail)<a href="mailto:{{ $supportEmail }}">{{ $supportEmail }}</a>@endif
                        </p>
                    </details>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="section" style="padding-top: 0; padding-bottom: 40px;">
    <div class="wrap">
        <div class="cta-band">
            @include('vet-site.partials._mark', ['class' => 'bigmark'])
            <h2>{{ translate('messages.vs_band_title') }}</h2>
            <p>{{ translate('messages.vs_band_body') }}</p>
            <a class="btn btn--lg" href="{{ route('vet-clinic.create') }}">
                {{ translate('messages.vs_cta_apply') }}
                @include('vet-site.partials._icon', ['name' => 'arrow-right', 'class' => 'vi-flip'])
            </a>
        </div>
    </div>
</section>
@endsection
