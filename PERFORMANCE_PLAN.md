# Making Waddi feel like Talabat

A staged plan to close the perceived-speed gap with the major delivery apps.
Every number below was measured against production from Egypt on 29 Aug 2026,
not estimated.

## The starting position

```
DNS               2 ms
TCP connect      71 ms   ← round trip, Egypt → server
TLS handshake   +88 ms   ← full handshake every connection
server work      65 ms   ← measured on localhost, uncontended
─────────────────────────
one API request 417 ms
```

**The server is not the bottleneck.** 65 ms of work per request is competitive;
Talabat's backend is not meaningfully faster per call. The gap is entirely in
transport, round-trip count, and payload size — all of which are fixable without
touching application logic.

| Measured today | Value |
|---|---|
| Compression | **off** — `10,982 B` → `1,872 B` gzipped (83% waste) |
| Protocol | **HTTP/1.1** — no multiplexing |
| API `Cache-Control` | `no-cache, private` on everything |
| Image `Cache-Control` | **absent** — revalidates on every screen |
| Store image | 300×300 PNG, **57 KB** (WebP ≈ 10 KB) |
| Home screen | **9 API calls** ≈ 1,312 ms before first paint |

Target, achievable without the server getting one millisecond faster:

```
9 calls, keep-alive, today       1312 ms
1 aggregated call                 289 ms
1 aggregated call via Cairo edge   95 ms      ← ~14× improvement
```

---

# Tier 1 — Transport ✅ DONE (29 Aug 2026)

Deployed and measured from Egypt:

| | Before | After |
|---|---|---|
| Protocol | HTTP/1.1 | **HTTP/2** |
| `stores/get-stores/all` | 10,983 B | **1,900 B** (83%) |
| `module` | 10,630 B | **1,612 B** (85%) |
| `config` | 8,939 B | **2,387 B** (74%) |
| Admin stylesheet | 299 KB every load | **55 KB once**, then cached 30d |
| Uploaded images | revalidated every screen | **immutable, 1 year** |
| API home screen total | ~56 KB | **~10 KB** |

Server capacity was unaffected: the localhost load test returned 52.2 req/s and
7,370 requests both before and after, identical. At these payload sizes
compression costs microseconds against ~65 ms of PHP per request, so
`gzip_comp_level 5` does not need lowering.

Two things this cost, worth recording:

- Stock `nginx.conf` already sets `gzip on;` — a duplicate is fatal. It also
  leaves `gzip_types` commented out, defaulting to `text/html`, which is why
  compression appeared enabled while every JSON response went out uncompressed.
- The asset regex initially hijacked the `/public/` alias and 404'd every admin
  stylesheet, because nginx evaluates regex locations before falling back to a
  prefix match. Fixed with `^~` on the alias. **`nginx -t` passes on this;
  only requesting a real asset catches it.**

Implemented by `deploy/nginx/waddy-performance.conf` (http-context drop-in) and
`deploy/nginx/apply-vhost-tuning.sh` (server-context, idempotent, self-reverting).

---

## Original analysis

**Effort:** ~2 hours · **Risk:** low, all reversible · **No application changes**

Expected: **83% fewer bytes**, one TLS handshake instead of up to six, and
images that stop revalidating.

### 1.1 Compression

nginx `http` block:

```nginx
gzip              on;
gzip_vary         on;
gzip_proxied      any;
gzip_comp_level   5;
gzip_min_length   256;
gzip_types        application/json application/javascript text/css text/plain
                  application/xml image/svg+xml;
```

`gzip_comp_level 5` is deliberate — 9 costs noticeably more CPU for ~2% more
compression, and CPU is the constrained resource on this box.

Brotli is better still (~15% over gzip) but needs `ngx_brotli`, which Ubuntu's
nginx does not ship. Not worth compiling nginx for; revisit if you move to
Cloudflare, which does Brotli at the edge for free.

