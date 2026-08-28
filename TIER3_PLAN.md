# Tier 3 — collapsing the home screen

Detailed plan for the round-trip and payload work in
[PERFORMANCE_PLAN.md](PERFORMANCE_PLAN.md). This is the tier that actually closes
the gap with Talabat; Tiers 1 and 2 make each request cheaper to deliver, this
one removes most of the requests.

All figures measured against production from Egypt, 29 Aug 2026.

---

## 1. What the home screen costs today

| Endpoint | Payload | TTFB |
|---|---|---|
| `config` | 8,939 B | 264 ms |
| `module` | 10,630 B | 318 ms |
| `banners` | 241 B | 328 ms |
| `categories` | 1,638 B | 343 ms |
| `categories/popular` | **2 B** | — |
| `stores/get-stores/all` | 10,983 B | 454 ms |
| `stores/popular` | 10,824 B | 376 ms |
| `stores/latest` | 10,754 B | 345 ms |
| `stores/top-rated` | **54 B** | — |
| `items/popular` | 2,311 B | 378 ms |
| **Total** | **≈ 56 KB** | **≈ 1,312 ms** sequential |

Three findings fall out of this, and each is worth acting on independently of the
aggregate endpoint.

### 1.1 The store sections are the same data three times

```
stores/get-stores/all  ->  stores 3, 4
stores/popular         ->  stores 4, 3
stores/latest          ->  stores 4, 3
```

Identical stores, reordered, at ~10.8 KB each. **~32 KB is spent conveying ~11 KB
of stores plus two orderings.** With a catalogue of two stores this is invisible;
at fifty stores across five sections it is the single largest thing on the wire.

### 1.2 Two sections return nothing

`categories/popular` returns `2 B` (an empty array) and `stores/top-rated`
returns `54 B`. The app is paying a full round trip — 71 ms RTT plus a TLS
handshake if the connection is cold — for sections with no content.

Either populate them or stop calling them. In an aggregate response an empty
section costs a few bytes instead of a round trip.

### 1.3 The formatters run 2 queries per store

`Helpers::store_data_formatting()`, inside its `foreach`:

```php
$item->load('storeConfig');                                        // 1 query per store
$extra_packaging_data = BusinessSetting::where('key', 'extra_packaging_data')
    ->first()?->value ?? '';                                       // 1 query per store
```

The second is the worse of the two — the same key, returning the same value,
re-queried for every store in the list, while a cached accessor
(`Helpers::get_business_settings()`) already exists and is used elsewhere.

Three sections × 10 stores = **60 avoidable queries per home screen**.

> **Fix this first.** It is a contained change, it benefits v1 immediately, and
> it makes the aggregate endpoint cheap to build. Doing it after the aggregate
> means the aggregate inherits the problem.

---

## 2. The aggregate endpoint

### 2.1 Shape

```
GET /api/v2/m/{module}/z/{zone}/home?lat=&lng=
```

Module and zone live **in the path**, not in headers. That is deliberate: it
resolves the caching blocker in PERFORMANCE_PLAN §2.1 by construction. A CDN keys
on URL, so a URL that fully describes the response is cacheable; a header-varying
one is not.

`lat`/`lng` stay in the query string and are used only for distance sorting. If
they turn out to change the payload materially, they must either be rounded to a
coarse grid (≈1 km) for cache-key stability or the response marked private.

### 2.2 Normalised payload

The core design decision. Entities appear **once**; sections reference them by id:

```jsonc
{
  "stores": {
    "3": { "id": 3, "name": "...", "logo": "...", "avg_rating": 4.5,
           "delivery_time": "30-40", "distance": 1.2, "free_delivery": false },
    "4": { "id": 4, "name": "...", "...": "..." }
  },
  "items":      { "12": { "...": "..." } },
  "categories": [ { "id": 1, "name": "...", "image": "..." } ],
  "banners":    [ { "id": 1, "image": "...", "link": "..." } ],
  "sections": [
    { "key": "popular",   "title": "Popular",       "store_ids": [4, 3] },
    { "key": "latest",    "title": "New on Waddi",  "store_ids": [4, 3] },
    { "key": "top_rated", "title": "Top rated",     "store_ids": [] }
  ],
  "config": { "currency_symbol": "E£", "...": "..." },
  "meta":   { "generated_at": "...", "ttl": 60 }
}
```

Two savings compound here:

1. **Deduplication** — each store serialised once regardless of how many sections
   reference it.
2. **List projection** — a store card renders a name, image, rating, delivery
   time and distance. The current endpoints return the *full* store object at
   roughly 5 KB each. A card needs a few hundred bytes.

```
today                            ≈ 56 KB
deduplicated                     ≈ 35 KB
deduplicated + list projection   ≈ 12 KB
      "        "      + gzip     ≈  3 KB      (Tier 1 gives the gzip)
```

Keep the full object on the store *detail* endpoint, where it is actually
rendered.

### 2.3 Assemble from independently cached fragments

Do **not** build this as one large query. Each section caches separately:

```php
$stores = Cache::remember("home:stores:popular:{$module}:{$zone}", 60,
    fn () => $this->popularStores($module, $zone));
```

