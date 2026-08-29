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

### 1.3 The formatters run 2 queries per store ✅ FIXED

`Helpers::store_data_formatting()` used to do this inside its `foreach`:

```php
$item->load('storeConfig');                                        // 1 query per store
$extra_packaging_data = BusinessSetting::where('key', 'extra_packaging_data')
    ->first()?->value ?? '';                                       // 1 query per store
```

The second is the worse of the two — the same key, returning the same value,
re-queried for every store in the list, while a cached accessor
(`Helpers::get_business_settings()`) already exists and is used elsewhere.

Three sections × 10 stores = **60 avoidable queries per home screen**.

> **Fixed.** The multi-row branch now does one `loadMissing(['storeConfig',
> 'module'])` for the whole set and reads `extra_packaging_data` through the
> cached `Helpers::get_business_settings()` once, outside the loop. The
> aggregate endpoint in §2 can now be built on top of it without inheriting
> the problem.

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

## 3. Images ✅ SHIPPED (29 Aug 2026)

Store logos are **300–500 px PNGs at 10–57 KB**, rendered on a card at roughly
120 px. Promotional banners were worse: one is a **596 KB PNG**.

The resize is not where the saving is — the **format conversion** is. A 500 px
PNG logo re-encoded to WebP at the same dimensions is already ~70% smaller;
resizing it to a 150 px thumbnail takes it to 87%.

### 3.1 What was built

| | |
|---|---|
| `config/imagevariants.php` | sizes, quality, which upload directories get variants |
| `app/Services/ImageVariantService.php` | generate / delete / resolve-URLs |
| `app/Jobs/GenerateImageVariants.php` | one queued job per image |
| `app/Console/Commands/BackfillImageVariants.php` | `php artisan images:backfill-variants` |
| `app/Traits/HasImageVariants.php` | the read side, on Store / Item / Category / Banner |
| `tests/Unit/ImageVariantServiceTest.php` | 10 tests, no database required |

Uploads are unchanged in shape: `Helpers::upload()` still stores the original
and still returns the same filename. It now also dispatches a job that writes

```
store/2024-11-19-abc.png                    ← original, untouched
store/variants/thumb/2024-11-19-abc.webp    ← 150 px
store/variants/thumb/2024-11-19-abc.jpg     ← fallback
store/variants/card/2024-11-19-abc.webp     ← 400 px
store/variants/card/2024-11-19-abc.jpg
```

`variants/` rather than `thumb/` directly under `store/`, because `store/cover/`
is a real content directory and a size name could one day collide with one.

`Helpers::update()` and `Helpers::check_and_delete()` remove the variant set
along with the original, so replacing a logo does not leave the old one behind.

### 3.2 Measured

Backfilling the 19 images in the demo dataset:

| | Original | Smallest WebP | |
|---|---|---|---|
| Store logo | 10,878 B | 1,368 B | thumb |
| Store cover | 14,580 B | 2,824 B | card |
| Category icon | 9,559 B | 228 B | thumb |
| Promotional banner | 596,165 B | 11,742 B | card |
| Module icon | 197,327 B | 2,950 B | thumb |
| **19 images** | **1,464,447 B** | **57,880 B** | **96% smaller** |

Correctness checks that were actually run, not assumed:

- output dimensions are 150/400 as configured, and a 120 px source is **not**
  upscaled to 400
- the WebP keeps its alpha channel (corner pixel alpha 127)
- the JPEG fallback is flattened onto **white**, not GD's default black
- re-running the backfill is a no-op; `--force` regenerates

### 3.3 The read side is opt-in, and that is deliberate

`logo_full_url` and the other `*_full_url` strings are **unchanged**. The
variant set arrives as a new sibling key:

```jsonc
"logo_variants": {
  "webp": { "thumb": "https://.../store/variants/thumb/abc.webp",
            "card":  "https://.../store/variants/card/abc.webp" },
  "jpg":  { "thumb": "...", "card": "..." },
  "original": "https://.../store/abc.png"
}
```

