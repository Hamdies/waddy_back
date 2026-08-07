@php
    $locale  = app()->getLocale();
    $isRtl   = $locale === 'ar';
    $result  = session('redeem_result');
    $message = session('redeem_message');
    $prize   = session('redeem_prize');
    $ok      = $result === 'ok';
    $codeValue = session('redeem_attempted') ?? ($prefill ?? '');
    $langHref  = request()->fullUrlWithQuery(['lang' => $isRtl ? 'en' : 'ar']);
    $cap = $prize['value_cap'] ?? $venue->effective_prize_value_cap;
    $capText = $cap ? rtrim(rtrim(number_format((float) $cap, 2), '0'), '.') : null;
    $currency = $prize['currency'] ?? config('placestovisit.prize.currency', 'EGP');
    $validity = config('placestovisit.prize.validity_days', 7);
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
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Regular.otf') }}") format("opentype"); font-weight:400; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Medium.otf') }}") format("opentype"); font-weight:500; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Bold.otf') }}") format("opentype"); font-weight:700; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Black.otf') }}") format("opentype"); font-weight:900; font-display:swap }

        :root {
            --mint:#1EF2A0; --mint-deep:#0FD98C; --teal:#134E4A; --teal-900:#0C3532;
            --red:#FF3B30; --green:#22C55E; --panel:#0E3532;
            --ink:#10312E; --ink-2:#3F5754; --ink-3:#6E8481;
            --paper:#FFFFFF; --paper-3:#E7ECEA; --border:#134E4A; --teal-100:#D3E0DE;
            --font-display:"Thmanyah Sans", system-ui, sans-serif;
        }

        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent }
        html, body { margin:0; overflow-x:hidden }
        body { font-family:var(--font-display); color:var(--ink); background:var(--paper); -webkit-font-smoothing:antialiased }
        a { color:var(--teal) }

        @keyframes pop { 0% { transform:scale(.6) rotate(-8deg); opacity:0 } 60% { transform:scale(1.08) rotate(2deg) } 100% { transform:scale(1) rotate(0); opacity:1 } }
        @keyframes rise { from { transform:translateY(16px); opacity:0 } to { transform:translateY(0); opacity:1 } }
        @keyframes shake { 0%,100% { transform:translateX(0) } 20% { transform:translateX(-7px) } 40% { transform:translateX(7px) } 60% { transform:translateX(-5px) } 80% { transform:translateX(5px) } }
        @keyframes tickerscroll { from { transform:translateX(0) } to { transform:translateX(-50%) } }
        @keyframes wavedrift { from { transform:translateX(0) } to { transform:translateX(-1200px) } }
        @keyframes spin { from { transform:rotate(0) } to { transform:rotate(360deg) } }
        @keyframes bob { 0%,100% { transform:translateY(0) rotate(var(--rot,0deg)) } 50% { transform:translateY(-9px) rotate(var(--rot,0deg)) } }

        /* ── NAV ── */
        .nav { position:relative; z-index:6; display:flex; align-items:center; justify-content:space-between; padding:14px 24px; background:var(--panel); border-bottom:3px solid var(--border) }
        .brand { display:flex; align-items:center; gap:10px; font-weight:900; font-size:18px; letter-spacing:.02em; color:#fff; text-transform:uppercase }
        .brand img { height:26px; display:block }
        .brand .accent { color:var(--mint) }
        .navvenue { font-weight:900; font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); background:var(--mint); border:2.5px solid var(--mint); border-radius:6px; padding:6px 11px; white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900) }

        /* ── TICKER ── */
        .ticker { position:relative; z-index:6; background:var(--panel); border-bottom:3px solid var(--border); overflow:hidden; padding:10px 0 }
        .tickertrack { display:flex; width:max-content; animation:tickerscroll 22s linear infinite }
        .tickertrack span { display:flex; align-items:center; gap:14px; padding:0 18px; font-weight:900; font-size:13px; letter-spacing:.05em; text-transform:uppercase; color:var(--mint); white-space:nowrap }
        .tickertrack span b { color:#fff }

        /* ══ THE LOUD STAGE ══ */
        .stage { position:relative; background:var(--mint); overflow:hidden; padding:52px 16px 84px }

        /* sunburst rays */
        .rays { position:absolute; top:-30%; left:50%; width:170vmax; height:170vmax; transform:translateX(-50%); z-index:0; opacity:.16; animation:spin 90s linear infinite; pointer-events:none }
        /* halftone dots */
        .halftone { position:absolute; inset:0; z-index:1; pointer-events:none; opacity:.28;
            background-image:radial-gradient(var(--teal) 1.6px, transparent 1.7px); background-size:15px 15px }
        /* the two drifting wave bands */
        .waveband { position:absolute; left:0; right:0; z-index:2; overflow:hidden; pointer-events:none;
            -webkit-mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent);
            mask-image:linear-gradient(90deg,transparent,#000 7%,#000 93%,transparent) }
        .waveband.a { top:26% }
        .waveband.b { bottom:12%; opacity:.75 }
        .waveband svg { display:block; animation:wavedrift 18s linear infinite }
        .waveband.b svg { animation-duration:26s; animation-direction:reverse }

        /* floating stickers */
        .float { position:absolute; z-index:3; font-weight:900; text-transform:uppercase; letter-spacing:.04em;
            border:3px solid var(--border); border-radius:8px; padding:9px 13px; font-size:12px; white-space:nowrap;
            box-shadow:4px 4px 0 0 var(--teal-900); animation:bob 5s ease-in-out infinite; pointer-events:none }
        .float.f1 { --rot:-7deg; top:9%;  inset-inline-start:6%;  background:var(--red);   color:#fff; animation-delay:0s }
        .float.f2 { --rot:8deg;  top:17%; inset-inline-end:7%;   background:var(--paper); color:var(--teal); animation-delay:.7s }
        .float.f3 { --rot:6deg;  bottom:16%; inset-inline-start:9%; background:var(--panel); color:var(--mint); animation-delay:1.4s }
        .float.f4 { --rot:-9deg; bottom:10%; inset-inline-end:8%; background:var(--paper); color:var(--teal); animation-delay:2.1s }
        .glyph { position:absolute; z-index:3; font-size:44px; line-height:1; animation:bob 6s ease-in-out infinite; pointer-events:none; opacity:.9 }
        .glyph.g1 { --rot:-12deg; top:30%; inset-inline-start:3%; animation-delay:.4s }
        .glyph.g2 { --rot:14deg;  top:38%; inset-inline-end:4%;  animation-delay:1.8s }
        @media (max-width:900px) { .float, .glyph { display:none } }

        /* ── HEAD ── */
        .head { position:relative; z-index:4; text-align:center; margin-bottom:38px }
        .head .logomark { height:40px; margin-bottom:14px; filter:drop-shadow(3px 3px 0 rgba(12,53,50,.35)) }
        .head .h1 {
            font-weight:900; font-size:clamp(38px, 11vw, 76px); line-height:.88; text-transform:uppercase;
            color:var(--paper); margin:0; letter-spacing:-.02em;
            -webkit-text-stroke:3px var(--teal);
            text-shadow:6px 6px 0 var(--teal-900);
            paint-order:stroke fill;
        }
        .head .h1 em { color:var(--mint); font-style:normal }
        .head p { margin:18px auto 0; max-width:430px; font-size:14px; font-weight:900; color:var(--teal); line-height:1.5; text-transform:uppercase; letter-spacing:.03em }

        /* ── CARD ── */
        .cardwrap { position:relative; z-index:5; display:flex; justify-content:center }
        .redeemcard {
            width:100%; max-width:520px; padding:38px 28px 32px; text-align:center; position:relative;
            background:var(--paper); border:4px solid var(--border); border-radius:14px;
            box-shadow:10px 10px 0 0 var(--teal-900);
        }
        .cardtab {
            position:absolute; top:-19px; left:50%; transform:translateX(-50%) rotate(-2deg);
            background:var(--panel); color:var(--mint); border:3px solid var(--border); border-radius:8px;
            padding:7px 16px; font-weight:900; font-size:11px; letter-spacing:.14em; text-transform:uppercase;
            white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900);
        }
        .rc-title {
            font-weight:900; font-size:clamp(30px, 8vw, 40px); line-height:.95; text-transform:uppercase;
            color:var(--ink); margin:14px 0 10px; letter-spacing:-.015em;
        }
        .rc-title u { text-decoration:none; color:var(--mint); -webkit-text-stroke:2.5px var(--teal); paint-order:stroke fill }
        .rc-sub { font-size:13px; font-weight:700; color:var(--ink-3); line-height:1.5; margin:0 0 24px }

        .fieldlbl { text-align:start; font-weight:900; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-3); margin-bottom:8px }
        .codefield {
            width:100%; font-family:var(--font-display); font-weight:900;
            font-size:clamp(22px, 6.5vw, 27px); letter-spacing:.16em; text-transform:uppercase;
            color:var(--ink); background:var(--paper);
            border:4px solid var(--fieldborder, var(--border)); border-radius:10px;
            box-shadow:5px 5px 0 0 var(--fieldborder, var(--border));
            padding:19px 12px; outline:none; text-align:center; direction:ltr;
        }
        .codefield:focus { background:#F2FFF9; box-shadow:5px 5px 0 0 var(--mint-deep) }
        .codefield::placeholder { color:#C2CFCC; letter-spacing:.1em; font-weight:900; font-size:17px; text-transform:none }
        .codefield.err { animation:shake .4s }
        .msgrow { display:flex; align-items:flex-start; justify-content:center; gap:8px; margin-top:14px; min-height:18px }
        .msgrow.err { color:var(--red) }
        .msgrow .txt { font-weight:900; font-size:12.5px; letter-spacing:.02em; line-height:1.4 }
        .redeembtn {
            width:100%; margin-top:22px; border:4px solid var(--border); border-radius:12px;
            box-shadow:6px 6px 0 0 var(--teal-900); background:var(--mint); color:var(--teal);
            font-family:var(--font-display); font-weight:900; font-size:19px; letter-spacing:.05em;
            text-transform:uppercase; padding:19px; cursor:pointer;
        }
        .redeembtn:disabled { background:var(--paper-3); color:var(--ink-3); box-shadow:none; cursor:not-allowed; border-color:#B9C6C3 }
        .redeembtn:active:not(:disabled) { transform:translate(3px,3px); box-shadow:3px 3px 0 0 var(--teal-900) }
        .fine { text-align:center; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-3); margin-top:14px }

        /* ── WON ── */
        .wonbadge { width:100px; height:100px; margin:6px auto 0; border-radius:28px; border:4px solid var(--border); box-shadow:7px 7px 0 0 var(--mint); background:var(--teal); display:flex; align-items:center; justify-content:center; font-weight:900; font-size:48px; color:#fff; animation:pop .5s cubic-bezier(.2,1.3,.5,1) both }
        .wontitle { font-weight:900; font-size:clamp(30px,8vw,38px); line-height:.95; text-transform:uppercase; color:var(--ink); margin-top:20px; animation:rise .4s .08s both; letter-spacing:-.015em }
        .wonsub { font-size:13px; font-weight:700; color:var(--ink-3); margin-top:10px; animation:rise .4s .14s both; line-height:1.5 }
        .prize2 { position:relative; padding:24px; margin-top:26px; text-align:center; background:var(--mint); border:4px solid var(--border); border-radius:14px; box-shadow:6px 6px 0 0 var(--teal-900); animation:rise .4s .2s both }
        .prize2 .sticker { position:absolute; top:-16px; inset-inline-end:-12px; background:var(--red); color:#fff; font-weight:900; font-size:11px; letter-spacing:.05em; border:3px solid var(--border); border-radius:7px; padding:7px 11px; transform:rotate(7deg); white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900) }
        .prize2 .pname { font-weight:900; font-size:23px; color:var(--teal); line-height:1.1; text-transform:uppercase; letter-spacing:-.01em }
        .prize2 .ploc { font-size:12.5px; font-weight:900; color:var(--teal); opacity:.85; margin-top:8px; text-transform:uppercase; letter-spacing:.05em }
        .prize2 .pcode { margin-top:16px; font-weight:900; font-size:15px; letter-spacing:.14em; direction:ltr; color:var(--teal); background:var(--paper); border:3px solid var(--teal); border-radius:8px; padding:10px 16px; display:inline-block; box-shadow:3px 3px 0 0 var(--teal) }
        .wonactions { margin-top:24px; animation:rise .4s .28s both }
        .ghostbtn { display:block; width:100%; border:4px solid var(--border); border-radius:12px; background:transparent; color:var(--teal); font-family:var(--font-display); font-weight:900; font-size:15px; letter-spacing:.05em; text-transform:uppercase; padding:15px; cursor:pointer; text-decoration:none; text-align:center }
        .ghostbtn:active { transform:translate(2px,2px) }

        /* ── STRIP ── */
        .strip { position:relative; z-index:6; background:var(--panel); border-top:3px solid var(--border); display:flex; justify-content:center; padding:24px 16px }
        .stripin { display:flex; max-width:900px; width:100% }
        .chip { flex:1; text-align:center; padding:0 12px; font-weight:900; font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:#fff; border-inline-end:2px solid var(--teal-100) }
        .chip:last-child { border-inline-end:none }
        .chip b { display:block; color:var(--mint); font-size:26px; margin-bottom:4px; line-height:1 }

        /* ── FOOT ── */
        .foot { position:relative; z-index:6; background:var(--panel); padding:26px 24px }
        .footin { max-width:900px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap }
        .footbrand { display:flex; align-items:center; gap:9px; font-weight:900; font-size:15px; color:#fff; text-transform:uppercase }
        .footbrand img { height:20px }
        .footlinks { display:flex; gap:16px; flex-wrap:wrap }
        .footlinks a { color:#fff; opacity:.75; text-decoration:none; font-size:12px; font-weight:700 }
        .footlinks a:hover { opacity:1; color:var(--mint) }
        .copy { width:100%; font-size:11px; font-weight:700; color:#fff; opacity:.5; padding-top:14px; border-top:2px solid rgba(255,255,255,.14) }

        @media (max-width:520px) {
            .nav { padding:12px 16px }
            .navvenue { display:none }
            .stage { padding:36px 14px 60px }
            .chip { font-size:9.5px; padding:0 7px }
            .chip b { font-size:21px }
            .redeemcard { padding:32px 20px 26px }
        }
        @media (prefers-reduced-motion:reduce) {
            .rays, .waveband svg, .float, .glyph, .tickertrack { animation:none !important }
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

<div class="stage">

    {{-- ── background layers: rays → halftone → waves → floating objects ── --}}
    <svg class="rays" viewBox="0 0 100 100" aria-hidden="true">
        @for ($i = 0; $i < 24; $i++)
            <polygon points="50,50 {{ 50 + 60 * cos(deg2rad($i * 15)) }},{{ 50 + 60 * sin(deg2rad($i * 15)) }} {{ 50 + 60 * cos(deg2rad($i * 15 + 7.5)) }},{{ 50 + 60 * sin(deg2rad($i * 15 + 7.5)) }}"
                     fill="{{ $i % 2 ? '#134E4A' : 'transparent' }}"></polygon>
        @endfor
    </svg>
    <div class="halftone"></div>

    @php
        $wavePath = 'M0,90 Q150,20 300,90 Q450,160 600,90 Q750,20 900,90 Q1050,160 1200,90 Q1350,20 1500,90 Q1650,160 1800,90 Q1950,20 2100,90 Q2250,160 2400,90 Q2550,20 2700,90 Q2850,160 3000,90 Q3150,20 3300,90 Q3450,160 3600,90';
    @endphp
    <div class="waveband a">
        <svg width="3600" height="140" viewBox="0 0 3600 140" aria-hidden="true">
            <path d="{{ $wavePath }}" fill="none" stroke="#FFFFFF" stroke-width="60" stroke-linecap="round"></path>
            <path d="{{ $wavePath }}" fill="none" stroke="#134E4A" stroke-width="50" stroke-linecap="round"></path>
        </svg>
    </div>
    <div class="waveband b">
        <svg width="3600" height="140" viewBox="0 0 3600 140" aria-hidden="true">
            <path d="{{ $wavePath }}" fill="none" stroke="#0C3532" stroke-width="34" stroke-linecap="round"></path>
            <path d="{{ $wavePath }}" fill="none" stroke="#1EF2A0" stroke-width="24" stroke-linecap="round"></path>
        </svg>
    </div>

    <div class="float f1">🎁 {{ translate('messages.prize_free_item') }}</div>
    <div class="float f2">⏳ {{ $validity }} {{ translate('messages.days_validity') }}</div>
    <div class="float f3">★ {{ translate('messages.single_use_codes') }}</div>
    <div class="float f4">📍 {{ $venue->title }}</div>
    <div class="glyph g1">⚡</div>
    <div class="glyph g2">✦</div>

    {{-- ── head ── --}}
    <div class="head">
        <img class="logomark" src="{{ asset('assets/spots/waddi-logo.png') }}" alt="WADDI">
        <h1 class="h1">{!! translate('messages.crack_the_code_html') !!}</h1>
        <p>{{ translate('messages.redeem_head_sub') }}</p>
    </div>

    {{-- ── card ── --}}
    <div class="cardwrap">
        <div class="redeemcard">
            @if ($ok)
                <div class="cardtab">✓ {{ translate('messages.prize_redeemed') }}</div>
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
                <div class="cardtab">{{ translate('messages.enter_prize_code') }}</div>
                <h2 class="rc-title">{!! translate('messages.cash_it_in_html') !!}</h2>
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
</div>

<div class="strip">
    <div class="stripin">
        <div class="chip"><b>{{ $stats['outstanding'] }}</b>{{ translate('messages.outstanding') }}</div>
        <div class="chip"><b>{{ $stats['redeemed_total'] }}</b>{{ translate('messages.redeemed') }}</div>
        <div class="chip"><b>{{ $validity }}</b>{{ translate('messages.days_validity') }}</div>
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

        // A scanned QR lands here prefilled — caret to the end, not selected,
        // so a stray tap can't wipe it.
        var len = input.value.length;
        input.setSelectionRange(len, len);
    })();
</script>
</body>
</html>
