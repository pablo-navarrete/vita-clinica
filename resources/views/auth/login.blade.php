<x-guest-layout :motivation="$motivation ?? null">
    <h2 class="auth-form-title">¡Bienvenido de nuevo!</h2>
    <p class="auth-form-description">Ingresa tus credenciales para continuar con tu jornada.</p>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}" class="auth-login-form">
        @csrf
        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                placeholder="nombre@ejemplo.cl" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}"
                @if ($errors->has('email')) aria-describedby="email-errors" @endif>
            <div id="email-errors"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
        </div>
        <div class="auth-field" x-data="{ showPassword: false }">
            <label for="password">Contraseña</label>
            <div class="password-control">
                <input id="password" type="password" :type="showPassword ? 'text' : 'password'" name="password" required autocomplete="current-password"
                    placeholder="Tu contraseña" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}"
                    @if ($errors->has('password')) aria-describedby="password-errors" @endif>
                <button type="button" class="password-toggle" x-cloak @click="showPassword = !showPassword"
                    :aria-label="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'" :aria-pressed="showPassword" aria-controls="password">
                    <svg x-show="!showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>
                    <svg x-show="showPassword" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a20 20 0 0 1-3.1 3.9M6.2 6.2A21 21 0 0 0 2 12s3.5 7 10 7a12 12 0 0 0 5.8-1.8M10 10a3 3 0 0 0 4 4"/></svg>
                </button>
            </div>
            <div id="password-errors"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
        </div>
        @if (Route::has('password.request'))
            <a class="auth-recovery" href="{{ route('password.request') }}">Olvidé mi contraseña</a>
        @endif
        <button type="submit" class="auth-submit">Iniciar sesión<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
    </form>
</x-guest-layout>
