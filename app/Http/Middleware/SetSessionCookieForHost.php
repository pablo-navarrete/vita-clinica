<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SetSessionCookieForHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $baseCookieName = (string) config('session.cookie_base', config('session.cookie'));
        $host = Str::slug($request->getHost(), '_');

        config(['session.cookie' => $baseCookieName . '_' . $host]);

        return $next($request);
    }
}
