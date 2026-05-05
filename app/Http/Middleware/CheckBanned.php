<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && (Auth::user()->is_banned || Auth::user()->isDeactivated())) {
            Auth::logout();
            return redirect()->route('login')->withErrors(['username' => 'Bu hesap artık aktif değil.']);
        }

        return $next($request);
    }
}
