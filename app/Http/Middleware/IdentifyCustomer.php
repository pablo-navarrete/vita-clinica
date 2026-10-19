<?php

namespace App\Http\Middleware;
use App\Models\Logo;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
   public function handle(Request $request, Closure $next): Response
{
    $host = $request->getHost();
    $mainDomain = config('app.domain');

    // Si es el dominio principal, no existe tenant
    if ($host === $mainDomain) {
        app()->instance('customer', null);
        app()->instance('logos_by_location', []);

        return $next($request);
    }

    /*
    |--------------------------------------------------------------------------
    | Obtener el subdominio
    |--------------------------------------------------------------------------
    | Ejemplo:
    | Host: clinica1.vita-clinica.test
    | Resultado: clinica1
    */
    $subdomain = str_replace('.' . $mainDomain, '', $host);

    // Buscar cliente por subdominio
    $customer = Customer::where('domain', $subdomain)->first();

    if (!$customer) {
        abort(404);
    }

    if ((int) $customer->status !== 1) {
        if (auth()->check()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->view('errors.clinic-unavailable', [
            'clinicName' => $customer->name,
        ], 503);
    }

    $user = auth()->user();

    /*
    |--------------------------------------------------------------------------
    | Validación Tenant
    |--------------------------------------------------------------------------
    */
    if ($user && $user->customer_id !== null && (int) $user->customer_id !== (int) $customer->id) {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        abort(403, 'Tu cuenta no pertenece a esta clínica.');
    }

    /*
    |--------------------------------------------------------------------------
    | Compartir Customer
    |--------------------------------------------------------------------------
    */
    app()->instance('customer', $customer);

    /*
    |--------------------------------------------------------------------------
    | Logos por ubicación
    |--------------------------------------------------------------------------
    */
    $logos = Logo::with('locations')
        ->where('customer_id', $customer->id)
        ->where('status', 1)
        ->get();

    $logosByLocation = [];

    foreach ($logos as $logo) {

        foreach ($logo->locations as $location) {

            $key = strtolower($location->location);

            if (!isset($logosByLocation[$key])) {
                $logosByLocation[$key] = $logo;
            }
        }
    }

    app()->instance('logos_by_location', $logosByLocation);

    return $next($request);
}
}
