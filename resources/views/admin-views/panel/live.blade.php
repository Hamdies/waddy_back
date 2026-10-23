<!DOCTYPE html>
<html lang="{{ $boot['lang'] }}" dir="{{ $boot['lang'] === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Waddy Live Ops</title>
    <link rel="icon" type="image/png" href="{{ asset('public/assets/admin/panel/waddy-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha384-sHL9NAb7lN7rfvG5lfHpm643Xkcjzp4jFvuavGOndn6pjVqS6ny56CAt3nsEVT4H" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/panel/live.css') }}?v={{ @filemtime(public_path('assets/admin/panel/live.css')) }}">
</head>
<body>
<div id="app"><div class="loading">…</div></div>
<script>
    window.LIVE = @json($boot);
    window.LIVE.logo = @json(asset('public/assets/admin/panel/waddy-logo.png'));
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha384-cxOPjt7s7Iz04uaHJceBmS+qpjv2JkIHNVcuOrM+YHwZOmJGBXI00mdUXEq65HTH" crossorigin="anonymous"></script>
<script src="{{ asset('public/assets/admin/panel/live.js') }}?v={{ @filemtime(public_path('assets/admin/panel/live.js')) }}"></script>
</body>
</html>
