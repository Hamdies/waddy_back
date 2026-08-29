# Waddi backend — security, capacity, de-vendoring and delivery

Work carried out 28–29 August 2026 against `waddyapp.com`
(Laravel 10 · PHP 8.2 · MySQL 8 · nginx 1.18 · Redis · Contabo, 4 cores / 7.8 GB).

**33 commits · 326 files · 91,489 lines removed · 3,807 added.** Everything below
is deployed and verified in production unless explicitly marked otherwise.

| | Before | After |
|---|---|---|
| Unauthenticated data-exposure paths | 4 | **0** |
| Dependency advisories | 64 (3 critical, 19 high) | **6** (0 critical, 2 high) |
| Peak throughput | 35.6 req/s | **52.2 req/s** |
| Median latency at equal load | 271 ms | **197 ms** |
| API home-screen bytes | ~56 KB | **~10 KB** |
| Admin stylesheet | 299 KB every load | **55 KB once**, cached 30 days |
| Vendor scaffolding | — | **~90,000 lines removed** |

---

# 1. Security

## 1.1 Any order readable by anyone — critical

`OrderController::get_order_details` scoped to the owner **only when a user was
authenticated**:

```php
->when(isset($request->user), fn($q) => $q->where('is_guest', 0))
->when($request->user,       fn($q) => $q->where('user_id', $user_id))
->findOrFail($request->order_id);
```

For a guest both closures were skipped and the lookup ran unscoped. The gate in
front of it, `APIGuestMiddleware`, accepted the mere *presence* of a `guest_id`
parameter without validating it — so no account was needed. Any order id returned
the customer's delivery address, phone number, items and payment state, and ids
are sequential.

**Fixed** via `Order::scopedToRequester()`, now shared by every order endpoint.
Verified in production: a freshly created guest requesting another party's order
receives `404` where it previously received `200` and full PII.

## 1.2 Any order's payment information rewritable — critical

`update_offline_payment_info` and `offline_payment` resolved the order by id with
**no ownership check at all**, both inside the guest-reachable route group. An
anonymous caller could overwrite a stranger's payment details, reset the order to
`pending`, and trigger a notification to the real customer.

## 1.3 Guest identity crossed into registered accounts — high

Guests and registered users share `orders.user_id`; `is_guest` is the only thing
separating the two id spaces. Three list endpoints omitted that flag on the guest
branch, so passing a registered user's id as `guest_id` returned their real order
history.

## 1.4 Guest credential was guessable by construction — high

`guest_id` was the auto-increment primary key of the `guests` table, handed to
clients as their entire identity. Guests now receive a 64-character random token;
`APIGuestMiddleware` treats it as authoritative and overwrites any client-supplied
`guest_id` with the one the token resolves to.

Legacy id-only clients still work. Set `GUEST_REQUIRE_TOKEN=true` to reject them
once every shipped app sends a token.

## 1.5 Ratings could be written against any order — medium

Both review endpoints confirmed the order *existed* but never that it belonged to
the reviewer, was delivered, or contained the item. A copy-paste bug also meant an
invalid `item_id` passed validation because the check re-tested `$order`.

## 1.6 SSRF in the image proxy — high

`/image-proxy` took a URL from any unauthenticated caller and passed it to an HTTP
client, returning the body — a request-forgery primitive reaching cloud metadata,
localhost services, or anything on the private network.

Kept working but constrained: http/https only; every resolved IP checked against
private and reserved ranges; **redirects not followed** (an allowed host
redirecting to an internal one would otherwise bypass the IP check); `image/*`
responses only; 8 MB / 8 s caps; `throttle:60,1`.

Verified in production — `169.254.169.254` → `422`, a real image → `200`.

## 1.7 Unbound SQL interpolation — medium

`Zone::scopeContains` interpolated caller input into a `whereRaw`. Dead code —
every caller uses the spatial package's `whereContains` — and the SQL was invalid
anyway. Removed.

## 1.8 Dependency advisories — 64 → 6

Scoped upgrade, all within-major: `laravel/framework` 10.48.27 → 10.50.3,
`phpoffice/phpspreadsheet` 1.29.9 → **1.30.6** (8 crit/high, reachable from the
three spreadsheet-import endpoints), `league/commonmark` 2.6.1 → 2.10.0,
`guzzle` 7.9.2 → 7.15.5, `phpseclib` 3.0.43 → 3.0.57.

