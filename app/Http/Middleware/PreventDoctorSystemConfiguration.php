<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDoctorSystemConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        $isDoctor = $request->user()
            ?->roles()
            ->where('roles.name', 'Doctor')
            ->exists();

        abort_if($isDoctor, 403, 'No tienes permiso para acceder a la configuración del sistema.');

        return $next($request);
    }
}
