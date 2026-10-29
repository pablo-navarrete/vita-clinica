<x-guest-layout :motivation="$motivation ?? null">
    <h2 class="auth-form-title">Inicia sesión</h2>
    <p class="auth-form-description">Ingresa tus datos para acceder a tu espacio de trabajo.</p>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="auth-login-form">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Contraseña')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        

       <div>
    @if (Route::has('password.request'))
        <a
            class="auth-recovery hover:underline rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            href="{{ route('password.request') }}"
        >
            {{ __('Olvidé mi contraseña') }}
        </a>
    @endif

    <x-primary-button
        style="background-color: {{ themeColor(600) }}"
        class="auth-submit hover:brightness-90 transition text-white font-bold py-2 px-4 rounded"
    >
        {{ __('Ingresar') }}
    </x-primary-button>
</div>
    </form>

</x-guest-layout>