The six remaining cannot be fixed here: three `laravel/framework` advisories need
12.60+, and `phpunit`/`psysh` are dev-only so `--no-dev` keeps them off production.

> `composer audit` audits the **installed** `vendor/`, not the lock. Use
> `composer audit --locked` to check a pending upgrade, or it reports the old
> numbers and the fix looks like it did nothing.

## 1.9 Other findings

- **`php artisan database:refresh`** ran `db:wipe` then restored the vendor's demo
  database over live data. Registered and runnable on production. Removed.
- **Cache invalidation had never worked.** `BusinessSettingObserver` swept the
  `cache` DB table, but production runs `CACHE_DRIVER=file` — so admin settings
  edits stayed cached indefinitely until someone ran `cache:clear`.
- **`TrustProxies` extended a class that does not exist.** It inherited from
  `Fideloper\Proxy\TrustProxies`, dropped from Laravel in v9 and not installed —
  enabling it (as the Cloudflare work required) would have fatalled every request.
- **Vendor Apple credentials** hardcoded in a dead `apple_client_secret()` — team
  `7WSYLQ8Y87`, bundle `com.sixamtech.sixamMartApp`.
- **Debug routes live in production** — `/test` running `dd('Hello tester')`.

---

# 2. Capacity

## 2.1 The ceiling was a config default

Baseline: **35.6 req/s**, zero errors, sub-second worst case. The server was not
straining; it would not go faster. PHP-FPM was running Ubuntu's untouched
`pm.max_children = 5` — five concurrent PHP processes on a machine with four idle
cores and six free gigabytes.

```
5 workers ÷ 140 ms per request = 35.7 req/s      ← theoretical
                                 35.6 req/s      ← measured
```

## 2.2 What changed

| Change | Effect |
|---|---|
| `pm.max_children` 5 → 30 | The real win. 42 MB/worker measured, ~1.3 GB of 6 GB free |
| OPcache 10,000 → 30,000 files, 128 → 256 MB | Codebase is 19,208 PHP files — it could not cache half the application |
| `ConfigServiceProvider` query caching | **14 uncached queries per request** before any controller ran |
| Push notifications → queue | Two blocking round trips to Google inside the request, × 109 call sites |
| FCM OAuth token cached 55 min | A fresh token was minted for **every individual message** |
| Store/item formatter N+1s | 3 queries per store × 3 sections; a `where id = ?` per category per item |
| Redis for cache, session, queue | See §2.4 |

## 2.3 The wall is now four CPU cores

Laptop measurements were unusable — between two runs the 1-VU smoke moved
154 ms → 190 ms with no server-side change, larger than the effect being measured.
k6 is closed-loop, so RTT directly caps throughput.

Re-run **on the server against localhost**:

```
1 VU:   190 ms (laptop)  →  65 ms (localhost)      ~125 ms was network

60 VU:  47.0 req/s (laptop)  →  52.2 req/s (localhost)
        iteration time 12.77 s → 11.50 s  = 1.26 s saved
        network removed:  10 requests × 125 ms = 1.25 s
```

The saving matches the network removal almost exactly. **Server-side time did not
change.** Every millisecond removed from the network was one the server was going
to spend anyway.

```
unloaded response         65 ms
service time at 60 VU    575 ms      ← 8.8× degradation, pure contention

CPU ceiling: 4 cores ÷ 65 ms = 61.5 req/s
measured:                       52.2 req/s   (85% of theoretical)
```

| Lever | Effect |
|---|---|
| More PHP workers | **None** — 30 already exceeds what 4 cores can run |
| Redis, OPcache, query caching | Already banked — they are why it is 65 ms |
| More CPU cores | **Linear** — 8 cores ≈ 100 req/s |
| Less work per request | The only software lever left; needs profiling |

At ~9 API calls per home screen, 52 req/s ≈ **6 app opens per second**.

## 2.4 Redis — kept, but not for the reason predicted

Redis produced **no measurable throughput gain**, and the rationale — relieving
file-lock contention — was wrong. The dataset is 5.5 MB, so cache files were
served from the OS page cache, which is already RAM; Redis replaces a page-cache
read with a TCP round trip.

Kept for architectural reasons: the queue no longer polls MySQL every 3 seconds,
file sessions cannot work across multiple app servers, and sessions/cache stop
accumulating on disk. Configured `maxmemory 512mb`, `maxmemory-policy allkeys-lru`
(the default `noeviction` makes Redis *refuse writes* when full).

