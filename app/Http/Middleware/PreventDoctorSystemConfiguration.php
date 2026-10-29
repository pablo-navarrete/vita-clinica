<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreventDoctorSystemConfiguration
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isSuperAdmin = $user?->roles()
            ->whereNull('roles.customer_id')
            ->where('roles.name', 'Super Administrador')
            ->exists();
        $isClinicAdmin = $user && app()->bound('customer')
            ? $user->roles()->where('roles.customer_id', app('customer')->id)->where('roles.name', 'Administrador')->exists()
            : false;

        $isDoctor = ! $isSuperAdmin && ! $isClinicAdmin && $user
            ? $user->roles()->where('roles.name', 'Doctor')->exists()
            : false;

        abort_if($isDoctor, 403, 'No tienes permiso para acceder a la configuración del sistema.');

        return $next($request);
    }
}
