// Launch test: what 2,000 / 5,000 / 10,000 installed users do to the server, at a normal busy hour and
// during a spike (push notification to everyone, influencer post). Each phase runs app users browsing
// (capacity.js) and guests placing real cash-on-delivery orders (orders.js) at the same time.
// WRITES orders: needs ALLOW_ORDERS=1; remove them afterwards with `php artisan loadtest:cleanup`.
//
//   k6 run -e ALLOW_ORDERS=1 tests/load/launch.js
//   k6 run -e ALLOW_ORDERS=1 -e INSTALLS=20000 tests/load/launch.js     # other install counts
//
// INSTALLS    comma-separated install counts                 default 2000,5000,10000
// PHASE_TIME  seconds per phase                               default 120
//
// From installs to load (typical delivery-app numbers, deliberately generous on orders):
//   busy hour: 25% of installs open the app that day, 15% of those sessions fall in the busiest hour,
//              a session lasts ~5 minutes  -> users at once = installs x 0.25 x 1.5 x 0.15 x 5/60 ≈ installs / 133
//              orders/min = installs / 2000
//   spike:     3% of all installs open the app within the same few minutes -> users at once = installs x 0.03
//              orders/min = installs / 500
import { user } from './capacity.js';
import { buyer } from './orders.js';

const INSTALLS = (__ENV.INSTALLS || '2000,5000,10000').split(',').map((n) => parseInt(n, 10));
const PHASE_TIME = parseInt(__ENV.PHASE_TIME || '120', 10);
const GAP = 10;

const PHASES = [];
INSTALLS.forEach((installs) => {
    PHASES.push({ installs, kind: 'busy hour', users: Math.max(1, Math.round(installs / 133)), ordersPerMin: Math.max(1, Math.round(installs / 2000)) });
    PHASES.push({ installs, kind: 'spike', users: Math.round(installs * 0.03), ordersPerMin: Math.max(1, Math.round(installs / 500)) });
});

const scenarios = {};
const thresholds = { 'http_req_duration': [{ threshold: 'p(95)<20000', abortOnFail: true, delayAbortEval: '60s' }] };
PHASES.forEach((p, i) => {
    const tag = p.installs + '_' + (p.kind === 'spike' ? 'spike' : 'busy');
    const start = (i * (PHASE_TIME + GAP)) + 's';
    scenarios['browse_' + tag] = {
        executor: 'constant-vus', exec: 'browse', vus: p.users, duration: PHASE_TIME + 's', startTime: start,
        gracefulStop: '5s', tags: { phase: tag },
    };
    scenarios['order_' + tag] = {
        executor: 'constant-arrival-rate', exec: 'order', rate: p.ordersPerMin, timeUnit: '1m', duration: PHASE_TIME + 's',
        startTime: start, preAllocatedVUs: Math.max(2, p.ordersPerMin), maxVUs: p.ordersPerMin * 4 + 4, gracefulStop: '30s', tags: { phase: tag },
    };
    thresholds['http_req_duration{phase:' + tag + '}'] = ['max>=0'];
    thresholds['http_req_failed{phase:' + tag + '}'] = ['rate>=0'];
    thresholds['http_reqs{phase:' + tag + '}'] = ['count>=0'];
    thresholds['order_failed{phase:' + tag + '}'] = ['rate>=0'];
    thresholds['orders_placed{phase:' + tag + '}'] = ['count>=0'];
    thresholds['place_order_time{phase:' + tag + '}'] = ['max>=0'];
});

export const options = { scenarios, thresholds, summaryTrendStats: ['med', 'p(95)', 'max'] };

export function setup() {
    if (!__ENV.ALLOW_ORDERS) {
        throw new Error('launch.js places real orders. Pass -e ALLOW_ORDERS=1 to confirm.');
    }
}

export function browse() {
    user();
}

export function order() {
    buyer();
}

export function handleSummary(data) {
    const m = data.metrics;
    const v = (name, stat) => (m[name] && m[name].values[stat] !== undefined ? m[name].values[stat] : null);
    const ms = (x) => (x === null ? '-' : x >= 1000 ? (x / 1000).toFixed(1) + 's' : Math.round(x) + 'ms');
    const pad = (s, n) => (String(s) + ' '.repeat(n)).slice(0, n);

    let out = '\n  WADDY LAUNCH TEST  (' + PHASE_TIME + ' s per phase, browsing + real orders together)\n\n'
        + '  ' + pad('installs', 10) + pad('moment', 11) + pad('users', 7) + pad('ord/min', 9) + pad('req/s', 8) + pad('median', 9)
        + pad('p95', 9) + pad('errors', 8) + pad('orders', 8) + pad('ord fail', 9) + pad('order p95', 11) + 'verdict\n';
    PHASES.forEach((p, i) => {
        const tag = p.installs + '_' + (p.kind === 'spike' ? 'spike' : 'busy');
        const s = '{phase:' + tag + '}';
        const count = v('http_reqs' + s, 'count');
        if (!count) {
            return;
        }
        const ran = Math.max(1, Math.min(PHASE_TIME, data.state.testRunDurationMs / 1000 - i * (PHASE_TIME + GAP)));
        const p95 = v('http_req_duration' + s, 'p(95)');
        const err = v('http_req_failed' + s, 'rate') || 0;
        const orderFail = v('order_failed' + s, 'rate') || 0;
        const verdict = p95 < 2000 && err < 0.01 && orderFail < 0.02 ? 'OK' : p95 < 5000 && err < 0.1 ? 'SLOW' : 'BROKEN';
        out += '  ' + pad(p.installs.toLocaleString(), 10) + pad(p.kind, 11) + pad(p.users, 7) + pad(p.ordersPerMin, 9)
            + pad((count / ran).toFixed(1), 8) + pad(ms(v('http_req_duration' + s, 'med')), 9) + pad(ms(p95), 9)
            + pad((err * 100).toFixed(1) + '%', 8) + pad(v('orders_placed' + s, 'count') || 0, 8) + pad((orderFail * 100).toFixed(1) + '%', 9)
            + pad(ms(v('place_order_time' + s, 'p(95)')), 11) + verdict + '\n';
    });
    out += '\n  OK = 95% of requests under 2 s, under 1% errors and under 2% failed orders. SLOW = under 5 s. BROKEN = worse.\n'
        + '  Driver and store apps are not included (they need their own logins).\n';
    return { stdout: out + '\n' };
}
