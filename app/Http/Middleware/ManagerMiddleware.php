<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ManagerMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isManager = $request->user()?->isManager();
        $isAdmin = $request->user()?->isAdmin();
        if (! $isManager && ! $isAdmin) {
            abort(403, 'You are not authorized to access this page.');
        }

        return $next($request);
    }
}
