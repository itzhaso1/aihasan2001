<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('platform_admin')->check()) {
            if ($request->expectsJson()) {
                abort(Response::HTTP_FORBIDDEN, 'Platform admin authentication required.');
            }

            return redirect()->route('platform.login');
        }

        return $next($request);
    }
}
