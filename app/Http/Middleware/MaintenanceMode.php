<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class MaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Cache::get('maintenance_mode', false)) {
            return $next($request);
        }

        /* Admin bypass — admins can use the site normally */
        if (Auth::check() && Auth::user()->isAdmin()) {
            return $next($request);
        }

        /* Allow login page so admins can authenticate */
        if ($request->is('login') || $request->is('maintenance')) {
            return $next($request);
        }

        return redirect('/maintenance');
    }
}