> **Do not** enable gzip on already-compressed types (PNG, JPEG, WebP, ZIP). It
> burns CPU for nothing. The `gzip_types` list above deliberately excludes them.

### 1.2 HTTP/2

In the `waddyapp.com` server block:

```nginx
listen 443 ssl;
http2 on;          # nginx ≥ 1.25.1; older syntax is `listen 443 ssl http2;`
```

This is the highest-leverage line in Tier 1. Today the app opens up to six
connections for nine calls, each paying the 88 ms handshake. HTTP/2 multiplexes
all nine over one connection with one handshake.

### 1.3 TLS handshake cost

```nginx
ssl_session_cache    shared:SSL:10m;
ssl_session_timeout  1d;
ssl_session_tickets  off;
ssl_stapling         on;
ssl_stapling_verify  on;
resolver             1.1.1.1 8.8.8.8 valid=300s;
resolver_timeout     5s;
```

Session resumption turns the 88 ms full handshake into an abbreviated one for
returning clients. OCSP stapling removes a separate lookup the client would
otherwise make against Let's Encrypt.

### 1.4 Static asset caching

```nginx
location /storage/ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
    access_log off;
    try_files $uri =404;
}

location ~* \.(css|js|woff2?|svg|ico)$ {
    expires 30d;
    add_header Cache-Control "public, max-age=2592000";
    access_log off;
}
```

Uploaded filenames already carry a date and hash (`2026-01-28-697a420f020c0.png`)
and are never rewritten in place, so `immutable` is safe and correct. Today every
image costs a round trip just to be told it has not changed.

> `try_files $uri =404` matters: without it a missing image falls through to
> Laravel and costs a full PHP request to produce a 404.

### Verification

```bash
sudo nginx -t && sudo systemctl reload nginx

curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" \
  -H "moduleId: 1" -H "zoneId: [1]" \
  https://waddyapp.com/api/v1/stores/get-stores/all?offset=1\&limit=10 \
  | grep -iE "content-encoding|vary"          # want: gzip

curl -s -o /dev/null -w "%{http_version}\n" https://waddyapp.com/api/v1/config   # want: 2

curl -s -o /dev/null -D - https://waddyapp.com/storage/business/<some>.png \
  | grep -i cache-control                      # want: immutable
```

Re-run the localhost load test afterwards. Compression trades CPU for bytes, and
CPU is the constrained resource — if throughput drops below ~50 req/s, lower
`gzip_comp_level` to 3.

---

# Tier 2 — Edge

**Effort:** ~1 week · **Risk:** medium — one correctness blocker must be cleared first

### 2.1 Blocker: responses vary by header, and say nothing about it

Verified in production: `GET /api/v1/categories` returns **different content** for
`moduleId: 1` versus `moduleId: 2`, at the same URL, with **no `Vary` header**.

The API varies on `moduleId`, `zoneId`, `latitude`, `longitude` and
`X-localization` — all headers. A CDN keys its cache on the URL. Enable API
caching today and it will serve Groceries data to someone browsing Food, and
Maadi stores to someone in Zamalek.

**This must be resolved before any API response is made cacheable.** Three
options:

| Option | Correctness | Hit rate | Effort |
|---|---|---|---|
| **A.** Send `Vary: moduleId, zoneId, X-localization` | Correct | Poor — every combination is a separate entry, and some CDNs ignore `Vary` on custom headers | Trivial |
| **B.** Move the discriminators into the URL: `/api/v2/m/{module}/z/{zone}/categories` | Correct | Excellent — clean, predictable keys | Days; needs an app release |
| **C.** Cache only genuinely global endpoints | Correct | Limited but free | Hours |

**Recommended: C now, B alongside the Tier 3 aggregate endpoint.** Option A is a
trap — it looks like it works, and the failure mode is silently serving the wrong
city's restaurants.

