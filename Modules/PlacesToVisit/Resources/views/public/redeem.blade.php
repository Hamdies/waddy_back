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

    // The wave marquee. One geometry, drawn edge-to-edge with no mask —
    // masking is what produced the white rectangles at the viewport edges.
    $wavePath = 'M0,100 Q300,20 600,100 Q900,180 1200,100 Q1500,20 1800,100 Q2100,180 2400,100 Q2700,20 3000,100 Q3300,180 3600,100';
    $phrase   = strtoupper(translate('messages.wave_marquee')) . '  •  '
              . strtoupper(translate('messages.wave_marquee_2')) . '  •  ';
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
            --mint:#1EF2A0; --mint-deep:#0FD98C; --mint-100:#D6FCEC;
            --teal:#134E4A; --teal-900:#0C3532; --panel:#0E3532;
            --red:#FF3B30;
            --ink:#10312E; --ink-2:#3F5754; --ink-3:#6E8481;
            --paper:#FFFFFF; --paper-3:#E7ECEA; --border:#134E4A; --teal-100:#D3E0DE;
            --font-display:"Thmanyah Sans", system-ui, sans-serif;
        }

        * { box-sizing:border-box; -webkit-tap-highlight-color:transparent }
        html, body { margin:0; overflow-x:hidden }
        body { font-family:var(--font-display); color:var(--ink); background:var(--mint); -webkit-font-smoothing:antialiased }

        @keyframes pop { 0% { transform:scale(.6) rotate(-8deg); opacity:0 } 60% { transform:scale(1.08) rotate(2deg) } 100% { transform:scale(1) rotate(0); opacity:1 } }
        @keyframes rise { from { transform:translateY(14px); opacity:0 } to { transform:translateY(0); opacity:1 } }
        @keyframes shake { 0%,100% { transform:translateX(0) } 20% { transform:translateX(-7px) } 40% { transform:translateX(7px) } 60% { transform:translateX(-5px) } 80% { transform:translateX(5px) } }
        @keyframes tickerscroll { from { transform:translateX(0) } to { transform:translateX(-50%) } }

        /* ── HEADER ──────────────────────────────────────────────
           The mark is MINT artwork (#1EF2A0), so it may only ever sit on
           teal/panel. On mint it is invisible — that is not a contrast
           preference, the two colours are identical. */
        .nav { position:relative; z-index:6; display:flex; align-items:center; justify-content:space-between; gap:12px;
               padding:16px 24px; background:var(--panel); border-bottom:3px solid var(--teal-900) }
        .brand { display:flex; align-items:center; gap:12px; min-width:0 }
        .brand img { height:40px; width:auto; display:block; flex:0 0 auto }
        .brand .word { font-weight:900; font-size:20px; letter-spacing:.04em; color:#fff; text-transform:uppercase; white-space:nowrap }
        .brand .word .name { color:#fff }
        .brand .word i { color:var(--mint); font-style:normal }
        .navvenue { font-weight:900; font-size:11px; letter-spacing:.1em; text-transform:uppercase;
                    color:var(--teal); background:var(--mint); border-radius:6px; padding:7px 12px;
                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:44vw }

        .ticker { position:relative; z-index:6; background:var(--teal-900); overflow:hidden; padding:9px 0 }
        .tickertrack { display:flex; width:max-content; animation:tickerscroll 30s linear infinite }
        .tickertrack span { display:flex; align-items:center; gap:14px; padding:0 16px; font-weight:900; font-size:11.5px;
                            letter-spacing:.06em; text-transform:uppercase; color:var(--mint); white-space:nowrap }
        .tickertrack span b { color:#fff; opacity:.5 }

          /* Waves removed (top and bottom) — kept layout intact */
          .waveband { display:none }

        /* ── STAGE ── */
        .stage { position:relative; z-index:4; background:var(--mint); padding:30px 16px 34px;
                 display:flex; flex-direction:column; align-items:center }

        /* Heading sits ABOVE the card in normal flow — never behind it. */
        .head { text-align:center; margin-bottom:22px; max-width:520px }
        .head .h1 { font-weight:900; font-size:clamp(34px, 8vw, 58px); line-height:.92; text-transform:uppercase;
                    color:var(--paper); margin:0; letter-spacing:-.015em;
                    -webkit-text-stroke:2.5px var(--teal); text-shadow:4px 4px 0 var(--teal-900); paint-order:stroke fill }
        .head .h1 em { color:var(--mint); font-style:normal }
        .head p { margin:14px 0 0; font-size:14px; font-weight:900; color:var(--teal);
                  line-height:1.4; text-transform:uppercase; letter-spacing:.05em; opacity:.85 }

        /* ── CARD ── */
        .redeemcard { width:100%; max-width:440px; padding:34px 24px 26px; text-align:center; position:relative;
                  background:var(--paper); border:4px solid var(--teal-900); border-radius:14px;
                  box-shadow:9px 9px 0 0 var(--teal-900) }
        .cardtab { position:absolute; top:-22px; left:50%; transform:translateX(-50%);
               background:var(--teal-900); color:var(--mint); border-radius:10px; padding:8px 14px;
               font-weight:900; font-size:11px; letter-spacing:.12em; text-transform:uppercase; white-space:nowrap }
        .cardtab.brand { display:flex; align-items:center; gap:10px; padding:10px 18px; border-radius:12px }
        .cardtab.brand img { height:30px; width:auto; display:block }
        .cardtab.brand .brand-word { font-weight:900; font-size:15px; letter-spacing:.04em; color:var(--mint) }
        .rc-title { font-weight:900; font-size:clamp(24px, 6.5vw, 30px); line-height:.95; text-transform:uppercase;
                    color:var(--ink); margin:8px 0 6px; letter-spacing:-.015em }
        .rc-title u { text-decoration:none; color:var(--mint-deep) }
        .rc-sub { font-size:12.5px; font-weight:700; color:var(--ink-3); line-height:1.45; margin:0 0 20px }

        .fieldlbl { text-align:start; font-weight:900; font-size:10.5px; letter-spacing:.12em; text-transform:uppercase;
                    color:var(--ink-3); margin-bottom:7px }
        .codefield { width:100%; font-family:var(--font-display); font-weight:900;
                     font-size:clamp(22px, 6.5vw, 27px); letter-spacing:.16em; text-transform:uppercase;
                     color:var(--ink); background:var(--paper);
                     border:4px solid var(--fieldborder, var(--teal-900)); border-radius:10px;
                     box-shadow:4px 4px 0 0 var(--fieldborder, var(--teal-900));
                     padding:18px 12px; outline:none; text-align:center; direction:ltr }
        .codefield:focus { background:var(--mint-100); box-shadow:4px 4px 0 0 var(--mint-deep); border-color:var(--mint-deep) }
        .codefield::placeholder { color:#BFCCC9; letter-spacing:.1em; font-weight:900; font-size:17px; text-transform:none }
        .codefield.err { animation:shake .4s }
        .msgrow { display:flex; align-items:flex-start; justify-content:center; gap:7px; margin-top:11px; min-height:16px }
        .msgrow.err { color:var(--red) }
        .msgrow .txt { font-weight:900; font-size:12.5px; line-height:1.4 }
        .redeembtn { width:100%; margin-top:16px; border:4px solid var(--teal-900); border-radius:12px;
                     box-shadow:5px 5px 0 0 var(--teal-900); background:var(--mint); color:var(--teal);
                     font-family:var(--font-display); font-weight:900; font-size:18px; letter-spacing:.05em;
                     text-transform:uppercase; padding:18px; cursor:pointer }
        .redeembtn:disabled { background:var(--paper-3); color:var(--ink-3); box-shadow:none; cursor:not-allowed; border-color:#BFCCC9 }
        .redeembtn:active:not(:disabled) { transform:translate(3px,3px); box-shadow:2px 2px 0 0 var(--teal-900) }
        .redeembtn.busy { opacity:.7; pointer-events:none }
        .fine { text-align:center; font-size:10.5px; font-weight:900; text-transform:uppercase; letter-spacing:.06em;
                color:var(--ink-3); margin-top:11px }

        /* ── WON ── */
        .wonbadge { width:88px; height:88px; margin:2px auto 0; border-radius:24px; border:4px solid var(--teal-900);
                    box-shadow:6px 6px 0 0 var(--mint); background:var(--teal); display:flex; align-items:center;
                    justify-content:center; font-weight:900; font-size:42px; color:#fff;
                    animation:pop .5s cubic-bezier(.2,1.3,.5,1) both }
        .wontitle { font-weight:900; font-size:clamp(25px,7vw,32px); line-height:.95; text-transform:uppercase;
                    color:var(--ink); margin-top:15px; animation:rise .4s .08s both; letter-spacing:-.015em }
        .wonsub { font-size:12.5px; font-weight:700; color:var(--ink-3); margin-top:8px; animation:rise .4s .14s both; line-height:1.5 }
        .prize2 { position:relative; padding:20px; margin-top:20px; text-align:center; background:var(--mint);
                  border:4px solid var(--teal-900); border-radius:14px; box-shadow:5px 5px 0 0 var(--teal-900);
                  animation:rise .4s .2s both }
        .prize2 .sticker { position:absolute; top:-15px; inset-inline-end:-10px; background:var(--red); color:#fff;
                           font-weight:900; font-size:10.5px; letter-spacing:.05em; border:3px solid var(--teal-900);
                           border-radius:7px; padding:6px 10px; transform:rotate(6deg); white-space:nowrap }
        .prize2 .pname { font-weight:900; font-size:21px; color:var(--teal); line-height:1.1; text-transform:uppercase; letter-spacing:-.01em }
        .prize2 .ploc { font-size:11.5px; font-weight:900; color:var(--teal); opacity:.85; margin-top:6px; text-transform:uppercase; letter-spacing:.05em }
        .prize2 .pcode { margin-top:13px; font-weight:900; font-size:15px; letter-spacing:.14em; direction:ltr;
                         color:var(--teal); background:var(--paper); border:3px solid var(--teal); border-radius:8px;
                         padding:9px 15px; display:inline-block }
        .wonactions { margin-top:18px; animation:rise .4s .28s both }
        .ghostbtn { display:block; width:100%; border:4px solid var(--teal-900); border-radius:12px; background:transparent;
                    color:var(--teal); font-family:var(--font-display); font-weight:900; font-size:14.5px;
                    letter-spacing:.05em; text-transform:uppercase; padding:14px; cursor:pointer; text-decoration:none; text-align:center }
        .ghostbtn:active { transform:translate(2px,2px) }

        /* ── FOOTER (strip + links share one panel, flush to wave B) ── */
        .footer { position:relative; z-index:5; background:var(--panel) }
        .strip { display:flex; justify-content:center; padding:22px 16px 18px }
        .stripin { display:flex; max-width:760px; width:100% }
        .chip { flex:1; text-align:center; padding:0 10px; font-weight:900; font-size:10px; letter-spacing:.07em;
                text-transform:uppercase; color:#fff; opacity:.85; border-inline-end:2px solid rgba(255,255,255,.14) }
        .chip:last-child { border-inline-end:none }
        .chip b { display:block; color:var(--mint); font-size:24px; margin-bottom:3px; line-height:1; opacity:1 }
        .footin { max-width:760px; margin:0 auto; padding:16px 22px 22px; display:flex; align-items:center;
                  justify-content:space-between; gap:14px; flex-wrap:wrap; border-top:2px solid rgba(255,255,255,.12) }
        .footlinks { display:flex; gap:15px; flex-wrap:wrap }
        .footlinks a { color:#fff; opacity:.7; text-decoration:none; font-size:12px; font-weight:700 }
        .footlinks a:hover { opacity:1; color:var(--mint) }
        .copy { font-size:11px; font-weight:700; color:#fff; opacity:.42 }

        @media (max-width:560px) {
            .nav { padding:11px 15px }
            .brand .word { font-size:15px }
            .waveband, .waveband svg { height:74px }
            .stage { padding:24px 14px 28px }
            .chip { font-size:9px; padding:0 6px }
            .chip b { font-size:20px }
            .redeemcard { padding:30px 18px 22px }
            .footin { justify-content:center; text-align:center }
        }
        @media (prefers-reduced-motion:reduce) {
            .waveband svg, .tickertrack { animation:none !important }
        }
    </style>
</head>
<body>

<div class="nav">
    <div class="brand">
        <img src="{{ $logoUrl }}" alt="WADDI">
        <span class="word"><span class="name">WADDI</span> <i>SPOTS</i></span>
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

{{-- Waves removed (top) --}}

<div class="stage">
    <div class="head">
        <h1 class="h1">{!! translate('messages.crack_the_code_html') !!}</h1>
        <p>{{ translate('messages.redeem_head_sub_staff') }}</p>
    </div>

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
            <div class="cardtab brand">
                <img src="{{ $logoUrl }}" alt="WADDI">
                <span class="brand-word">WADDI <i>SPOTS</i></span>
            </div>
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

{{-- Waves removed (bottom) --}}

<div class="footer">
    <div class="strip">
        <div class="stripin">
            <div class="chip"><b>{{ $stats['outstanding'] }}</b>{{ translate('messages.outstanding') }}</div>
            <div class="chip"><b>{{ $stats['redeemed_total'] }}</b>{{ translate('messages.redeemed') }}</div>
            <div class="chip"><b>{{ $validity }}</b>{{ translate('messages.days_validity') }}</div>
        </div>
    </div>
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

        // A scanned QR lands here prefilled — caret to the end, not selected.
        var len = input.value.length;
        input.setSelectionRange(len, len);

        // Cafe wifi is slow; without feedback staff double-submit.
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
