<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Tells Laravel which upstream proxies may be trusted to report the real client
 * address, so `$request->ip()` is the customer rather than the proxy.
 *
 * This previously extended Fideloper\Proxy\TrustProxies, a package dropped from
 * Laravel in v9 and not installed here — enabling the middleware in that state
 * would have fatalled every request with a class-not-found. It now extends the
 * framework's own implementation.
 *
 * Still disabled in Kernel.php, and correctly so: with no proxy in front,
 * trusting forwarded headers would let any client spoof its own IP and walk
 * straight past the rate limiter.
 *
 * When a CDN is added, prefer configuring nginx's real_ip module with the
 * provider's ranges over enabling this — nginx corrects REMOTE_ADDR before PHP
 * ever sees the request, so nothing downstream has to be told to trust anything.
 * If this middleware is used instead, set TRUSTED_PROXIES to the provider's
 * ranges. Never '*' unless nginx already refuses connections from anywhere else;
 * a wildcard means any client can claim any address via X-Forwarded-For.
 */
class TrustProxies extends Middleware
{
    /**
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO;

    public function __construct()
    {
        $configured = trim((string) env('TRUSTED_PROXIES', ''));

        if ($configured !== '') {
            $this->proxies = array_values(array_filter(array_map('trim', explode(',', $configured))));
        }
    }
}
