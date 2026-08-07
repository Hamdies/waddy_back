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
    $logoUrl  = asset('assets/spots/waddi-logo.png');
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0E3532">
    <title>{{ translate('messages.redeem_waddi_spots_prize') }} — {{ $venue->title }}</title>
    <link rel="icon" href="{{ $logoUrl }}">
    <style>
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Regular.otf') }}") format("opentype"); font-weight:400; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Medium.otf') }}") format("opentype"); font-weight:500; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Bold.otf') }}") format("opentype"); font-weight:700; font-display:swap }
        @font-face { font-family:"Thmanyah Sans"; src:url("{{ asset('assets/spots/fonts/thmanyahsans-Black.otf') }}") format("opentype"); font-weight:900; font-display:swap }

        :root {
            --mint:#1EF2A0; --mint-deep:#0FD98C; --teal:#134E4A; --teal-900:#0C3532;
            --red:#FF3B30; --panel:#0E3532;
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
        @keyframes wavedrift { from { transform:translateX(0) } to { transform:translateX(-1800px) } }

        /* ── NAV ── */
        .nav { position:relative; z-index:6; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 20px; background:var(--panel); border-bottom:3px solid var(--border) }
        .brand { display:flex; align-items:center; gap:9px; font-weight:900; font-size:17px; letter-spacing:.02em; color:#fff; text-transform:uppercase }
        /* Teal plate behind the mark — white-on-mint was washing it out */
        .brand .mark { height:30px; width:30px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; background:var(--mint); border:2.5px solid var(--mint); border-radius:7px }
        .brand .mark img { height:20px; display:block }
        .brand .accent { color:var(--mint) }
        .navvenue { font-weight:900; font-size:11px; letter-spacing:.1em; text-transform:uppercase; color:var(--teal); background:var(--mint); border:2.5px solid var(--mint); border-radius:6px; padding:6px 11px; white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900); overflow:hidden; text-overflow:ellipsis; max-width:46vw }

        /* ── TICKER ── */
        .ticker { position:relative; z-index:6; background:var(--panel); border-bottom:3px solid var(--border); overflow:hidden; padding:9px 0 }
        .tickertrack { display:flex; width:max-content; animation:tickerscroll 26s linear infinite }
        .tickertrack span { display:flex; align-items:center; gap:14px; padding:0 18px; font-weight:900; font-size:12px; letter-spacing:.05em; text-transform:uppercase; color:var(--mint); white-space:nowrap }
        .tickertrack span b { color:#fff }

        /* ══ STAGE ══ */
        .stage { position:relative; background:var(--mint); overflow:hidden; padding:0 0 34px }

        /* wave bands now CARRY content: repeating text + the WADDI mark */
        .waveband { position:relative; z-index:2; overflow:hidden; pointer-events:none; line-height:0;
            -webkit-mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent);
            mask-image:linear-gradient(90deg,transparent,#000 5%,#000 95%,transparent) }
        .waveband svg { display:block; animation:wavedrift 30s linear infinite }
        .waveband.b svg { animation-duration:38s; animation-direction:reverse }

        /* ── HEAD (compressed: the input has to win) ── */
        .head { position:relative; z-index:4; text-align:center; padding:26px 16px 4px }
        .head .h1 {
            font-weight:900; font-size:clamp(26px, 6vw, 34px); line-height:.95; text-transform:uppercase;
            color:var(--paper); margin:0; letter-spacing:-.015em;
            -webkit-text-stroke:2.5px var(--teal); text-shadow:4px 4px 0 var(--teal-900); paint-order:stroke fill;
        }
        .head .h1 em { color:var(--mint); font-style:normal }
        .head p { margin:10px auto 0; max-width:400px; font-size:12.5px; font-weight:900; color:var(--teal); line-height:1.4; text-transform:uppercase; letter-spacing:.04em; opacity:.8 }

        /* ── CARD ── */
        .cardwrap { position:relative; z-index:5; display:flex; justify-content:center; padding:0 16px }
        .redeemcard { width:100%; max-width:460px; padding:30px 24px 26px; text-align:center; position:relative;
            background:var(--paper); border:4px solid var(--border); border-radius:14px; box-shadow:9px 9px 0 0 var(--teal-900) }
        .cardtab { position:absolute; top:-18px; left:50%; transform:translateX(-50%) rotate(-2deg);
            background:var(--panel); color:var(--mint); border:3px solid var(--border); border-radius:8px;
            padding:7px 15px; font-weight:900; font-size:11px; letter-spacing:.14em; text-transform:uppercase;
            white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900) }
        .rc-title { font-weight:900; font-size:clamp(24px, 6.5vw, 30px); line-height:.95; text-transform:uppercase; color:var(--ink); margin:12px 0 6px; letter-spacing:-.015em }
        .rc-title u { text-decoration:none; color:var(--mint); -webkit-text-stroke:2.5px var(--teal); paint-order:stroke fill }
        .rc-sub { font-size:12.5px; font-weight:700; color:var(--ink-3); line-height:1.45; margin:0 0 20px }

        .fieldlbl { text-align:start; font-weight:900; font-size:11px; letter-spacing:.12em; text-transform:uppercase; color:var(--ink-3); margin-bottom:7px }
        .codefield { width:100%; font-family:var(--font-display); font-weight:900;
            font-size:clamp(22px, 6.5vw, 27px); letter-spacing:.16em; text-transform:uppercase;
            color:var(--ink); background:var(--paper);
            border:4px solid var(--fieldborder, var(--border)); border-radius:10px;
            box-shadow:5px 5px 0 0 var(--fieldborder, var(--border));
            padding:18px 12px; outline:none; text-align:center; direction:ltr }
        .codefield:focus { background:#F2FFF9; box-shadow:5px 5px 0 0 var(--mint-deep) }
        .codefield::placeholder { color:#C2CFCC; letter-spacing:.1em; font-weight:900; font-size:17px; text-transform:none }
        .codefield.err { animation:shake .4s }
        .msgrow { display:flex; align-items:flex-start; justify-content:center; gap:8px; margin-top:12px; min-height:16px }
        .msgrow.err { color:var(--red) }
        .msgrow .txt { font-weight:900; font-size:12.5px; letter-spacing:.02em; line-height:1.4 }
        .redeembtn { width:100%; margin-top:18px; border:4px solid var(--border); border-radius:12px;
            box-shadow:6px 6px 0 0 var(--teal-900); background:var(--mint); color:var(--teal);
            font-family:var(--font-display); font-weight:900; font-size:19px; letter-spacing:.05em;
            text-transform:uppercase; padding:18px; cursor:pointer }
        .redeembtn:disabled { background:var(--paper-3); color:var(--ink-3); box-shadow:none; cursor:not-allowed; border-color:#B9C6C3 }
        .redeembtn:active:not(:disabled) { transform:translate(3px,3px); box-shadow:3px 3px 0 0 var(--teal-900) }
        .redeembtn.busy { opacity:.75; pointer-events:none }
        .fine { text-align:center; font-size:11px; font-weight:900; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-3); margin-top:12px }

        /* ── WON ── */
        .wonbadge { width:92px; height:92px; margin:4px auto 0; border-radius:26px; border:4px solid var(--border); box-shadow:7px 7px 0 0 var(--mint); background:var(--teal); display:flex; align-items:center; justify-content:center; font-weight:900; font-size:44px; color:#fff; animation:pop .5s cubic-bezier(.2,1.3,.5,1) both }
        .wontitle { font-weight:900; font-size:clamp(26px,7vw,34px); line-height:.95; text-transform:uppercase; color:var(--ink); margin-top:16px; animation:rise .4s .08s both; letter-spacing:-.015em }
        .wonsub { font-size:12.5px; font-weight:700; color:var(--ink-3); margin-top:9px; animation:rise .4s .14s both; line-height:1.5 }
        .prize2 { position:relative; padding:22px; margin-top:22px; text-align:center; background:var(--mint); border:4px solid var(--border); border-radius:14px; box-shadow:6px 6px 0 0 var(--teal-900); animation:rise .4s .2s both }
        .prize2 .sticker { position:absolute; top:-16px; inset-inline-end:-12px; background:var(--red); color:#fff; font-weight:900; font-size:11px; letter-spacing:.05em; border:3px solid var(--border); border-radius:7px; padding:7px 11px; transform:rotate(7deg); white-space:nowrap; box-shadow:3px 3px 0 0 var(--teal-900) }
        .prize2 .pname { font-weight:900; font-size:22px; color:var(--teal); line-height:1.1; text-transform:uppercase; letter-spacing:-.01em }
        .prize2 .ploc { font-size:12px; font-weight:900; color:var(--teal); opacity:.85; margin-top:7px; text-transform:uppercase; letter-spacing:.05em }
        .prize2 .pcode { margin-top:14px; font-weight:900; font-size:15px; letter-spacing:.14em; direction:ltr; color:var(--teal); background:var(--paper); border:3px solid var(--teal); border-radius:8px; padding:10px 16px; display:inline-block; box-shadow:3px 3px 0 0 var(--teal) }
        .wonactions { margin-top:20px; animation:rise .4s .28s both }
        .ghostbtn { display:block; width:100%; border:4px solid var(--border); border-radius:12px; background:transparent; color:var(--teal); font-family:var(--font-display); font-weight:900; font-size:15px; letter-spacing:.05em; text-transform:uppercase; padding:15px; cursor:pointer; text-decoration:none; text-align:center }
        .ghostbtn:active { transform:translate(2px,2px) }

        /* ── STRIP ── */
        .strip { position:relative; z-index:6; background:var(--panel); border-top:3px solid var(--border); display:flex; justify-content:center; padding:20px 16px }
        .stripin { display:flex; max-width:900px; width:100% }
        .chip { flex:1; text-align:center; padding:0 10px; font-weight:900; font-size:10.5px; letter-spacing:.06em; text-transform:uppercase; color:#fff; border-inline-end:2px solid var(--teal-100) }
        .chip:last-child { border-inline-end:none }
        .chip b { display:block; color:var(--mint); font-size:24px; margin-bottom:3px; line-height:1 }

        /* ── FOOT ── */
        .foot { position:relative; z-index:6; background:var(--panel); padding:20px 24px }
        .footin { max-width:900px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:14px; flex-wrap:wrap }
        .footlinks { display:flex; gap:15px; flex-wrap:wrap }
        .footlinks a { color:#fff; opacity:.7; text-decoration:none; font-size:12px; font-weight:700 }
        .footlinks a:hover { opacity:1; color:var(--mint) }
        .copy { font-size:11px; font-weight:700; color:#fff; opacity:.45 }

        @media (max-width:520px) {
            .nav { padding:10px 14px }
            .brand span.word { display:none }
            .stage { padding-bottom:26px }
            .head { padding:20px 14px 4px }
            .chip { font-size:9px; padding:0 6px }
            .chip b { font-size:20px }
            .redeemcard { padding:26px 18px 22px }
        }
        @media (prefers-reduced-motion:reduce) {
            .waveband svg, .tickertrack { animation:none !important }
        }
    </style>
</head>
<body>

<div class="nav">
    <div class="brand">
        <span class="mark"><img src="{{ $logoUrl }}" alt="WADDI"></span>
        <span class="word">WADDI <span class="accent">SPOTS</span></span>
    </div>
    <div class="navvenue">{{ $venue->title }}</div>
</div>

<div class="ticker">
    <div class="tickertrack">
        @php
            $tick = strtoupper($venue->title) . ' &nbsp;<b>·</b>&nbsp; '
                . translate('messages.staff_redemption_page') . ' &nbsp;<b>·</b>&nbsp; '
                . translate('messages.one_item_per_code') . ' &nbsp;<b>·</b>&nbsp; ';
        @endphp
        <span>{!! $tick !!}{!! $tick !!}</span><span>{!! $tick !!}{!! $tick !!}</span>
    </div>
</div>

<div class="stage">

    @php
        // One wave geometry, reused by both bands.
        $wavePath = 'M0,80 Q225,10 450,80 Q675,150 900,80 Q1125,10 1350,80 Q1575,150 1800,80 Q2025,10 2250,80 Q2475,150 2700,80 Q2925,10 3150,80 Q3375,150 3600,80';
        // Repeating marquee that rides the curve — the mark is drawn as a
        // glyph-sized image so it reads as brand, not as a word.
        $phrase = strtoupper(translate('messages.wave_marquee')) . '  •  ' . strtoupper(translate('messages.wave_marquee_2')) . '  •  ';
    @endphp

    {{-- ── wave band A: the marquee, above the card ── --}}
    <div class="waveband a">
        <svg width="3600" height="150" viewBox="0 0 3600 150" aria-hidden="true">
            <defs><path id="wavea" d="{{ $wavePath }}" fill="none"></path></defs>
            <path d="{{ $wavePath }}" fill="none" stroke="#FFFFFF" stroke-width="58" stroke-linecap="round"></path>
            <path d="{{ $wavePath }}" fill="none" stroke="#134E4A" stroke-width="46" stroke-linecap="round"></path>
            <text font-family="Thmanyah Sans, sans-serif" font-weight="900" font-size="16" letter-spacing="2.5" fill="#1EF2A0">
                <textPath href="#wavea" startOffset="0">{{ str_repeat($phrase, 8) }}</textPath>
            </text>
            {{-- WADDI mark riding the curve between phrases --}}
            @for ($i = 0; $i < 9; $i++)
                <image href="{{ $logoUrl }}" width="26" height="26" x="{{ 150 + $i * 400 }}" y="{{ $i % 2 ? 96 : 40 }}" opacity=".95"></image>
            @endfor
        </svg>
    </div>

    {{-- ── head: compressed so the input wins ── --}}
    <div class="head">
        <h1 class="h1">{!! translate('messages.crack_the_code_html') !!}</h1>
        <p>{{ translate('messages.redeem_head_sub_staff') }}</p>
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

                <form method="POST" id="redeemform"
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

                <div class="fine">{{ translate('messages.one_item_per_code') }}</div>
            @endif
        </div>
    </div>
</div>

{{-- ── wave band B: mirrors A below the card, closing the frame ── --}}
<div class="waveband b" style="background:var(--mint)">
    <svg width="3600" height="150" viewBox="0 0 3600 150" aria-hidden="true">
        <defs><path id="waveb" d="{{ $wavePath }}" fill="none"></path></defs>
        <path d="{{ $wavePath }}" fill="none" stroke="#0C3532" stroke-width="52" stroke-linecap="round"></path>
        <path d="{{ $wavePath }}" fill="none" stroke="#1EF2A0" stroke-width="40" stroke-linecap="round"></path>
        <text font-family="Thmanyah Sans, sans-serif" font-weight="900" font-size="15" letter-spacing="2.5" fill="#0C3532">
            <textPath href="#waveb" startOffset="0">{{ str_repeat($phrase, 8) }}</textPath>
        </text>
    </svg>
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
        <div class="footlinks">
            <a href="{{ route('about-us') }}">{{ translate('messages.about_us') }}</a>
            <a href="{{ route('contact-us') }}">{{ translate('messages.contact_us') }}</a>
            <a href="{{ route('terms-and-conditions') }}">{{ translate('messages.terms_and_condition') }}</a>
            <a href="{{ $langHref }}">{{ $isRtl ? 'English' : 'العربية' }}</a>
        </div>
        <div class="copy">© {{ date('Y') }} WADDI Spots</div>
    </div>
</div>

<script>
    (function () {
        var input = document.getElementById('code');
        var btn = document.getElementById('submitbtn');
        var form = document.getElementById('redeemform');
        if (!input) return;

        // Cashiers read this off a cracked phone screen — uppercase it and
        // insert the dash so the field always matches what the winner sees.
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

        // Submitting is a network round trip on cafe wifi. Without this the
        // button looks inert and staff double-submit.
        if (form && btn) {
            form.addEventListener('submit', function () {
                btn.classList.add('busy');
                btn.textContent = '…';
            });
        }
    })();
</script>
</body>
</html>
