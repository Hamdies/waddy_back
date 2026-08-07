@php
    $locale  = app()->getLocale();
    $isRtl   = $locale === 'ar';
    $result  = session('redeem_result');
    $message = session('redeem_message');
    $prize   = session('redeem_prize');
    $ok      = $result === 'ok';
    // A failed attempt keeps its code in the field; a fresh scan prefills it.
    $codeValue = session('redeem_attempted') ?? ($prefill ?? '');
    $langHref  = request()->fullUrlWithQuery(['lang' => $isRtl ? 'en' : 'ar']);
    $cap = $prize['value_cap'] ?? $venue->effective_prize_value_cap;
    $capText = $cap ? rtrim(rtrim(number_format((float) $cap, 2), '0'), '.') : null;
    $currency = $prize['currency'] ?? config('placestovisit.prize.currency', 'EGP');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0E3532">
    <title>{{ translate('messages.redeem_waddi_spots_prize') }} — {{ $venue->title }}</title>
    <link rel="icon" href="{{ asset('assets/spots/waddi-logo.png') }}">
    <style>
        /* ── Thmanyah Sans — same family the app ships ── */
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Regular.otf') }}") format("opentype"); font-weight:400; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Medium.otf') }}") format("opentype"); font-weight:500; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Bold.otf') }}") format("opentype"); font-weight:700; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Black.otf') }}") format("opentype"); font-weight:900; font-display:swap }

        /* ── WADDI Spots tokens ── */
        :root {
            --mint:#1EF2A0; --teal:#134E4A; --teal-900:#0C3532; --red:#FF3B30; --green:#22C55E;
            --panel:#0E3532; --ink:#10312E; --ink-2:#3F5754; --ink-3:#6E8481;
            --paper:#FFFFFF; --paper-2:#F4F3EE; --paper-3:#E7ECEA; --border:#134E4A;
            --mint-100:#D6FCEC; --red-100:#FFE1DF; --teal-100:#D3E0DE;
            --font-display:"Thmanyah Sans", system-ui, sans-serif;
        }

        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent }
        html, body { margin:0 }
        body {
            font-family:var(--font-display);
            color:var(--ink); background:var(--paper);
            -webkit-font-smoothing:antialiased;
        }
        a { color:var(--teal) }

        @keyframes pop {
            0%   { transform:scale(.6) rotate(-8deg); opacity:0 }
            60%  { transform:scale(1.08) rotate(2deg) }
            100% { transform:scale(1) rotate(0); opacity:1 }
        }
        @keyframes rise {
            from { transform:translateY(16px); opacity:0 }
            to   { transform:translateY(0); opacity:1 }
        }
        @keyframes shake {
            0%,100% { transform:translateX(0) }
            20%  { transform:translateX(-7px) }
            40%  { transform:translateX(7px) }
            60%  { transform:translateX(-5px) }
            80%  { transform:translateX(5px) }
        }
        @keyframes tickerscroll {
            from { transform:translateX(0) }
            to   { transform:translateX(-50%) }
        }

        /* ── NAV ── */
        .nav {
            display:flex; align-items:center; justify-content:space-between;
            padding:14px 24px; background:var(--panel); border-bottom:3px solid var(--border);
        }
        .brand {
            display:flex; align-items:center; gap:10px; font-weight:900; font-size:18px;
            letter-spacing:.02em; color:#fff; text-transform:uppercase;
        }
        .brand img { height:26px; display:block }
        .brand .accent { color:var(--mint) }
        .navvenue {
            font-weight:700; font-size:11px; letter-spacing:.1em; text-transform:uppercase;
            color:var(--mint); border:2px solid var(--mint); border-radius:6px; padding:6px 10px;
            white-space:nowrap;
        }

        /* ── TICKER ── */
        .ticker {
            background:var(--panel); border-bottom:3px solid var(--border); overflow:hidden; padding:10px 0;
            -webkit-mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);
            mask-image:linear-gradient(90deg,transparent,#000 6%,#000 94%,transparent);
        }
        .tickertrack { display:flex; width:max-content; animation:tickerscroll 20s linear infinite }
        .tickertrack span {
            display:flex; align-items:center; gap:14px; padding:0 18px;
            font-weight:900; font-size:13px; letter-spacing:.05em; text-transform:uppercase;
            color:var(--mint); white-space:nowrap;
        }
        .tickertrack span b { color:#fff }

        /* ── HEAD ── */
        .plainhead { padding:44px 20px 8px; text-align:center; background:var(--mint) }
        .plainhead .logomark { height:40px; margin-bottom:14px }
        .plainhead .h1 {
            font-weight:900; font-size:clamp(34px, 9vw, 52px); line-height:.96;
            text-transform:uppercase; color:var(--teal); margin:0;
        }
        .plainhead .h1 em { color:var(--panel); font-style:normal }
        .plainhead p {
            margin:14px auto 0; max-width:440px; font-size:14px; font-weight:700;
            color:var(--teal); opacity:.75; line-height:1.5;
        }

        /* ── REDEEM ── */
        .redeemwrap {
            background:var(--mint); padding:44px 16px 72px;
            display:flex; justify-content:center; position:relative; overflow:hidden;
        }
        .wavebg {
            position:absolute; top:50%; left:0; right:0; transform:translateY(-50%);
            overflow:hidden; z-index:1; opacity:.9;
            -webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);
            mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);
        }
        .redeemcard {
            width:100%; max-width:520px; padding:36px 28px 32px; text-align:center;
            position:relative; z-index:2; background:var(--paper);
            border:3px solid var(--border); border-radius:12px;
            box-shadow:8px 8px 0 0 var(--border);
        }
        .rc-kicker {
            font-weight:700; font-size:11px; letter-spacing:.14em; text-transform:uppercase;
            color:var(--ink-3); margin-bottom:10px;
        }
        .rc-title {
            font-weight:900; font-size:clamp(26px, 7vw, 32px); line-height:1;
            text-transform:uppercase; color:var(--ink); margin:0 0 10px;
        }
        .rc-sub { font-size:13.5px; font-weight:700; color:var(--ink-3); line-height:1.5; margin:0 0 26px }
        .fieldlbl {
            text-align:start; font-weight:700; font-size:11px; letter-spacing:.1em;
            text-transform:uppercase; color:var(--ink-3); margin-bottom:8px;
        }
        .codefield {
            width:100%; font-family:var(--font-display); font-weight:900;
            font-size:clamp(20px, 6vw, 24px); letter-spacing:.16em; text-transform:uppercase;
            color:var(--ink); background:var(--paper);
            border:3px solid var(--fieldborder, var(--border)); border-radius:9px;
            box-shadow:4px 4px 0 0 var(--fieldborder, var(--border));
            padding:18px 12px; outline:none; text-align:center;
            direction:ltr; /* the code is ASCII whatever the page language */
        }
        .codefield::placeholder { color:var(--ink-3); letter-spacing:.06em; font-weight:700; font-size:15px; text-transform:none }
        .codefield.err { animation:shake .4s }
        .msgrow {
            display:flex; align-items:flex-start; justify-content:center; gap:8px;
            margin-top:14px; min-height:18px;
        }
        .msgrow.err { color:var(--red) }
        .msgrow .txt { font-weight:900; font-size:12px; letter-spacing:.03em; line-height:1.4 }
        .redeembtn {
            width:100%; margin-top:24px; border:3px solid var(--border); border-radius:11px;
            box-shadow:5px 5px 0 0 var(--border); background:var(--mint); color:var(--teal);
            font-family:var(--font-display); font-weight:900; font-size:17px; letter-spacing:.04em;
            text-transform:uppercase; padding:17px; cursor:pointer;
        }
        .redeembtn:disabled { background:var(--paper-3); color:var(--ink-3); box-shadow:none; cursor:not-allowed }
        .redeembtn:active:not(:disabled) { transform:translate(2px,2px); box-shadow:2px 2px 0 0 var(--border) }
        .fine { text-align:center; font-size:11px; font-weight:700; color:var(--ink-3); margin-top:14px }

        /* ── WON ── */
        .wonbadge {
            width:96px; height:96px; margin:0 auto; border-radius:26px;
            border:3px solid var(--border); box-shadow:6px 6px 0 0 var(--mint);
            background:var(--teal); display:flex; align-items:center; justify-content:center;
            font-weight:900; font-size:44px; color:#fff;
            animation:pop .5s cubic-bezier(.2,1.3,.5,1) both;
        }
        .wontitle {
            font-weight:900; font-size:30px; line-height:1; text-transform:uppercase;
            color:var(--ink); margin-top:20px; animation:rise .4s .08s both;
        }
        .wonsub { font-size:13px; font-weight:700; color:var(--ink-3); margin-top:8px; animation:rise .4s .14s both }
        .prize2 {
            position:relative; padding:22px; margin-top:26px; text-align:center;
            background:var(--mint); border:3px solid var(--border); border-radius:12px;
            box-shadow:5px 5px 0 0 var(--border); animation:rise .4s .2s both;
        }
        .prize2 .sticker {
            position:absolute; top:-14px; inset-inline-end:-10px; background:var(--red); color:#fff;
            font-weight:900; font-size:10px; letter-spacing:.04em; border:2.5px solid var(--border);
            border-radius:6px; padding:6px 9px; transform:rotate(6deg); white-space:nowrap;
        }
        .prize2 .pname { font-weight:900; font-size:21px; color:var(--teal); line-height:1.15 }
        .prize2 .ploc { font-size:12px; font-weight:700; color:var(--teal); opacity:.8; margin-top:6px }
        .prize2 .pcode {
            margin-top:14px; font-weight:900; font-size:13px; letter-spacing:.1em; direction:ltr;
            color:var(--teal); background:rgba(19,78,74,.1); border:2px solid var(--teal);
            border-radius:6px; padding:9px 12px; display:inline-block;
        }
        .wonactions { margin-top:24px; animation:rise .4s .28s both }
        .ghostbtn {
            display:block; width:100%; border:3px solid var(--border); border-radius:11px;
            background:transparent; color:var(--teal); font-family:var(--font-display);
            font-weight:900; font-size:15px; letter-spacing:.03em; text-transform:uppercase;
            padding:14px; cursor:pointer; text-decoration:none; text-align:center;
        }

        /* ── STRIP ── */
        .strip {
            background:var(--panel); border-top:3px solid var(--border);
            display:flex; justify-content:center; padding:22px 16px;
        }
        .stripin { display:flex; max-width:900px; width:100% }
        .chip {
            flex:1; text-align:center; padding:0 12px; font-weight:900; font-size:11px;
            letter-spacing:.05em; text-transform:uppercase; color:#fff;
            border-inline-end:2px solid var(--teal-100);
        }
        .chip:last-child { border-inline-end:none }
        .chip b { display:block; color:var(--mint); font-size:20px; margin-bottom:4px }

        /* ── FOOT ── */
        .foot { background:var(--panel); padding:28px 24px }
        .footin {
            max-width:900px; margin:0 auto; display:flex; align-items:center;
            justify-content:space-between; gap:16px; flex-wrap:wrap;
        }
        .footbrand { display:flex; align-items:center; gap:9px; font-weight:900; font-size:15px; color:#fff; text-transform:uppercase }
        .footbrand img { height:20px }
        .footlinks { display:flex; gap:16px; flex-wrap:wrap }
        .footlinks a { color:#fff; opacity:.75; text-decoration:none; font-size:12px; font-weight:700 }
        .footlinks a:hover { opacity:1; color:var(--mint) }
        .copy { width:100%; font-size:11px; font-weight:700; color:#fff; opacity:.5; padding-top:14px; border-top:2px solid rgba(255,255,255,.14) }

        @media (max-width:520px) {
            .nav { padding:12px 16px }
            .navvenue { display:none }
            .chip { font-size:10px; padding:0 8px }
            .chip b { font-size:17px }
        }
    </style>
</head>
<body>

<div class="nav">
    <div class="brand">
        <img src="{{ asset('assets/spots/waddi-logo.png') }}" alt="WADDI">
        WADDI <span class="accent">SPOTS</span>
    </div>
    <div class="navvenue">{{ $venue->title }}</div>
</div>

<div class="ticker">
    <div class="tickertrack">
        @php
            $tick = strtoupper($venue->title) . ' &nbsp;<b>·</b>&nbsp; '
                . translate('messages.staff_redemption_page') . ' &nbsp;<b>·</b>&nbsp; '
                . translate('messages.single_use_codes') . ' &nbsp;<b>·</b>&nbsp; ';
        @endphp
        <span>{!! $tick !!}{!! $tick !!}</span><span>{!! $tick !!}{!! $tick !!}</span>
    </div>
</div>

<div class="plainhead">
    <img class="logomark" src="{{ asset('assets/spots/waddi-logo.png') }}" alt="WADDI">
    <h1 class="h1">{!! translate('messages.crack_the_code_html') !!}</h1>
    <p>{{ translate('messages.redeem_head_sub') }}</p>
</div>

<div class="redeemwrap">
    <div class="wavebg">
        <svg width="3600" height="140" viewBox="0 0 3600 140" aria-hidden="true">
            <defs>
                <path id="wp1" d="M0,90 Q150,20 300,90 Q450,160 600,90 Q750,20 900,90 Q1050,160 1200,90 Q1350,20 1500,90 Q1650,160 1800,90 Q1950,20 2100,90 Q2250,160 2400,90 Q2550,20 2700,90 Q2850,160 3000,90 Q3150,20 3300,90 Q3450,160 3600,90" fill="none"></path>
            </defs>
            <path d="M0,90 Q150,20 300,90 Q450,160 600,90 Q750,20 900,90 Q1050,160 1200,90 Q1350,20 1500,90 Q1650,160 1800,90 Q1950,20 2100,90 Q2250,160 2400,90 Q2550,20 2700,90 Q2850,160 3000,90 Q3150,20 3300,90 Q3450,160 3600,90" fill="none" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round"></path>
            <path d="M0,90 Q150,20 300,90 Q450,160 600,90 Q750,20 900,90 Q1050,160 1200,90 Q1350,20 1500,90 Q1650,160 1800,90 Q1950,20 2100,90 Q2250,160 2400,90 Q2550,20 2700,90 Q2850,160 3000,90 Q3150,20 3300,90 Q3450,160 3600,90" fill="none" stroke="#134E4A" stroke-width="50" stroke-linecap="round"></path>
        </svg>
    </div>

    <div class="redeemcard">
        @if ($ok)
            {{-- ── Redeemed ── --}}
            <div class="wonbadge">✓</div>
            <div class="wontitle">{{ translate('messages.prize_redeemed') }}</div>
            <div class="wonsub">{{ $message }}</div>

            <div class="prize2">
                @if (!empty($prize['expires_at']))
                    <span class="sticker">⏳ {{ translate('messages.expires') }} {{ $prize['expires_at'] }}</span>
                @endif
                <div class="pname">
                    @if ($capText)
                        {{ translate('messages.give_one_free_item_up_to') }} {{ $capText }} {{ $currency }}
                    @else
                        {{ translate('messages.prize_free_item') }}
                    @endif
                </div>
                <div class="ploc">📍 {{ $venue->title }}</div>
                @if (!empty($prize['code']))
                    <div class="pcode">{{ $prize['code'] }}</div>
                @endif
            </div>

            <div class="wonactions">
                <a class="ghostbtn" href="{{ route('spots.redeem', $token) }}{{ request('lang') ? '?lang=' . request('lang') : '' }}">
                    {{ translate('messages.redeem_another_code') }}
                </a>
            </div>
        @else
            {{-- ── Entry (and every failure state) ── --}}
            <div class="rc-kicker">{{ translate('messages.enter_prize_code') }}</div>
            <h2 class="rc-title">{{ translate('messages.cash_it_in') }}</h2>
            <p class="rc-sub">{{ translate('messages.redeem_card_sub') }}</p>

            <form method="POST"
                  action="{{ route('spots.redeem.submit', $token) }}{{ request('lang') ? '?lang=' . request('lang') : '' }}"
                  autocomplete="off">
                @csrf
                <div class="fieldlbl">{{ translate('messages.code') }}</div>
                <div style="{{ $result ? '--fieldborder:var(--red)' : '' }}">
                    <input id="code" class="codefield {{ $result ? 'err' : '' }}" name="code"
                           value="{{ $codeValue }}" placeholder="XXXX-XXXX" maxlength="9"
                           inputmode="text" autocapitalize="characters" autocorrect="off"
                           spellcheck="false" required autofocus>
                </div>

                <div class="msgrow {{ $result ? 'err' : '' }}">
                    @if ($result)
                        <span class="txt">⚠ {{ $message }}</span>
                    @endif
                </div>

                <button type="submit" class="redeembtn" id="submitbtn">
                    {{ translate('messages.redeem_prize') }}
                </button>
            </form>

            <div class="fine">{{ translate('messages.single_use_codes') }}</div>
        @endif
    </div>
</div>

<div class="strip">
    <div class="stripin">
        <div class="chip"><b>{{ $stats['outstanding'] }}</b>{{ translate('messages.outstanding') }}</div>
        <div class="chip"><b>{{ $stats['redeemed_total'] }}</b>{{ translate('messages.redeemed') }}</div>
        <div class="chip"><b>{{ config('placestovisit.prize.validity_days', 7) }}</b>{{ translate('messages.days_validity') }}</div>
    </div>
</div>

<div class="foot">
    <div class="footin">
        <div class="footbrand">
            <img src="{{ asset('assets/spots/waddi-logo.png') }}" alt="WADDI">WADDI SPOTS
        </div>
        <div class="footlinks">
            <a href="{{ route('about-us') }}">{{ translate('messages.about_us') }}</a>
            <a href="{{ route('contact-us') }}">{{ translate('messages.contact_us') }}</a>
            <a href="{{ route('terms-and-conditions') }}">{{ translate('messages.terms_and_condition') }}</a>
            <a href="{{ $langHref }}">{{ $isRtl ? 'English' : 'العربية' }}</a>
        </div>
        <div class="copy">© {{ date('Y') }} WADDI Spots · {{ translate('messages.redeem_page_hint') }}</div>
    </div>
</div>

<script>
    // Cashiers read this off a cracked phone screen — uppercase it and insert
    // the dash for them so the field always matches what the winner sees.
    (function () {
        var input = document.getElementById('code');
        var btn = document.getElementById('submitbtn');
        if (!input) return;

        function sync() {
            var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            input.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
            if (btn) btn.disabled = raw.length === 0;
        }

        input.addEventListener('input', sync);
        sync();

        // A scanned QR lands here with the code already filled — put the
        // caret at the end rather than selecting, so a stray tap can't wipe it.
        var len = input.value.length;
        input.setSelectionRange(len, len);
    })();
</script>
</body>
</html>
