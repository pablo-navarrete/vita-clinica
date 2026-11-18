<?php
namespace App\Services\Signatures;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class SigningIdentityVerifier
{
    public function verify(User $user, string $password): void
    {
        $key = 'encounter-sign:' . $user->customer_id . ':' . $user->id;
        $ipKey = 'encounter-sign-ip:' . hash('sha256', (string) request()->ip());
        if (RateLimiter::tooManyAttempts($key, 5) || RateLimiter::tooManyAttempts($ipKey, 25)) {
            throw ValidationException::withMessages(['password' => 'Demasiados intentos. Espera unos minutos antes de volver a firmar.']);
        }
        if (!Hash::check($password, $user->getAuthPassword())) {
            RateLimiter::hit($key, 300);
            RateLimiter::hit($ipKey, 300);
            throw ValidationException::withMessages(['password' => 'La contraseña no es correcta.']);
        }
        // Do not reset counters on success: parallel attempts cannot erase another failure.
    }
}
