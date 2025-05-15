<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureRgpdAccepted
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check() && is_null(Auth::user()->rgpd_accepted_at)) {
            return redirect()->route('rgpd.notice');
        }

        return $next($request);
    }
}
