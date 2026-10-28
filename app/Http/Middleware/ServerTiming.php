<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds `Server-Timing: db;dur=…;desc="N queries", app;dur=…` to API answers
 * when the request carries `X-Timing-Key` equal to SERVER_TIMING_KEY. Off
 * when the key is unset. Lets the partner-apps baseline (§8) read query
 * counts and DB time per endpoint on prod from outside, without logs.
 *
 * First in the api group, so the rate limiter and the token middlewares are
 * counted too.
 */
class ServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = config('app.server_timing_key');
        if (!$key || !hash_equals((string) $key, (string) $request->header('X-Timing-Key'))) {
            return $next($request);
        }

        $start = microtime(true);
        $queries = 0;
        $dbMs = 0.0;
        DB::listen(function (QueryExecuted $q) use (&$queries, &$dbMs) {
            $queries++;
            $dbMs += $q->time;
        });

        $response = $next($request);

        $appMs = (microtime(true) - $start) * 1000;
        $response->headers->set('Server-Timing', sprintf(
            'db;dur=%.1f;desc="%d queries", app;dur=%.1f, boot;dur=%.1f',
            $dbMs, $queries, $appMs, defined('LARAVEL_START') ? ($start - LARAVEL_START) * 1000 : 0
        ));
        return $response;
    }
}
