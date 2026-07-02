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

    if ($host === $mainDomain) {
        app()->instance('customer', null);
        app()->instance('logos_by_location', []);
        return $next($request);
    }

    $customer = Customer::where('domain', $host)->first();

    if (! $customer) {
        abort(404);
    }

    $user = auth()->user();

    // 🔐 validación tenant
    if ($user && $user->customer_id !== null && $user->customer_id !== $customer->id) {
        abort(403);
    }

    // 👤 set customer siempre
    app()->instance('customer', $customer);

    // 🧠 LOGOS POR UBICACIÓN
    $logos = Logo::with('locations')
        ->where('customer_id', $customer->id)
        ->where('status', 1)
        ->get();

    $logosByLocation = [];

    foreach ($logos as $logo) {
        foreach ($logo->locations as $loc) {

            $key = strtolower($loc->location);

            if (!isset($logosByLocation[$key])) {
                $logosByLocation[$key] = $logo;
            }
        }
    }

    app()->instance('logos_by_location', $logosByLocation);

    return $next($request);
}
}
