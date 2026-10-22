// Order rush: app guests placing real cash-on-delivery orders at the same time, while (optionally) other
// users browse, drivers send their location and stores poll for new orders. WRITES orders to the database
// and notifies the stores, so it only runs with ALLOW_ORDERS=1. Every order uses a k6-...@loadtest.invalid
// email and the name "k6 Load Test", so they are easy to find and delete afterwards.
//
//   k6 run -e ALLOW_ORDERS=1 tests/load/orders.js                         # 1 order, to check the flow
//   k6 run -e ALLOW_ORDERS=1 -e STEPS=5,10,20,40 tests/load/orders.js     # orders/minute levels
//
// STEPS      orders started per minute, one level after another   default: a single order
// STEP_TIME  seconds per level                                    default 90
// BROWSE     app users browsing at the same time (capacity.js)    default 0
// STORES     module:store pairs to spread orders over            default the Food stores + The Pet Mart in Maadi
//            (Groceries is left out: its cash-on-delivery cap was 10 EGP on 2026-10-22, below every store minimum)
// ZONE / LAT / LNG   delivery zone and customer location          default zone 1 (Maadi)
import http from 'k6/http';
import { sleep, fail } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';
import { user as browser } from './capacity.js';

const BASE = (__ENV.BASE_URL || 'https://waddyapp.com').replace(/\/$/, '') + '/api/v1/';
const STEPS = __ENV.STEPS ? __ENV.STEPS.split(',').map((n) => parseInt(n, 10)) : null;
const STEP_TIME = parseInt(__ENV.STEP_TIME || '90', 10);
const BROWSE = parseInt(__ENV.BROWSE || '0', 10);
const STORES = (__ENV.STORES || '2:32,2:33,2:34,2:35,2:36,7:61').split(',').map((pair) => {
    const [module, store] = pair.split(':');
    return { module, store };
});
const ZONE = __ENV.ZONE || '1';
const LAT = __ENV.LAT || '29.9635';
const LNG = __ENV.LNG || '31.2933';
const GAP = 5;

const placed = new Counter('orders_placed');
const failed = new Rate('order_failed');
const placeTime = new Trend('place_order_time', true);

const scenarios = {};
const thresholds = { order_failed: ['rate<0.02'], 'http_req_duration{name:order_place}': ['p(95)<5000'] };
if (STEPS) {
    STEPS.forEach((perMin, i) => {
        const tag = String(perMin);
        scenarios['orders_' + tag] = {
            executor: 'constant-arrival-rate', exec: 'buyer', rate: perMin, timeUnit: '1m', duration: STEP_TIME + 's',
            startTime: (i * (STEP_TIME + GAP)) + 's', preAllocatedVUs: Math.max(2, perMin), maxVUs: perMin * 4, tags: { step: tag },
        };
        thresholds['order_failed{step:' + tag + '}'] = ['rate>=0'];
        thresholds['place_order_time{step:' + tag + '}'] = ['max>=0'];
        thresholds['orders_placed{step:' + tag + '}'] = ['count>=0'];
    });
    if (BROWSE) {
        scenarios.browsers = { executor: 'constant-vus', exec: 'browse', vus: BROWSE, duration: (STEPS.length * (STEP_TIME + GAP)) + 's' };
    }
} else {
    scenarios.one = { executor: 'shared-iterations', exec: 'buyer', vus: 1, iterations: 1 };
}

export const options = { scenarios, thresholds, summaryTrendStats: ['med', 'p(95)', 'max'] };

export function setup() {
    if (!__ENV.ALLOW_ORDERS) {
        fail('orders.js places real orders. Pass -e ALLOW_ORDERS=1 to confirm.');
    }
}

export function browse() {
    browser();
}

function headers(token, module) {
    const h = { Accept: 'application/json', zoneId: '[' + ZONE + ']', moduleId: module || STORES[0].module, latitude: LAT, longitude: LNG, 'X-localization': 'en' };
    if (token) {
        h['guest-token'] = token;
    }
    return h;
}

function json(res) {
    try {
        return res.json();
    } catch (e) {
        return null;
    }
}

function give(reason) {
    console.warn('order not placed: ' + reason);
    failed.add(true);
}