> Cache lives in **Redis db 1**, sessions in **db 0**. `redis-cli` defaults to
> db 0, so `redis-cli DBSIZE` shows neither cache keys nor the full picture — use
> `redis-cli -n 1`. Queue keys are prefixed: `waddy_database_queues:default`.

---

# 3. Delivery — bytes and round trips

Measured from Egypt, which is where the customers are:

```
DNS               2 ms
TCP connect      71 ms
TLS handshake   +88 ms   ← full handshake every connection
server work      65 ms
─────────────────────────
one API request 417 ms
```

**The server was never the problem.** 65 ms per request is competitive. The gap
was transport, round-trip count and payload size.

## 3.1 Tier 1 — transport ✅

| | Before | After |
|---|---|---|
| Protocol | HTTP/1.1 | **HTTP/2** |
| `stores/get-stores/all` | 10,983 B | **1,900 B** (83%) |
| `module` | 10,630 B | **1,612 B** (85%) |
| `config` | 8,939 B | **2,387 B** (74%) |
| Admin stylesheet | 299 KB every load | **55 KB once**, cached 30 d |
| Uploaded images | revalidated every screen | **immutable, 1 year** |

Server capacity **unaffected** — 52.2 req/s and 7,370 requests both before and
after, identical. At these payload sizes compression costs microseconds against
65 ms of PHP.

Two non-obvious failures on the way:

- Stock `nginx.conf` sets `gzip on;` (a duplicate is fatal) while leaving
  `gzip_types` commented out, defaulting to `text/html` — **compression appeared
  enabled while every JSON response went out uncompressed**.
- The asset regex hijacked the `/public/` alias and 404'd every admin stylesheet,
  because nginx evaluates regex locations **before** falling back to a prefix
  match. Fixed with `^~`. `nginx -t` passes on this; only requesting a real asset
  catches it.

Implemented by `deploy/nginx/waddy-performance.conf` (http-context drop-in) and
`deploy/nginx/apply-vhost-tuning.sh` (server-context, idempotent, self-reverting).

## 3.2 Tier 2 — Cloudflare ✅ (with corrections)

Migrated `waddyapp.com` to Cloudflare, free plan. The nameserver move was made a
**no-op** first — every record grey-clouded so both nameserver sets returned the
same IP — then the proxy enabled separately. That decoupling matters: nameserver
changes are gated on registry TTLs of up to two days, while the proxy toggle is
reversible in seconds.

**Two predictions were wrong:**

- **No Cairo PoP.** Egypt is served from **Marseille** (`colo=MRS`). Measured
  direct vs proxied, API latency is roughly neutral — 0.065 s vs 0.051 s connect,
  0.309 s vs 0.282 s TTFB. The predicted "71 ms → 15 ms" did not happen.
- **Polish is a paid feature.** Edge WebP conversion is not on the free plan, so
  the 57 KB → ~10 KB image saving must come from the origin instead.

**What it does deliver, measured:** an image served from edge cache took
**0.176 s against 0.351 s** direct — half the time, and never touching the origin.
Plus HTTP/3, 0-RTT, TLS 1.3, edge HTTPS redirect, DDoS protection, and a hidden
origin IP.

`deploy/nginx/cloudflare-realip.sh` generates the real_ip config from Cloudflare's
published ranges. **Without it every request arrives from a Cloudflare address and
the 120/min per-IP limiter locks out every user at once.** Verified un-spoofable:
sending a forged `CF-Connecting-IP` from an untrusted source did not reset the
rate-limit counter.

Solved in nginx rather than Laravel's `TrustProxies` deliberately — nginx corrects
`REMOTE_ADDR` before PHP runs, so the limiter, `$request->ip()` and audit logging
are all correct with no application change.

## 3.3 Tier 3 — origin-side image variants ✅

Cloudflare Polish being a paid feature (§3.2) left the 57 KB image problem to the
origin. It is now solved there, which is the more durable place anyway.

`Helpers::upload()` — the one function every upload in the codebase funnels
through, along with its duplicate in `FileManagerTrait` — now queues a job that
writes resized WebP copies beside the original:

```
store/2024-11-19-abc.png                    ← original, untouched
store/variants/thumb/2024-11-19-abc.webp    ← 150 px
store/variants/card/2024-11-19-abc.webp     ← 400 px
                    …and a .jpg of each, for clients without WebP
```

