# Waddi — server capacity testing

Everything here is **read-only**. No orders, reviews, SMS, pushes or payment
calls. Running it against production creates no data and costs no money.

---

## Step 1 — Gather facts from the Contabo server

SSH in and run this. It prints what we need and changes nothing:

```bash
cd /path/to/your/laravel/root   # wherever artisan lives

echo "===== APP ====="
php -v | head -1
php artisan --version
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|DB_DATABASE|CACHE_DRIVER|QUEUE_CONNECTION|SESSION_DRIVER|REDIS_HOST)=' .env

echo "===== CACHES (should all say cached in production) ====="
ls -la bootstrap/cache/ | grep -E 'config|routes|events' || echo "NO CACHES — big perf loss"

echo "===== QUEUE WORKER RUNNING? ====="
ps aux | grep -c "[q]ueue:work"

echo "===== OPCACHE ====="
php -i | grep -E "opcache.enable|opcache.memory_consumption|opcache.max_accelerated_files" | head -5

echo "===== HARDWARE ====="
nproc; free -h | head -2; df -h / | tail -1

echo "===== WEB/PHP-FPM WORKER LIMITS (the real concurrency ceiling) ====="
grep -rE "^(pm|pm.max_children|pm.start_servers|pm.max_spare_servers)" /etc/php/*/fpm/pool.d/*.conf 2>/dev/null
grep -rE "MaxRequestWorkers|worker_processes|worker_connections" /etc/apache2/mods-enabled/mpm_*.conf /etc/nginx/nginx.conf 2>/dev/null

echo "===== DATABASE SIZE ====="
mysql -u root -p -e "
SELECT table_schema AS db, COUNT(*) tables_,
       ROUND(SUM(data_length+index_length)/1024/1024,1) mb
FROM information_schema.tables GROUP BY table_schema ORDER BY mb DESC;"
```

Then, against **your real database name** (from `DB_DATABASE` above):

```bash
mysql -u root -p YOUR_REAL_DB -e "
SELECT 'orders' t, COUNT(*) n FROM orders
UNION ALL SELECT 'users',  COUNT(*) FROM users
UNION ALL SELECT 'items',  COUNT(*) FROM items
UNION ALL SELECT 'stores', COUNT(*) FROM stores
UNION ALL SELECT 'guests', COUNT(*) FROM guests;

-- The IDs the load test needs:
SELECT id, module_name FROM modules WHERE status = 1;
SELECT id, name FROM zones WHERE status = 1;

-- Missing indexes on the hottest columns:
SHOW INDEX FROM orders WHERE Column_name IN ('user_id','store_id','zone_id','order_status','is_guest');
SHOW INDEX FROM items  WHERE Column_name IN ('store_id','category_id','status');
"
```

Also worth capturing — is MySQL logging slow queries at all?

```bash
mysql -u root -p -e "SHOW VARIABLES LIKE 'slow_query%'; SHOW VARIABLES LIKE 'long_query_time';"
```

Send me that output and I can tell you exactly where the ceiling is and why.

---

## Step 2 — Point the test at the server

Use the `modules.id` and `zones.id` you got above:

```bash
export BASE_URL=https://your-domain.com
export MODULE_ID=1
export ZONE_ID=1
```

## Step 3 — Smoke test first (1 user, 30s)

```bash
k6 run -e BASE_URL=$BASE_URL -e MODULE_ID=$MODULE_ID -e ZONE_ID=$ZONE_ID loadtest/smoke.js
```

All checks must pass. Failures here mean wrong IDs/headers — fix before loading.
You are otherwise just benchmarking 403 error pages, which are fast and tell you
nothing.

## Step 4 — Capacity test

```bash
k6 run -e BASE_URL=$BASE_URL -e MODULE_ID=$MODULE_ID -e ZONE_ID=$ZONE_ID \
       -e PEAK_VUS=25 loadtest/load.js
```

Then raise `PEAK_VUS` (50, 100, 200…) until p95 latency climbs sharply or the
run self-aborts. That inflection point is your capacity.

### Running against a live site — read this

- Run it in your **quietest hour**.
- Start at `PEAK_VUS=25` and step up. Don't jump straight to 200.
- The test **aborts itself** if failures exceed 5% or p95 exceeds 2s, so it
  backs off rather than piling on.
- Watch the box while it runs: `htop`, and
  `tail -f storage/logs/laravel.log`.
- Ideally point it at a staging clone. If you must use production, the
  read-only endpoint list means the worst case is temporary slowness for real
  users, not corrupted data.

### Reading the result

- **`Throughput req/s`** — sustained capacity.
- **`p95` / `p99`** — what your slowest users feel. p95 under ~500ms is healthy;
  over 2s means users perceive the app as broken.
- **Slowest endpoints table** — tells you which query to fix first.
- **Failure rate** above 0 under load usually means PHP-FPM `pm.max_children`
  or MySQL `max_connections` is the ceiling, not your code.

Full raw metrics land in `loadtest/results/summary.json`.