Endpoints that are safe to cache today because they do not vary by module or
zone: static asset routes, and the landing/config content once the module-scoped
parts are split out. Audit each one before adding a header to it; the cost of
getting this wrong is customer-visible and hard to notice.

### 2.2 Cache-Control middleware

Laravel already has `cache.headers` registered in `app/Http/Kernel.php`. For
per-route control:

```php
Route::get('banners', 'BannerController@get_banners')
    ->middleware('cache.headers:public;max_age=60;etag');
```

`etag` matters as much as `max_age`: on revalidation an unchanged response
returns `304` with no body, which on a mobile connection is the difference
between 2 KB and nothing.

Start at `max_age=60`. Sixty seconds of staleness on a banner is invisible to a
customer and removes that endpoint from your server almost entirely under load.

### 2.3 Cloudflare

Free tier covers most of this, with two caveats found on 29 Aug 2026:

- **Egypt is served from Marseille (`colo=MRS`), not Cairo.** Measured direct vs
  proxied, API latency was roughly neutral — 0.065 s vs 0.051 s connect, 0.309 s
  vs 0.282 s TTFB. The "RTT 71 ms → 15 ms" figure below was wrong.
- **Polish is a paid feature**, so edge WebP conversion is not available.

The real free-tier win is edge caching of static content: an image served from
cache measured **0.176 s versus 0.351 s** direct — half the time, and it never
touches the origin. That offload matters more as the catalogue grows.

Order of operations matters:

1. Add the domain, copy existing DNS records, **leave records grey-clouded**
   (DNS-only) at first.
2. Set SSL/TLS mode to **Full (strict)** — your Let's Encrypt cert is valid, and
   `Flexible` would leave Cloudflare→origin unencrypted.
3. Enable **Brotli**, **HTTP/3**, **0-RTT**, **Always Use HTTPS**.
4. Orange-cloud `www` and the apex. Verify the site works end to end.
5. **Cache rule: bypass cache for `/api/*` initially.** Turn caching on
   per-endpoint only after §2.1 is resolved.
6. Cache rule: `/storage/*` → cache everything, edge TTL 1 year, respect origin
   headers.
7. ~~Enable **Polish (Lossy) + WebP**~~ — **NOT available on the free plan.**
   Verified 29 Aug 2026: the dashboard shows "Upgrade required" (Pro, ~$20/mo).
   The 57 KB → ~10 KB image saving must therefore come from the origin instead —
   see TIER3_PLAN.md §3, which uses intervention/image (already a dependency) to
   generate WebP variants on upload.

> Keep the origin rate limiter. Cloudflare hides client IPs behind its own, so
> also enable `TrustProxies` (currently commented out in `app/Http/Kernel.php`)
> with Cloudflare's ranges, or **every request will appear to come from one IP**
> and the throttle will lock out your entire user base.

That `TrustProxies` line is the single most likely way this step breaks
production. Do it in the same change, not afterwards.

### 2.4 Verification

```bash
curl -s -o /dev/null -D - https://waddyapp.com/storage/<img>.png | grep -iE "cf-cache-status|content-type"
# want: cf-cache-status: HIT, content-type: image/webp

curl -s -o /dev/null -w "connect %{time_connect}s  ttfb %{time_starttransfer}s\n" https://waddyapp.com/api/v1/config
# want: connect well under 71 ms
```

Check `storage/logs/laravel.log` and the rate limiter for a spike in 429s in the
first hour — that is the `TrustProxies` symptom.

---

# Tier 3 — Round trips and the client

**Effort:** 2–4 weeks · **Risk:** medium, needs a coordinated app release
**This is the tier that actually closes the gap.**

### 3.1 One home-screen endpoint

Nine sequential calls cost `9 × (71 + 65) = 1,312 ms`. One call costs 289 ms, and
95 ms behind an edge.

`StoreController@get_combined_data` exists but is a `switch` on `data_type` — it
returns one section per call, so it does not reduce round trips. What is needed
is a genuine aggregate:

