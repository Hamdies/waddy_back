# Waddi backend — security, capacity and de-vendoring

Work carried out 28 August 2026 against `waddyapp.com` (Laravel 10, PHP 8.2,
MySQL 8, nginx, Contabo). Everything below is deployed and verified in
production unless explicitly marked otherwise.

| | Before | After |
|---|---|---|
| Unauthenticated data-exposure paths | 4 | 0 |
| Dependency advisories | 64 (3 critical, 19 high) | 6 (0 critical, 2 high) |
| Peak throughput | 35.6 req/s | 52.2 req/s |
| Median latency at equal load | 271 ms | 197 ms |
| Vendor scaffolding | — | ~90,000 lines removed |

---

## 1. Security

### 1.1 Any order readable by anyone — critical

`OrderController::get_order_details` applied owner scoping only when the request
carried an authenticated user:

```php
->when(isset($request->user), fn($q) => $q->where('is_guest', 0))
->when($request->user,       fn($q) => $q->where('user_id', $user_id))
->findOrFail($request->order_id);
```

For a guest both closures were skipped and the lookup ran unscoped. The gate in
front of it, `APIGuestMiddleware`, accepted the mere *presence* of a `guest_id`
parameter without validating it, so no account was needed at all. Any order id
returned the customer's delivery address, phone number, items and payment state.
Order ids are sequential, so the whole table was enumerable.

**Fixed** by `Order::scopedToRequester()`, a single scope both list and detail
endpoints now share. Verified in production: a freshly created guest requesting
another party's order receives `404`.

### 1.2 Any order's payment information rewritable — critical

`update_offline_payment_info` and `offline_payment` both resolved the order by id
with no ownership check, and both sat inside the guest-reachable route group.
An anonymous caller could overwrite a stranger's offline payment details, reset
the order to `pending`, and trigger a notification to the real customer.

**Fixed** — both now scope through `scopedToRequester()`.

### 1.3 Guest identity crossed into registered accounts — high

Guests and registered users share `orders.user_id`; the `is_guest` flag is the
only thing separating the two id spaces. `get_order_list`, `get_running_orders`
and `update_payment_method` omitted that flag on the guest branch, so passing a
registered user's id as `guest_id` returned their real order history.

**Fixed** — the shared scope always applies `is_guest`.

### 1.4 Guest credential was guessable by construction — high

`guest_id` was the auto-increment primary key of the `guests` table, handed to
clients as their entire identity.

**Fixed** — guests now receive a 64-character random token, and
`APIGuestMiddleware` treats it as authoritative, overwriting any client-supplied
`guest_id` with the one the token resolves to. The legacy id-only path still
works for already-installed apps; set `GUEST_REQUIRE_TOKEN=true` to reject it
once every shipped client sends a token.

### 1.5 Ratings could be written against any order — medium

Both review endpoints confirmed the referenced order existed but never that it
belonged to the reviewer, was delivered, or contained the item being rated. A
copy-paste bug also meant an invalid `item_id` passed validation, because the
check re-tested the order variable.

**Fixed** in `ItemController@submit_product_review` and
`DeliveryManReviewController@submit_review`.

### 1.6 SSRF in the image proxy — high

`/image-proxy` took a URL from any unauthenticated caller and passed it straight
to an HTTP client, returning the body. That is a server-side request forgery
primitive: cloud metadata at `169.254.169.254`, services bound to localhost, or
anything reachable inside the private network.

**Fixed** rather than removed, since the mobile client may depend on it. Only
`http`/`https`; every resolved IP checked against private and reserved ranges;
**redirects not followed** (an allowed host redirecting to an internal one would
otherwise bypass the IP check); only `image/*` responses returned; 8 MB and 8 s
caps; `throttle:60,1`.

Verified in production — `169.254.169.254` returns `422`, a real image `200`.

`config/imageproxy.php` adds an optional `IMAGE_PROXY_ALLOWED_HOSTS` allowlist,
which is stronger than IP filtering and also closes the DNS-rebinding window the
IP approach leaves open. Empty by default.

### 1.7 Unbound SQL interpolation — medium

`Zone::scopeContains` interpolated caller input into a `whereRaw`. It was dead
code — every caller uses the spatial package's `whereContains` — and the SQL it
produced was invalid anyway. **Removed.**

### 1.8 Dependency advisories

