{{-- Top bar. $cta: show the Apply button (wide screens; phones get the sticky bar). --}}
@php($otherLang = app()->getLocale() === 'ar' ? 'en' : 'ar')
<header class="topbar">
    <div class="wrap">
        <a class="brand" href="{{ route('vet-clinic.landing') }}" aria-label="Waddy Vets">
            @include('vet-site.partials._mark', ['class' => 'brand-mark'])
            <span class="brand-name">Waddy<small>{{ translate('messages.vs_badge_vets') }}</small></span>
        </a>
        <div class="topbar-actions">
            <a class="lang" href="{{ request()->fullUrlWithQuery(['lang' => $otherLang]) }}" hreflang="{{ $otherLang }}" lang="{{ $otherLang }}">
                @include('vet-site.partials._icon', ['name' => 'globe'])
                {{ $otherLang === 'ar' ? 'عربي' : 'English' }}
            </a>
            @if($cta ?? false)
                <a class="btn btn--teal" href="{{ route('vet-clinic.create') }}">{{ translate('messages.vs_cta_apply_short') }}</a>
            @endif
        </div>
    </div>
</header>