```
GET /api/v2/m/{module}/z/{zone}/home
```

returning banners, categories, popular stores, latest stores, popular items and
config in **one** response.

Design notes that matter:

- **Build it in v2**, leave v1 untouched. Old app versions stay in the field for
  months.
- **Assemble from cached fragments**, not one giant query. Each section cached
  independently (`Cache::remember("home:banners:{$module}:{$zone}", 60, ...)`)
  means a slow section degrades alone rather than taking the screen with it.
- **Module and zone in the path**, resolving §2.1 for this endpoint by
  construction and making it edge-cacheable.
- **Cap each section** at what the screen actually renders — 10 items, not 50.
- **Return an ETag** for the whole payload so a returning user gets `304`.

Expect the response to be larger than any single current call but far smaller
than the nine combined, especially gzipped.

### 3.2 Images ✅ DONE (29 Aug 2026)

Cloudflare Polish turned out to be a paid feature (§2.3), so this was done at the
origin instead — which is the more durable place regardless.

`Helpers::upload()` now queues `thumb` (150 px) and `card` (400 px) WebP copies,
each with a JPEG fallback, beside the untouched original; a queued
`images:backfill-variants` command does the same for everything already uploaded.
The API exposes them as a `logo_variants` object *only* to a client that asks
with `X-Image-Variants: 1`, so payloads for the builds in the field are
unchanged.

**Measured on the existing image set: 1,464 KB → 58 KB, 96% smaller.** The
conversion, not the resize, is most of that — a 500 px PNG re-encoded to WebP at
unchanged dimensions is already ~70% smaller.

The caveat this section originally understated: the pipeline deploys with no app
release, but the bytes only leave the wire once the client requests the variant
URLs. Detail and caveats in [TIER3_PLAN.md](TIER3_PLAN.md) §3.

### 3.3 The client is where "instant" actually comes from

This is most of the perceived difference, and none of it is backend work:

- **Skeleton screens** — draw layout immediately, never a spinner on a blank page.
- **Stale-while-revalidate** — render last-known-good data from local storage on
  launch, fetch in the background, reconcile. Uber Eats appears instant because
  it is not waiting for the network to draw anything.
- **Prefetch** the store detail payload when a card scrolls into view.
- **Optimistic UI** on cart actions — update locally, reconcile with the server
  after.
- **Persist the home payload** across launches with a short TTL.

A backend that answers in 95 ms still feels slow behind a spinner. A backend that
answers in 300 ms feels instant behind a skeleton screen and a warm cache.

---

# Sequencing

| Stage | Effort | Gain | Depends on |
|---|---|---|---|
| Tier 1 ✅ | hours | 83% fewer bytes, one handshake | — |
| Cloudflare + `TrustProxies` ✅ | days | edge cache, HTTP/3; **not** the predicted RTT win | Tier 1 |
| Tier 3 image variants ✅ | days | 96% fewer image bytes | — |
| §2.1 header/URL audit | days | correctness gate | — |
| Tier 3 aggregate endpoint | weeks | 1,312 → 289 ms | §2.1 |
| Client caching / skeletons | weeks | perceived instant | aggregate |

Do Tier 1 first — it is hours of work, needs no app release, and its gains are
independent of everything else. Do not enable API caching at the edge until
§2.1 is settled.

**Re-measure after each stage** with the localhost load test and a timing
breakdown from a phone on mobile data in Cairo. The laptop-versus-localhost
lesson from the capacity work applies here too: measure from where the customers
are, or the network noise will exceed the effect being measured.

## What this does not fix

The server still does 65 ms of work per request and still tops out at **52 req/s
on 4 cores** (see [AUDIT.md](AUDIT.md) §2.3). Tiers 1–3 make each request cheaper
to deliver and reduce how many are needed — they do not raise that ceiling. When
concurrent traffic genuinely approaches it, the lever is more cores, and it
scales linearly.
