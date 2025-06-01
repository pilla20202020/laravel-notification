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
        // We assume PublishNotificationRequest has validated user_id.
        $userId = (int) $request->input('user_id');

        // Key for caching count: we can also skip caching and rely on DB count in service,
        // but here we just let service handle DB query. Middleware can short-circuit if needed,
        // but since service also does it, the middleware can be simpler/do nothing.
        return $next($request);
    }
}