Scoped upgrade of the vulnerable packages: **64 → 6 advisories, 3 critical → 0**.
All moves within-major: `laravel/framework` 10.48.27 → 10.50.3,
`phpoffice/phpspreadsheet` 1.29.9 → 1.30.6 (8 crit/high, reachable from the three
spreadsheet-import endpoints), `league/commonmark` 2.6.1 → 2.10.0,
`guzzle` 7.9.2 → 7.15.5, `phpseclib` 3.0.43 → 3.0.57.

The six that remain cannot be fixed here: three `laravel/framework` advisories
require 12.60+, and `phpunit`/`psysh` are dev-only so `--no-dev` keeps them off
production. The only real remaining exposure is a CRLF injection in Laravel's
default `email` validation rule, which needs a framework major upgrade.

> **Note:** `composer audit` audits the *installed* `vendor/`, not the lock file.
> Use `composer audit --locked` to check a pending upgrade, or it will report the
> old numbers and look like the fix did nothing.

### 1.9 Other findings

- **`php artisan database:refresh`** ran `db:wipe` and restored the vendor's
  bundled demo database over live data. Registered and runnable on production;
  nothing scheduled it, but one typo would have destroyed the database. Removed.
- **Cache invalidation had never worked.** `BusinessSettingObserver` invalidated
  by sweeping the `cache` DB table, but production runs `CACHE_DRIVER=file`, so
  it found nothing. Admin settings edits stayed cached indefinitely until someone
  ran `cache:clear`. Fixed.
- **Runtime writes to tracked language files.** See §3.3.
- **Vendor Apple credentials** hardcoded in a dead `Helpers::apple_client_secret()`
  — team `7WSYLQ8Y87`, bundle `com.sixamtech.sixamMartApp`. Removed. Real Apple
  sign-in reads from `business_settings` and is untouched.
- **Debug routes in production** — `/test` running `dd('Hello tester')`, and an
  empty `module-test`. Removed.

### Still open

- `OrderController.php:339` — `$payment->save()` sits outside its `if ($payment)`
  guard and will fatal on an order with no unpaid payment row.
- Laravel 12 upgrade, for the last CRLF advisory.

---

## 2. Capacity

### 2.1 The original ceiling

The baseline came back at **35.6 req/s** with zero errors and sub-second
worst-case latency. The server was not straining; it simply would not go faster.

PHP-FPM was running Ubuntu's untouched default of `pm.max_children = 5` — five
concurrent PHP processes on a machine with four idle cores and six gigabytes of
free memory.

```
5 workers ÷ 140 ms per request = 35.7 req/s      ← theoretical
                                 35.6 req/s      ← measured
```

### 2.2 What changed

| Change | Effect |
|---|---|
| `pm.max_children` 5 → 30 | The real win. 42 MB/worker measured, so ~1.3 GB of 6 GB free. |
| OPcache 10,000 → 30,000 files, 128 → 256 MB | Codebase is 19,208 PHP files; it could not cache half the application. |
| `ConfigServiceProvider` query caching | Was issuing **14 uncached queries per request** before any controller ran. |
| Push notifications → queue | Two blocking round trips to Google inside the user's request, ×109 call sites. |
| FCM OAuth token cached 55 min | A fresh token was minted for *every individual message*. |
| Redis for cache, session, queue | See §2.4. |

The per-endpoint result showed a near-identical saving on every endpoint
regardless of what it does — 70–97 ms each. That flatness is the signature of
fixed per-request overhead disappearing, not any individual query getting faster.

### 2.3 The final measurement — CPU-bound

Measuring from a laptop put the internet in the measurement: between two runs the
1-VU smoke moved 154 ms → 190 ms with no server-side change, larger than the
effect being measured. k6 is closed-loop, so RTT directly caps throughput.

Re-run **on the server against localhost**:

