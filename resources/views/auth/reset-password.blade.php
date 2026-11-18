<x-guest-layout :motivation="['label' => 'UN NUEVO COMIENZO', 'title' => 'Tu espacio te espera.', 'description' => 'Elige una nueva contraseña para volver a trabajar con confianza.']">
    <h2 class="auth-form-title">Restablecer contraseña</h2>
    <p class="auth-form-description">Escribe tu correo y confirma tu nueva contraseña.</p>
    <form method="POST" action="{{ route('password.store') }}" class="auth-login-form">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username" aria-describedby="email-errors">
            <div id="email-errors"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
        </div>
        <div class="auth-field">
            <label for="password">Nueva contraseña</label>
            <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password" aria-describedby="password-errors">
            <div id="password-errors"><x-input-error :messages="$errors->get('password')" class="mt-2" /></div>
        </div>
        <div class="auth-field">
            <label for="password_confirmation">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required minlength="8" autocomplete="new-password" aria-describedby="confirmation-errors">
            <div id="confirmation-errors"><x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" /></div>
        </div>
        <button type="submit" class="auth-submit">Restablecer contraseña</button>
        <a class="auth-recovery" href="{{ route('password.request') }}">Solicitar un nuevo enlace</a>
    </form>
</x-guest-layout>
