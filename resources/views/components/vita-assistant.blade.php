@php
    $portalMode = $portalMode ?? false;
    $publicMode = $publicMode ?? false;
    $assistantHasClinic = app()->bound('customer') && app('customer');
    $assistantIsSuperadmin = !$portalMode && !$publicMode && !$assistantHasClinic
        && request()->getHost()===config('app.domain') && auth('web')->check()
        && auth('web')->user()->customer_id===null
        && (int)auth('web')->user()->status===1
        && \App\Support\ClinicBranchContext::isSuperAdministrator(auth('web')->user());
    $assistantContext = $portalMode ? 'mi-salud' : ($assistantHasClinic ? ($publicMode ? 'clinic-public' : 'clinic') : ($assistantIsSuperadmin ? 'superadmin' : 'demo'));
    $assistantEndpoint = $assistantHasClinic
        ? url($publicMode ? '/public-assistant/ask' : '/assistant/ask')
        : '';
    $assistantConnectedStatus = $publicMode
        ? 'Informacion publica de la clinica'
        : ($assistantHasClinic ? 'Conectado a la agenda de esta clínica' : 'Modo demostración. No consulta información de pacientes');
    $assistantWelcome = $publicMode
        ? 'Hola, soy Viti. Puedo ayudarte a consultar servicios, datos de contacto y como solicitar una cita.'
        : ($assistantHasClinic ? 'Hola, soy Viti. Puedo revisar las próximas citas, las citas de hoy y la última cita atendida de esta clínica. No consulto fichas clínicas.' : 'Hola, soy Viti, el robotito de VITA. Estoy en modo demostración y no consulto datos clínicos.');
    if (!$publicMode && auth()->check() && $assistantHasClinic) {
        $assistantWelcome = 'Hola, ' . auth()->user()->name . '. Soy Viti, tu asistente en ' . app('customer')->name
            . '. Puedo consultar horarios disponibles y ayudarte a agendar una cita con el RUT del paciente. Dime, por ejemplo: horarios del próximo lunes.';
    }
    $assistantConversationScope = $publicMode
        ? 'public'
        : (auth()->check() ? 'admin-' . auth()->id() . '-clinic-' . ($assistantHasClinic ? app('customer')->id : 'global') : '');
    if ($portalMode) {
        $assistantEndpoint = route('mi-salud.assistant.ask');
        $assistantConnectedStatus = 'Ayuda de Mi Salud';
        $assistantWelcome = 'Hola, soy Viti, tu asistente en Mi Salud. Puedo orientarte sobre tu perfil, Mis atenciones y cómo encontrar un centro para agendar.';
        $assistantConversationScope = '';
    }
    if ($assistantIsSuperadmin) {
        $assistantEndpoint = route('superadmin.assistant.ask');
        $assistantConnectedStatus = 'Conectado al superadmin VITA';
        $assistantWelcome = 'Hola, '.auth('web')->user()->name.'. Soy Viti, tu asistente del superadmin VITA. Puedes preguntarme: ¿Cuántos clientes nuevos hay este mes?';
        $assistantConversationScope = 'superadmin-'.auth('web')->id().'-v1';
    }
@endphp
@if (($portalMode && auth('mi_salud')->check()) || (!$portalMode && ($publicMode || auth()->check())))
    @unless (request()->routeIs('customers.telemedicine.room', 'telemedicine.patient.room'))
        <div class="vita-assistant" data-vita-assistant
             data-endpoint="{{ $assistantEndpoint }}"
             data-assistant-context="{{ $assistantContext }}"
             data-connected-status="{{ $assistantConnectedStatus }}"
             data-welcome="{{ $assistantWelcome }}"
             data-persist-conversation="{{ $assistantConversationScope }}"
             aria-label="Asistente VITA">
            <section id="vita-assistant-panel" class="vita-assistant__panel" data-assistant-panel hidden role="dialog" aria-modal="false" aria-labelledby="vita-assistant-title">
                <header class="vita-assistant__header">
                    <div class="vita-assistant__avatar viti-robot-frame" aria-hidden="true">@include('components.viti-robot')</div>
                    <div class="vita-assistant__heading">
                        <span class="vita-assistant__eyebrow">ASISTENTE VIRTUAL</span>
                        <h2 id="vita-assistant-title">Viti</h2>
                        <p><span class="vita-assistant__online-dot"></span> Aqui para ayudarte</p>
                    </div>
                    <div class="vita-assistant__header-actions">
                        <button type="button" data-minimize aria-label="Minimizar asistente" title="Minimizar"><i class="fa-solid fa-minus" aria-hidden="true"></i></button>
                        <button type="button" data-close aria-label="Cerrar asistente" title="Cerrar"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
                    </div>
                </header>
                <div class="vita-assistant__status" data-assistant-status role="status">{{ $assistantConnectedStatus }}</div>
                <div class="vita-assistant__messages" data-messages role="list" aria-label="Conversación con Viti"></div>
                <form class="vita-assistant__composer">
                    <label class="sr-only" for="vita-assistant-input">Escribe un mensaje</label>
                    <textarea id="vita-assistant-input" rows="1" maxlength="1200" placeholder="Escribe tu mensaje…"></textarea>
                    <div data-assistant-phone-field hidden><label for="vita-assistant-phone">Teléfono del paciente</label><input id="vita-assistant-phone" type="tel" data-assistant-phone disabled autocomplete="tel"><button type="button" data-assistant-phone-cancel>Cancelar registro</button></div>
                    <div class="vita-assistant__tools">
                        <div class="vita-assistant__tools-left">
                            <button type="button" data-mic aria-label="Dictar mensaje" title="Dictar mensaje"><i class="fa-solid fa-microphone" aria-hidden="true"></i></button>
                            <button type="button" data-voice aria-pressed="false" aria-label="Activar voz de respuesta" title="Activar voz de respuesta"><i class="fa-solid fa-volume-xmark" aria-hidden="true"></i><span class="sr-only">Activar voz</span></button>
                        </div>
                        <button class="vita-assistant__send" type="submit" data-send aria-label="Enviar mensaje"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span>Enviar</span></button>
                    </div>
                </form>
                <p class="vita-assistant__privacy">{{ $assistantIsSuperadmin ? 'Resumen de clientes de VITA según tus permisos de superadministración.' : ($portalMode ? 'No envíes documentos ni datos médicos personales.' : ($publicMode ? 'Consulta informacion publica de la clinica. No envies datos medicos personales.' : 'Asistente de esta clínica. No ingreses datos médicos sensibles.')) }}</p>
            </section>

            <button class="vita-assistant__toggle" type="button" data-assistant-toggle aria-expanded="false" aria-controls="vita-assistant-panel" aria-label="Abrir asistente Viti">
                <div class="viti-robot-frame viti-robot-frame--button" aria-hidden="true">@include('components.viti-robot')</div>
            </button>
        </div>

        <link rel="stylesheet" href="{{ asset('css/vita-assistant.css').'?v='.filemtime(public_path('css/vita-assistant.css')) }}">
    @endunless
@endif
