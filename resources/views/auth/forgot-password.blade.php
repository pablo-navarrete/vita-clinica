<x-guest-layout :motivation="['label' => 'VUELVE A TU ESPACIO', 'title' => 'Recupera tu acceso con tranquilidad.', 'description' => 'Te acompañamos para que puedas continuar con tu jornada.']">
    <h2 class="auth-form-title">¿Olvidaste tu contraseña?</h2>
    <p class="auth-form-description">Ingresa el correo que usas para iniciar sesión y te enviaremos un enlace para restablecerla.</p>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('password.email') }}" class="auth-login-form">
        @csrf
        <div class="auth-field">
            <label for="email">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" aria-describedby="email-errors">
            <div id="email-errors"><x-input-error :messages="$errors->get('email')" class="mt-2" /></div>
        </div>
        <button type="submit" class="auth-submit">Enviar enlace de recuperación</button>
        <a class="auth-recovery" href="{{ route('login') }}">Volver a iniciar sesión</a>
    </form>
</x-guest-layout>
