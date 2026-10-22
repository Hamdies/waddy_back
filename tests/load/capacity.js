// Capacity: "how many people can use the Waddy app at the same time?"
// Holds a fixed number of app users at once for STEP_TIME, then the next number, and prints a table:
// users at once → requests/second, response times, errors, and OK / SLOW / BROKEN.
// It stops by itself as soon as a level is clearly broken. Needs k6 (brew install k6). Read-only: GET requests only.
//
//   k6 run -e BASE_URL=https://waddyapp.com tests/load/capacity.js
//
// STEPS      users at once to test, in order          default 10,25,50,75,100,150,200,300
// STEP_TIME  seconds to hold each level                default 90
// THINK      seconds a user waits between screens      default 5-15 (a real person reading, then tapping)
// ZONE, LAT, LNG   the delivery zone the users are in  default zone 1 (Maadi) and its centre
//
// A "user" opens the app (config), sees the home screen of a section (Groceries, Food, Pets or Places),
// opens a store, then an item, like the customer app does. That is what Firebase shows as "active users".
import http from 'k6/http';
import { sleep } from 'k6';

const BASE = (__ENV.BASE_URL || 'https://waddyapp.com').replace(/\/$/, '') + '/api/v1/';
const STEPS = (__ENV.STEPS || '10,25,50,75,100,150,200,300').split(',').map((n) => parseInt(n, 10));
const STEP_TIME = parseInt(__ENV.STEP_TIME || '90', 10);
const [THINK_MIN, THINK_MAX] = (__ENV.THINK || '5-15').split('-').map(Number);
const ZONE = __ENV.ZONE || '1';
const LAT = __ENV.LAT || '29.9635';
const LNG = __ENV.LNG || '31.2933';
const GAP = 5;

// Store sections and how often users open them; Places (module 4) has its own API.
const SECTIONS = [{ id: 1, weight: 0.4 }, { id: 2, weight: 0.3 }, { id: 7, weight: 0.15 }, { id: 4, weight: 0.15, places: true }];

const OK_P95 = 2000, OK_ERR = 0.01, SLOW_P95 = 5000, BROKEN_P95 = 10000, BROKEN_ERR = 0.1;

const scenarios = {};
const thresholds = {};
STEPS.forEach((users, i) => {
    const tag = String(users);
    scenarios['users_' + tag] = {
        executor: 'constant-vus', exec: 'user', vus: users, duration: STEP_TIME + 's',
        startTime: (i * (STEP_TIME + GAP)) + 's', gracefulStop: '3s', tags: { step: tag },
    };
    // The abort thresholds also make k6 keep per-step numbers for the table below.
    thresholds['http_req_duration{step:' + tag + '}'] = [{ threshold: 'p(95)<' + BROKEN_P95, abortOnFail: true, delayAbortEval: '45s' }];
    thresholds['http_req_failed{step:' + tag + '}'] = [{ threshold: 'rate<' + BROKEN_ERR, abortOnFail: true, delayAbortEval: '45s' }];
    thresholds['http_reqs{step:' + tag + '}'] = ['count>=0'];
});

// Every endpoint name used below, so the summary can rank them by speed.
const NAMES = ['config', 'modules', 'banners', 'categories', 'stores_popular', 'items_popular', 'items_discounted', 'campaigns',
    'flash_sales', 'store_details', 'store_items', 'item_details', 'places', 'places_categories', 'places_banners',
    'places_trending', 'place_details', 'place_reviews'];
NAMES.forEach((n) => {
    thresholds['http_req_duration{name:' + n + '}'] = ['max>=0'];
});

export const options = {
    scenarios,
    thresholds,
    summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'],
};

function think() {
    sleep(THINK_MIN + Math.random() * (THINK_MAX - THINK_MIN));
}

function pickSection() {
    let r = Math.random();
    for (let i = 0; i < SECTIONS.length; i++) {
        r -= SECTIONS[i].weight;
        if (r <= 0) {
            return SECTIONS[i];
        }
    }
    return SECTIONS[0];
}

function pick(list) {
    return list && list.length ? list[Math.floor(Math.random() * list.length)] : null;
}

function get(path, name, moduleId) {
    const headers = { Accept: 'application/json', zoneId: '[' + ZONE + ']', latitude: LAT, longitude: LNG, 'X-localization': 'ar' };
    if (moduleId) {
        headers.moduleId = String(moduleId);
    }
    const res = http.get(BASE + path, { headers, tags: { name } });
    if (res.status === 0 || res.status >= 400) {
        console.warn(name + ' failed: ' + res.status + ' ' + (res.error || ''));
    }
    try {
        return res.json();
    } catch (e) {
        return null;
    }
}

let started = false;

export function user() {
    if (!started) {
        get('config', 'config');
        get('module', 'modules');
        started = true;
    }
    const section = pickSection();
    if (section.places) {
        placesUser(section.id);
    } else {
        storeUser(section.id);
    }
}

