<div class="vita-patient-access" role="group" aria-label="Mi Salud: acceso de pacientes" data-portal-access data-session-url="{{ route('mi-salud.session-status') }}">
    <a class="vita-patient-login" data-portal-guest href="{{ route('mi-salud.login') }}">Iniciar sesión</a>
    <a class="vita-patient-register" data-portal-guest href="{{ route('mi-salud.register') }}">Registrarme</a>
    <a class="vita-patient-register" data-portal-panel href="{{ route('mi-salud.panel') }}" hidden>Ir a mi panel</a>
</div>
@once
<script src="{{ asset('js/vita-patient-access.js').'?v='.filemtime(public_path('js/vita-patient-access.js')) }}" defer></script>
@endonce
