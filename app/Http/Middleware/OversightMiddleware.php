<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OversightMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canAccessOversight(), 403, 'Denetim erişimi yetkiniz yok.');

        return $next($request);
    }
}