but only when the client asks, via `X-Image-Variants: 1` or `?image_variants=1`.

Appending it unconditionally would add ~150 bytes per card to every response —
including the builds in the field that cannot use it. On a fifty-store home
screen that is 7 KB spent telling old clients about URLs they will never
request, which is the exact opposite of the point of this tier. An old client
now gets a **byte-identical** payload; there is a test asserting it.

> **The sequencing claim in §5 was too optimistic.** This ships with no app
> release, but the bytes do not come off the wire until the app requests the
> variant URLs. What ships today is the pipeline, the backfill and the API;
> the saving lands with the next client release.
>
> The one route to a saving with no client change would be nginx serving WebP
> by content negotiation on `Accept: image/webp` — but Flutter's image loader
> sends no `Accept` header, so it would only ever help the admin panel and the
> web landing page.

### 3.4 Running the backfill

```bash
php artisan images:backfill-variants --dry-run     # what would be processed
php artisan images:backfill-variants               # queue it
php artisan images:backfill-variants --sync        # encode inline instead
journalctl -u waddy-queue -f                       # watch it
```

`--dir=store`, `--limit=100` and `--force` narrow it down. Already-generated
images are skipped, so it is safe to re-run and safe to interrupt.

Add it to the deploy runbook only if you want it automatic; it is idempotent,
but on a large catalogue the first run is a lot of CPU and belongs on a quiet
window rather than inside a deploy.

### 3.5 Caveats worth knowing before touching this

- **Variant URLs are immutable to caches.** nginx serves `/storage/` with
  `Cache-Control: public, max-age=31536000, immutable` (Tier 1). Regenerating
  a variant in place with `--force` — after lowering `webp_quality`, say —
  changes bytes at a URL browsers and Cloudflare have been told never to
  revalidate. Change quality only alongside a re-upload, or purge the edge.
- **`QUEUE_CONNECTION=sync` makes generation inline.** On any box where the
  worker is not running, an admin saving a store logo pays the encode inside
  the request. That is the right failure mode — the alternative is a job that
  silently never runs — but it is why `waddy-queue` matters (AUDIT §5.1).
- **GIFs are excluded on purpose.** Intervention 2 flattens animation to the
  first frame, so a variant would silently lose it.
- **Verify nginx knows the MIME type.** `.webp` has been in nginx's
  `mime.types` since 1.11.6, but confirm rather than assume:
  ```bash
  curl -sI https://waddyapp.com/storage/store/variants/card/<file>.webp \
    | grep -i 'content-type\|cache-control'
  # want: image/webp, and the immutable 1-year policy
  ```
- **Encoding failures are non-fatal by design.** A store still saves if its
  logo cannot be decoded; the API keeps serving the original and the failure is
  logged. Grep for `Image variant generation failed`.

### 3.6 What is left here

- Point the app at `logo_variants.webp.card` and send the opt-in header.
- Consider `hero` (1200 px) for `store/cover` once the detail screen is
  measured — it is configured but the cover only needs it if the screen
  actually renders that large.
- AVIF is another ~20% below WebP, but PHP-GD on the production box cannot
  encode it. Not worth an Imagick install for 20%.


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

| Step | Effort | Depends on | State |
|---|---|---|---|
| 1.3 formatter N+1 fix | hours | — | ✅ done — `store_data_formatting()` loads `storeConfig`/`module` once and reads a cached `extra_packaging_data` |
| 3 image variants | ~3 days | — | ✅ done — pipeline, backfill and API shipped; see §3 |
| 1.2 drop or populate empty sections | hours | — | open, app-side |
| 2.x aggregate endpoint | ~1 week | 1.3 | open — backend first, app later |
| 4 client caching | ~1 week | 2.x | open — needs the aggregate |

The two independent wins are in. Neither needed an app release to *deploy*, but
§3's bytes only come off the wire once the client requests the variant URLs —
see the note in §3.3. Build the aggregate behind v2 while the app team works to
both changes at once.

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
