<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $clinic = app()->bound('customer') ? app('customer') : null;
        try {
            $status = Password::sendResetLink([
                'email' => $request->input('email'),
                'customer_id' => $clinic?->id,
            ]);
        } catch (\Throwable $exception) {
            report($exception);
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'No pudimos enviar el correo. Inténtalo nuevamente más tarde o contacta al administrador.',
            ]);
        }

        return back()->with('status', 'Si el correo corresponde a una cuenta de este sitio, recibirás un enlace para restablecer tu contraseña. Revisa también la carpeta de spam.');
    }
}
