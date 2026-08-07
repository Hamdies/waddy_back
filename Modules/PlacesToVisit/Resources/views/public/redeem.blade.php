@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $result = session('redeem_result');
    $message = session('redeem_message');
    $prize = session('redeem_prize');
    $ok = $result === 'ok';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ translate('messages.redeem_waddi_spots_prize') }} — {{ $venue->title }}</title>
    <style>
        /* WADDI Spots — neubrutalist, teal border, mint neon. Zero-blur shadows. */
        :root {
            --mint: #1EF2A0;
            --teal: #134E4A;
            --teal-900: #0C3532;
            --ink: #10312E;
            --ink-2: #3F5754;
            --ink-3: #6E8481;
            --paper: #FFFFFF;
            --canvas: #DDE4E2;
            --canvas-dot: #C7D1CE;
            --red: #FF3B30;
            --green: #22C55E;
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }

        body {
            margin: 0;
            padding: 16px;
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: var(--ink);
            background-color: var(--canvas);
            background-image: radial-gradient(var(--canvas-dot) 1px, transparent 1px);
            background-size: 16px 16px;
        }

        .wrap { max-width: 460px; margin: 0 auto; }

        .card {
            background: var(--paper);
            border: 3px solid var(--teal);
            border-radius: 10px;
            box-shadow: 4px 4px 0 var(--teal);
            padding: 20px;
            margin-bottom: 16px;
        }

        .masthead {
            background: var(--teal);
            color: #fff;
            border: 3px solid var(--teal-900);
            border-radius: 10px;
            box-shadow: 4px 4px 0 var(--mint);
            padding: 16px 20px;
            margin-bottom: 16px;
        }
        .masthead .brand {
            font-size: 12px; font-weight: 800; letter-spacing: .16em;
            text-transform: uppercase; color: var(--mint); margin-bottom: 4px;
        }
        .masthead h1 { margin: 0; font-size: 22px; font-weight: 900; letter-spacing: -0.02em; }
        .masthead .sub { margin-top: 6px; font-size: 13px; color: rgba(255,255,255,.75); }

        label {
            display: block; font-size: 12px; font-weight: 800; letter-spacing: .1em;
            text-transform: uppercase; color: var(--ink-3); margin-bottom: 8px;
        }

        input[name="code"] {
            width: 100%;
            padding: 16px 12px;
            font-size: 30px;
            font-weight: 900;
            letter-spacing: .14em;
            text-align: center;
            text-transform: uppercase;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            color: var(--ink);
            background: #F5F8F7;
            border: 3px solid var(--teal);
            border-radius: 8px;
            outline: none;
            /* The code is ASCII regardless of page direction — never mirror it */
            direction: ltr;
        }
        input[name="code"]:focus { background: #fff; box-shadow: 3px 3px 0 var(--mint); }
        input[name="code"]::placeholder { color: #B9C6C3; letter-spacing: .14em; }

        button {
            width: 100%;
            margin-top: 16px;
            padding: 16px;
            font-size: 17px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--teal-900);
            background: var(--mint);
            border: 3px solid var(--teal);
            border-radius: 8px;
            box-shadow: 4px 4px 0 var(--teal);
            cursor: pointer;
        }
        button:active { transform: translate(2px, 2px); box-shadow: 2px 2px 0 var(--teal); }

        .result { text-align: center; }
        .result .icon { font-size: 56px; line-height: 1; margin-bottom: 8px; }
        .result h2 { margin: 0 0 6px; font-size: 20px; font-weight: 900; }
        .result p { margin: 0; font-size: 15px; color: var(--ink-2); line-height: 1.5; }
        .result.ok { border-color: var(--green); box-shadow: 4px 4px 0 var(--green); }
        .result.ok h2 { color: var(--green); }
        .result.bad { border-color: var(--red); box-shadow: 4px 4px 0 var(--red); }
        .result.bad h2 { color: var(--red); }

        .chip {
            display: inline-block; margin-top: 12px; padding: 8px 14px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 18px; font-weight: 900; letter-spacing: .1em; direction: ltr;
            background: #F5F8F7; border: 2px solid var(--teal); border-radius: 6px;
        }

        .stats { display: flex; gap: 12px; }
        .stat {
            flex: 1; text-align: center; padding: 12px 8px;
            border: 2px solid var(--teal); border-radius: 8px; background: #F5F8F7;
        }
        .stat .n { font-size: 22px; font-weight: 900; line-height: 1; }
        .stat .l {
            margin-top: 4px; font-size: 10px; font-weight: 800; letter-spacing: .08em;
            text-transform: uppercase; color: var(--ink-3);
        }

        .hint { font-size: 13px; color: var(--ink-2); line-height: 1.6; margin: 0; }
        .hint strong { color: var(--ink); }

        .foot {
            text-align: center; font-size: 11px; letter-spacing: .1em;
            text-transform: uppercase; color: var(--ink-3); padding-bottom: 24px;
        }
        .foot a { color: var(--ink-3); }
    </style>
</head>
<body>
<div class="wrap">

    <div class="masthead">
        <div class="brand">WADDI Spots</div>
        <h1>{{ $venue->title }}</h1>
        <div class="sub">{{ translate('messages.staff_redemption_page') }}</div>
    </div>

    @if ($result)
        <div class="card result {{ $ok ? 'ok' : 'bad' }}">
            <div class="icon">{{ $ok ? '✅' : '❌' }}</div>
            <h2>{{ $ok ? translate('messages.prize_redeemed') : translate('messages.not_redeemed') }}</h2>
            <p>{{ $message }}</p>

            @if ($ok && $prize)
                <div class="chip">{{ $prize['code'] }}</div>
                @if (!empty($prize['value_cap']))
                    <p style="margin-top:12px">
                        {{ translate('messages.give_one_free_item_up_to') }}
                        <strong>{{ rtrim(rtrim(number_format($prize['value_cap'], 2), '0'), '.') }} {{ $prize['currency'] }}</strong>
                    </p>
                @endif
            @elseif ($result === 'already_redeemed' && $prize && !empty($prize['redeemed_at']))
                <p style="margin-top:8px">{{ translate('messages.redeemed_on') }} {{ $prize['redeemed_at'] }}</p>
            @endif
        </div>
    @endif

    <form class="card" method="POST" action="{{ route('spots.redeem.submit', $token) }}{{ request('lang') ? '?lang=' . request('lang') : '' }}" autocomplete="off">
        @csrf
        <label for="code">{{ translate('messages.enter_prize_code') }}</label>
        <input id="code" name="code" placeholder="XXXX-XXXX" maxlength="9"
               inputmode="text" autocapitalize="characters" autocorrect="off"
               spellcheck="false" required autofocus>
        <button type="submit">{{ translate('messages.redeem_prize') }}</button>
    </form>

    <div class="card">
        <div class="stats">
            <div class="stat">
                <div class="n">{{ $stats['outstanding'] }}</div>
                <div class="l">{{ translate('messages.outstanding') }}</div>
            </div>
            <div class="stat">
                <div class="n">{{ $stats['redeemed_total'] }}</div>
                <div class="l">{{ translate('messages.redeemed') }}</div>
            </div>
        </div>
        <p class="hint" style="margin-top:16px">
            {{ translate('messages.redeem_page_hint') }}
        </p>
    </div>

    <div class="foot">
        <a href="?lang={{ $isRtl ? 'en' : 'ar' }}">{{ $isRtl ? 'English' : 'العربية' }}</a>
    </div>

</div>

<script>
    // Cashiers type this off a cracked phone screen — uppercase it and drop the
    // dash in for them so the field always matches what's on the winner's app.
    (function () {
        var input = document.getElementById('code');
        if (!input) return;
        input.addEventListener('input', function () {
            var raw = input.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, 8);
            input.value = raw.length > 4 ? raw.slice(0, 4) + '-' + raw.slice(4) : raw;
        });
    })();
</script>
</body>
</html>
