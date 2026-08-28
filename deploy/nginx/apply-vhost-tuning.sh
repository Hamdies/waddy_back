#!/usr/bin/env bash
#
# Tier 1, part 2 — the two server-context changes that cannot live in a
# conf.d drop-in. See deploy/nginx/README.md.
#
#   1. HTTP/2 on the TLS listener
#   2. Long-lived caching for /storage and static assets
#
# Idempotent: running it twice makes no further change. Backs up first, and
# reverts automatically if nginx rejects the result.
#
#   sudo bash deploy/nginx/apply-vhost-tuning.sh

set -euo pipefail

VHOST=${VHOST:-/etc/nginx/sites-available/waddyapp.com}
BACKUP="${VHOST}.bak-$(date +%F-%H%M%S)"

[[ -f "$VHOST" ]] || { echo "no such file: $VHOST" >&2; exit 1; }
[[ $EUID -eq 0 ]] || { echo "run with sudo" >&2; exit 1; }

cp -a "$VHOST" "$BACKUP"
echo "backup: $BACKUP"

python3 - "$VHOST" <<'PY'
import re, sys

path = sys.argv[1]
src = open(path).read()
orig = src
changed = []

# --- 1. HTTP/2 -------------------------------------------------------------
# nginx 1.18 predates the standalone `http2 on;` directive (1.25.1+), so it goes
# on the listen line. Certbot manages this line, but only rewrites it when
# `certbot --nginx` reconfigures; plain renewals just swap the cert files.
if re.search(r'^\s*listen\s+443\s+ssl\s+http2\s*;', src, re.M):
    print("  http2: already present")
else:
    src, n = re.subn(r'(^\s*listen\s+443\s+ssl)(\s*;)', r'\1 http2\2', src, count=1, flags=re.M)
    if n:
        changed.append("http2 on the 443 listener")
    else:
        print("  http2: WARNING — no `listen 443 ssl;` found, skipped")

# --- 2. Static caching -----------------------------------------------------
# `^~` stops nginx evaluating regex locations for /storage/, so uploads always
# get the 1-year immutable policy and never fall through to the asset regex.
#
# `try_files $uri =404` keeps a missing image from reaching Laravel, which would
# otherwise spend a PHP worker producing a 404 — workers are the scarce resource
# on this box.
BLOCK = """    # --- static caching (Tier 1) ---
    # Upload filenames carry a date and hash and are never rewritten in place,
    # so `immutable` is safe: browsers stop revalidating entirely.
    location ^~ /storage/ {
        # No `expires` directive: it emits its own Cache-Control header, and
        # combined with add_header the response carries two of them. Only
        # add_header can express `immutable`, so that is the one kept.
        add_header Cache-Control "public, max-age=31536000, immutable";
        access_log off;
        try_files $uri =404;
    }

    location ~* \\.(css|js|woff2?|ttf|otf|svg|ico|png|jpe?g|gif|webp|avif)$ {
        add_header Cache-Control "public, max-age=2592000";
        access_log off;
    }

"""

# Replace an existing block rather than skipping it, so corrections to the
# policy can be redeployed by re-running this script.
existing = re.search(
    r'^[ \t]*# --- static caching \(Tier 1\) ---.*?\n(?=[ \t]*location\s+/\s*\{)',
    src, re.S | re.M)
if existing:
    src = src[:existing.start()] + src[existing.end():]
    changed.append("replaced previous caching block")

# Insert immediately before the catch-all `location / {`.
m = re.search(r'^([ \t]*)location\s+/\s*\{', src, re.M)
if not m:
    print("  caching: WARNING — no `location / {` found, skipped")
else:
    src = src[:m.start()] + BLOCK + src[m.start():]
    changed.append("caching for /storage and static assets")

if src != orig:
    open(path, 'w').write(src)

print("  applied: " + (", ".join(changed) if changed else "nothing (already up to date)"))
PY

if nginx -t; then
    systemctl reload nginx
    echo
    echo "reloaded. verifying:"
    # This box's curl is built without HTTP/2, so it will report 1.1 even when
    # nginx is serving h2. Read the ALPN advertisement from OpenSSL instead.
    printf '  h2 advertised : '
    echo | openssl s_client -alpn h2 -connect waddyapp.com:443 \
        -servername waddyapp.com 2>/dev/null \
        | grep -i 'ALPN protocol' || echo 'none (check from a client with HTTP/2)'
else
    echo
    echo "nginx rejected the config — reverting to $BACKUP" >&2
    cp -a "$BACKUP" "$VHOST"
    nginx -t
    exit 1
fi
