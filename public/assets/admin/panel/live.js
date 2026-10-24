/* Waddy live ops — design handoff v4. Data comes from /admin/panel/api/live. */
(function () {
  'use strict';

  var BOOT = window.LIVE;
  var ST = ['placed', 'preparing', 'pickup', 'onway', 'delivered'];
  var SC = { placed: ['#FFF8E1', '#7A5600'], preparing: ['#E6F4F3', '#134E4A'], pickup: ['#FFEEEE', '#C23B3B'], onway: ['#E6FCF3', '#0A6B47'], delivered: ['#F5F7F6', '#6B7876'] };
  var T = {
    en: { sub: 'Maadi operations', live: 'Live ops', overview: 'Overview', board: 'Dispatch board', onShift: 'Couriers on shift', busy: 'busy', busyCap: 'Busy', idle: 'Idle', selected: 'Selected', allZones: 'All zones', mapTitle: 'Couriers in Maadi', feedTitle: 'Live orders', active: 'active', zonesTitle: 'Zones', zone: 'Zone', ordersCol: 'Orders', etaCol: 'ETA', loadCol: 'Load', merchTitle: 'Top merchants', kOrders: 'Orders', kRev: 'Revenue', kEta: 'Avg. delivery', kOnTime: 'On time', kCouriers: 'Couriers busy', vsWeek: 'vs last week', idleN: 'idle', placed: 'New', preparing: 'Preparing', pickup: 'Ready for pickup', onway: 'On the way', delivered: 'Delivered', min: 'min', egp: 'EGP', items: 'Items', itemsN: 'items', total: 'Total', customer: 'Customer', courier: 'Courier', order: 'Order', unassigned: 'Unassigned', assign: 'Assign', reassign: 'Reassign', markAs: 'Mark as', courierIdle: 'is idle', today: 'Today', d7: '7 days', d30: '30 days', byHour: 'Orders by hour', byDay: 'Orders by day', byWeek: 'Orders by week', nowNote: 'Highlighted: current', empty: 'No orders', liveA: 'Live', activeOrders: 'active orders', ago: '{n} ago', back: '← Back to admin', noFix: 'couriers without a recent location', pt: 'pt', orders: 'orders', late: 'late {n}' },
    ar: { sub: 'عمليات المعادي', live: 'العمليات المباشرة', overview: 'نظرة عامة', board: 'لوحة التوزيع', onShift: 'المندوبين في الوردية', busy: 'مشغول', busyCap: 'مشغول', idle: 'متاح', selected: 'محدد', allZones: 'كل المناطق', mapTitle: 'المندوبين في المعادي', feedTitle: 'الطلبات المباشرة', active: 'نشط', zonesTitle: 'المناطق', zone: 'المنطقة', ordersCol: 'الطلبات', etaCol: 'الوقت المتوقع', loadCol: 'الضغط', merchTitle: 'أفضل المتاجر', kOrders: 'الطلبات', kRev: 'الإيرادات', kEta: 'متوسط التوصيل', kOnTime: 'في الموعد', kCouriers: 'مندوبين مشغولين', vsWeek: 'عن الأسبوع الماضي', idleN: 'متاح', placed: 'جديد', preparing: 'قيد التحضير', pickup: 'جاهز للاستلام', onway: 'في الطريق', delivered: 'تم التوصيل', min: 'د', egp: 'ج.م', items: 'المنتجات', itemsN: 'منتجات', total: 'الإجمالي', customer: 'العميل', courier: 'المندوب', order: 'طلب', unassigned: 'بدون مندوب', assign: 'تعيين', reassign: 'إعادة تعيين', markAs: 'تحويل إلى', courierIdle: 'متاح حاليًا', today: 'اليوم', d7: '٧ أيام', d30: '٣٠ يوم', byHour: 'الطلبات بالساعة', byDay: 'الطلبات باليوم', byWeek: 'الطلبات بالأسبوع', nowNote: 'المميز: الحالي', empty: 'لا توجد طلبات', liveA: 'مباشر', activeOrders: 'طلب نشط', ago: 'منذ {n}', back: '→ العودة للوحة', noFix: 'مندوبين بدون موقع حديث', pt: 'نقطة', orders: 'طلب', late: 'متأخر {n}' }
  };
  var DAYS = { en: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'], ar: ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'] };

  var store = { get: function (k, d) { try { var v = localStorage.getItem('waddy.' + k); return v === null ? d : v; } catch (e) { return d; } }, set: function (k, v) { try { localStorage.setItem('waddy.' + k, v); } catch (e) { /* private mode */ } } };
  var S = { lang: store.get('lang', BOOT.lang), view: store.get('liveView', 'live'), zone: 'all', range: 'today', sel: null, data: null, err: null, toast: null, acting: false };

  function t() { return T[S.lang]; }
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function fmt(v) { return Math.round(Number(v) || 0).toLocaleString('en-US'); }
  function fill(str, map) { return String(str).replace(/\{(\w+)\}/g, function (m, k) { return map[k] != null ? map[k] : m; }); }
  function dur(min) {
    var ar = S.lang === 'ar', h = Math.floor(min / 60), m = min % 60;
    if (h >= 24) return Math.floor(h / 24) + (ar ? ' يوم' : 'd') + ' ' + (h % 24) + (ar ? 'س' : 'h');
    return h ? h + (ar ? 'س ' : 'h ') + m + (ar ? 'د' : 'm') : m + ' ' + t().min;
  }
  function name(c) { return c ? c.name : t().unassigned; }

  var CSRF = document.querySelector('meta[name="csrf-token"]').content;
  function api(method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' } };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(BOOT.base + '/api/' + path, opts).then(function (r) {
      if (r.status === 401 || r.status === 419 || (r.redirected && /login/.test(r.url))) { location.reload(); throw new Error('session'); }
      return r.json().catch(function () { return {}; }).then(function (d) { if (!r.ok) throw new Error(d.message || 'Error ' + r.status); return d; });
    });
  }
  function load() {
    var q = '?range=' + S.range + (S.zone !== 'all' ? '&zone=' + S.zone : '');
    return api('GET', 'live' + q).then(function (d) { S.data = d; S.err = null; render(); pushMap(); })
      .catch(function (e) { S.err = e.message; render(); });
  }
  function flash(msg) { S.toast = msg; render(); clearTimeout(flash._t); flash._t = setTimeout(function () { S.toast = null; render(); }, 2600); }

  // ------------------------------------------------------------------- view model
  function couriersById() { var m = {}; (S.data ? S.data.couriers : []).forEach(function (c) { m[c.id] = c; }); return m; }
  function loadColor(l) { return l > 0.8 ? '#FF6B6B' : l > 0.6 ? '#FFBE0B' : '#1EF2A0'; }
  function ov(o, C) {
    var c = o.courier ? C[o.courier] : null, mins = Math.max(0, Math.round((Date.now() - new Date(o.placedAt)) / 60000)), k = o.stage;
    return {
      o: o, k: k, c: c, ago: fill(t().ago, { n: dur(mins) }),
      meta: o.customer + ' · ' + (o.zone || ''),
      eta: o.eta == null || k === 'delivered' ? '—' : o.eta < 0 ? fill(t().late, { n: dur(-o.eta) }) : dur(o.eta), isLate: o.eta != null && o.eta < 0 && k !== 'delivered',
      needsAssign: !c && k === 'pickup', noCourierYet: !c && (k === 'placed' || k === 'preparing')
    };
  }
  function visible() { return (S.data ? S.data.orders : []).filter(function (o) { return S.zone === 'all' || String(o.zone_id) === String(S.zone); }); }

  function sidebar() {
    var C = S.data ? S.data.couriers : [], busy = C.filter(function (c) { return c.busy; }).length, tot = C.length;
    var h = '<aside class="side"><a class="brand" href="' + esc(BOOT.panel) + '"><img src="' + esc(BOOT.logo) + '" alt="Waddy"><div><b>Waddy</b><span>' + esc(t().sub) + '</span></div></a><nav class="nav">';
    [['live', t().live], ['overview', t().overview], ['board', t().board]].forEach(function (v) { h += '<button class="u' + (S.view === v[0] ? ' on' : '') + '" data-a="view" data-v="' + v[0] + '"><i></i><span>' + esc(v[1]) + '</span></button>'; });
    h += '</nav><div class="shift"><small>' + esc(t().onShift) + '</small><div><b>' + busy + '</b><span>/ ' + tot + ' ' + esc(t().busy) + '</span></div><div class="bar"><div style="width:' + (tot ? Math.round(busy / tot * 100) : 0) + '%"></div></div></div>';
    return h + '<a class="back" href="' + esc(BOOT.panel) + '">' + esc(t().back) + '</a></aside>';
  }

  function header() {
    var title = { live: t().live, overview: t().overview, board: t().board }[S.view];
    var active = visible().filter(function (o) { return o.stage !== 'delivered'; }).length;
    var date = new Date().toLocaleDateString(S.lang === 'ar' ? 'ar-EG' : 'en-GB', { weekday: 'long', day: 'numeric', month: 'long' });
    var h = '<header class="top"><div><h1>' + esc(title) + '</h1><div class="liveline"><i></i><span>' + esc(t().liveA + ' · ' + active + ' ' + t().activeOrders + ' · ' + date) + '</span></div></div><div class="tools">';
    h += '<div class="seg mob">';
    [['live', t().live], ['overview', t().overview], ['board', t().board]].forEach(function (v) { h += '<button class="u' + (S.view === v[0] ? ' on' : '') + '" data-a="view" data-v="' + v[0] + '">' + esc(v[1]) + '</button>'; });
    h += '</div><select data-change="zone"><option value="all"' + (S.zone === 'all' ? ' selected' : '') + '>' + esc(t().allZones) + '</option>';
    (S.data ? S.data.zones : []).forEach(function (z) { h += '<option value="' + z.id + '"' + (String(S.zone) === String(z.id) ? ' selected' : '') + '>' + esc(z.name) + '</option>'; });
    h += '</select><div class="seg"><button class="u' + (S.lang === 'en' ? ' on' : '') + '" data-a="lang" data-v="en">EN</button><button class="u' + (S.lang === 'ar' ? ' on' : '') + '" data-a="lang" data-v="ar">عربي</button></div></div></header>';
    return h;
  }

  function kpis(big) {
    var k = S.data.kpis, now = k.now, prev = k.prev, C = S.data.couriers, busy = C.filter(function (c) { return c.busy; }).length;
    var good = ['#0A6B47', '#E6FCF3'], bad = ['#C23B3B', '#FFEEEE'], muted = ['#6B7876', '#F5F7F6'];
    function pct(a, b) { return b ? Math.round((a - b) / b * 100) : null; }
    function card(label, value, delta, unit, higherIsGood) {
      var sub = delta === null ? '—' : (delta > 0 ? '+' : delta < 0 ? '−' : '±') + Math.abs(delta) + unit + ' ' + t().vsWeek;
      var tn = delta === null || delta === 0 ? muted : (delta > 0) === higherIsGood ? good : bad;
      return { label: label, value: value, sub: sub, tn: tn };
    }
    var cards = [
      card(t().kOrders, fmt(now.orders), pct(now.orders, prev.orders), '%', true),
      card(t().kRev, t().egp + ' ' + fmt(now.gmv), pct(now.gmv, prev.gmv), '%', true),
      card(t().kEta, now.eta === null ? '—' : now.eta + ' ' + t().min, now.eta !== null && prev.eta !== null ? now.eta - prev.eta : null, ' ' + t().min, false),
      card(t().kOnTime, now.onTime === null ? '—' : now.onTime + '%', now.onTime !== null && prev.onTime !== null ? now.onTime - prev.onTime : null, ' ' + t().pt, true),
      { label: t().kCouriers, value: busy + ' / ' + C.length, sub: (C.length - busy) + ' ' + t().idleN, tn: muted }
    ];
    var h = '<div class="kpis' + (big ? ' big' : '') + '">';
    cards.forEach(function (c) { h += '<div class="kpi"><small>' + esc(c.label) + '</small><b>' + esc(c.value) + '</b><span style="color:' + c.tn[0] + (big ? ';background:' + c.tn[1] : '') + '">' + esc(c.sub) + '</span></div>'; });
    return h + '</div>';
  }

  function liveView() {
    var C = couriersById(), vis = visible();
    var feed = vis.filter(function (o) { return o.stage !== 'delivered'; }).concat(vis.filter(function (o) { return o.stage === 'delivered'; }).slice(0, 6));
    var nofix = S.data.couriers.filter(function (c) { return c.lat === null; }).length;
    var h = '<div class="stack">' + kpis(false) + '<div class="row">';
    h += '<div class="panel" style="flex:2 1 520px;height:580px;position:relative"><div class="panel-h"><span>' + esc(t().mapTitle) + '</span><div class="legend"><span><i style="background:#134E4A"></i>' + esc(t().busyCap) + '</span><span><i style="background:#fff;border:2px solid #134E4A"></i>' + esc(t().idle) + '</span><span><i style="background:#1EF2A0"></i>' + esc(t().selected) + '</span></div></div>';
    h += '<div class="map" data-keep="1"><div id="map"></div></div>' + (nofix ? '<span class="nofix">' + nofix + ' ' + esc(t().noFix) + '</span>' : '') + '</div>';
    h += '<div class="panel" style="flex:1 1 340px;height:580px"><div class="panel-h"><span>' + esc(t().feedTitle) + '</span><span class="count">' + vis.filter(function (o) { return o.stage !== 'delivered'; }).length + ' ' + esc(t().active) + '</span></div><div class="feed">';
    if (!feed.length) h += '<div class="empty">' + esc(t().empty) + '</div>';
    feed.forEach(function (o) {
      var v = ov(o, C);
      h += '<div class="forder' + (S.sel === o.id ? ' on' : '') + '" data-a="sel" data-v="' + o.id + '"><div><small>#' + o.id + '</small><b>' + esc(o.merchant) + '</b></div><span class="st" style="background:' + SC[v.k][0] + ';color:' + SC[v.k][1] + '">' + esc(t()[v.k]) + '</span><small>' + esc(v.meta) + '</small><span class="ago">' + esc(v.ago) + '</span></div>';
    });
    h += '</div></div></div><div class="row">' + zonesTable() + merchantsList() + '</div></div>';
    return h;
  }
  function zonesTable() {
    var h = '<div class="panel" style="flex:1 1 440px"><div class="panel-h"><span>' + esc(t().zonesTitle) + '</span></div><div class="zgrid head"><span>' + esc(t().zone) + '</span><span>' + esc(t().ordersCol) + '</span><span>' + esc(t().etaCol) + '</span><span>' + esc(t().loadCol) + '</span></div>';
    S.data.zones.forEach(function (z) {
      var pct = Math.round(z.load * 100) + '%';
      h += '<div class="zgrid z' + (String(S.zone) === String(z.id) ? ' on' : '') + '" data-a="zone" data-v="' + z.id + '"><b>' + esc(z.name) + '</b><span>' + fmt(z.orders) + '</span><span>' + (z.eta === null ? '—' : z.eta + ' ' + esc(t().min)) + '</span><div class="load"><div><div style="width:' + pct + ';background:' + loadColor(z.load) + '"></div></div><small>' + pct + '</small></div></div>';
    });
    return h + '</div>';
  }
  function merchantsList() {
    var h = '<div class="panel" style="flex:1 1 380px"><div class="panel-h"><span>' + esc(t().merchTitle) + '</span></div>';
    if (!S.data.merchants.length) h += '<div class="empty">' + esc(t().empty) + '</div>';
    S.data.merchants.forEach(function (m, i) {
      h += '<div class="merch"><span class="rk">' + (i + 1) + '</span><div><b>' + esc(m.name) + '</b><small>' + esc(m.category) + '</small></div><span>' + fmt(m.orders) + ' ' + esc(t().orders) + '</span><em>★ ' + esc(m.rating) + '</em></div>';
    });
    return h + '</div>';
  }

  function overview() {
    var h = '<div class="stack"><div class="ranges">';
    [['today', t().today], ['7d', t().d7], ['30d', t().d30]].forEach(function (r) { h += '<button class="u' + (S.range === r[0] ? ' on' : '') + '" data-a="range" data-v="' + r[0] + '">' + esc(r[1]) + '</button>'; });
    h += '</div>' + kpis(true);
    var bars = S.data.bars, mx = Math.max.apply(null, bars.map(function (b) { return b.v; })) || 1;
    var title = S.range === 'today' ? t().byHour : S.range === '7d' ? t().byDay : t().byWeek;
    h += '<div class="row"><div class="chart" style="flex:2 1 520px"><div class="chart-h"><span>' + esc(title) + '</span><small>' + esc(t().nowNote) + '</small></div><div class="cbars">';
    bars.forEach(function (b, i) {
      var label = S.range === '7d' ? DAYS[S.lang][b.day] : S.range === '30d' && S.lang === 'ar' ? b.label.replace('Week', 'أسبوع') : b.label;
      h += '<div><small>' + fmt(b.v) + '</small><div style="height:' + Math.round(b.v / mx * 78) + '%;background:' + (i === bars.length - 1 ? '#1EF2A0' : '#134E4A') + '"></div><em>' + esc(label) + '</em></div>';
    });
    h += '</div></div><div class="panel" style="flex:1 1 340px;border-radius:18px"><div style="padding:18px 22px 10px;font-size:15px;font-weight:600">' + esc(t().merchTitle) + '</div>';
    var mMax = S.data.merchants.length ? S.data.merchants[0].orders || 1 : 1;
    S.data.merchants.forEach(function (m) { h += '<div class="mshare"><div><b>' + esc(m.name) + '</b><span>' + fmt(m.orders) + '</span></div><div class="bar6"><div style="width:' + Math.round(m.orders / mMax * 100) + '%;background:#134E4A"></div></div></div>'; });
    if (!S.data.merchants.length) h += '<div class="empty">' + esc(t().empty) + '</div>';
    h += '</div></div><div class="stack" style="gap:12px"><span style="font-size:15px;font-weight:600">' + esc(t().zonesTitle) + '</span><div class="ztiles">';
    S.data.zones.forEach(function (z) {
      var pct = Math.round(z.load * 100) + '%';
      h += '<div class="ztile" data-a="openZone" data-v="' + z.id + '"><b>' + esc(z.name) + '</b><div><b>' + fmt(z.orders) + '</b><small>' + esc(t().ordersCol) + '</small></div><div class="bar6"><div style="width:' + pct + ';background:' + loadColor(z.load) + '"></div></div><div><span>' + esc(t().loadCol) + ' ' + pct + '</span><span>' + (z.eta === null ? '—' : z.eta + ' ' + esc(t().min)) + '</span></div></div>';
    });
    return h + '</div></div></div>';
  }

  function board() {
    var C = couriersById(), vis = visible(), h = '<div class="board">';
    ST.slice(0, 4).forEach(function (k) {
      var cards = vis.filter(function (o) { return o.stage === k; });
      h += '<div class="col"><div class="col-h"><i style="background:' + SC[k][1] + '"></i><b>' + esc(t()[k]) + '</b><span>' + cards.length + '</span></div>';
      if (!cards.length) h += '<div class="empty">' + esc(t().empty) + '</div>';
      cards.forEach(function (o) {
        var v = ov(o, C);
        h += '<div class="ocard' + (S.sel === o.id ? ' on' : '') + '" data-a="sel" data-v="' + o.id + '"><div><span>#' + o.id + '</span><span>' + esc(v.ago) + '</span></div><b>' + esc(o.merchant) + '</b><small>' + esc(v.meta + ' · ' + fmt(o.total) + ' ' + t().egp) + '</small><div class="ocard-f">';
        if (v.needsAssign && BOOT.canDispatch) h += '<button class="u assign" data-a="assign" data-v="' + o.id + '">' + esc(t().assign) + '</button>';
        else if (v.c) h += '<span class="who"><i>' + esc((v.c.first || v.c.name || '?')[0]) + '</i>' + esc(v.c.name) + '</span>';
        else h += '<span style="color:#9EAAA8">' + esc(t().unassigned) + '</span>';
        h += '<span' + (v.isLate ? ' style="color:#C23B3B;font-weight:600"' : '') + '>' + esc(v.eta) + '</span></div></div>';
      });
      h += '</div>';
    });
    return h + '</div>';
  }

  function drawer() {
    var o = S.data && S.data.orders.filter(function (x) { return x.id === S.sel; })[0];
    if (!o) return '';
    var C = couriersById(), v = ov(o, C), idx = ST.indexOf(o.stage);
    var h = '<div class="scrim" data-a="close"></div><aside class="drawer"><div class="dh"><div><small>' + esc(t().order) + ' #' + o.id + '</small><b>' + esc(o.merchant) + '</b><span class="st" style="background:' + SC[o.stage][0] + ';color:' + SC[o.stage][1] + '">' + esc(t()[o.stage]) + '</span></div><button class="u dx" data-a="close">×</button></div><div class="db"><div class="steps">';
    ST.forEach(function (k, i) {
      var time = i <= idx && o.times[k] ? new Date(o.times[k]).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' }) : '';
      h += '<div class="step"><div><i style="background:' + (i < idx ? '#134E4A' : i === idx ? '#1EF2A0' : '#E4ECEA') + '"></i><span style="background:' + (i === 4 ? 'transparent' : i < idx ? '#134E4A' : '#E4ECEA') + '"></span></div><div><b style="color:' + (i <= idx ? '#1A1F1E' : '#9EAAA8') + '">' + esc(t()[k]) + '</b><small>' + esc(time) + '</small></div></div>';
    });
    h += '</div><div class="facts">';
    [[t().customer, o.customer], [t().zone, o.zone || '—'], [t().courier, name(v.c)], [t().etaCol, v.eta], [t().items, o.items + ' ' + t().itemsN], [t().total, fmt(o.total) + ' ' + t().egp]].forEach(function (f) { h += '<div><small>' + esc(f[0]) + '</small><b>' + esc(f[1]) + '</b></div>'; });
    h += '</div></div>';
    if (idx < 4 && BOOT.canDispatch) {
      h += '<div class="df"><button class="u go" data-a="advance" data-v="' + o.id + '"' + (S.acting ? ' disabled' : '') + '>' + esc(t().markAs + ' ' + t()[ST[idx + 1]]) + '</button><button class="u alt" data-a="' + (v.c ? 'reassign' : 'assign') + '" data-v="' + o.id + '"' + (S.acting ? ' disabled' : '') + '>' + esc(v.c ? t().reassign : t().assign) + '</button></div>';
    }
    return h + '</aside>';
  }

  function view() {
    var page = !S.data ? (S.err ? '<div class="loading">' + esc(S.err) + '</div>' : '<div class="loading">…</div>') : S.view === 'live' ? liveView() : S.view === 'overview' ? overview() : board();
    var h = '<div class="shell">' + sidebar() + '<main class="main">' + header() + page + '</main>' + drawer();
    if (S.toast) h += '<div class="toast">' + esc(S.toast) + '</div>';
    return h + '</div>';
  }

  // ----------------------------------------------------------------- rendering
  function morph(from, to) {
    if (from.nodeType !== to.nodeType || from.nodeName !== to.nodeName) { from.replaceWith(to); return; }
    if (from.nodeType === 3 || from.nodeType === 8) { if (from.nodeValue !== to.nodeValue) from.nodeValue = to.nodeValue; return; }
    if (from.hasAttribute && from.hasAttribute('data-keep')) return;
    var i, attr;
    for (i = from.attributes.length - 1; i >= 0; i--) { attr = from.attributes[i].name; if (!to.hasAttribute(attr)) from.removeAttribute(attr); }
    for (i = 0; i < to.attributes.length; i++) { attr = to.attributes[i]; if (from.getAttribute(attr.name) !== attr.value) from.setAttribute(attr.name, attr.value); }
    var a = from.childNodes, b = Array.prototype.slice.call(to.childNodes), nb = b.length;
    for (i = 0; i < nb; i++) { if (i < a.length) morph(a[i], b[i]); else from.appendChild(b[i]); }
    while (a.length > nb) from.removeChild(from.lastChild);
    if (from.nodeName === 'SELECT') { var opt = to.querySelector('option[selected]'); if (opt && from.value !== opt.value) from.value = opt.value; }
  }
  var root = document.getElementById('app'), queued = false;
  function render() {
    if (queued) return; queued = true;
    requestAnimationFrame(function () {
      queued = false;
      document.documentElement.lang = S.lang; document.documentElement.dir = S.lang === 'ar' ? 'rtl' : 'ltr';
      var tpl = document.createElement('template'); tpl.innerHTML = view();
      var next = tpl.content.firstElementChild;
      if (!root.firstElementChild || root.firstElementChild.className !== 'shell') { root.innerHTML = ''; root.appendChild(next); }
      else morph(root.firstElementChild, next);
      mountMap();
    });
  }

  // ----------------------------------------------------------------------- map
  var map = null, layers = { zones: {}, couriers: {} }, mapEl = null;
  function mountMap() {
    var el = document.getElementById('map');
    if (!el) { if (map) { map.remove(); map = null; layers = { zones: {}, couriers: {} }; mapEl = null; } return; }
    if (map && mapEl === el) return;
    if (map) map.remove();
    mapEl = el; layers = { zones: {}, couriers: {} };
    map = window.L.map(el, { zoomControl: false, scrollWheelZoom: false });
    map.fitBounds([[29.947, 31.244], [29.977, 31.311]], { padding: [10, 10] });
    var tl = window.L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles © Esri — Esri, DeLorme, NAVTEQ · © OpenStreetMap contributors', maxZoom: 16 }).addTo(map);
    tl.on('tileerror', function (e) { if (e.tile) e.tile.style.visibility = 'hidden'; });
    window.L.control.zoom({ position: 'bottomright' }).addTo(map);
    new ResizeObserver(function () { if (map) map.invalidateSize(); }).observe(el);
    map._fitted = false;
    pushMap();
  }
  function pushMap() {
    if (!map || !S.data) return;
    var Lf = window.L, sel = S.data.orders.filter(function (o) { return o.id === S.sel; })[0], selC = sel && sel.stage !== 'delivered' ? sel.courier : null;
    var bounds = [];
    S.data.zones.forEach(function (z) {
      if (!z.ring.length) return;
      var color = loadColor(z.load) === '#1EF2A0' ? '#1D706A' : loadColor(z.load), active = String(S.zone) === String(z.id);
      var style = { color: color, weight: active ? 3 : 1.5, fillColor: color, fillOpacity: active ? 0.28 : 0.14, dashArray: active ? null : '4 4' };
      var p = layers.zones[z.id];
      if (!p) { p = Lf.polygon(z.ring, style).addTo(map); p.on('click', function () { A.zone(String(z.id)); render(); load(); }); layers.zones[z.id] = p; }
      else p.setStyle(style);
      p.unbindTooltip(); p.bindTooltip(z.name + ' · ' + Math.round(z.load * 100) + '%', { direction: 'top', sticky: true });
      bounds = bounds.concat(z.ring);
    });
    var seen = {};
    S.data.couriers.forEach(function (k) {
      if (k.lat === null) return;
      seen[k.id] = true;
      var isSel = selC === k.id, size = isSel ? 34 : 24, bg = isSel ? '#1EF2A0' : (k.busy ? '#134E4A' : '#fff'), fg = isSel ? '#134E4A' : (k.busy ? '#1EF2A0' : '#134E4A');
      var html = '<div style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:' + bg + ';color:' + fg + ';border:2px solid ' + (k.busy || isSel ? '#fff' : '#134E4A') + ';display:grid;place-items:center;font:600 ' + (isSel ? 13 : 10) + 'px Alexandria,sans-serif;box-shadow:0 3px 10px rgba(19,78,74,.3);opacity:' + (k.online ? 1 : 0.55) + '">' + esc((k.first || k.name || '?')[0]) + '</div>';
      var icon = Lf.divIcon({ html: html, className: '', iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
      var m = layers.couriers[k.id];
      if (!m) { m = Lf.marker([k.lat, k.lng], { icon: icon }).addTo(map); m.on('click', function () { courierClick(k.id); }); layers.couriers[k.id] = m; }
      else { m.setLatLng([k.lat, k.lng]); m.setIcon(icon); }
      m.setZIndexOffset(isSel ? 1000 : 0);
      m.unbindTooltip(); m.bindTooltip(k.name, { direction: 'top', offset: [0, -size / 2] });
    });
    Object.keys(layers.couriers).forEach(function (id) { if (!seen[id]) { layers.couriers[id].remove(); delete layers.couriers[id]; } });
    if (!map._fitted && bounds.length) { map.fitBounds(bounds, { padding: [10, 10] }); map._fitted = true; }
  }
  function courierClick(id) {
    var o = S.data.orders.filter(function (x) { return x.courier === id && x.stage !== 'delivered'; })[0];
    if (o) { S.sel = o.id; render(); pushMap(); return; }
    var c = couriersById()[id];
    flash((c ? c.name : '') + ' ' + t().courierIdle);
  }

  // ------------------------------------------------------------------- actions
  function act(order, what) {
    S.acting = true; render();
    api('POST', 'live', { order: order, do: what }).then(function (r) { S.acting = false; flash(r.message || '✓'); load(); })
      .catch(function (e) { S.acting = false; flash(e.message); });
  }
  var A = {
    view: function (v) { S.view = v; store.set('liveView', v); },
    lang: function (v) { S.lang = v; store.set('lang', v); },
    sel: function (v) { S.sel = +v; setTimeout(pushMap); },
    close: function () { S.sel = null; setTimeout(pushMap); },
    zone: function (v) { S.zone = String(S.zone) === String(v) ? 'all' : v; },
    openZone: function (v) { S.zone = v; S.view = 'live'; load(); },
    range: function (v) { S.range = v; load(); },
    assign: function (v, el, e) { if (e) e.stopPropagation(); act(+v, 'assign'); },
    reassign: function (v) { act(+v, 'reassign'); },
    advance: function (v) { act(+v, 'advance'); }
  };
  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-a]');
    if (!el || !root.contains(el)) return;
    var key = el.getAttribute('data-a');
    if (key === 'assign') e.stopPropagation();
    var fn = A[key]; if (!fn) return;
    fn(el.getAttribute('data-v'), el, e);
    if (key === 'zone') load();
    render();
  }, true);
  document.addEventListener('change', function (e) {
    if (e.target.getAttribute('data-change') === 'zone') { S.zone = e.target.value; load(); render(); }
  });
  window.addEventListener('keydown', function (e) { if (e.key === 'Escape' && S.sel) { S.sel = null; render(); pushMap(); } });

  render(); load();
  setInterval(function () { if (!document.hidden) load(); }, 8000);
})();
