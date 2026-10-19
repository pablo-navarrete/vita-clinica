<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user
            ?->roles()
            ->where('roles.name', 'Super Administrador')
            ->exists();

        if (!$isSuperAdmin) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            abort(403, 'Tu sesión se cerró porque esta cuenta no tiene acceso al panel de superadministración.');
        }

        return $next($request);
    }
}
