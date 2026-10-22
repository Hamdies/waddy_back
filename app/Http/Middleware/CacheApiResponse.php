<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Serves a cached copy of a public catalogue response (home rails, categories, banners, config) for a
 * short time. These answers depend only on the URL and the zone/module/language/image headers, never on
 * who is asking, so every app user in the same zone shares one copy instead of each one re-running the
 * same heavy list queries. Under load those queries were the server's ceiling (capacity test, 2026-10-22).
 *
 * Only for GET routes whose response does not depend on the user or their location.
 */
class CacheApiResponse
{
    public function handle(Request $request, Closure $next, int $seconds = 60)
    {
        if (!$request->isMethod('GET')) {
            return $next($request);
        }

        $key = 'api_response:' . sha1(implode('|', [
            $request->fullUrl(),
            $request->header('zoneId'),
            $request->header('moduleId'),
            $request->header('X-localization'),
            $request->header('X-Image-Variants'),
        ]));

        $cached = Cache::get($key);
        if (is_string($cached)) {
            return response($cached, 200, ['Content-Type' => 'application/json', 'X-Cache' => 'HIT']);
        }

        $response = $next($request);
        if ($response instanceof JsonResponse && $response->getStatusCode() === 200) {
            Cache::put($key, $response->getContent(), $seconds);
        }
        $response->headers->set('X-Cache', 'MISS');

        return $response;
    }
}
