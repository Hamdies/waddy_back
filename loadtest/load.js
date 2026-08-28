// Capacity test — finds the throughput ceiling of the API.
//
// Read-only. Ramps in stages and ABORTS AUTOMATICALLY if the server starts
// failing or slowing badly, so it degrades gracefully instead of taking a live
// site down. Run the smoke test first.
//
//   k6 run -e BASE_URL=https://your-host loadtest/load.js
//
// Start with PEAK_VUS low (25) and raise it only once you have seen a clean
// run. On a live server, run it during your quietest hour.

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate } from 'k6/metrics';
import { BASE, HEADERS, endpoints } from './config.js';

const PEAK = parseInt(__ENV.PEAK_VUS || '25', 10);

// Abort guards. The defaults are protective, for testing a healthy server.
// For a deliberate BASELINE run against a server you already know is
// saturated, loosen them (e.g. MAX_P95=10000 MAX_FAIL=0.30) so the run
// measures the ceiling instead of aborting the moment it finds it.
const MAX_P95 = parseInt(__ENV.MAX_P95 || '2000', 10);
const MAX_FAIL = parseFloat(__ENV.MAX_FAIL || '0.05');

// Stage lengths, shortened for runs against live sites.
const RAMP = __ENV.RAMP || '30s';
const HOLD = __ENV.HOLD || '2m';

// Per-endpoint latency, so you can tell which query is the bottleneck rather
// than only seeing one blended average.
const byEndpoint = {};
for (const ep of endpoints) {
  byEndpoint[ep.name] = new Trend(`dur_${ep.name}`, true);
}
const errorRate = new Rate('endpoint_errors');

export const options = {
  // Local runs address the box by IP, so the cert will not match the host.
  insecureSkipTLSVerify: true,
  // k6 computes avg/min/med/max/p(90)/p(95) by default — p(99) has to be asked
  // for explicitly, or it reads back as 0 in the summary.
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
  stages: [
    { duration: RAMP, target: Math.ceil(PEAK * 0.2) },   // warm caches/opcache
    { duration: RAMP, target: Math.ceil(PEAK * 0.5) },
    { duration: RAMP, target: PEAK },
    { duration: HOLD, target: PEAK },                    // hold — the real read
    { duration: '20s', target: 0 },                      // recovery
  ],
  thresholds: {
    // abortOnFail stops the run once the server is genuinely unhealthy.
    http_req_failed: [
      { threshold: `rate<${MAX_FAIL}`, abortOnFail: true, delayAbortEval: '30s' },
    ],
    http_req_duration: [
      { threshold: `p(95)<${MAX_P95}`, abortOnFail: true, delayAbortEval: '30s' },
    ],
  },
};

export default function () {
  // Each VU walks the whole catalogue, mimicking an app opening its home screen.
  for (const ep of endpoints) {
    const res = http.get(`${BASE}${ep.path}`, {
      headers: HEADERS,
      tags: { endpoint: ep.name },
    });

    byEndpoint[ep.name].add(res.timings.duration);

    const ok = check(res, { 'status is 200': (r) => r.status === 200 });
    errorRate.add(!ok);
  }

  sleep(1); // think time — without it you measure a hammer, not users
}

export function handleSummary(data) {
  const m = data.metrics;
  const g = (k, stat) => (m[k] && m[k].values[stat] != null ? m[k].values[stat] : 0);
  const f = (n) => Number(n).toFixed(0);

  const rows = endpoints
    .map((ep) => {
      const key = `dur_${ep.name}`;
      return {
        name: ep.name,
        p95: g(key, 'p(95)'),
        med: g(key, 'med'),
      };
    })
    .sort((a, b) => b.p95 - a.p95);

  let out = '\n================ WADDI CAPACITY SUMMARY ================\n\n';
  out += `Peak VUs configured : ${PEAK}\n`;
  out += `Requests            : ${f(g('http_reqs', 'count'))}\n`;
  out += `Throughput          : ${g('http_reqs', 'rate').toFixed(1)} req/s\n`;
  out += `Failure rate        : ${(g('http_req_failed', 'rate') * 100).toFixed(2)} %\n\n`;
  out += `Latency  p50 ${f(g('http_req_duration', 'med'))}ms  `;
  out += `p95 ${f(g('http_req_duration', 'p(95)'))}ms  `;
  out += `p99 ${f(g('http_req_duration', 'p(99)'))}ms  `;
  out += `max ${f(g('http_req_duration', 'max'))}ms\n\n`;
  out += 'Slowest endpoints (p95):\n';
  for (const r of rows) {
    out += `  ${r.name.padEnd(18)} p95 ${f(r.p95).padStart(6)}ms   median ${f(r.med).padStart(6)}ms\n`;
  }
  out += '\n=======================================================\n';

  // Relative to the working directory, so this works whether the script is run
  // from the repo root or from a copy elsewhere. Override with SUMMARY_OUT.
  const out_file = __ENV.SUMMARY_OUT || 'summary.json';

  return {
    stdout: out,
    [out_file]: JSON.stringify(data, null, 2),
  };
}
