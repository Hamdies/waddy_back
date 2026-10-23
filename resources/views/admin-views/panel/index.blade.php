<!DOCTYPE html>
<html lang="{{ $boot['lang'] }}" dir="{{ $boot['lang'] === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Waddy Admin</title>
    <link rel="icon" type="image/png" href="{{ asset('public/assets/admin/panel/waddy-logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alexandria:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/panel/panel.css') }}?v={{ @filemtime(public_path('assets/admin/panel/panel.css')) }}">
</head>
<body>
<div id="app"><div class="loading">…</div></div>
<script>
    window.PANEL = @json($boot);
    window.PANEL.logo = @json(asset('public/assets/admin/panel/waddy-logo.png'));
</script>
<script src="{{ asset('public/assets/admin/panel/panel.js') }}?v={{ @filemtime(public_path('assets/admin/panel/panel.js')) }}"></script>
</body>
</html>
