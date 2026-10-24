<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\CentralLogics\Helpers;

class DmTokenIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken() ?? $request->input('token');
        if(!$token || strlen($token) < 1) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth-001', 'message' => 'Unauthorized.']
                ]
            ], 401);
        }
        $dm = \App\Models\DeliveryMan::where('auth_token', $token)->first();
        if(!$dm) {
            return response()->json([
                'errors' => [
                    ['code' => 'auth-001', 'message' => 'Unauthorized.']
                ]
            ], 401);
        }
        // Controllers get the rider from here instead of looking them up by
        // token a second time. `token` is merged too, so a request that only
        // sends `Authorization: Bearer` (no ?token=) works the same.
        $request->merge(['dm' => $dm, 'token' => $token]);
        return $next($request);
    }
}
