<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Define the rate limiting parameters
        $maxAttempts = 5; // Max allowed attempts
        $decayMinutes = 15; // Time in minutes before attempts reset

        // Generate a unique key based on IP and route
        $key = 'rate_limit_' . $request->ip() . '_' . $request->path();

        // Check how many attempts have been made
        if (Cache::has($key)) {
            $attempts = Cache::get($key);

            // If the max attempts are reached, deny access
            if ($attempts >= $maxAttempts) {
                return Response::json(['error' => 'Too many requests. Please try again later.'], 429);
            }

            // Increment attempts
            Cache::increment($key);
        } else {
            // Set the key in cache with the initial attempt and expiration
            Cache::put($key, 1, now()->addMinutes($decayMinutes));
        }

        return $next($request);
    }
}
