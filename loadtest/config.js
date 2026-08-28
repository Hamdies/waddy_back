// Shared configuration for the Waddi load tests.
//
// Every endpoint listed here is READ-ONLY. Nothing in this suite places an
// order, writes a review, sends an SMS/push, or touches a payment gateway —
// running it against production creates no data and costs no money.

export const BASE = __ENV.BASE_URL || 'http://localhost:8000';

// These must match real rows on the server you are testing, otherwise you will
// be benchmarking 403/404 error paths instead of the real work.
//   moduleId  -> modules.id
//   zoneId    -> zones.id
// Find them with the SQL in loadtest/README.md.
export const MODULE_ID = __ENV.MODULE_ID || '1';
export const ZONE_ID = __ENV.ZONE_ID || '1';
export const LATITUDE = __ENV.LATITUDE || '30.0444';   // Cairo
export const LONGITUDE = __ENV.LONGITUDE || '31.2357';

// Set HOST_HEADER when hitting the server by IP from the box itself — nginx
// matches on server_name, so without it the request lands on the default
// vhost and gets redirected off the machine, which defeats the point of
// testing locally.
export const HOST_HEADER = __ENV.HOST_HEADER || '';

export const HEADERS = Object.assign(
  {
    'Content-Type': 'application/json',
    moduleId: MODULE_ID,
    zoneId: `[${ZONE_ID}]`,
    latitude: LATITUDE,
    longitude: LONGITUDE,
    'X-localization': 'en',
  },
  HOST_HEADER ? { Host: HOST_HEADER } : {}
);

// Ordered cheapest-first so a failure in the basics is obvious immediately.
export const endpoints = [
  { name: 'config',            path: '/api/v1/config' },
  { name: 'module',            path: '/api/v1/module' },
  { name: 'banners',           path: '/api/v1/banners' },
  { name: 'categories',        path: '/api/v1/categories' },
  { name: 'stores_all',        path: '/api/v1/stores/get-stores/all?offset=1&limit=10' },
  { name: 'stores_popular',    path: '/api/v1/stores/popular?offset=1&limit=10' },
  { name: 'stores_latest',     path: '/api/v1/stores/latest?offset=1&limit=10' },
  { name: 'stores_top_rated',  path: '/api/v1/stores/top-rated?offset=1&limit=10' },
  { name: 'items_popular',     path: '/api/v1/items/popular?offset=1&limit=10' },
  { name: 'categories_popular', path: '/api/v1/categories/popular' },
];
// NB: /api/v1/items/latest is deliberately absent — it requires store_id and
// category_id, so it cannot stand in for a generic catalogue read.
