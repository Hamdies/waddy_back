@extends('layouts.admin.app')

@section('title', translate('messages.qr_code_generator'))

@push('css_or_js')
<style>
    .qr-types { display: flex; flex-wrap: wrap; gap: 8px; }
    .qr-types button { border: 1px solid #e7eaf3; background: #fff; border-radius: 999px; padding: 7px 16px;
                       font-weight: 500; color: #334257; display: inline-flex; align-items: center; gap: 6px; }
    .qr-types button.active { background: var(--primary, #107980); border-color: var(--primary, #107980); color: #fff; }
    .qr-pane { display: none; }
    .qr-pane.active { display: block; }
    .qr-presets { display: flex; flex-wrap: wrap; gap: 6px; }
    .qr-presets button { border: 1px dashed #c9d1dc; background: #f8fafd; border-radius: 8px; padding: 5px 12px; font-size: 13px; }
    .qr-presets button:hover { border-style: solid; border-color: var(--primary, #107980); }
    .qr-sticky { position: sticky; top: 80px; }
    .qr-stage { background: repeating-conic-gradient(#f1f3f7 0% 25%, #fff 0% 50%) 50% / 18px 18px;
                border-radius: 12px; padding: 20px; display: grid; place-items: center; min-height: 340px; }
    .qr-stage-inner { background: transparent; text-align: center; }
    .qr-stage canvas, .qr-stage svg { display: block; max-width: 100%; height: auto; }
    .qr-caption-preview { font-weight: 700; margin-top: 6px; word-break: break-word; }
    .qr-encoded { font-family: SFMono-Regular, Menlo, monospace; font-size: 12px; background: #f8fafd; border-radius: 8px;
                  padding: 8px 10px; word-break: break-all; max-height: 84px; overflow: auto; color: #5b6777; }
    .qr-shape { display: flex; flex-wrap: wrap; gap: 6px; }
    .qr-shape label { margin: 0; }
    .qr-shape input { position: absolute; opacity: 0; }
    .qr-shape span { display: inline-block; border: 1px solid #e7eaf3; border-radius: 8px; padding: 5px 12px; font-size: 13px; cursor: pointer; }
    .qr-shape input:checked + span { border-color: var(--primary, #107980); background: rgba(16,121,128,.08); color: var(--primary, #107980); font-weight: 600; }
    .qr-color { display: flex; align-items: center; gap: 8px; }
    .qr-color input[type="color"] { width: 44px; height: 38px; padding: 2px; border-radius: 8px; border: 1px solid #e7eaf3; cursor: pointer; }
    .qr-recent { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
    .qr-recent button { text-align: start; border: 1px solid #e7eaf3; background: #fff; border-radius: 10px; padding: 10px 12px; }
    .qr-recent button:hover { border-color: var(--primary, #107980); }
    .qr-recent strong { display: block; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .qr-recent small { color: #8a94a6; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
</style>
@endpush

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <i class="tio-qr-code"></i> {{ translate('messages.qr_code_generator') }}
        </h1>
        <p class="text-muted mb-0">{{ translate('messages.qr_code_generator_hint') }}</p>
    </div>

    <div class="row">
        {{-- Left: what goes in the code, and how it looks --}}
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.qr_content') }}</h5></div>
                <div class="card-body">
                    <div class="qr-types mb-4" id="qrTypes">
                        <button type="button" data-type="link" class="active"><i class="tio-link"></i> {{ translate('messages.qr_type_link') }}</button>
                        <button type="button" data-type="text"><i class="tio-text"></i> {{ translate('messages.qr_type_text') }}</button>
                        <button type="button" data-type="whatsapp"><i class="tio-whatsapp"></i> WhatsApp</button>
                        <button type="button" data-type="phone"><i class="tio-call"></i> {{ translate('messages.qr_type_phone') }}</button>
                        <button type="button" data-type="email"><i class="tio-email"></i> {{ translate('messages.qr_type_email') }}</button>
                        <button type="button" data-type="wifi"><i class="tio-wifi"></i> Wi-Fi</button>
                    </div>

                    <div class="qr-pane active" data-pane="link">
                        @if(count($presets))
                            <label class="input-label">{{ translate('messages.qr_our_links') }}</label>
                            <div class="qr-presets mb-3">
                                @foreach($presets as $p)
                                    <button type="button" data-url="{{ $p['url'] }}" data-label="{{ translate('messages.' . $p['label']) }}">
                                        {{ $p['group'] === 'apps' ? '📱' : '🌐' }} {{ translate('messages.' . $p['label']) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.qr_url') }}</label>
                            <input type="url" class="form-control qr-in" id="linkUrl" placeholder="https://" value="{{ url('/') }}">
                        </div>
                        <div class="form-group mb-0">
                            <label class="input-label">
                                {{ translate('messages.qr_campaign') }}
                                <span class="text-muted font-weight-normal">({{ translate('messages.optional') }})</span>
                            </label>
                            <input type="text" class="form-control qr-in" id="linkCampaign" maxlength="60" placeholder="{{ translate('messages.qr_campaign_placeholder') }}">
                            <small class="text-muted">{{ translate('messages.qr_campaign_hint') }}</small>
                        </div>
                    </div>

                    <div class="qr-pane" data-pane="text">
                        <textarea class="form-control qr-in" id="textBody" rows="4" maxlength="1200" placeholder="{{ translate('messages.qr_text_placeholder') }}"></textarea>
                    </div>

                    <div class="qr-pane" data-pane="whatsapp">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.qr_whatsapp_number') }}</label>
                            <input type="tel" class="form-control qr-in" id="waPhone" placeholder="01xxxxxxxxx">
                            <small class="text-muted">{{ translate('messages.qr_whatsapp_number_hint') }}</small>
                        </div>
                        <div class="form-group mb-0">
                            <label class="input-label">{{ translate('messages.qr_whatsapp_message') }} <span class="text-muted font-weight-normal">({{ translate('messages.optional') }})</span></label>
                            <textarea class="form-control qr-in" id="waText" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>

                    <div class="qr-pane" data-pane="phone">
                        <label class="input-label">{{ translate('messages.phone') }}</label>
                        <input type="tel" class="form-control qr-in" id="telPhone" placeholder="01xxxxxxxxx">
                    </div>

                    <div class="qr-pane" data-pane="email">
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.email') }}</label>
                            <input type="email" class="form-control qr-in" id="mailTo" placeholder="hello@example.com">
                        </div>
                        <div class="form-group">
                            <label class="input-label">{{ translate('messages.qr_email_subject') }} <span class="text-muted font-weight-normal">({{ translate('messages.optional') }})</span></label>
                            <input type="text" class="form-control qr-in" id="mailSubject" maxlength="150">
                        </div>
                        <div class="form-group mb-0">
                            <label class="input-label">{{ translate('messages.qr_email_body') }} <span class="text-muted font-weight-normal">({{ translate('messages.optional') }})</span></label>
                            <textarea class="form-control qr-in" id="mailBody" rows="2" maxlength="500"></textarea>
                        </div>
                    </div>

                    <div class="qr-pane" data-pane="wifi">
                        <div class="row">
                            <div class="col-md-6 form-group">
                                <label class="input-label">{{ translate('messages.qr_wifi_name') }}</label>
                                <input type="text" class="form-control qr-in" id="wifiSsid" maxlength="64">
                            </div>
                            <div class="col-md-6 form-group">
                                <label class="input-label">{{ translate('messages.password') }}</label>
                                <input type="text" class="form-control qr-in" id="wifiPass" maxlength="64" autocomplete="off">
                            </div>
                            <div class="col-md-6 form-group mb-md-0">
                                <label class="input-label">{{ translate('messages.qr_wifi_security') }}</label>
                                <select class="form-control qr-in" id="wifiSec">
                                    <option value="WPA">WPA / WPA2 / WPA3</option>
                                    <option value="WEP">WEP</option>
                                    <option value="nopass">{{ translate('messages.qr_wifi_open') }}</option>
                                </select>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <label class="d-flex align-items-center mb-2">
                                    <input type="checkbox" class="qr-in mr-2" id="wifiHidden"> {{ translate('messages.qr_wifi_hidden') }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">{{ translate('messages.qr_style') }}</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-6 form-group">
                            <label class="input-label">{{ translate('messages.qr_color') }}</label>
                            <div class="qr-color">
                                <input type="color" class="qr-in" id="fg" value="#000000">
                                <input type="text" class="form-control" id="fgHex" value="#000000" maxlength="7" style="max-width: 110px;">
                            </div>
                        </div>
                        <div class="col-sm-6 form-group">
                            <label class="input-label">{{ translate('messages.qr_background') }}</label>
                            <div class="qr-color">
                                <input type="color" class="qr-in" id="bg" value="#ffffff">
                                <input type="text" class="form-control" id="bgHex" value="#ffffff" maxlength="7" style="max-width: 110px;">
                                <label class="d-flex align-items-center mb-0 ml-1 text-nowrap">
                                    <input type="checkbox" class="qr-in mr-1" id="bgClear"> {{ translate('messages.qr_transparent') }}
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-soft-warning py-2 px-3" id="contrastWarn" style="display: none;">
                        <i class="tio-warning"></i> <span></span>
                    </div>

                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.qr_dots') }}</label>
                        <div class="qr-shape">
                            @foreach(['square' => 'qr_shape_square', 'rounded' => 'qr_shape_rounded', 'extra-rounded' => 'qr_shape_extra_rounded', 'dots' => 'qr_shape_dots', 'classy' => 'qr_shape_classy', 'classy-rounded' => 'qr_shape_classy_rounded'] as $v => $l)
                                <label><input type="radio" class="qr-in" name="dotType" value="{{ $v }}" {{ $v === 'square' ? 'checked' : '' }}><span>{{ translate('messages.' . $l) }}</span></label>
                            @endforeach
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.qr_corners') }}</label>
                        <div class="qr-shape">
                            @foreach(['square' => 'qr_shape_square', 'extra-rounded' => 'qr_shape_rounded', 'dot' => 'qr_shape_circle'] as $v => $l)
                                <label><input type="radio" class="qr-in" name="cornerType" value="{{ $v }}" {{ $v === 'square' ? 'checked' : '' }}><span>{{ translate('messages.' . $l) }}</span></label>
                            @endforeach
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="input-label">{{ translate('messages.logo') }}</label>
                        <div class="qr-shape mb-2">
                            <label><input type="radio" class="qr-in" name="logoMode" value="none" checked><span>{{ translate('messages.qr_logo_none') }}</span></label>
                            @if($logo)
                                <label><input type="radio" class="qr-in" name="logoMode" value="brand"><span>{{ translate('messages.qr_logo_ours') }}</span></label>
                            @endif
                            <label><input type="radio" class="qr-in" name="logoMode" value="upload"><span>{{ translate('messages.qr_logo_upload') }}</span></label>
                        </div>
                        <input type="file" id="logoFile" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="form-control" style="display: none; padding-top: 7px;">
                    </div>

                    <div class="row">
                        <div class="col-sm-6 form-group mb-sm-0">
                            <label class="input-label">{{ translate('messages.qr_caption') }} <span class="text-muted font-weight-normal">({{ translate('messages.optional') }})</span></label>
                            <input type="text" class="form-control qr-in" id="caption" maxlength="40" placeholder="{{ translate('messages.qr_caption_placeholder') }}">
                        </div>
                        <div class="col-sm-6 form-group mb-0">
                            <label class="input-label">{{ translate('messages.qr_margin') }}</label>
                            <input type="range" class="custom-range qr-in" id="margin" min="0" max="40" value="16">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right: live preview and downloads --}}
        <div class="col-lg-5">
            <div class="qr-sticky">
                <div class="card mb-3">
                    <div class="card-body">
                        <div class="qr-stage">
                            <div class="qr-stage-inner" id="stageInner">
                                <div id="qrPreview"></div>
                                <div class="qr-caption-preview" id="captionPreview"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-3 mb-1">
                            <small class="text-muted">{{ translate('messages.qr_encoded') }}</small>
                            <small class="text-muted" id="charCount"></small>
                        </div>
                        <div class="qr-encoded" id="encoded"></div>

                        <div class="row mt-3">
                            <div class="col-6 form-group mb-2">
                                <label class="input-label">{{ translate('messages.qr_file_name') }}</label>
                                <input type="text" class="form-control" id="fileName" value="waddy-qr" maxlength="60">
                            </div>
                            <div class="col-6 form-group mb-2">
                                <label class="input-label">{{ translate('messages.qr_size') }}</label>
                                <select class="form-control" id="exportSize">
                                    <option value="512">512 px</option>
                                    <option value="1024" selected>1024 px</option>
                                    <option value="2048">2048 px · {{ translate('messages.qr_print') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap" style="gap: 8px;">
                            <button type="button" class="btn btn--primary flex-grow-1" data-export="png"><i class="tio-download-to"></i> PNG</button>
                            <button type="button" class="btn btn-outline-primary flex-grow-1" data-export="svg"><i class="tio-download-to"></i> SVG</button>
                            <button type="button" class="btn btn-white" id="copyPng" title="{{ translate('messages.qr_copy_image') }}"><i class="tio-copy"></i></button>
                        </div>
                        <small class="text-muted d-block mt-2">{{ translate('messages.qr_svg_hint') }}</small>
                    </div>
                </div>

                <div class="card mb-3" id="recentCard" style="display: none;">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">{{ translate('messages.qr_recent') }}</h5>
                        <button type="button" class="btn btn-sm btn-link text-muted p-0" id="recentClear">{{ translate('messages.qr_clear') }}</button>
                    </div>
                    <div class="card-body"><div class="qr-recent" id="recentList"></div></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('script_2')
<script src="{{ asset('public/assets/admin/js/vendor/qr-code-styling.js') }}"></script>
<script>
    "use strict";
    (function () {
        const T = {
            empty: @json(translate('messages.qr_empty')),
            tooLong: @json(translate('messages.qr_too_long')),
            lowContrast: @json(translate('messages.qr_low_contrast')),
            inverted: @json(translate('messages.qr_inverted')),
            copied: @json(translate('messages.qr_copied')),
            copyFailed: @json(translate('messages.qr_copy_failed')),
            downloaded: @json(translate('messages.qr_downloaded')),
            chars: @json(translate('messages.qr_chars')),
        };
        const BRAND_LOGO = @json($logo);
        const PREVIEW = 280;
        const RECENT_KEY = 'waddy.qr.recent';
        const $ = id => document.getElementById(id);
        const val = id => ($(id).value || '').trim();
        const radio = name => document.querySelector(`input[name="${name}"]:checked`).value;

        let type = 'link';
        let uploadedLogo = null;

        // ---------- What to encode ----------

        // 01xxxxxxxxx → 201xxxxxxxxx; anything else just loses spaces, dashes and the plus.
        function intlPhone(raw) {
            let d = raw.replace(/[^\d]/g, '');
            if (/^01\d{9}$/.test(d)) d = '2' + d;
            if (d.startsWith('00')) d = d.slice(2);
            return d;
        }
        const wifiEscape = s => s.replace(/([\\;,:"])/g, '\\$1');

        function payload() {
            switch (type) {
                case 'link': {
                    let url = val('linkUrl');
                    if (!url) return '';
                    if (!/^[a-z][a-z0-9+.-]*:/i.test(url)) url = 'https://' + url;
                    const campaign = val('linkCampaign');
                    if (campaign) {
                        try {
                            const u = new URL(url);
                            u.searchParams.set('utm_source', 'qr');
                            u.searchParams.set('utm_medium', 'qr');
                            u.searchParams.set('utm_campaign', campaign.toLowerCase().replace(/\s+/g, '-'));
                            url = u.toString();
                        } catch (e) { /* not a URL we can tag; encode as typed */ }
                    }
                    return url;
                }
                case 'text':
                    return $('textBody').value;
                case 'whatsapp': {
                    const p = intlPhone(val('waPhone'));
                    if (!p) return '';
                    const msg = val('waText');
                    return 'https://wa.me/' + p + (msg ? '?text=' + encodeURIComponent(msg) : '');
                }
                case 'phone': {
                    const raw = val('telPhone');
                    return raw ? 'tel:' + raw.replace(/[^\d+]/g, '') : '';
                }
                case 'email': {
                    const to = val('mailTo');
                    if (!to) return '';
                    const q = [];
                    if (val('mailSubject')) q.push('subject=' + encodeURIComponent(val('mailSubject')));
                    if (val('mailBody')) q.push('body=' + encodeURIComponent(val('mailBody')));
                    return 'mailto:' + to + (q.length ? '?' + q.join('&') : '');
                }
                case 'wifi': {
                    const ssid = $('wifiSsid').value;
                    if (!ssid) return '';
                    const sec = $('wifiSec').value;
                    let s = 'WIFI:T:' + sec + ';S:' + wifiEscape(ssid) + ';';
                    if (sec !== 'nopass') s += 'P:' + wifiEscape($('wifiPass').value) + ';';
                    if ($('wifiHidden').checked) s += 'H:true;';
                    return s + ';';
                }
            }
            return '';
        }

        // A short human name for the recent list.
        function summary() {
            switch (type) {
                case 'link': return val('linkUrl').replace(/^https?:\/\//, '');
                case 'text': return val('textBody').slice(0, 40);
                case 'whatsapp': return 'WhatsApp ' + val('waPhone');
                case 'phone': return val('telPhone');
                case 'email': return val('mailTo');
                case 'wifi': return 'Wi-Fi ' + $('wifiSsid').value;
            }
            return '';
        }

        // ---------- How it looks ----------

        function logoImage() {
            const mode = radio('logoMode');
            if (mode === 'brand') return BRAND_LOGO;
            if (mode === 'upload') return uploadedLogo;
            return null;
        }

        function options(size) {
            const fg = $('fg').value;
            const corner = radio('cornerType');
            const image = logoImage();
            return {
                width: size, height: size, type: 'canvas',
                data: payload() || ' ',
                margin: Math.round(parseInt($('margin').value, 10) * size / PREVIEW),
                image: image || undefined,
                // A logo hides modules, so it needs the highest error correction.
                qrOptions: { errorCorrectionLevel: image ? 'H' : 'M' },
                dotsOptions: { color: fg, type: radio('dotType') },
                cornersSquareOptions: { color: fg, type: corner },
                cornersDotOptions: { color: fg, type: corner === 'dot' ? 'dot' : 'square' },
                backgroundOptions: { color: $('bgClear').checked ? 'transparent' : $('bg').value },
                imageOptions: { margin: Math.round(6 * size / PREVIEW), imageSize: 0.32, hideBackgroundDots: true, crossOrigin: 'anonymous' },
            };
        }

        const qr = new QRCodeStyling(options(PREVIEW));
        qr.append($('qrPreview'));

        function luminance(hex) {
            const c = [1, 3, 5].map(i => parseInt(hex.substr(i, 2), 16) / 255)
                .map(v => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4));
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        }

        function render() {
            const data = payload();
            qr.update(options(PREVIEW));
            $('qrPreview').style.opacity = data ? 1 : 0.15;
            $('encoded').textContent = data || T.empty;
            $('charCount').textContent = data.length + ' ' + T.chars;
            $('charCount').classList.toggle('text-danger', data.length > 1200);

            const cap = val('caption');
            $('captionPreview').textContent = cap;
            $('captionPreview').style.color = $('fg').value;
            $('stageInner').style.background = $('bgClear').checked ? 'transparent' : $('bg').value;
            $('stageInner').style.padding = cap ? '0 0 10px' : '0';

            // Scanners want dark modules on a light ground with clear contrast.
            const warn = $('contrastWarn');
            const lf = luminance($('fg').value), lb = luminance($('bg').value);
            let msg = '';
            if (data.length > 1200) msg = T.tooLong;
            else if (!$('bgClear').checked && lf > lb) msg = T.inverted;
            else if (!$('bgClear').checked && (Math.max(lf, lb) + 0.05) / (Math.min(lf, lb) + 0.05) < 4) msg = T.lowContrast;
            warn.style.display = msg ? '' : 'none';
            warn.querySelector('span').textContent = msg;
        }

        // ---------- Export ----------

        async function buildBlob(ext) {
            const size = parseInt($('exportSize').value, 10);
            const exporter = new QRCodeStyling({ ...options(size), type: ext === 'svg' ? 'svg' : 'canvas' });
            const blob = await exporter.getRawData(ext);
            const cap = val('caption');
            if (!cap) return blob;
            return ext === 'svg' ? withSvgCaption(blob, cap, size) : withPngCaption(blob, cap, size);
        }

        function captionStyle(size) {
            return { font: Math.round(size * 0.075), band: Math.round(size * 0.14) };
        }

        async function withPngCaption(blob, text, size) {
            const img = await createImageBitmap(blob);
            const { font, band } = captionStyle(size);
            const c = document.createElement('canvas');
            c.width = size; c.height = size + band;
            const ctx = c.getContext('2d');
            if (!$('bgClear').checked) { ctx.fillStyle = $('bg').value; ctx.fillRect(0, 0, c.width, c.height); }
            ctx.drawImage(img, 0, 0);
            ctx.fillStyle = $('fg').value;
            ctx.font = `700 ${font}px system-ui, -apple-system, "Segoe UI", Tahoma, sans-serif`;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, size / 2, size + band / 2 - font * 0.15, size * 0.92);
            return new Promise(res => c.toBlob(res, 'image/png'));
        }

        async function withSvgCaption(blob, text, size) {
            const doc = new DOMParser().parseFromString(await blob.text(), 'image/svg+xml');
            const svg = doc.documentElement;
            const { font, band } = captionStyle(size);
            const h = size + band;
            svg.setAttribute('height', h);
            svg.setAttribute('viewBox', `0 0 ${size} ${h}`);
            const ns = 'http://www.w3.org/2000/svg';
            if (!$('bgClear').checked) {
                const r = doc.createElementNS(ns, 'rect');
                r.setAttribute('x', 0); r.setAttribute('y', size); r.setAttribute('width', size); r.setAttribute('height', band);
                r.setAttribute('fill', $('bg').value);
                svg.appendChild(r);
            }
            const t = doc.createElementNS(ns, 'text');
            t.setAttribute('x', size / 2); t.setAttribute('y', size + band / 2);
            t.setAttribute('text-anchor', 'middle'); t.setAttribute('dominant-baseline', 'middle');
            t.setAttribute('font-family', 'system-ui, -apple-system, Segoe UI, Tahoma, sans-serif');
            t.setAttribute('font-weight', '700'); t.setAttribute('font-size', font);
            t.setAttribute('fill', $('fg').value);
            t.textContent = text;
            svg.appendChild(t);
            return new Blob([new XMLSerializer().serializeToString(doc)], { type: 'image/svg+xml' });
        }

        function fileName(ext) {
            const base = val('fileName').replace(/[^\w\-]+/g, '-').replace(/^-+|-+$/g, '') || 'waddy-qr';
            return base + '.' + ext;
        }

        document.querySelectorAll('[data-export]').forEach(btn => btn.addEventListener('click', async () => {
            if (!payload()) { toastr.warning(T.empty); return; }
            const ext = btn.dataset.export;
            const blob = await buildBlob(ext);
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = fileName(ext);
            document.body.appendChild(a);
            a.click();
            a.remove();
            setTimeout(() => URL.revokeObjectURL(a.href), 2000);
            saveRecent();
            toastr.success(T.downloaded);
        }));

        $('copyPng').addEventListener('click', async () => {
            if (!payload()) { toastr.warning(T.empty); return; }
            try {
                const blob = await buildBlob('png');
                await navigator.clipboard.write([new ClipboardItem({ 'image/png': blob })]);
                saveRecent();
                toastr.success(T.copied);
            } catch (e) {
                toastr.error(T.copyFailed);
            }
        });

        // ---------- Recent (this browser only) ----------

        const inputIds = ['linkUrl', 'linkCampaign', 'textBody', 'waPhone', 'waText', 'telPhone', 'mailTo', 'mailSubject',
                          'mailBody', 'wifiSsid', 'wifiSec', 'fg', 'bg', 'caption', 'margin', 'fileName'];

        function readRecent() {
            try { return JSON.parse(localStorage.getItem(RECENT_KEY)) || []; } catch (e) { return []; }
        }
        function writeRecent(list) {
            try { localStorage.setItem(RECENT_KEY, JSON.stringify(list)); } catch (e) { /* storage blocked */ }
        }

        // Wi-Fi passwords are never stored.
        function snapshot() {
            const s = { type, label: summary(), at: Date.now(), dotType: radio('dotType'), cornerType: radio('cornerType'),
                        bgClear: $('bgClear').checked, wifiHidden: $('wifiHidden').checked, v: {} };
            inputIds.forEach(id => s.v[id] = $(id).value);
            return s;
        }

        function saveRecent() {
            const s = snapshot();
            // Built from the stored fields, not payload(), which holds the Wi-Fi password.
            const key = s.type + '|' + JSON.stringify(s.v);
            const list = readRecent().filter(r => r.key !== key);
            list.unshift({ ...s, key });
            writeRecent(list.slice(0, 12));
            drawRecent();
        }

        function restore(s) {
            inputIds.forEach(id => { if (s.v[id] !== undefined) $(id).value = s.v[id]; });
            $('wifiPass').value = '';
            $('bgClear').checked = !!s.bgClear;
            $('wifiHidden').checked = !!s.wifiHidden;
            document.querySelector(`input[name="dotType"][value="${s.dotType}"]`)?.click();
            document.querySelector(`input[name="cornerType"][value="${s.cornerType}"]`)?.click();
            $('fgHex').value = $('fg').value;
            $('bgHex').value = $('bg').value;
            setType(s.type);
        }

        function drawRecent() {
            const list = readRecent();
            $('recentCard').style.display = list.length ? '' : 'none';
            const box = $('recentList');
            box.innerHTML = '';
            list.forEach(s => {
                const b = document.createElement('button');
                b.type = 'button';
                const strong = document.createElement('strong');
                strong.textContent = s.label || s.type;
                const small = document.createElement('small');
                small.textContent = s.type + ' · ' + new Date(s.at).toLocaleDateString();
                b.append(strong, small);
                b.addEventListener('click', () => restore(s));
                box.appendChild(b);
            });
        }
        $('recentClear').addEventListener('click', () => { writeRecent([]); drawRecent(); });

        // ---------- Wiring ----------

        function setType(t) {
            type = t;
            document.querySelectorAll('#qrTypes button').forEach(b => b.classList.toggle('active', b.dataset.type === t));
            document.querySelectorAll('.qr-pane').forEach(p => p.classList.toggle('active', p.dataset.pane === t));
            render();
        }
        document.querySelectorAll('#qrTypes button').forEach(b => b.addEventListener('click', () => setType(b.dataset.type)));

        document.querySelectorAll('.qr-presets button').forEach(b => b.addEventListener('click', () => {
            $('linkUrl').value = b.dataset.url;
            const slug = b.dataset.label.toLowerCase().replace(/[^\w]+/g, '-').replace(/^-+|-+$/g, '');
            if (slug) $('fileName').value = 'qr-' + slug;
            render();
        }));

        // Keep the swatch and its hex box in step.
        [['fg', 'fgHex'], ['bg', 'bgHex']].forEach(([pick, hex]) => {
            $(pick).addEventListener('input', () => { $(hex).value = $(pick).value; });
            $(hex).addEventListener('input', () => {
                if (/^#[0-9a-f]{6}$/i.test($(hex).value)) { $(pick).value = $(hex).value; render(); }
            });
        });

        document.querySelectorAll('input[name="logoMode"]').forEach(r => r.addEventListener('change', () => {
            $('logoFile').style.display = radio('logoMode') === 'upload' ? '' : 'none';
        }));
        $('logoFile').addEventListener('change', () => {
            const f = $('logoFile').files[0];
            if (!f) return;
            const reader = new FileReader();
            reader.onload = e => { uploadedLogo = e.target.result; render(); };
            reader.readAsDataURL(f);
        });

        let timer;
        document.querySelectorAll('.qr-in').forEach(el => {
            el.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(render, 120); });
            el.addEventListener('change', render);
        });

        drawRecent();
        render();
    })();
</script>
@endpush
