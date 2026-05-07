<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    private const EXCEPT_PATHS = [
        'up',
        'login',
        'maintenance',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! Cache::get('maintenance_mode', false)) {
            return $next($request);
        }

        if ($this->isWhitelisted($request)) {
            return $next($request);
        }

        /* Admin bypass — admins can use the site normally */
        if (Auth::check() && Auth::user()->isAdmin()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Bakım modu aktif. Lütfen daha sonra tekrar deneyin.'], 503);
        }

        return redirect('/maintenance');
    }

    private function isWhitelisted(Request $request): bool
    {
        foreach (self::EXCEPT_PATHS as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

        return false;
    }
}
