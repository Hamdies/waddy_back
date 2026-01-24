<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to track user's last activity time.
 * 
 * Updates the user's last_active_at timestamp for "last seen" feature.
 * Only updates if the last activity was more than 1 minute ago to prevent DB spam.
 */
class UpdateLastActive
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            
            // Only update if last activity was > 1 minute ago (prevents DB spam)
            if (!$user->last_active_at || $user->last_active_at->diffInMinutes(now()) >= 1) {
                $user->last_active_at = now();
                $user->saveQuietly(); // saveQuietly to avoid triggering events
            }
        }
        
        return $next($request);
    }
}