```
1 VU:   p50 190 ms (from laptop)  →  65 ms (localhost)   ~125 ms was network

60 VU:  47.0 req/s (laptop)  →  52.2 req/s (localhost)
        iteration time 12.77s → 11.50s   = 1.26s saved
        network removed:  10 requests × 125 ms = 1.25s
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

**The constraint is four CPU cores.** Not configuration.

| Lever | Effect |
|---|---|
| More PHP workers | None — 30 already exceeds what 4 cores can run |
| Redis, OPcache, query caching | Already banked — they are why it is 65 ms |
| More CPU cores | Linear — 8 cores ≈ 100 req/s |
| Less work per request | The only software lever left; needs profiling |

At ~9 API calls per home-screen load, 52 req/s is roughly **6 app opens per
second** (~350/minute).

### 2.4 On Redis

Redis did **not** produce a measurable throughput gain, and the rationale for it
— relieving file-lock contention — was wrong. The dataset is 5.5 MB, so cache
files were served from the OS page cache, which is already RAM; Redis replaces a
page-cache read with a TCP round trip.

It was kept for architectural reasons, not performance ones:

- the queue no longer polls MySQL every 3 seconds
- file sessions **cannot** work across multiple app servers, so Redis is a
  prerequisite for ever scaling horizontally
- sessions and cache stop accumulating on disk

Configured with `maxmemory 512mb` and `maxmemory-policy allkeys-lru`. The default
`noeviction` makes Redis *refuse writes* when full rather than evict, which
surfaces as sporadic failures under load.

Note that cache lives in **Redis db 1** and sessions in **db 0**. `redis-cli`
defaults to db 0, so `redis-cli DBSIZE` will not show cache keys —
use `redis-cli -n 1`. Queue keys are prefixed: `waddy_database_queues:default`.

---

## 3. Removing the 6amMart scaffolding

Much smaller than expected: the licence machinery was already inert
(`ActivationClass::is_local()` and `checkActivationCache()` hardcoded to return
`true`, both middlewares pass-through no-ops), there were no phone-home URLs, and
no licence tables.

### Phase 1a — installer and updater

`InstallController`, `UpdateController`, `routes/install.php`, `routes/update.php`
and the step views (none of which were ever registered by `RouteServiceProvider`,
so they had always been unreachable), `InstallationMiddleware`, the vendor
packaging commands, `database:refresh`, and the 4.6 MB `installation/` directory
of demo SQL dumps.

### Phase 1b — licence activation

`ActivationClass`, `ActivationCheckMiddleware` and all 9 `actch:` route usages,
`AddonActivationController`, `Admin\System\AddonController`, their views and
sidebar entries, the activation-check endpoints, `Helpers::requestSender`, and the
`laravelpkg/laravelchk` package.

> **Kept deliberately:** `AddonService::getAddData/getImportData/getBulkExportData`
> and `Admin\Item\AddonController`. Those are *product* addons — the "extra
> cheese" kind — and share only a name with the licence system. Deleting the
> class wholesale would have broken product management.

### Phase 1c — repository hygiene

209 files untracked: `.qoder/` (134 — generated repo documentation, kept on disk),
`.idea/` (14), `.vscode/` (1), `app/tmp/` (60 mpdf scratch files), a committed
2.3 MB `composer.phar`, and a stray `.rnd`.

### Phase 2 — payment gateways

Waddi operates in Egypt only and takes payment through **Paymob**. Removed 12
controllers (~2,100 lines) plus their routes, views, config files, the
`app/Library/SslCommerz` support library, 111 lines of dead gateway setup running
on every request boot, and 10 composer packages.

The important part was **narrowing the advertised gateway lists**.
`getPaymentMethods`, `getDefaultPaymentMethods` and
`Helpers::getActivePaymentGateways` each selected from a hardcoded list of all
thirteen gateways, so any row left active in `addon_settings` would have had the
customer app offer a payment method whose controller no longer exists. Those
lists are now `['paymob_accept']`.

Route registration also no longer depends on a `Modules/Gateways` addon that does
not exist — the old condition included a file that never resolved.

Verified in production: `active_payment_method_list` returns exactly one gateway.

### Known remnant

`BusinessSettingsController` still carries configuration forms for the removed
gateways — 45 references in a 7,684-line file. Unreachable from the customer app,
but they still render in admin. Left for a focused pass.

### Phase 3 — not started

Unused module types (`pharmacy`, `ecommerce`, `parcel`, `rental`) are candidates,
pending `SELECT DISTINCT module_type FROM modules` to confirm what is actually in
use.

---

## 4. Operational changes

### 4.1 Queue worker

`deploy/waddy-queue.service`, running as a systemd unit. It takes **no connection
argument**, so it follows `QUEUE_CONNECTION` from `.env` — pinning a driver in the
unit meant that switching to Redis left the worker polling an empty MySQL table
while jobs accumulated, with no error anywhere.

> Add `systemctl restart waddy-queue` to your deploy routine. Workers hold code in
> memory and keep running the old version until recycled.

### 4.2 Language files no longer written at runtime

`translate()` appended missing keys to `resources/lang/<locale>/messages.php`
during the request, so production continuously edited a tracked file — which
blocked `git pull` on every deploy.

It was also a hazard. The write rewrites the entire file via `var_export` — 589 KB
across 8,241 keys — on any request hitting a missing key, and two concurrent
requests can interleave and truncate it. That file is `include()`d on every
request, so a truncated write is a **site-wide fatal**. Raising `pm.max_children`
from 5 to 30 made the collision roughly six times more likely.

Nothing a visitor sees changed: the missing-key branch already returned the
humanised key, and the write only persisted it.

Gated behind `TRANSLATION_AUTOWRITE`, default off. Developers set it locally to
collect keys, then commit the language file. Deliberate admin edits through
`LanguageController` are untouched.

### 4.3 Composer platform pin

`config.platform.php` is pinned to `8.2.30`. Resolving a lock on a machine running
PHP 8.4 selected packages requiring 8.3/8.4, which `composer install` then refused
on the server. The pin makes resolution target the production runtime regardless
of which machine does it.

### 4.4 Deploy runbook

```bash
cd /var/www/waddy && git pull origin main
composer install --no-dev -o          # install, never update
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:clear
sudo systemctl restart php8.2-fpm waddy-queue
```

Production now tracks `main`. It previously ran from a local `order-track-enhance`
branch reported as 92 commits ahead — which turned out to contain nothing that was
not already on `origin/main` (`git log origin/main..HEAD` was empty), so the branch
name was simply lying about what was deployed.

---

## 5. Load testing

Scripts live in `loadtest/`. Every endpoint is read-only — no orders, reviews,
SMS, pushes or payment calls.

```bash
# from the repo root, against production
k6 run --quiet -e BASE_URL=https://waddyapp.com -e MODULE_ID=1 -e ZONE_ID=1 \
  loadtest/smoke.js