export function buyer() {
    // 1. App start: a guest identity.
    const g = json(http.post(BASE + 'auth/guest/request', null, { headers: headers(), tags: { name: 'order_guest' } }));
    if (!g || !g.guest_token) {
        return give('no guest token');
    }
    const token = g.guest_token;
    const { module: MODULE, store: STORE_ID } = STORES[Math.floor(Math.random() * STORES.length)];

    // 2. Browse the store and fill the bag past its minimum order.
    const store = json(http.get(BASE + 'stores/details/' + STORE_ID, { headers: headers(token, MODULE), tags: { name: 'order_store' } })) || {};
    const list = json(http.get(BASE + 'items/latest?store_id=' + STORE_ID + '&category_id=0&offset=1&limit=24', { headers: headers(token, MODULE), tags: { name: 'order_items' } }));
    const items = ((list && list.products) || []).filter((p) => p.stock > 0 && !(p.variations || []).length && !(p.food_variations || []).length);
    if (!items.length) {
        return give('store ' + STORE_ID + ' has no simple in-stock items');
    }
    sleep(2 + Math.random() * 3);
    // Stop just past the store's minimum: the zone caps cash-on-delivery orders, so a big basket is refused.
    const minimum = parseFloat(store.minimum_order || 0);
    const cheap = items.sort((x, y) => x.price - y.price).slice(0, 8).sort(() => Math.random() - 0.5);
    let total = 0;
    for (let i = 0; i < cheap.length && (total <= minimum || i === 0); i++) {
        const item = cheap[i];
        const res = http.post(BASE + 'customer/cart/add', { item_id: item.id, model: 'Item', price: item.price, quantity: 1 }, { headers: headers(token, MODULE), tags: { name: 'order_cart_add' } });
        if (res.status !== 200) {
            return give('cart add answered ' + res.status + ' ' + String(res.body).slice(0, 200));
        }
        total += parseFloat(item.price);
        sleep(1 + Math.random() * 2);
    }

    // 3. Checkout.
    const tag = __VU + '-' + __ITER + '-' + Date.now();
    const started = Date.now();
    const res = http.post(BASE + 'customer/order/place', {
        payment_method: 'cash_on_delivery', order_type: 'delivery', store_id: STORE_ID, distance: '1.2',
        address: 'k6 load test, 1 Test Street', latitude: LAT, longitude: LNG,
        contact_person_name: 'k6 Load Test', contact_person_number: '+2010' + String(Math.floor(Math.random() * 1e8)).padStart(8, '0'),
        contact_person_email: 'k6-' + tag + '@loadtest.invalid', order_amount: String(total),
    }, { headers: headers(token, MODULE), tags: { name: 'order_place' }, timeout: '60s' });
    placeTime.add(Date.now() - started);
    const body = json(res);
    if (res.status !== 200 || !body || !body.order_id) {
        return give('place answered ' + res.status + ' ' + String(res.body).slice(0, 300));
    }
    placed.add(1);
    failed.add(false);
    if (!STEPS) {
        console.log('order placed: #' + body.order_id + ' at store ' + STORE_ID + ' for ' + total + ' in ' + (Date.now() - started) + ' ms');
    }
}

export function handleSummary(data) {
    const m = data.metrics;
    const v = (name, stat) => (m[name] && m[name].values[stat] !== undefined ? m[name].values[stat] : null);
    const ms = (x) => (x === null ? '-' : x >= 1000 ? (x / 1000).toFixed(1) + 's' : Math.round(x) + 'ms');
    const pad = (s, n) => (String(s) + ' '.repeat(n)).slice(0, n);
    let out = '\n  WADDY ORDERS' + (BROWSE ? ' (with ' + BROWSE + ' app users browsing)' : '') + '\n\n';
    if (STEPS) {
        out += '  ' + pad('orders/min', 12) + pad('placed', 8) + pad('failed', 8) + pad('median', 9) + pad('p95', 9) + 'slowest\n';
        STEPS.forEach((perMin) => {
            const s = '{step:' + perMin + '}';
            if (v('place_order_time' + s, 'max') === null || v('place_order_time' + s, 'max') === 0) {
                return;
            }
            out += '  ' + pad(perMin, 12) + pad(v('orders_placed' + s, 'count') || 0, 8) + pad(((v('order_failed' + s, 'rate') || 0) * 100).toFixed(1) + '%', 8)
                + pad(ms(v('place_order_time' + s, 'med')), 9) + pad(ms(v('place_order_time' + s, 'p(95)')), 9) + ms(v('place_order_time' + s, 'max')) + '\n';
        });
    }
    out += '\n  total placed: ' + (v('orders_placed', 'count') || 0) + ', place-order time median ' + ms(v('place_order_time', 'med')) + ', p95 ' + ms(v('place_order_time', 'p(95)')) + '\n';
    return { stdout: out + '\n' };
}
