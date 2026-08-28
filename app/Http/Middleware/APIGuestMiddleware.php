<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Guest;
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

        // Preferred path: an unguessable token proves who the guest is.
        $guestToken = $request->header('guest-token') ?: $request->input('guest_token');

        if ($guestToken) {
            $guest = Guest::where('token', $guestToken)->first();

            if ($guest) {
                // The token is authoritative — never trust a client-supplied
                // guest_id alongside it.
                $request->merge(['guest_id' => $guest->id]);
                $request->attributes->set('guest', $guest);

                return $next($request);
            }

            return response()->json(['errors' => 'Unauthorized'], 401);
        }

        // Legacy path: guest_id alone. It is guessable, so it is only accepted
        // while clients are still being migrated to tokens, and only for a
        // guest row that actually exists.
        if ($request->guest_id && !config('guest.require_token')) {
            $guest = Guest::find($request->guest_id);

            if ($guest) {
                $request->attributes->set('guest', $guest);

                return $next($request);
            }
        }

        return response()->json(['errors' => 'Unauthorized'], 401);
    }
}
