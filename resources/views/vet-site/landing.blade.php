@extends('vet-site.layout')

@section('title', translate('messages.vs_landing_title'))

@section('content')
@include('vet-site.partials._topbar', ['cta' => true])

<main>
    <section class="hero">
        <div class="wrap hero-grid">
            <div>
                <span class="label">{{ translate('messages.vs_eyebrow') }}</span>
                <h1>{{ translate('messages.vs_hero_title') }}</h1>
                <p class="hero-lede">{{ translate('messages.vs_hero_lede') }}</p>
                <div class="hero-cta" id="heroCta">
                    <a class="btn btn--teal btn--block" href="{{ route('vet-clinic.create') }}" style="max-width: 320px;">
                        {{ translate('messages.vs_cta_apply') }}
                        @include('vet-site.partials._icon', ['name' => 'arrow-right', 'class' => 'vi-flip'])
                    </a>
                    <a class="text-link" href="#how">{{ translate('messages.vs_cta_how') }}</a>
                </div>
                <ul class="facts">
                    @foreach(['vs_fact_free', 'vs_fact_direct', 'vs_fact_reviewed'] as $fact)
                        <li>@include('vet-site.partials._icon', ['name' => 'check']){{ translate('messages.' . $fact) }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="hero-visual">
                @include('vet-site.partials._phone')
            </div>
        </div>
    </section>

    <section class="section section--tint" id="how">
        <div class="wrap split">
            <div>
                <h2>{{ translate('messages.vs_why_title') }}</h2>
                <ul class="rows">
                    @foreach([
                        ['navigation', 'vs_b1_title', 'vs_b1_body'],
                        ['phone', 'vs_b2_title', 'vs_b2_body'],
                        ['paw-print', 'vs_b3_title', 'vs_b3_body'],
                    ] as [$icon, $title, $body])
                        <li class="row">
                            <span class="row-icon">@include('vet-site.partials._icon', ['name' => $icon])</span>
                            <div>
                                <h3>{{ translate('messages.' . $title) }}</h3>
                                <p>{{ translate('messages.' . $body) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h2>{{ translate('messages.vs_how_title') }}</h2>
                <ol class="steps">
                    @foreach([['vs_s1_title', 'vs_s1_body'], ['vs_s2_title', 'vs_s2_body'], ['vs_s3_title', 'vs_s3_body']] as [$title, $body])
                        <li><div><b>{{ translate('messages.' . $title) }}</b><span>{{ translate('messages.' . $body) }}</span></div></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="wrap split">
            <div>
                <h2>{{ translate('messages.vs_faq_title') }}</h2>
            </div>
            <div class="faq">
                @foreach(['1', '2', '3', '4'] as $n)
                    <details>
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
    </section>

    <section class="section" style="padding-top: 0;">
        <div class="wrap">
            <div class="closing">
                <div>
                    <h2>{{ translate('messages.vs_band_title') }}</h2>
                    <p>{{ translate('messages.vs_band_body') }}</p>
                </div>
                <a class="btn btn--teal" href="{{ route('vet-clinic.create') }}">
                    {{ translate('messages.vs_cta_apply') }}
                    @include('vet-site.partials._icon', ['name' => 'arrow-right', 'class' => 'vi-flip'])
                </a>
            </div>
        </div>
    </section>
</main>

<div class="sticky-cta" id="stickyCta">
    <a class="btn btn--block" href="{{ route('vet-clinic.create') }}">{{ translate('messages.vs_cta_apply') }}</a>
</div>
@endsection

@push('scripts')
<script>
    // Phones: show the bottom apply bar once the hero button has scrolled out of view.
    (function () {
        var bar = document.getElementById('stickyCta'), hero = document.getElementById('heroCta');
        if (!bar || !hero || !('IntersectionObserver' in window)) return;
        new IntersectionObserver(function (entries) {
            bar.classList.toggle('is-on', !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0);
        }).observe(hero);
    })();
</script>
@endpush
