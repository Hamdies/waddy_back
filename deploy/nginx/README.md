# nginx transport tuning (Tier 1)

Implements Tier 1 of [PERFORMANCE_PLAN.md](../../PERFORMANCE_PLAN.md). No
application changes, no app release. Measured against production from Egypt:

| | Today |
|---|---|
| Compression | **off** — 10,982 B response gzips to 1,872 B (83% saved) |
| Protocol | **HTTP/1.1** — nine home-screen calls open up to six connections |
| TLS handshake | **~88 ms**, paid per connection |
| Image `Cache-Control` | **absent** — a 57 KB PNG revalidates on every screen |

## Part 1 — the drop-in (zero risk)

`waddy-performance.conf` is http-context only. Ubuntu's `nginx.conf` already has
`include /etc/nginx/conf.d/*.conf;`, so it applies **without editing any existing
file**, and rolling back is deleting it.

```bash
cd /var/www/waddy
sudo cp deploy/nginx/waddy-performance.conf /etc/nginx/conf.d/
sudo nginx -t && sudo systemctl reload nginx
```

`nginx -t` must pass before the reload. If it fails, `sudo rm
/etc/nginx/conf.d/waddy-performance.conf` and nothing has changed.

### Verify

```bash
curl -s -o /dev/null -D - -H "Accept-Encoding: gzip" \
     -H "moduleId: 1" -H "zoneId: [1]" \
     "https://waddyapp.com/api/v1/stores/get-stores/all?offset=1&limit=10" \
  | grep -iE "content-encoding|vary"
```

Want `content-encoding: gzip` and `vary: Accept-Encoding`.

```bash
# bytes actually transferred, before vs after
curl -s -o /dev/null -w "identity %{size_download} B\n" \
     -H "moduleId: 1" -H "zoneId: [1]" \
     "https://waddyapp.com/api/v1/stores/get-stores/all?offset=1&limit=10"
curl -s -o /dev/null --compressed -w "gzip     %{size_download} B\n" \
     -H "moduleId: 1" -H "zoneId: [1]" \
     "https://waddyapp.com/api/v1/stores/get-stores/all?offset=1&limit=10"
```

> `curl -w %{size_download}` reports the **decompressed** size, so both lines may
> read ~10,983. To see what actually crossed the wire, watch
> `%{size_request}`/`%{speed_download}` or trust the `content-encoding` header
> plus the local `gzip -9 -c file | wc -c` measurement — 1,872 B.

## Part 2 — the server block (needs editing your vhost)

Two directives are server-context and cannot be dropped in. They go in
`/etc/nginx/sites-available/waddyapp.com`.

**Back it up first:**

```bash
sudo cp /etc/nginx/sites-available/waddyapp.com{,.bak-$(date +%F)}
nginx -v      # decides which http2 syntax to use
```

### 2a. HTTP/2

The highest-leverage line in Tier 1. Today the client opens up to six
connections for nine calls, each paying the ~88 ms handshake. HTTP/2 multiplexes
all nine over one connection.

nginx **≥ 1.25.1** — add a separate directive inside the `server` block that
listens on 443:

```nginx
http2 on;
```

nginx **< 1.25.1** — append to the existing listen line instead:

```nginx
listen 443 ssl http2;
listen [::]:443 ssl http2;
```

Do not use both forms; the old syntax is deprecated in newer nginx and warns.

### 2b. Static asset caching

Uploaded filenames carry a date and hash (`2026-01-28-697a420f020c0.png`) and are
never rewritten in place, so `immutable` is correct — the browser will not
revalidate at all.

```nginx
location /storage/ {
    expires 1y;
    add_header Cache-Control "public, max-age=31536000, immutable";
    access_log off;
    try_files $uri =404;
}

location ~* \.(css|js|woff2?|ttf|svg|ico)$ {
    expires 30d;
    add_header Cache-Control "public, max-age=2592000";
    access_log off;
}
```

> `try_files $uri =404` matters. Without it a missing image falls through to
> Laravel and costs a full PHP request to produce a 404 — on a box where PHP
> workers are the scarce resource.

Place both **before** the catch-all `location / { try_files ... index.php }`.
nginx matches prefix locations by longest match, but regex locations are
evaluated in order, so position is worth getting right.

### Verify

```bash
sudo nginx -t && sudo systemctl reload nginx

curl -s -o /dev/null -w "HTTP/%{http_version}\n" https://waddyapp.com/api/v1/config \
     -H "moduleId: 1" -H "zoneId: [1]"        # want: 2

curl -s -o /dev/null -D - https://waddyapp.com/storage/business/<file>.png \
  | grep -i cache-control                      # want: immutable
```

## After: re-measure

Compression trades CPU for bytes, and CPU is what limits this box to ~52 req/s.
Re-run the localhost load test:

```bash
cp -r /var/www/waddy/loadtest ~/loadtest && cd ~/loadtest
k6 run -e BASE_URL=https://127.0.0.1 -e HOST_HEADER=waddyapp.com \
  -e MODULE_ID=1 -e ZONE_ID=1 \
  -e PEAK_VUS=60 -e MAX_P95=15000 -e MAX_FAIL=0.30 -e RAMP=20s -e HOLD=60s load.js
```

Baseline to compare against: **52.2 req/s, p50 735 ms**. If throughput drops
meaningfully, lower `gzip_comp_level` to 3 — the compression ratio barely moves
and the CPU cost roughly halves.

Requires `LOADTEST_EXEMPT_IPS=127.0.0.1` in `.env` for the run, removed straight
after — see [AUDIT.md](../../AUDIT.md) §5.

## Rollback

```bash
sudo rm /etc/nginx/conf.d/waddy-performance.conf
sudo cp /etc/nginx/sites-available/waddyapp.com.bak-<date> \
        /etc/nginx/sites-available/waddyapp.com
sudo nginx -t && sudo systemctl reload nginx
```
