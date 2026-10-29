@php
    $publicMode = $publicMode ?? false;
    $assistantHasClinic = app()->bound('customer') && app('customer');
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
@endphp
@if ($publicMode || auth()->check())
    @unless (request()->routeIs('customers.telemedicine.room', 'telemedicine.patient.room'))
        <div class="vita-assistant" data-vita-assistant
             data-endpoint="{{ $assistantEndpoint }}"
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
                <div class="vita-assistant__status" data-assistant-status role="status">Modo demostración · No consulta información de pacientes</div>
                <div class="vita-assistant__messages" data-messages role="list" aria-label="Conversación con Viti"></div>
                <form class="vita-assistant__composer">
                    <label class="sr-only" for="vita-assistant-input">Escribe un mensaje</label>
                    <textarea id="vita-assistant-input" rows="1" maxlength="1200" placeholder="Escribe tu mensaje…"></textarea>
                    <div class="vita-assistant__tools">
                        <div class="vita-assistant__tools-left">
                            <button type="button" data-mic aria-label="Dictar mensaje" title="Dictar mensaje"><i class="fa-solid fa-microphone" aria-hidden="true"></i></button>
                            <button type="button" data-voice aria-pressed="false" aria-label="Activar voz de respuesta" title="Activar voz de respuesta"><i class="fa-solid fa-volume-xmark" aria-hidden="true"></i><span class="sr-only">Activar voz</span></button>
                        </div>
                        <button class="vita-assistant__send" type="submit" data-send aria-label="Enviar mensaje"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i><span>Enviar</span></button>
                    </div>
                </form>
                <p class="vita-assistant__privacy">{{ $publicMode ? 'Consulta informacion publica de la clinica. No envies datos medicos personales.' : 'Demostracion segura: no ingreses datos de pacientes.' }}</p>
            </section>

            <button class="vita-assistant__toggle" type="button" data-assistant-toggle aria-expanded="false" aria-controls="vita-assistant-panel" aria-label="Abrir asistente Viti">
                <div class="viti-robot-frame viti-robot-frame--button" aria-hidden="true">@include('components.viti-robot')</div>
            </button>
        </div>

        <style>
            .vita-assistant { --vita-cyan: #00c6ff; --vita-purple: #7b2ff7; position: fixed; z-index: 80; right: 24px; bottom: 24px; font-family: inherit; }
            .vita-assistant__toggle { position: relative; z-index: 3; display: grid; place-items: center; width: 92px; height: 108px; padding: 0; overflow: visible; border: 0; border-radius: 0; color: #fff; background: transparent; box-shadow: none; cursor: grab; touch-action: none; user-select: none; transition: filter .2s ease, transform .2s ease; }
            .vita-assistant__toggle:active { cursor: grabbing; }
            .vita-assistant__toggle:hover { filter: drop-shadow(0 5px 14px rgba(0,198,255,.42)); transform: translateY(-3px); }
            .vita-assistant__toggle:focus { outline: none; box-shadow: none; }
            .vita-assistant__toggle:focus-visible { outline: 2px solid #00c6ff; outline-offset: 3px; border-radius: 18px; }
            .vita-assistant.is-avatar-hidden .vita-assistant__toggle { display: none; }
            .viti-robot-frame { position: relative; display: grid; flex: 0 0 auto; place-items: center; overflow: visible; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(14,28,70,.22)); }
            .viti-robot-art { display: block; width: 100%; height: 100%; overflow: visible; animation: viti-float 2.8s ease-in-out infinite; }
            .vita-assistant.is-selecting .viti-robot-frame--button .viti-robot-art { animation: viti-select .58s cubic-bezier(.2,.8,.3,1.3); }
            .vita-assistant.is-selecting .viti-eye { animation: none; transform: scaleY(.08); transition: transform .12s ease-in; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-robot-art { animation: none; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-head { transform-box: view-box; transform-origin: 90px 105px; animation: viti-head-drag .42s ease-in-out infinite alternate; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-head-accessories { transform-box: view-box; transform-origin: 90px 105px; animation: viti-head-drag .42s ease-in-out infinite alternate; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-torso { transform-box: view-box; transform-origin: 90px 170px; animation: viti-torso-drag .42s ease-in-out infinite alternate; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-wave-arm { animation: none; transform: rotate(-22deg); }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-rest-arm { animation: none; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-mouth { display: none; }
            .vita-assistant.is-dragging .viti-robot-frame--button .viti-mouth-open { opacity: 1; animation: none; }
            .vita-assistant.is-dropping .vita-assistant__toggle { animation: viti-drop .45s cubic-bezier(.2,.8,.3,1.3); }
            .viti-robot-frame--button { width: 100%; height: 100%; overflow: visible; border-radius: 0; }
            .viti-robot-frame--button .viti-robot-art { width: 100%; height: 100%; }
            .viti-robot-frame .viti-wave-arm { transform-box: view-box; transform-origin: 53px 143px; animation: viti-wave 5s ease-in-out infinite; }
            .viti-robot-frame .viti-eye { transform-box: fill-box; transform-origin: center; animation: viti-blink 4.8s ease-in-out infinite; }
            .viti-robot-frame .viti-eye-right { animation-delay: .08s; }
            .viti-robot-frame .viti-mouth-open { opacity: 0; }
            .vita-assistant[data-viti-state="talking"] .viti-robot-frame .viti-mouth { opacity: 0; }
            .vita-assistant[data-viti-state="talking"] .viti-robot-frame .viti-mouth-open { animation: viti-talk .34s ease-in-out infinite alternate; opacity: 1; }
            @keyframes viti-float { 0%,100% { transform: translateY(1px); } 50% { transform: translateY(-3px); } }
            @keyframes viti-select { 0% { transform: scale(1); } 38% { transform: scale(1.12) translateY(-7px) rotate(-4deg); } 70% { transform: scale(.96) translateY(1px) rotate(2deg); } 100% { transform: scale(1); } }
            @keyframes viti-drag { from { transform: rotate(-5deg) scale(.98); } to { transform: rotate(5deg) scale(1.04); } }
            @keyframes viti-head-drag { from { transform: rotate(-9deg) translate(-2px,1px); } to { transform: rotate(8deg) translate(2px,-2px); } }
            @keyframes viti-torso-drag { from { transform: rotate(4deg) translateY(1px); } to { transform: rotate(-4deg) translateY(-2px); } }
            @keyframes viti-drop { 0% { transform: translateY(-8px) scale(1.04); } 55% { transform: translateY(3px) scale(.96); } 78% { transform: translateY(-2px) scale(1.02); } 100% { transform: translateY(0) scale(1); } }
            @keyframes viti-wave { 0%,70%,100% { transform: rotate(0); } 76% { transform: rotate(-42deg); } 82% { transform: rotate(-34deg); } 88% { transform: rotate(-44deg); } 94% { transform: rotate(0); } }
            @keyframes viti-blink { 0%,43%,47%,100% { transform: scaleY(1); } 45% { transform: scaleY(.12); } }
            @keyframes viti-talk { from { ry: 2px; } to { ry: 7px; } }
            .vita-assistant__toggle::before { content: ""; position: absolute; z-index: -1; inset: 58% 18% 5%; border-radius: 50%; background: radial-gradient(ellipse,rgba(0,220,255,.3),transparent 72%); filter: blur(5px); animation: vita-assistant-glow 2.4s ease-in-out infinite; }
            @keyframes vita-assistant-glow { 0%,100% { opacity: .45; transform: scale(.92); } 50% { opacity: 1; transform: scale(1); } }
            .vita-assistant__sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }
            .vita-assistant__panel { position: fixed; z-index: 2; right: 24px; bottom: 108px; display: flex; flex-direction: column; width: min(410px,calc(100vw - 28px)); height: min(610px,calc(100dvh - 140px)); min-height: 400px; overflow: hidden; border: 1px solid rgba(123,47,247,.15); border-radius: 22px; background: #fff; box-shadow: 0 24px 70px rgba(17,24,39,.28); animation: vita-assistant-in .2s ease-out; }
            .vita-assistant__panel[hidden] { display: none; }
            @keyframes vita-assistant-in { from { opacity: 0; transform: translateY(12px) scale(.98); } to { opacity: 1; transform: translateY(0) scale(1); } }
            .vita-assistant__header { display: flex; align-items: center; min-height: 164px; padding: 8px 14px 8px 4px; color: white; background: radial-gradient(circle at 15% 30%,rgba(0,198,255,.32),transparent 34%),linear-gradient(120deg,#172448,#392082); }
            .vita-assistant__avatar { flex: 0 0 146px; width: 146px; height: 158px; }
            .vita-assistant__heading { min-width: 0; flex: 1; }
            .vita-assistant__eyebrow { color: #a7eaff; font-size: 9px; font-weight: 800; letter-spacing: .16em; }
            .vita-assistant__heading h2 { margin: 2px 0 3px; color: #fff; font-size: 19px; font-weight: 800; letter-spacing: .04em; }
            .vita-assistant__heading p { display: flex; align-items: center; gap: 6px; margin: 0; color: #e5e7eb; font-size: 11px; }
            .vita-assistant__online-dot { width: 7px; height: 7px; border-radius: 50%; background: #43e0bd; box-shadow: 0 0 8px #43e0bd; }
            .vita-assistant__header-actions { display: flex; align-self: flex-start; gap: 5px; }
            .vita-assistant__header-actions button { display: grid; width: 30px; height: 30px; place-items: center; border: 0; border-radius: 9px; color: #fff; background: rgba(255,255,255,.12); cursor: pointer; }
            .vita-assistant__header-actions button:hover { background: rgba(255,255,255,.24); }
            .vita-assistant__status { padding: 7px 14px; color: #4b5563; background: #f8fafc; border-bottom: 1px solid #eef2f7; font-size: 10px; }
            .vita-assistant__messages { display: flex; flex: 1; flex-direction: column; gap: 10px; overflow-y: auto; padding: 15px 13px; overscroll-behavior: contain; }
            .vita-message { max-width: 88%; padding: 9px 12px; border-radius: 14px; box-shadow: 0 2px 7px rgba(15,23,42,.06); overflow-wrap: anywhere; }
            .vita-message p { margin: 0; color: #263248; font-size: 13px; line-height: 1.5; }
            .vita-message__label { display: block; margin-bottom: 3px; color: #6b7280; font-size: 10px; font-weight: 700; }
            .vita-message__actions { display: flex; flex-wrap: wrap; gap: 7px; margin-top: 10px; }
            .vita-message__action { display: inline-flex; align-items: center; justify-content: center; min-height: 32px; padding: 6px 10px; border: 1px solid #c9eafa; border-radius: 9px; color: #135d86; background: #effaff; font-size: 11px; font-weight: 700; line-height: 1.2; text-decoration: none; transition: background .18s ease, border-color .18s ease, transform .18s ease; }
            .vita-message__action:hover { border-color: #66cbed; color: #0b4f78; background: #dcf5ff; text-decoration: none; transform: translateY(-1px); }
            .vita-message--assistant { align-self: flex-start; border: 1px solid #e9eaf5; border-bottom-left-radius: 4px; background: #f8f9ff; }
            .vita-message--assistant .vita-message__label { color: #6442b8; }
            .vita-message--user { align-self: flex-end; border-bottom-right-radius: 4px; background: #e8f8ff; }
            .vita-message--typing p { color: #717b8c; font-style: italic; }
            .vita-assistant__composer { padding: 10px 12px 8px; border-top: 1px solid #edf0f5; background: #fff; }
            .vita-assistant__composer textarea { display: block; width: 100%; min-height: 42px; max-height: 100px; resize: vertical; padding: 10px 11px; border: 1px solid #dbe2ed; border-radius: 12px; outline: none; color: #1f2937; font: inherit; font-size: 13px; }
            .vita-assistant__composer textarea:focus { border-color: var(--vita-cyan); box-shadow: 0 0 0 3px rgba(0,198,255,.14); }
            .vita-assistant__tools { display: flex; justify-content: space-between; align-items: center; padding-top: 8px; }
            .vita-assistant__tools-left { display: flex; gap: 5px; }
            .vita-assistant__tools button { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-width: 35px; height: 34px; padding: 0 9px; border: 1px solid #e4e8f0; border-radius: 10px; color: #536078; background: white; cursor: pointer; }
            .vita-assistant__tools button:hover { color: #5828b0; border-color: #c7b4ef; background: #faf8ff; }
            .vita-assistant__tools button:disabled { opacity: .5; cursor: not-allowed; }
            .vita-assistant__tools .vita-assistant__send { border: 0; color: white; background: linear-gradient(105deg,var(--vita-cyan),var(--vita-purple)); font-size: 12px; font-weight: 700; }
            .vita-assistant__tools .vita-assistant__send:hover { filter: brightness(.94); color: white; }
            .vita-assistant__privacy { margin: 0; padding: 0 12px 9px; color: #8490a2; background: #fff; text-align: center; font-size: 9px; }
            @media (max-width: 640px) {
                .vita-assistant { right: 12px; bottom: 14px; }
                .vita-assistant__panel { right: 12px; bottom: 94px; width: calc(100vw - 24px); height: min(620px,calc(100dvh - 112px)); min-height: min(390px,calc(100dvh - 112px)); border-radius: 18px; }
                .vita-assistant__toggle { width: 78px; height: 92px; }
                .vita-assistant__header { min-height: 138px; padding-right: 10px; }
                .vita-assistant__avatar { flex-basis: clamp(104px,32vw,128px); width: clamp(104px,32vw,128px); height: 138px; }
            }
            @media (prefers-reduced-motion: reduce) { .vita-assistant *, .vita-assistant *::before, .vita-assistant *::after { animation-duration: .01ms !important; transition-duration: .01ms !important; } }
        </style>
    @endunless
@endif
