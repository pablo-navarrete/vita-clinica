<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Runs before cookies/session. This group never resolves a clinical tenant. */
class PortalSession
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->getHost() === config('app.domain'), 404);
        $previous = ['session.cookie'=>config('session.cookie'),'session.path'=>config('session.path'),'session.domain'=>config('session.domain')];
        config(['session.cookie'=>'vita_mi_salud_session', 'session.path'=>'/mi-salud', 'session.domain'=>null]);
        // A previously resolved store (tests or persistent workers) must use the portal cookie too.
        $store=app('session')->driver();$previousName=$store->getName();$store->setName('vita_mi_salud_session');
        app()->forgetInstance('customer');
        app()->instance('customer', null);
        try { $response = $next($request);
        $response->headers->set('Cache-Control','private, no-store, max-age=0');
        $response->headers->set('Referrer-Policy','no-referrer');
        $response->headers->set('X-Robots-Tag','noindex, nofollow');
        $response->headers->set('X-Content-Type-Options','nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        return $response;
        } finally { $store->setName($previousName);config($previous); }
    }
}
