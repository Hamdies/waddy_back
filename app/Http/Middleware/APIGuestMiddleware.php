<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Throwable;

class APIGuestMiddleware
{
    private function looksLikeJwt(?string $token): bool
    {
        $token = trim((string) $token);

        return $token !== '' && $token !== 'null' && substr_count($token, '.') === 2;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if ($this->looksLikeJwt($token)) {
            try {
                $user = auth('api')->user();
                if ($user) {
                    $request->merge(['user' => $user]);
                    return $next($request);
                }
            } catch (Throwable $e) {
                // Ignore malformed/expired bearer tokens here so guest requests can continue.
            }
        }

        if ($request->guest_id) {
            return $next($request);
        }

        return response()->json(['errors' => 'Unauthorized'], 401);
    }
}