k6 run -e BASE_URL=https://waddyapp.com -e MODULE_ID=1 -e ZONE_ID=1 \
  -e PEAK_VUS=60 -e MAX_P95=15000 -e MAX_FAIL=0.30 -e RAMP=20s -e HOLD=60s \
  loadtest/load.js
```

Run the smoke test first; it must report zero failures, otherwise the load test
is benchmarking error pages.

**For an accurate reading, run it on the server against localhost.** Network RTT
from a laptop exceeded the effects being measured:

```bash
cp -r /var/www/waddy/loadtest ~/loadtest && cd ~/loadtest
k6 run -e BASE_URL=https://127.0.0.1 -e HOST_HEADER=waddyapp.com \
  -e MODULE_ID=1 -e ZONE_ID=1 \
  -e PEAK_VUS=60 -e MAX_P95=15000 -e MAX_FAIL=0.30 -e RAMP=20s -e HOLD=60s load.js
```

`HOST_HEADER` is required when addressing the box by IP — nginx matches on
`server_name`, and without it the request lands on the default vhost and gets
redirected back out over the internet. The snap build of k6 is confined and
cannot read `/var/www`, hence the copy into `$HOME`.

### The rate limiter blocks load testing

`throttle:api` allows 120 req/min per IP — about 2 req/s — so any single-source
test measures the limiter, not the server. `config/loadtest.php` provides an
env-gated IP allowlist, **empty by default**:

```bash
echo 'LOADTEST_EXEMPT_IPS=<your-ip>,127.0.0.1' >> .env && php artisan config:cache
# ... run the test ...
sed -i '/LOADTEST_EXEMPT_IPS/d' .env && php artisan config:cache
```

> While an IP sits in that list it has **no brute-force or scraping protection**
> on the API. Remove it as soon as the run finishes.

Loopback is not implicitly trusted — a local run needs `127.0.0.1` in the list too.

---

## 6. Outstanding

**Needs a human, after the dependency upgrade:**

1. Run a **spreadsheet import** — `phpspreadsheet` moved a minor version and is
   the likeliest behaviour change.
2. Place a **real Paymob order** — config looking right is not money moving.
3. Click through **admin → Business Settings** — two sidebar entries were removed
   there, and Blade only fails at render time.

**Engineering, roughly by value:**

| | |
|---|---|
| More CPU cores | The only remaining throughput lever without profiling |
| `BusinessSettingsController` | 45 dead gateway references in a 7,684-line file |
| Phase 3 module types | Pending `SELECT DISTINCT module_type FROM modules` |
| Laravel 12 | Clears the last CRLF advisory; a project, not a task |
| `OrderController.php:339` | `$payment->save()` outside its null guard |
| Test coverage | ~800 lines of tests against ~120k lines of application code |
