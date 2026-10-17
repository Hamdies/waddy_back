@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $otherLang = $rtl ? 'en' : 'ar';
@endphp
<!doctype html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@yield('title') · Waddy Vets</title>
    <meta name="description" content="{{ translate('messages.vs_meta_description') }}">
    <meta name="theme-color" content="#1EF2A0">
    <meta property="og:title" content="@yield('title') · Waddy Vets">
    <meta property="og:description" content="{{ translate('messages.vs_meta_description') }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="#1EF2A0"/><text x="32" y="45" text-anchor="middle" font-family="Arial Black,Arial" font-weight="900" font-size="36" fill="#134E4A">W</text></svg>') }}">
    <link rel="preload" href="{{ asset('public/assets/vet/fonts/alexandria-900.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('public/assets/vet/fonts/thmanyah-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="{{ asset('public/assets/vet/vet.css') }}?v={{ @filemtime(public_path('assets/vet/vet.css')) }}">
    @stack('head')
</head>
<body class="@yield('body_class')">
    @yield('content')

    <footer class="footer">
        <div class="wrap">
            <a class="brand" href="{{ route('vet-clinic.landing') }}">
                @include('vet-site.partials._mark', ['class' => 'brand-mark'])
                <span class="brand-name">Waddy</span>
            </a>
            <nav aria-label="{{ translate('messages.vs_footer_nav') }}">
                <a href="{{ route('vet-clinic.landing') }}">{{ translate('messages.vs_nav_vets') }}</a>
                <a href="{{ route('home') }}">{{ translate('messages.vs_nav_main_site') }}</a>
                <a href="{{ route('privacy-policy') }}">{{ translate('messages.vs_nav_privacy') }}</a>
                <a href="{{ route('terms-and-conditions') }}">{{ translate('messages.vs_nav_terms') }}</a>
            </nav>
            <span>© {{ date('Y') }} Waddy</span>
        </div>
    </footer>
    @stack('scripts')
</body>
</html>
