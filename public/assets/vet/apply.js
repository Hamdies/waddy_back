/* Waddy Vets application wizard: steps, validation, the live preview card
   and the map. Plain JS, no dependencies; strings come from window.VET. */
(function () {
    "use strict";

    const V = window.VET || {};
    const $ = (sel, root = document) => root.querySelector(sel);
    const $$ = (sel, root = document) => Array.from(root.querySelectorAll(sel));
    const form = $('#vcaForm');
    if (!form) return;

    const steps = $$('.wstep', form);
    const bars = $$('.progress-bar span', form);
    const prevBtn = $('#prevBtn'), nextBtn = $('#nextBtn'), submitBtn = $('#submitBtn');
    let current = 0;

    // Preview helpers live up here: setup code below refreshes the preview before its own section runs.
    const pv = name => $(`#pvPhone [data-pv="${name}"]`);
    const icon = name => ($(`#pvIcons [data-icon="${name}"]`) || {}).innerHTML || '';
    const val = name => (form.elements[name] ? String(form.elements[name].value || '').trim() : '');

    // ---------- Steps ----------

    function show(i, focus) {
        current = Math.max(0, Math.min(steps.length - 1, i));
        steps.forEach((s, n) => s.classList.toggle('is-active', n === current));
        bars.forEach((b, n) => {
            b.classList.toggle('is-done', n < current);
            b.classList.toggle('is-current', n === current);
        });
        $('#stepCount').textContent = (V.step || ':current / :total').replace(':current', current + 1).replace(':total', steps.length);
        $('#stepName').textContent = steps[current].dataset.name || '';
        prevBtn.hidden = current === 0;
        nextBtn.hidden = current === steps.length - 1;
        submitBtn.hidden = current !== steps.length - 1;
        if (current === 1 && window.google && map) google.maps.event.trigger(map, 'resize');
        if (focus) {
            const top = form.getBoundingClientRect().top + window.scrollY - 16;
            if (window.scrollY > top) window.scrollTo({ top, behavior: 'smooth' });
        }
    }

    // ---------- Validation (the server validates again) ----------

    function setError(anchor, msg) {
        const holder = anchor.closest('.field') || anchor.parentElement;
        let p = holder.querySelector('.err[data-client]');
        if (!msg) { if (p) p.remove(); return; }
        if (!p) {
            p = document.createElement('p');
            p.className = 'err';
            p.dataset.client = '';
            p.setAttribute('role', 'alert');
            (anchor.closest('.prefix') || anchor).insertAdjacentElement('afterend', p);
        }
        p.textContent = msg;
    }

    function clearServerError(el) {
        el.classList.remove('is-invalid');
        const holder = el.closest('.field');
        holder && holder.querySelectorAll('.err:not([data-client])').forEach(e => e.remove());
    }

    function checkField(el) {
        let msg = '';
        const v = (el.value || '').trim();
        if (el.type === 'file') {
            if (el.required && !el.files.length) msg = V.required;
        } else if (el.type === 'checkbox') {
            if (el.required && !el.checked) msg = el.dataset.requiredMsg || V.required;
        } else if (el.required && !v) {
            msg = V.required;
        } else if (v && el.type === 'email' && !el.checkValidity()) {
            msg = V.email;
        } else if (v && el.type === 'url' && !/^https?:\/\/[^\s.]+\.[^\s]+$/i.test(v)) {
            msg = V.url;
        } else if (v && el.type === 'tel' && (!/^[0-9\s\-+()]+$/.test(v) || v.replace(/\D/g, '').length < (el.minLength > 0 ? el.minLength : 8))) {
            msg = V.phone;
        }
        el.classList.toggle('is-invalid', !!msg);
        if (el.type === 'file') el.closest('.drop')?.classList.toggle('is-invalid', !!msg);
        setError(el.type === 'checkbox' ? el.closest('label') : (el.type === 'file' ? el.closest('.drop') : el), msg);
        return !msg;
    }

    function validateStep(i) {
        const step = steps[i];
        let ok = true, first = null;
        $$('input, textarea', step).forEach(el => {
            if (el.type === 'hidden' || el.closest('.hp') || el.closest('template')) return;
            if (!el.required && !['email', 'url', 'tel'].includes(el.type)) return;
            if (!checkField(el)) { ok = false; first = first || el; }
        });
        const species = $('#speciesGroup', step);
        if (species) {
            const has = !!$('input:checked', species);
            setError(species, has ? '' : species.dataset.requiredMsg);
            if (!has) { ok = false; first = first || $('input', species); }
        }
        const lat = $('#latitude', step);
        if (lat) {
            const has = !!lat.value;
            setError($('#pinNote'), has ? '' : lat.dataset.requiredMsg);
            if (!has) { ok = false; first = first || $('#pac-input'); }
        }
        if (first) first.focus({ preventScroll: false });
        return ok;
    }

    nextBtn.addEventListener('click', () => { if (validateStep(current)) show(current + 1, true); });
    prevBtn.addEventListener('click', () => show(current - 1, true));

    form.addEventListener('submit', e => {
        for (let i = 0; i < steps.length; i++) {
            if (!validateStep(i)) { e.preventDefault(); show(i, true); return; }
        }
        submitBtn.disabled = true;
    });
    // Enter in a text field moves on rather than submitting from step 1.
    form.addEventListener('keydown', e => {
        if (e.key !== 'Enter' || e.target.tagName === 'TEXTAREA' || e.target.type === 'submit') return;
        e.preventDefault();
        if (e.target.id !== 'pac-input' && current < steps.length - 1) nextBtn.click();
    });
    form.addEventListener('input', e => {
        const el = e.target;
        if (el.classList.contains('is-invalid') || el.closest('.field')?.querySelector('.err')) { clearServerError(el); if (el.required || el.value) checkField(el); }
    });

    // ---------- Uploads ----------

    const MAX_IMAGE = 4 * 1024 * 1024, MAX_LICENSE = 5 * 1024 * 1024;

    $$('.drop').forEach(drop => {
        const input = $('input[type="file"]', drop);
        ['dragenter', 'dragover'].forEach(t => drop.addEventListener(t, () => drop.classList.add('is-drag')));
        ['dragleave', 'drop'].forEach(t => drop.addEventListener(t, () => drop.classList.remove('is-drag')));
        $('.drop-clear', drop)?.addEventListener('click', e => {
            e.preventDefault();
            input.value = '';
            drop.classList.remove('has-file');
            updatePreview();
        });
        input.addEventListener('change', () => {
            const file = input.files[0];
            const limit = input.name === 'license_document' ? MAX_LICENSE : MAX_IMAGE;
            if (file && file.size > limit) {
                input.value = '';
                setError(drop, V.tooBig);
                return;
            }
            setError(drop, '');
            drop.classList.remove('is-invalid');
            if (input.name === 'license_document') {
                const chip = $('#licenseChip');
                chip.classList.toggle('is-on', !!file);
                $('span', chip).textContent = file ? file.name : '';
                return;
            }
            const img = $('img', drop);
            if (!file) { drop.classList.remove('has-file'); updatePreview(); return; }
            const reader = new FileReader();
            reader.onload = ev => { img.src = ev.target.result; drop.classList.add('has-file'); updatePreview(); };
            reader.readAsDataURL(file);
        });
    });

    // ---------- Hours ----------

    $$('.hrow').forEach(row => {
        const toggle = $('.open-toggle', row);
        toggle.addEventListener('change', () => setOpen(row, toggle.checked));
    });

    function setOpen(row, open) {
        $('.open-toggle', row).checked = open;
        $('.closed-flag', row).value = open ? '' : '1';
        row.classList.toggle('is-closed', !open);
        const t = $('.switch-text', row);
        t.textContent = open ? t.dataset.on : t.dataset.off;
        updatePreview();
    }

    $$('.presets [data-fill]').forEach(btn => btn.addEventListener('click', () => {
        const [o, c] = btn.dataset.fill.split(',');
        $$('.hrow').forEach(row => {
            const [open, close] = $$('input[type="time"]', row);
            open.value = o; close.value = c;
            setOpen(row, true);
        });
    }));
    $('.presets [data-copy-first]')?.addEventListener('click', () => {
        const rows = $$('.hrow');
        const src = rows.find(r => !r.classList.contains('is-closed') && $$('input[type="time"]', r)[0].value);
        if (!src) return;
        const [so, sc] = $$('input[type="time"]', src).map(i => i.value);
        rows.forEach(row => {
            if (row.classList.contains('is-closed')) return;
            const [open, close] = $$('input[type="time"]', row);
            open.value = so; close.value = sc;
        });
        updatePreview();
    });

    // ---------- Services ----------

    $$('.svc').forEach(row => {
        const cb = $('input[type="checkbox"]', row);
        const price = $('input[type="number"]', row);
        cb.addEventListener('change', () => {
            row.classList.toggle('is-on', cb.checked);
            if (cb.checked) setTimeout(() => price.focus(), 0);
        });
    });

    // ---------- Team ----------

    const vetsList = $('#vetsList');
    const addVet = $('#addVet');
    const maxVets = parseInt(vetsList?.dataset.max || '4', 10);
    let vetIndex = vetsList ? $$('.vet-row', vetsList).length : 0;

    function initial(name) {
        const clean = name.trim().replace(/^(dr\.?|د\.?)\s*/i, '');
        return clean ? clean.charAt(0).toUpperCase() : '+';
    }
    function wireVet(row) {
        const name = $('.vet-name', row);
        name.addEventListener('input', () => { $('.vet-avatar', row).textContent = initial(name.value); });
        $('.vet-remove', row).addEventListener('click', () => {
            if ($$('.vet-row', vetsList).length > 1) row.remove();
            else $$('input', row).forEach(i => { i.value = ''; }), ($('.vet-avatar', row).textContent = '+');
            syncVets();
        });
    }
    function syncVets() {
        if (addVet) addVet.hidden = $$('.vet-row', vetsList).length >= maxVets;
        updatePreview();
    }
    if (vetsList) {
        $$('.vet-row', vetsList).forEach(wireVet);
        addVet.addEventListener('click', () => {
            if ($$('.vet-row', vetsList).length >= maxVets) return;
            const html = $('#vetTemplate').innerHTML.replace(/__i__/g, String(vetIndex++));
            vetsList.insertAdjacentHTML('beforeend', html);
            const row = vetsList.lastElementChild;
            wireVet(row);
            $('.vet-name', row).focus();
            syncVets();
        });
        syncVets();
    }

    // ---------- Live preview ----------


    // Mirrors VetClinic::isOpenNow(): Cairo time, overnight ranges carry past midnight.
    function openState() {
        const hours = {};
        $$('.hrow').forEach(row => {
            const day = $('.closed-flag', row).name.match(/\[(\w+)\]/)[1];
            const [o, c] = $$('input[type="time"]', row).map(i => i.value);
            hours[day] = { open: o, close: c, closed: row.classList.contains('is-closed') };
        });
        if (!Object.values(hours).some(h => h.closed || (h.open && h.close))) return null;
        const parts = new Intl.DateTimeFormat('en-GB', { timeZone: V.timezone, weekday: 'long', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
        const get = t => parts.find(p => p.type === t).value;
        const today = get('weekday').toLowerCase();
        const time = get('hour') + ':' + get('minute');
        const names = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
        const yesterday = names[(names.indexOf(today) + 6) % 7];
        const y = hours[yesterday];
        if (y && !y.closed && y.open && y.close && y.close < y.open && time <= y.close) return true;
        const h = hours[today];
        if (!h || h.closed) return false;
        if (!h.open || !h.close) return null;
        return h.close < h.open ? time >= h.open : (time >= h.open && time <= h.close);
    }

    function updatePreview() {
        if (!$('#pvPhone')) return;
        const en = val('name'), ar = val('name_ar');
        const name = (V.lang === 'ar' ? (ar || en) : (en || ar)) || V.sample;
        pv('name').textContent = name;

        const addr = val('address');
        pv('addr').textContent = addr ? addr.split(',').slice(0, 2).join(',') : V.sampleAddr;

        const logoImg = $('.drop input[name="logo"]')?.closest('.drop');
        const logo = pv('logo');
        if (logoImg && logoImg.classList.contains('has-file')) {
            logo.style.backgroundImage = `url("${$('img', logoImg).src}")`;
            logo.textContent = '';
        } else {
            logo.style.backgroundImage = '';
            logo.textContent = initial(en || ar || 'W') === '+' ? 'W' : initial(en || ar || 'W');
        }
        const coverDrop = $('.drop input[name="cover"]')?.closest('.drop');
        const cover = pv('cover');
        const hasCover = coverDrop && coverDrop.classList.contains('has-file');
        cover.classList.toggle('has-img', !!hasCover);
        cover.style.backgroundImage = hasCover ? `url("${$('img', coverDrop).src}")` : '';

        const state = openState();
        const open = pv('open');
        open.hidden = state === null;
        open.classList.toggle('is-closed', state === false);
        open.textContent = state ? V.openNow : V.closedNow;

        const tags = pv('tags');
        tags.innerHTML = '';
        const add = (iconName, label, price) => {
            const s = document.createElement('span');
            s.className = 'ccard-tag';
            s.innerHTML = icon(iconName);
            s.append(' ' + label);
            if (price) { const b = document.createElement('b'); b.textContent = ' · ' + price; s.append(b); }
            tags.append(s);
        };
        $$('input[name="species[]"]:checked').forEach(cb => add(cb.dataset.icon, cb.dataset.label));
        $$('input[name="services[]"]:checked').slice(0, 3).forEach(cb => {
            const price = form.elements[`service_prices[${cb.value}]`]?.value;
            add(cb.dataset.icon, cb.dataset.label, price ? Number(price).toLocaleString(V.lang === 'ar' ? 'ar-EG' : 'en-US') : '');
        });

        const vets = $$('.vet-name').map(i => i.value.trim()).filter(Boolean);
        const vetsBox = pv('vets');
        vetsBox.hidden = !vets.length;
        if (vets.length) {
            $('.ccard-avatars', vetsBox).innerHTML = '';
            vets.forEach(v => { const s = document.createElement('span'); s.textContent = initial(v); $('.ccard-avatars', vetsBox).append(s); });
            vetsBox.lastElementChild.textContent = vets.length === 1 ? vets[0] : V.vetsMore.replace(':count', vets.length);
        }

        // The app shows WhatsApp only for an Egyptian mobile (01…).
        const phone = val('phone').replace(/[\s\-()]/g, '');
        const mobile = !phone || /^(\+?20|0020)?1[0-25]\d{8}$/.test(phone.replace(/^0(?=1)/, ''));
        pv('wa').hidden = !mobile;
        pv('actions').classList.toggle('is-single', !mobile);
    }

    form.addEventListener('input', updatePreview);
    form.addEventListener('change', updatePreview);

    const preview = $('#preview');
    $('#previewOpen')?.addEventListener('click', () => { preview.classList.add('is-open'); $('#previewClose').focus(); });
    $('#previewClose')?.addEventListener('click', () => preview.classList.remove('is-open'));
    preview?.addEventListener('click', e => { if (e.target === preview) preview.classList.remove('is-open'); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') preview?.classList.remove('is-open'); });

    // ---------- Map ----------

    let map = null, marker = null;
    const latIn = $('#latitude'), lngIn = $('#longitude');

    function pinNote() {
        const note = $('#pinNote');
        if (!note) return;
        const set = !!latIn.value;
        note.classList.toggle('is-set', set);
        $('span', note).textContent = set ? note.dataset.set : note.dataset.unset;
        if (set) setError(note, '');
    }

    function setPoint(lat, lng, geocode) {
        latIn.value = lat;
        lngIn.value = lng;
        pinNote();
        if (!map) return;
        const pos = { lat, lng };
        marker.setPosition(pos);
        marker.setVisible(true);
        if (!geocode) return;
        new google.maps.Geocoder().geocode({ location: pos }, (results, status) => {
            const box = $('#address');
            if (status === 'OK' && results[0] && box && !box.dataset.touched) { box.value = results[0].formatted_address; updatePreview(); }
        });
    }
    $('#address')?.addEventListener('input', e => { e.target.dataset.touched = e.target.value ? '1' : ''; });

    window.vetInitMap = function () {
        const has = latIn.value && lngIn.value;
        const start = has ? { lat: parseFloat(latIn.value), lng: parseFloat(lngIn.value) } : { lat: 30.0444, lng: 31.2357 };
        map = new google.maps.Map($('#vcaMap'), {
            zoom: has ? 17 : 12, center: start, mapTypeControl: false, streetViewControl: false, fullscreenControl: false,
            gestureHandling: 'greedy', clickableIcons: false,
            styles: [{ featureType: 'poi.business', stylers: [{ visibility: 'off' }] }],
        });
        marker = new google.maps.Marker({
            position: start, map, draggable: true, visible: !!has,
            icon: { path: google.maps.SymbolPath.CIRCLE, scale: 11, fillColor: '#1EF2A0', fillOpacity: 1, strokeColor: '#134E4A', strokeWeight: 4 },
        });
        const input = $('#pac-input');
        const search = new google.maps.places.SearchBox(input);
        map.addListener('bounds_changed', () => search.setBounds(map.getBounds()));
        search.addListener('places_changed', () => {
            const place = (search.getPlaces() || [])[0];
            if (!place || !place.geometry || !place.geometry.location) return;
            const loc = place.geometry.location;
            map.setCenter(loc); map.setZoom(17);
            setPoint(loc.lat(), loc.lng(), false);
            const box = $('#address');
            if (place.formatted_address && box && !box.dataset.touched) { box.value = place.formatted_address; updatePreview(); }
        });
        map.addListener('click', e => setPoint(e.latLng.lat(), e.latLng.lng(), true));
        marker.addListener('dragend', e => setPoint(e.latLng.lat(), e.latLng.lng(), true));
    };

    window.vetMapUnavailable = function () {
        $('#vcaMap').style.display = 'none';
        $('#mapFallback').style.display = 'block';
        $('#pac-input').closest('.prefix').style.display = 'none';
        $('.map-search').style.position = 'static';
        $('.map-search').style.padding = '12px';
    };
    window.gm_authFailure = window.vetMapUnavailable;

    $('#useLocation')?.addEventListener('click', () => {
        const btn = $('#useLocation');
        if (!navigator.geolocation) { alertNote(V.locateFailed); return; }
        const label = btn.innerHTML;
        btn.disabled = true;
        btn.lastChild.textContent = ' ' + V.locating;
        navigator.geolocation.getCurrentPosition(pos => {
            btn.disabled = false; btn.innerHTML = label;
            const { latitude, longitude } = pos.coords;
            if (map) { map.setCenter({ lat: latitude, lng: longitude }); map.setZoom(18); }
            setPoint(latitude, longitude, true);
        }, () => {
            btn.disabled = false; btn.innerHTML = label;
            alertNote(V.locateFailed);
        }, { enableHighAccuracy: true, timeout: 12000 });
    });
    function alertNote(msg) { setError($('#pinNote'), msg); }

    // ---------- Start ----------

    pinNote();
    updatePreview();
    // After a failed submit, open the first step that has an error.
    let start = 0;
    if (V.hasErrors) {
        const bad = steps.findIndex(s => s.querySelector('.is-invalid, .err'));
        if (bad >= 0) start = bad;
    }
    show(start, false);
})();
