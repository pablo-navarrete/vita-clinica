<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Customer;
use App\Support\ClinicBranchContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): View
    {
        return view('auth.login', [
            'motivation' => app(\App\Support\LoginMotivation::class)->next($request->session(), $request->getHost()),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        $user = Auth::user();

        $customer = app()->bound('customer') ? app('customer') : null;
        if (!$customer && $request->route('customer')) {
            $customer = Customer::query()->where('domain', $request->route('customer'))->first();
        }
        if ($customer) {
            $branch = ClinicBranchContext::defaultBranch($user, (int) $customer->id);
            if ($branch) {
                $request->session()->put('active_branch_id', (int) $branch->id);
                $request->session()->put('active_branch_customer_id', (int) $customer->id);
            } else {
                $request->session()->forget(['active_branch_id', 'active_branch_customer_id']);
            }
        } else {
            $request->session()->forget(['active_branch_id', 'active_branch_customer_id']);
        }

        // SUPERADMIN
        if ($user->customer_id === null) {
            return redirect('/dashboard');
        }

        // La ruta nombrada usa el dominio de la clínica y conserva HTTP o HTTPS
        // según la solicitud actual, sin fijar el protocolo manualmente.
        return redirect()->route('customers.dashboard.index', [
            'customer' => $user->customer->domain,
        ]);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