Backfilling the existing images: **1,464 KB → 58 KB, 96% smaller.** The worst
single case was a 596 KB promotional banner at 11.7 KB. **The conversion, not the
resize, is most of that** — a 500 px PNG logo re-encoded to WebP at unchanged
dimensions is already ~70% smaller.

Two decisions are worth knowing about:

- **The API change is opt-in.** `logo_full_url` and its siblings are untouched;
  a `logo_variants` object appears only for a client sending
  `X-Image-Variants: 1`. Appending it unconditionally would have added ~150
  bytes per card to every response including the app builds in the field that
  cannot use it — 7 KB of pure overhead on a fifty-store home screen, which is
  the opposite of what this tier is for. There is a test asserting an
  opted-out payload is byte-identical.
- **Nothing is recorded in the database.** Variant paths are a pure function of
  the original path, so there is no migration, no per-model column, and no
  manifest that can drift from what is actually on disk. The cost is one
  existence probe per image on read, cached for a day — load-bearing on S3,
  where that probe is an HTTP HEAD.

> The variant URLs inherit `/storage/`'s `Cache-Control: immutable, max-age=1y`
> from Tier 1. Regenerating one in place (`--force`, after changing quality)
> therefore changes bytes at a URL caches have been told never to revalidate.
> Change encoding settings only alongside a re-upload, or purge the edge.

Backfill:

```bash
php artisan images:backfill-variants --dry-run   # what would be processed
php artisan images:backfill-variants             # queue it; idempotent, resumable
journalctl -u waddy-queue -f
```

Full detail, measurements and caveats in [TIER3_PLAN.md](TIER3_PLAN.md) §3.

---

# 4. Removing the 6amMart scaffolding

Smaller than expected: the licence machinery was already inert
(`ActivationClass::is_local()` and `checkActivationCache()` hardcoded to `true`,
both middlewares pass-through), no phone-home URLs, no licence tables.

| Phase | Removed |
|---|---|
| **1a** | Web installer and updater, their step views, `InstallationMiddleware`, packaging commands, `database:refresh`, and 4.6 MB of demo SQL dumps. None of the route files were ever registered, so they had always been unreachable. |
| **1b** | `ActivationClass`, `ActivationCheckMiddleware` + 9 `actch:` route usages, `AddonActivationController`, `System\AddonController`, their views and sidebar entries, `Helpers::requestSender`, `laravelpkg/laravelchk` |
| **1c** | 209 untracked files: `.qoder/` (134 — generated docs), `.idea/` (14), `app/tmp/` (60 mpdf scratch), a committed 2.3 MB `composer.phar`, `.rnd` |
| **2** | 12 payment gateway controllers (~2,100 lines), routes, views, config files, `app/Library/SslCommerz`, 111 lines of dead boot-path setup, and 10 composer packages |

**The trap avoided in 1b:** `AddonService` is a mixed class.
`getAddData`/`getImportData`/`getBulkExportData` are *product* addons — the "extra
cheese" kind — and share only a name with the licence system. Deleting it wholesale
would have broken product management.

**The important part of Phase 2** was not deleting controllers but **narrowing the
advertised gateway lists**. `getPaymentMethods`, `getDefaultPaymentMethods` and
`Helpers::getActivePaymentGateways` each selected from a hardcoded list of all
thirteen gateways, so any row left active in `addon_settings` would have had the
app offer a payment method whose controller no longer existed. Those lists are now
`['paymob_accept']`.

Verified in production: `active_payment_method_list` returns exactly one gateway.

**Known remnant:** `BusinessSettingsController` still carries config forms for the
removed gateways — 45 references in a 7,684-line file. Unreachable from the
customer app, but they still render in admin.

---

# 5. Operational changes

## 5.1 Queue worker

`deploy/waddy-queue.service`, systemd. Takes **no connection argument** so it
follows `QUEUE_CONNECTION` from `.env` — pinning a driver meant that switching to
Redis left the worker polling an empty MySQL table while jobs accumulated in
Redis, with no error anywhere. Push notifications would have silently stopped.

> Add `systemctl restart waddy-queue` to your deploy routine. Workers hold code in
> memory and keep running the old version until recycled.

## 5.2 Language files no longer written at runtime

`translate()` appended missing keys to `resources/lang/<locale>/messages.php`
during the request, so production continuously edited a tracked file — which
blocked `git pull` on every deploy.

It was also a hazard: the write rewrites the whole file via `var_export` (589 KB,
8,241 keys) on any request hitting a missing key, and two concurrent requests can
interleave and truncate it. That file is `include()`d on every request, so a
truncated write is a **site-wide fatal**. Raising `pm.max_children` from 5 to 30
made the collision roughly six times more likely.

