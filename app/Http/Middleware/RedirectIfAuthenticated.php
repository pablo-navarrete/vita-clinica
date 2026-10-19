<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            $auth = Auth::guard($guard);

            if ($auth->check()) {
                $user = $auth->user();

                // Cierra sesiones de profesionales que fueron desactivados
                // después de iniciar sesión y permite volver al formulario.
                if ($user->customer_id !== null && (int) $user->status !== 1) {
                    $auth->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    continue;
                }

                return redirect(RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