function storeUser(m) {
    // Home screen of the section
    get('banners', 'banners', m);
    get('categories', 'categories', m);
    const popular = get('stores/popular', 'stores_popular', m);
    get('items/popular?offset=1&limit=12', 'items_popular', m);
    get('items/discounted?offset=1&limit=12', 'items_discounted', m);
    get('campaigns/basic', 'campaigns', m);
    get('flash-sales', 'flash_sales', m);
    think();

    // A store
    const stores = (popular && popular.stores) || (Array.isArray(popular) ? popular : []);
    const store = pick(stores);
    if (!store) {
        return;
    }
    get('stores/details/' + store.id, 'store_details', m);
    const items = get('items/latest?store_id=' + store.id + '&category_id=0&offset=1&limit=12', 'store_items', m);
    think();

    // An item
    const item = pick(items && items.products);
    if (item) {
        get('items/details/' + item.id, 'item_details', m);
        think();
    }
}

function placesUser(m) {
    const list = get('places', 'places', m);
    get('places/categories', 'places_categories', m);
    get('places/banners', 'places_banners', m);
    get('places/trending', 'places_trending', m);
    think();
    const places = (list && (list.data || list.places)) || (Array.isArray(list) ? list : []);
    const place = pick(places);
    if (place && place.id) {
        get('places/' + place.id, 'place_details', m);
        get('places/' + place.id + '/reviews', 'place_reviews', m);
        think();
    }
}

// ---------- The table ----------
export function handleSummary(data) {
    const m = data.metrics;
    const v = (name, stat) => (m[name] && m[name].values[stat] !== undefined ? m[name].values[stat] : null);
    const ms = (x) => (x === null ? '-' : x >= 1000 ? (x / 1000).toFixed(1) + 's' : Math.round(x) + 'ms');
    const pad = (s, n) => (String(s) + ' '.repeat(n)).slice(0, n);

    const rows = [];
    let best = null;
    STEPS.forEach((users, i) => {
        const s = 'step:' + users;
        const count = v('http_reqs{' + s + '}', 'count');
        if (!count) {
            return;
        }
        const ran = Math.max(1, Math.min(STEP_TIME, data.state.testRunDurationMs / 1000 - i * (STEP_TIME + GAP)));
        const p95 = v('http_req_duration{' + s + '}', 'p(95)');
        const err = v('http_req_failed{' + s + '}', 'rate') || 0;
        const verdict = p95 < OK_P95 && err < OK_ERR ? 'OK' : p95 < SLOW_P95 && err < BROKEN_ERR ? 'SLOW' : 'BROKEN';
        const rps = count / ran;
        rows.push(pad(users, 7) + pad(rps.toFixed(1), 9) + pad(ms(v('http_req_duration{' + s + '}', 'med')), 9) + pad(ms(p95), 9)
            + pad(ms(v('http_req_duration{' + s + '}', 'max')), 9) + pad((err * 100).toFixed(1) + '%', 8) + verdict);
        if (verdict === 'OK') {
            best = { users, rps };
        }
    });

    let out = '\n  WADDY CAPACITY  (app users, ' + THINK_MIN + '-' + THINK_MAX + 's between screens)\n\n'
        + '  ' + pad('users', 7) + pad('req/s', 9) + pad('median', 9) + pad('p95', 9) + pad('slowest', 9) + pad('errors', 8) + 'verdict\n'
        + rows.map((r) => '  ' + r).join('\n') + '\n\n'
        + '  OK = 95% of requests under ' + OK_P95 / 1000 + 's and under 1% errors.  SLOW = under ' + SLOW_P95 / 1000 + 's.  BROKEN = worse.\n';
    if (best) {
        out += '\n  MAX WITH GOOD SPEED: ' + best.users + ' users at the same time (' + best.rps.toFixed(1) + ' requests/second).\n';
        if (best.users === STEPS[STEPS.length - 1]) {
            out += '  The highest level tested was still OK; run again with bigger STEPS to find the real limit.\n';
        }
    } else if (rows.length) {
        out += '\n  Even the first level (' + STEPS[0] + ' users) was not OK; run again with smaller STEPS, e.g. -e STEPS=2,5,10,15,20\n';
    }
    const slow = NAMES.map((n) => ({ n, med: v('http_req_duration{name:' + n + '}', 'med'), p95: v('http_req_duration{name:' + n + '}', 'p(95)') }))
        .filter((x) => x.p95 !== null && x.p95 > 0).sort((a, b) => b.p95 - a.p95);
    out += '\n  SLOWEST ENDPOINTS (whole run)\n' + slow.map((x) => '  ' + pad(x.n, 20) + 'median ' + pad(ms(x.med), 9) + 'p95 ' + ms(x.p95)).join('\n') + '\n';
    return { stdout: out + '\n' };
}
