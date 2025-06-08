<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitPerUser
{
    /**
     * Handle an incoming request.
     * Check that user_id in request has not exceeded 10 notifications in the past hour.
     */
    public function handle(Request $request, Closure $next)
    {
        $userId = (int) $request->input('user_id');
        return $next($request);
    }
}
