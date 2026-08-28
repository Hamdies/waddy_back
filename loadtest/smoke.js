// Smoke test — 1 virtual user, ~30s. Run this FIRST.
//
// It proves the endpoints, headers and IDs are correct before you point any
// real load at the server. If this reports failures, fix the config in
// config.js rather than moving on to the load stages.
//
//   k6 run -e BASE_URL=https://your-host loadtest/smoke.js

import http from 'k6/http';
import { check, group } from 'k6';
import { BASE, HEADERS, endpoints } from './config.js';

export const options = {
  // Local runs address the box by IP, so the cert will not match the host.
  insecureSkipTLSVerify: true,
  vus: 1,
  duration: '30s',
  thresholds: {
    // A smoke test must be clean; anything else means misconfiguration.
    http_req_failed: ['rate<0.01'],
  },
};

export default function () {
  for (const ep of endpoints) {
    group(ep.name, () => {
      const res = http.get(`${BASE}${ep.path}`, {
        headers: HEADERS,
        tags: { endpoint: ep.name },
      });

      const ok = check(res, {
        'status is 200': (r) => r.status === 200,
        'body is not empty': (r) => r.body && r.body.length > 0,
      });

      if (!ok) {
        // One tidy line per failure. Dumping the body floods the terminal with
        // HTML error pages when every endpoint fails for the same reason.
        const hint = String(res.body || '')
          .replace(/<[^>]*>/g, ' ')
          .replace(/\s+/g, ' ')
          .trim()
          .slice(0, 90);
        console.error(`${ep.name} -> HTTP ${res.status} | ${hint}`);
      }
    });
  }
}
