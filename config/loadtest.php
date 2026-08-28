<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate-limit exempt IPs
    |--------------------------------------------------------------------------
    |
    | Comma-separated IPs that bypass the `api` rate limiter. This exists so a
    | capacity test measures the server rather than the throttle (120 req/min
    | per IP caps any single-source test at ~2 req/s).
    |
    | SECURITY: leaving an IP in here permanently removes that address's only
    | brute-force and scraping protection. Set LOADTEST_EXEMPT_IPS for the
    | duration of a test run, then clear it and re-cache config.
    |
    | Read via config() rather than env() because env() returns null once
    | `php artisan config:cache` has run.
    |
    */

    'exempt_ips' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('LOADTEST_EXEMPT_IPS', ''))
    ))),

];