Nothing a visitor sees changed — the missing-key branch already returned the
humanised key. Gated behind `TRANSLATION_AUTOWRITE`, default off.

## 5.3 Composer platform pin

`config.platform.php` pinned to `8.2.30`. Resolving a lock on a machine running
PHP 8.4 selected packages requiring 8.3/8.4, which `composer install` then refused
on the server.

## 5.4 Deploy runbook

```bash
cd /var/www/waddy && git pull origin main
composer install --no-dev -o          # install, never update
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:clear
sudo systemctl restart php8.2-fpm waddy-queue
```

Production now tracks `main`. It previously ran from a local `order-track-enhance`
branch reported as 92 commits ahead — which contained nothing that was not already
on `origin/main`, so the branch name was simply lying about what was deployed.

---

# 6. Load testing

Scripts in `loadtest/`. Every endpoint is read-only.

**Measure from the server against localhost**, not a laptop — network RTT exceeded
the effects being measured:

```bash
cp -r /var/www/waddy/loadtest ~/loadtest && cd ~/loadtest
k6 run -e BASE_URL=https://127.0.0.1 -e HOST_HEADER=waddyapp.com \
  -e MODULE_ID=1 -e ZONE_ID=1 \
  -e PEAK_VUS=60 -e MAX_P95=15000 -e MAX_FAIL=0.30 -e RAMP=20s -e HOLD=60s load.js
```

`HOST_HEADER` is required when addressing by IP — nginx matches on `server_name`,
and without it the request lands on the default vhost and is redirected back out
over the internet. The snap build of k6 is confined and cannot read `/var/www`.

**The rate limiter blocks load testing.** `throttle:api` allows 120 req/min per IP
(~2 req/s), so any single-source test measures the limiter.
`config/loadtest.php` provides an env-gated allowlist, empty by default:

```bash
echo 'LOADTEST_EXEMPT_IPS=<ip>,127.0.0.1' >> .env && php artisan config:cache
# ... run ...
sed -i '/LOADTEST_EXEMPT_IPS/d' .env && php artisan config:cache
```

> While an IP sits in that list it has **no brute-force or scraping protection**.
> Loopback is not implicitly trusted — local runs need `127.0.0.1` in the list.

---

# 7. Outstanding

**Engineering, by value:**

| | |
|---|---|
| Aggregate home endpoint | 9 calls → 1, ~1,312 ms → ~289 ms. `TIER3_PLAN.md` §2 |
| App: request the variant URLs | The pipeline shipped (§3.3); the bytes come off the wire when the client sends `X-Image-Variants: 1` and reads `logo_variants.webp.card` |
| `Store::retrieved` runs a subscription sweep per hydrated row | `app/Models/Store.php:724`. Cheap on the happy path — a cached settings read that short-circuits — but it is a listener doing date arithmetic on every store in every list. Worth measuring before the aggregate endpoint multiplies the row count |
| Client skeleton screens / cached-first render | Where most of the *perceived* gap with Talabat lives |
| More CPU cores | The only remaining throughput lever without profiling |
| `BusinessSettingsController` | 45 dead gateway references in a 7,684-line file |
| Phase 3 module types | Pending `SELECT DISTINCT module_type FROM modules` |
| Laravel 12 | Clears the last CRLF advisory; a project, not a task |
| `OrderController.php:339` | `$payment->save()` outside its null guard |
| SPF + DMARC | No MX records, so anyone can spoof `@waddyapp.com` |
| Test coverage | ~800 lines of tests against ~120k lines of application code |

**Cloudflare, if not already done:** SSL/TLS mode → `Full (Strict)` (was `Full`,
which accepts any certificate including a forged one), and the `ftp` DNS record →
proxy off.

---

# 8. What the measurements changed

Three predictions in this session were wrong, and measuring is what caught them:

1. **"The server is slow."** It wasn't. 65 ms per request is competitive; the
   constraint was a five-worker PHP-FPM default.
2. **"Redis will help throughput."** It didn't. The dataset is small enough that
   file cache was already served from RAM.
3. **"Cloudflare will cut RTT from 71 ms to 15 ms."** It didn't — Egypt is served
   from Marseille. The real benefit is edge caching and origin offload.

The instinct to optimise the application was wrong each time. The wins came from a
config default, a byte count, and a worker limit.