Three reasons this matters more than it looks:

- **Independent invalidation** — a banner change should not evict the store list.
- **Graceful degradation** — one slow or failing section returns empty rather
  than taking the whole screen down. Wrap each in its own try/catch.
- **Staggered expiry** — different TTLs per section prevent every fragment
  expiring together and stampeding the database.

Suggested TTLs: banners and categories 300 s, store sections 60 s, config 600 s.

The pattern already exists in the codebase — `BannerController` uses
`Cache::remember($cacheKey, now()->addMinutes(20), ...)`. Follow it.

### 2.4 ETag

Hash the assembled payload and return it as an `ETag`. A returning customer whose
home screen has not changed gets a `304` with no body — on Egyptian mobile data
that is the difference between 3 KB and nothing.

Laravel's `cache.headers:...;etag` middleware does this, but it hashes after the
full response is built; for real savings, compute the hash from the fragment
cache keys and their versions and short-circuit before assembly.

### 2.5 What not to do

- **Do not touch v1.** Old app versions stay in the field for months. v2 is
  purely additive; v1 is deleted only when the analytics say nobody calls it.
- **Do not return everything.** Cap each section at what the screen renders — 10
  items, not 50. Pagination stays on the section endpoints.
- **Do not put user-specific data in it.** Cart counts, addresses and unread
  notifications make the response uncacheable. Those stay on separate,
  private-cached calls; the home *content* is identical for everyone in a
  module/zone, which is exactly why it can be cached at an edge.

---

## 3. Images

Store logos are currently **300×300 PNG at 57 KB**, rendered on a card at roughly
120 px. That is ~50 KB wasted per card, on mobile data, times every card on
screen.

Cloudflare Polish (PERFORMANCE_PLAN §2.3) converts to WebP at the edge and is the
fast path — no pipeline change. The durable fix is at upload:

- Generate `thumb` 150 px, `card` 400 px, `hero` 1200 px on upload.
  `intervention/image` is already a dependency.
- Store WebP with a JPEG fallback for older clients.
- Return a variant set from the API rather than one URL:

```jsonc
"logo": {
  "thumb": "https://.../thumb/abc.webp",
  "card":  "https://.../card/abc.webp",
  "fallback": "https://.../card/abc.jpg"
}
```

so the client requests the size it will actually display.

Backfill existing images with a queued command rather than a synchronous script —
the queue infrastructure is already in place.

Expected: **57 KB → ~8 KB** per store logo at card size.

---

## 4. The client

This is where most of the perceived difference lives, and none of it is backend
work. A 95 ms API behind a spinner still feels slow; a 300 ms API behind a
skeleton screen with warm cached data feels instant.

- **Skeleton screens.** Draw the layout immediately. Never a spinner on a blank
  page.
- **Stale-while-revalidate.** Persist the last home payload. On launch, render it
  instantly, fetch in the background, reconcile. The `meta.ttl` field exists for
  this.
- **Prefetch** the store detail payload when a card scrolls into view.
- **Optimistic UI** on cart actions — update locally, reconcile after.
- **Image placeholders** — a dominant-colour block or blurhash while the image
  loads, so the layout never shifts.

---

## 5. Sequencing

| Step | Effort | Depends on | Ship independently? |
|---|---|---|---|
| 1.3 formatter N+1 fix | hours | — | **yes**, benefits v1 today |
| 1.2 drop or populate empty sections | hours | — | **yes**, app-side |
| 2.x aggregate endpoint | ~1 week | 1.3 | backend first, app later |
| 3 image variants | ~3 days | — | **yes**, backfill via queue |
| 4 client caching | ~1 week | 2.x | needs the aggregate |

Ship the N+1 fix and image variants first — both improve v1 immediately with no
app release. Build the aggregate behind v2 while the app team works to it.

---

## 6. Verification

For each step, measure rather than assume:

```bash
# payload and timing for the aggregate, from Egypt
curl -s -o /tmp/home.json -w "ttfb %{time_starttransfer}s  %{size_download} B\n" \
  "https://waddyapp.com/api/v2/m/1/z/1/home?lat=30.0444&lng=31.2357"

# gzipped size — what a customer actually downloads
gzip -9 -c /tmp/home.json | wc -c

# query count for one request (temporary, non-production)
DB::listen(fn ($q) => Log::debug($q->sql));
```

Targets, against today's 9 calls / 56 KB / 1,312 ms:

| Metric | Target |
|---|---|
| Round trips | 1 |
| Payload, gzipped | < 5 KB |
| TTFB from Egypt, edge-cached | < 150 ms |
| Queries per request, warm cache | < 5 |
| Queries per request, cold cache | < 25 |

Re-run the localhost load test after the aggregate lands. It is one request doing
the work of nine, so it will be slower per request — what matters is total work
per home screen, not per call.

## 7. What this does not fix

The server still does ~65 ms of work per request and tops out at **52 req/s on
4 cores** ([AUDIT.md](AUDIT.md) §2.3). Tier 3 reduces how many requests a screen
needs and how many bytes each carries; it does not raise that ceiling. It does
push it further away — one cached aggregate call costs far less server work than
nine uncached ones, so the same hardware serves considerably more customers.
