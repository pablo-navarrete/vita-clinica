@php
    $currentColor = app('customer')->color ?? 'blue';
    $currentClinicUser = auth()->user();
    $availableClinicBranches = $currentClinicUser
        ? \App\Support\ClinicBranchContext::availableBranches($currentClinicUser, (int) app('customer')->id)
        : collect();
    $activeClinicBranch = (int) session('active_branch_customer_id') === (int) app('customer')->id
        ? $availableClinicBranches->firstWhere('id', (int) session('active_branch_id'))
        : null;
    $activeClinicBranch = $activeClinicBranch ?: $availableClinicBranches->first();
@endphp
<nav class="border-b shadow-sm"
    style="
        background-color: {{ themeColor(100) }};
        border-color: {{ themeColor(200) }};
    ">
    <div class="px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-16">

            <!-- IZQUIERDA -->
            <div class="flex items-center gap-4">

                <!-- Botón Sidebar Mobile -->
                <button @click="open = !open" style="color: {{ themeColor(600) }}"
                    class="md:hidden hover:brightness-75 transition">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>

                @if ($availableClinicBranches->count() > 1 && $activeClinicBranch)
                    <form id="clinic-branch-switcher" method="POST" action="{{ route('customers.branch-context.update', ['customer' => request()->route('customer')]) }}" class="relative mt-1">
                        @csrf
                        <label class="sr-only" for="active-clinic-branch">Sede activa</label>
                        <input id="active-clinic-branch" type="hidden" name="branch_id" value="{{ $activeClinicBranch->id }}">
                        <button id="clinic-branch-toggle" type="button" aria-haspopup="listbox" aria-expanded="false"
                            class="flex h-8 w-44 max-w-[48vw] items-center justify-between gap-2 rounded-lg border border-white/70 bg-white px-2.5 text-left text-xs font-semibold shadow-sm transition hover:bg-white/90 focus:outline-none focus:ring-2 focus:ring-white/70"
                            style="color: {{ themeColor(700) }}">
                            <span class="min-w-0 truncate"><i class="fa-solid fa-location-dot mr-2 opacity-70" aria-hidden="true"></i><span id="clinic-branch-current">{{ $activeClinicBranch->name }}</span></span>
                            <i class="fa-solid fa-chevron-down shrink-0 text-[10px]" aria-hidden="true"></i>
                        </button>
                        <div id="clinic-branch-menu" class="absolute left-0 top-full z-[80] mt-2 hidden w-60 max-w-[80vw] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xl">
                            <div class="border-b border-gray-100 p-2">
                                <label for="clinic-branch-search" class="sr-only">Buscar sede</label>
                                <div class="relative">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-xs text-gray-400" aria-hidden="true"></i>
                                    <input id="clinic-branch-search" type="search" autocomplete="off" placeholder="Buscar sede..." class="w-full rounded-lg border-gray-200 py-2 pl-8 pr-3 text-sm text-gray-700 focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            </div>
                            <ul id="clinic-branch-options" role="listbox" aria-label="Sedes disponibles" class="max-h-64 overflow-y-auto p-1">
                            @foreach ($availableClinicBranches as $branch)
                                <li role="option" aria-selected="{{ (int) $activeClinicBranch->id === (int) $branch->id ? 'true' : 'false' }}">
                                    <button type="button" data-branch-id="{{ $branch->id }}" data-branch-name="{{ $branch->name }}" class="clinic-branch-option flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm text-gray-700 transition hover:bg-gray-50" @if ((int) $activeClinicBranch->id === (int) $branch->id) style="background: {{ themeColor(50) }}; color: {{ themeColor(700) }}" @endif>
                                        <span class="truncate">{{ $branch->name }}</span>
                                        @if ((int) $activeClinicBranch->id === (int) $branch->id)<i class="fa-solid fa-check ml-2 text-xs" aria-hidden="true"></i>@endif
                                    </button>
                                </li>
                            @endforeach
                            </ul>
                            <p id="clinic-branch-empty" class="hidden px-3 py-4 text-center text-sm text-gray-500">No se encontraron sedes</p>
                        </div>
                    </form>
                @elseif ($activeClinicBranch)
                    <span class="rounded-full bg-white/70 px-3 py-1.5 text-xs font-semibold" style="color: {{ themeColor(700) }}">
                        {{ $activeClinicBranch->name }}
                    </span>
                @else
                    <span class="text-xs font-medium" style="color: {{ themeColor(700) }}">Sin sede activa</span>
                @endif

            </div>

            <!-- DERECHA -->
            <div class="flex shrink-0 items-center gap-1 sm:gap-2">
            
<!-- Papelera para ocultar a Viti al arrastrarlo -->
<button type="button" data-viti-trash aria-label="Soltar aquí para ocultar el avatar de Viti" title="Soltar aquí para ocultar a Viti"
    class="viti-nav-trash inline-flex h-10 w-10 items-center justify-center rounded-lg text-red-600 transition">
    <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
</button>

<!-- Acceso a Viti al otro lado de las notificaciones -->
<button type="button" data-viti-nav-toggle
    style="color: {{ themeColor(600) }}"
    class="viti-nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60 hover:brightness-90"
    aria-label="Abrir asistente Viti" title="Hablar con Viti">
    <span class="viti-nav-face" aria-hidden="true">@include('components.viti-robot')</span>
</button>

<!-- notificaciones -->
@php
    $tenantIdForNotifications = (int) app('customer')->id;
    $tenantNotifications = Auth::user()->notifications()
        ->where('data->customer_id', $tenantIdForNotifications)
        ->latest()
        ->take(6)
        ->get();
    $tenantUnreadNotificationCount = Auth::user()->unreadNotifications()
        ->where('data->customer_id', $tenantIdForNotifications)
        ->count();
@endphp
<div x-data="{ open: false }" class="relative">

    <!-- TRIGGER -->
    <button @click="open = !open"
        style="color: {{ themeColor(600) }}"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60 hover:brightness-90">

        <i class="fa-solid fa-bell text-lg"></i>

        @if ($tenantUnreadNotificationCount > 0)
            <span class="absolute -right-0.5 -top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                {{ $tenantUnreadNotificationCount > 99 ? '99+' : $tenantUnreadNotificationCount }}
            </span>
        @endif

    </button>

    <!-- DROPDOWN notificaciones -->
    <div x-show="open"
    x-cloak
        @click.outside="open = false"
        x-transition
        class="absolute right-0 mt-2 w-80 max-w-[90vw] bg-white shadow-lg rounded-lg z-50 overflow-hidden">

        <div class="flex items-center justify-between px-4 py-3 text-sm font-semibold text-gray-700 border-b">
            <span>Notificaciones</span>
            @if ($tenantUnreadNotificationCount > 0)
                <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">{{ $tenantUnreadNotificationCount }} nuevas</span>
            @endif
        </div>
        <div class="max-h-96 overflow-y-auto">
            @forelse ($tenantNotifications as $notification)
                @php
                    $notificationData = $notification->data;
                @endphp
                <div class="border-b border-gray-100 px-4 py-3 {{ $notification->unread() ? 'bg-blue-50' : 'bg-white' }}">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid {{ ($notificationData['event'] ?? '') === 'cancelled' ? 'fa-calendar-xmark' : 'fa-calendar-check' }} mt-1" style="color: {{ themeColor(600) }}"></i>
                        <a href="{{ route('customers.notifications.open', ['customer' => request()->route('customer'), 'notification' => $notification->id]) }}" class="min-w-0 flex-1 hover:underline">
                            <p class="text-sm font-semibold text-gray-800">{{ $notificationData['title'] ?? 'Cita médica' }}</p>
                            <p class="mt-0.5 truncate text-xs text-gray-600">{{ $notificationData['patient_name'] ?? 'Paciente' }} · {{ !empty($notificationData['starts_at']) ? \Illuminate\Support\Carbon::parse($notificationData['starts_at'])->timezone(config('app.timezone'))->format('d/m/Y H:i') : '—' }}</p>
                            <p class="mt-1 text-xs text-gray-500">{{ $notificationData['message'] ?? '' }}</p>
                        </a>
                        @if ($notification->unread())<span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-blue-600" title="No leída"></span>@endif
                    </div>
                </div>
            @empty
                <p class="px-4 py-6 text-center text-sm text-gray-500">No tienes notificaciones de citas.</p>
            @endforelse
        </div>
        <a href="{{ route('customers.notifications.index', ['customer' => request()->route('customer')]) }}"
            class="block border-t px-4 py-3 text-center text-sm font-semibold hover:bg-gray-50" style="color: {{ themeColor(700) }}">
            Mostrar todas
        </a>

    </div>
</div>


<!-- Chat -->
<div x-data="{ open: false }" class="relative">
    <button type="button" @click="open = !open" style="color: {{ themeColor(600) }}"
        class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60 hover:brightness-90" aria-label="Mensajes del chat" title="Chat interno">
        <i class="fa-solid fa-comment text-lg"></i>
        <span id="chat-nav-unread" class="absolute -right-0.5 -top-0.5 hidden min-w-5 h-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white"></span>
    </button>
    <div x-show="open" x-cloak @click.outside="open = false" x-transition
        class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-lg bg-white shadow-xl">
        <div class="flex items-center justify-between border-b px-4 py-3">
            <div><p class="text-sm font-semibold text-gray-800">Mensajes</p><p class="text-xs text-gray-500">Conversaciones recientes</p></div>
            <a href="{{ route('customers.chat.index', ['customer' => request()->route('customer')]) }}" class="text-xs font-semibold hover:underline" style="color: {{ themeColor(700) }}">Ver chat</a>
        </div>
        <div id="chat-nav-recent" class="max-h-96 overflow-y-auto">
            <p class="px-4 py-7 text-center text-sm text-gray-500"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando mensajes...</p>
        </div>
        <a href="{{ route('customers.chat.index', ['customer' => request()->route('customer')]) }}"
            class="block border-t px-4 py-3 text-center text-sm font-semibold hover:bg-gray-50" style="color: {{ themeColor(700) }}">Ir a mensajes</a>
    </div>
</div>

@include('customer.chat.presence')

<style>
    .viti-nav-toggle { overflow: hidden; }
    .viti-nav-trash { display: none; background: #fee2e2; }
    body.viti-avatar-dragging .viti-nav-trash { display: inline-flex; }
    body.viti-avatar-dragging .viti-nav-trash.is-drop-target { color: #fff; background: #dc2626; transform: scale(1.08); }
    .viti-nav-face { position: relative; display: block; width: 26px; height: 26px; overflow: hidden; }
    .viti-nav-face .viti-robot-art { position: absolute; top: -5px; left: -7px; width: 40px; height: 47px; max-width: none; animation: none !important; }
    .viti-nav-face .viti-head-accessories, .viti-nav-face .viti-head { transform-box: view-box; }
    .viti-nav-face .viti-arm, .viti-nav-face .viti-torso { display: none; }
    .viti-nav-face .viti-eye { animation: none; }
    .viti-nav-face .viti-mouth-open { opacity: 0; }
    .viti-nav-face *, .viti-nav-face *::before, .viti-nav-face *::after { animation: none !important; transition: none !important; }
    @media (prefers-reduced-motion: reduce) { .viti-nav-toggle, .viti-nav-face * { transition: none !important; animation: none !important; } }
</style>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const vitiNavToggle = document.querySelector('[data-viti-nav-toggle]');
        const vitiAssistantToggle = document.querySelector('[data-assistant-toggle]');
        const vitiAssistant = document.querySelector('[data-vita-assistant]');
        if (!vitiNavToggle || !vitiAssistantToggle || !vitiAssistant) return;

        vitiNavToggle.addEventListener('click', () => {
            try { sessionStorage.removeItem(`vita-assistant-avatar-hidden:${window.location.hostname}:${vitiAssistant.dataset.persistConversation || 'guest'}`); } catch (_) {}
            if (vitiAssistant.classList.contains('is-avatar-hidden')) {
                vitiAssistant.classList.remove('is-avatar-hidden');
            }
            vitiAssistantToggle.click();
        });
    });
</script>

                <a href="/" style="color: {{ themeColor(600) }}" class="inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60 hover:brightness-90"
                    target="_blank" rel="noopener noreferrer" aria-label="Ver web pública" title="Ver web pública">
                    <i class="fa-solid fa-globe"></i>
                </a>

                @php
                    $isDoctor = auth()->check() && auth()->user()->roles()->where('roles.name', 'Doctor')->exists();
                    $isThemeAdministrator = auth()->check() && auth()->user()->hasClinicRole('Administrador', (int) app('customer')->id);
                @endphp
                @if ($isThemeAdministrator || !$isDoctor)
                <!--color del sistema-->
                <div x-data="{ open: false }" class="relative">

                    <!-- TRIGGER -->
                    <button @click="open = !open" style="color: {{ themeColor(600) }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60 hover:brightness-90" aria-label="Cambiar tema" title="Cambiar tema">
                        <i class="fa-solid fa-brush text-lg"></i>
                    </button>

                    <!-- DROPDOWN -->
                    <div x-show="open" x-cloak @click.outside="open = false" x-transition
                        class="absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-lg z-50 overflow-hidden">

                        <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">
                            Tema del sistema
                        </div>

                        <form method="POST" action="/theme">
                            @csrf

                            <!-- RED -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="red"
                                    {{ $currentColor == 'red' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-red-600"></span>
                                Rojo
                            </label>

                            <!-- BLUE -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="blue"
                                    {{ $currentColor == 'blue' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-blue-600"></span>
                                Azul
                            </label>

                            <!-- GREEN -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="green"
                                    {{ $currentColor == 'green' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-green-600"></span>
                                Verde
                            </label>

                            <!-- purple -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="purple"
                                    {{ $currentColor == 'purple' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-purple-600"></span>
                                Morado
                            </label>
                            <!-- orange -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="orange"
                                    {{ $currentColor == 'orange' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-orange-600"></span>
                                Naranja
                            </label>
                            <!-- yellow -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="yellow"
                                    {{ $currentColor == 'yellow' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-yellow-600"></span>
                                Amarillo
                            </label>
                            <!-- gray -->
                            <label class="flex items-center gap-2 px-4 py-2 cursor-pointer hover:bg-gray-100">
                                <input type="radio" name="theme" value="gray"
                                    {{ $currentColor == 'gray' ? 'checked' : '' }}>
                                <span class="w-3 h-3 rounded-full bg-gray-600"></span>
                                Gris
                            </label>
                            <div class="border-t mt-2 pt-2 px-4 mb-2">
                                <button type="submit" style="background-color: {{ themeColor(600) }}"
                                    class="w-full text-white py-2 rounded hover:brightness-90 transition">
                                    Aplicar
                                </button>
                            </div>

                        </form>

                    </div>
                </div>
                @endif
                <x-dropdown align="right" width="48" content-classes="bg-white overflow-hidden">

                    <x-slot name="trigger">
                        <button 
                            class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-sm font-medium transition hover:bg-white/60 hover:brightness-90" aria-label="Abrir perfil" title="Perfil">
                            <div style="background-color: {{ themeColor(600) }}"
                                class="w-6 h-6 rounded-full text-white flex items-center justify-center font-bold text-xl">
                                {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
                            </div>

                           
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link style="background-color: {{ themeColor(100) }}">
                            <p>{{ Auth::user()->name }}</p>


                        </x-dropdown-link>
                        <hr>
                        <x-dropdown-link href="{{ route('customers.profile.index', ['customer' => request()->route('customer')]) }}">

                            Perfil
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}" class="border-t border-gray-100">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                class="rounded-b-md"
                                onclick="event.preventDefault();
                                         this.closest('form').requestSubmit();">
                                Cerrar Sesión
                            </x-dropdown-link>
                        </form>

                    </x-slot>

                </x-dropdown>

            </div>

        </div>
    </div>
</nav>

<style>
    #vita-chat-dock {
        --chat-viewport-height: 100vh;
        --chat-keyboard-offset: 0px;
        max-width: calc(100vw - 24px);
        bottom: var(--chat-keyboard-offset);
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    @supports (height: 100dvh) { #vita-chat-dock { --chat-viewport-height: 100dvh; } }
    #vita-chat-panel:not(.hidden) {
        display: flex;
        flex-direction: column;
        width: min(360px, calc(100vw - 24px));
        height: min(560px, calc(var(--chat-viewport-height) - 76px - env(safe-area-inset-bottom, 0px)));
        min-height: 0;
        flex-shrink: 0;
    }
    #vita-chat-panel > :not(#vita-chat-messages) { flex-shrink: 0; }
    #vita-chat-panel > div:first-child > div:first-child { flex: 1; }
    #vita-chat-messages { flex: 1; height: auto; min-height: 0; overscroll-behavior: contain; overflow-wrap: anywhere; }
    #vita-chat-form textarea { min-width: 0; width: 0; max-height: min(96px, 20vh); }
    #vita-chat-patient-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    #vita-chat-chips { min-width: 0; max-width: min(480px, calc(100vw - 420px)); }
    @media (max-width: 767px) {
        #vita-chat-dock { right: 12px; left: 12px; flex-direction: column; gap: 4px; }
        #vita-chat-panel:not(.hidden) { width: 100%; }
        #vita-chat-chips { max-width: 100%; width: fit-content; }
        #vita-chat-panel > div:first-child { padding: 8px 10px; }
        #vita-chat-panel > div:first-child > div:last-child { margin-left: 4px; gap: 2px; }
        #vita-chat-panel > div:first-child button,
        #vita-chat-open-full { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 36px; }
        #vita-chat-patient { padding: 8px 12px; }
        #vita-chat-form textarea { font-size: 16px; }
    }
</style>
<div id="vita-chat-dock" class="fixed bottom-0 right-3 z-[60] hidden items-end gap-2 sm:right-6" data-customer="{{ app('customer')->id }}" data-user-id="{{ auth()->id() }}"
     data-summary-url="{{ route('customers.chat.summary', ['customer' => request()->route('customer')]) }}"
     data-messages-url="{{ route('customers.chat.messages', ['customer' => request()->route('customer'), 'conversation' => '__ID__']) }}"
     data-delete-url-template="{{ route('customers.chat.messages.delete', ['customer' => request()->route('customer'), 'conversation' => '__ID__', 'message' => '__MESSAGE__']) }}"
     data-hide-url-template="{{ route('customers.chat.hide', ['customer' => request()->route('customer'), 'conversation' => '__ID__']) }}"
     data-send-url="{{ route('customers.chat.send', ['customer' => request()->route('customer'), 'conversation' => '__ID__']) }}"
     data-reaction-url-template="{{ route('customers.chat.reactions.toggle', ['customer' => request()->route('customer'), 'conversation' => '__ID__', 'message' => '__MESSAGE__']) }}"
     data-forward-url="{{ route('customers.chat.forward', ['customer' => request()->route('customer')]) }}"
     data-user-search-url="{{ route('customers.chat.users.search', ['customer' => request()->route('customer')]) }}"
     data-chat-index-url="{{ route('customers.chat.index', ['customer' => request()->route('customer')]) }}">
    <div id="vita-chat-panel" class="mb-0 hidden w-[min(340px,calc(100vw-24px))] overflow-hidden rounded-t-xl border border-gray-200 bg-white shadow-2xl">
        <div class="flex items-center justify-between px-4 py-3 text-white" style="background: {{ themeColor(700) }}">
            <div class="min-w-0"><p id="vita-chat-title" class="truncate text-sm font-semibold">Chat</p><p id="vita-chat-subtitle" class="truncate text-[11px] text-white/80"></p></div>
            <div class="ml-3 flex shrink-0 items-center gap-3"><button id="vita-chat-hide" type="button" class="text-white/90 hover:text-white" title="Eliminar chat de mi lista"><i class="fa-regular fa-trash-can"></i></button><a id="vita-chat-open-full" class="text-white/90 hover:text-white" title="Abrir mensajes"><i class="fa-solid fa-up-right-from-square"></i></a><button id="vita-chat-minimize" type="button" class="text-white/90 hover:text-white" title="Minimizar"><i class="fa-solid fa-minus"></i></button><button id="vita-chat-close" type="button" class="text-white/90 hover:text-white" title="Cerrar chat"><i class="fa-solid fa-xmark"></i></button></div>
        </div>
        <div id="vita-chat-patient" class="hidden border-b px-4 py-3" style="background: {{ themeColor(50) }}" role="region" aria-label="Paciente asociado a la conversación">
            <div class="flex items-start gap-2">
                <i class="fa-solid fa-thumbtack mt-1 text-xs" style="color: {{ themeColor(600) }}" aria-hidden="true"></i>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase text-gray-500">Paciente de la conversación</p>
                    <p id="vita-chat-patient-name" class="break-words text-sm font-semibold text-gray-800"></p>
                    <p id="vita-chat-patient-rut" class="text-xs text-gray-500"></p>
                    <a id="vita-chat-patient-link" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold hover:underline" style="color: {{ themeColor(700) }}">
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                        <span>Abrir ficha del paciente</span>
                    </a>
                </div>
            </div>
        </div>
        <div id="vita-chat-messages" class="h-80 space-y-2 overflow-y-auto bg-gray-50 p-3"></div>
        <div id="vita-chat-readonly" class="hidden border-t bg-gray-50 px-3 py-2 text-center text-xs text-gray-600">Saliste del grupo; puedes revisar el historial, pero no enviar mensajes.</div>
        <form id="vita-chat-form" class="relative flex items-end gap-2 border-t p-2">
            @csrf
            <input type="hidden" name="reply_to_id" id="vita-chat-reply-to">
            <div id="vita-chat-reply-preview" class="absolute bottom-[3.3rem] left-0 right-0 hidden items-center justify-between border-t bg-white px-3 py-2 text-xs shadow"><span class="min-w-0 truncate">Respondiendo a <strong id="vita-chat-reply-user"></strong>: <span id="vita-chat-reply-body"></span></span><button type="button" id="vita-chat-reply-cancel" class="ml-2 text-gray-500" aria-label="Cancelar respuesta"><i class="fa-solid fa-xmark"></i></button></div>
            <div id="vita-chat-emoji-picker" class="absolute bottom-full left-0 z-30 mb-1 hidden grid max-h-48 w-full grid-cols-8 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-lg sm:grid-cols-10"></div>
            <button type="button" id="vita-chat-emoji-toggle" class="grid h-10 w-9 shrink-0 place-items-center rounded-lg border text-lg hover:bg-gray-50" aria-label="Insertar emoji" title="Insertar emoji">😊</button>
            <textarea name="message" rows="1" maxlength="5000" required class="max-h-24 min-h-10 flex-1 resize-y rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Escribe un mensaje..."></textarea>
            <button type="submit" class="grid h-10 w-10 shrink-0 place-items-center rounded-lg text-white" style="background: {{ themeColor(600) }}" title="Enviar"><i class="fa-solid fa-paper-plane"></i></button>
        </form>
    </div>
    <div id="vita-chat-chips" class="mb-0 flex max-w-[calc(100vw-24px)] items-center gap-2 overflow-x-auto rounded-t-xl border border-b-0 border-gray-200 bg-white p-2 shadow-lg"></div>
</div>
<div id="vita-chat-forward-modal" class="fixed inset-0 z-[100] hidden items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="vita-chat-forward-title">
    <form id="vita-chat-forward-form" data-url="{{ route('customers.chat.forward', ['customer' => request()->route('customer')]) }}" class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white shadow-2xl">
        @csrf
        <input type="hidden" name="message_id" id="vita-chat-forward-message-id">
        <div class="flex items-center justify-between border-b px-5 py-4"><div><h2 id="vita-chat-forward-title" class="text-lg font-semibold text-gray-900">Reenviar mensaje</h2><p class="mt-1 text-sm text-gray-500">Busca personas o elige conversaciones y revisa los destinos antes de enviar.</p></div><button type="button" id="vita-chat-forward-close" class="grid h-9 w-9 place-items-center rounded-lg text-gray-500 hover:bg-gray-100" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button></div>
        <div class="grid gap-4 p-5 md:grid-cols-2">
            <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                <div class="flex border-b bg-gray-50 p-2" role="tablist" aria-label="Tipo de destino"><button type="button" id="vita-forward-mode-people" class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background: {{ themeColor(600) }}">Personas</button><button type="button" id="vita-forward-mode-chats" class="flex-1 rounded-lg px-3 py-2 text-sm font-semibold text-gray-600">Conversaciones</button></div>
                <label id="vita-forward-search-wrap" class="relative block p-2"><i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-gray-400"></i><input id="vita-chat-forward-search" type="search" autocomplete="off" class="w-full rounded-lg border-gray-300 py-2 pl-9 pr-3 text-sm" placeholder="Buscar por nombre o correo"></label>
                <div id="vita-chat-forward-available" class="max-h-72 space-y-2 overflow-y-auto p-2"><p class="px-3 py-5 text-center text-sm text-gray-400">Escribe para buscar personas.</p></div>
            </section>
            <section class="min-w-0 overflow-hidden rounded-xl border border-gray-200">
                <div class="border-b bg-gray-50 px-3 py-3"><p class="text-sm font-semibold text-gray-700">Destinos seleccionados</p><p id="vita-chat-forward-count" class="mt-1 text-xs text-gray-500">Ningún destino seleccionado</p></div>
                <div id="vita-chat-forward-selected" class="flex max-h-72 min-h-[180px] flex-wrap content-start gap-2 overflow-y-auto p-3"><p id="vita-chat-forward-empty" class="self-center text-sm text-gray-400">Selecciona personas o conversaciones para agregarlas aquí.</p></div>
            </section>
        </div>
        <div id="vita-chat-forward-inputs"></div>
        <div class="flex justify-end gap-2 border-t px-5 py-4"><button type="button" id="vita-chat-forward-cancel" class="rounded-lg border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancelar</button><button type="submit" id="vita-chat-forward-submit" disabled class="rounded-lg px-4 py-2 text-sm font-semibold text-white opacity-50 disabled:cursor-not-allowed" style="background: {{ themeColor(600) }}"><i class="fa-solid fa-share mr-1"></i>Reenviar</button></div>
    </form>
</div>
<div id="vita-chat-overflow" class="fixed bottom-14 right-3 z-[70] hidden w-72 max-w-[calc(100vw-24px)] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl sm:right-6">
    <div class="flex items-center justify-between border-b px-4 py-3"><span class="text-sm font-semibold text-gray-800">Otros chats</span><button id="vita-chat-overflow-close" type="button" class="rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-700" aria-label="Cerrar lista"><i class="fa-solid fa-xmark"></i></button></div>
    <div id="vita-chat-overflow-list" class="max-h-72 overflow-y-auto"></div>
</div>

@push('chrome-scripts')
<!-- Twemoji: código MIT y gráficos CC BY 4.0, https://github.com/jdecked/twemoji -->
<script src="https://cdn.jsdelivr.net/npm/@twemoji/api@17.0.3/dist/twemoji.min.js" integrity="sha384-Y5xukbGJwykbHHkTbLJykYLcBPFxrwipTbEh0puxhkz9CZ90raTPGe2Ks4vCxsYU" crossorigin="anonymous" defer></script>
<style>.chat-twemoji{display:inline-block;height:1em;width:1em;object-fit:contain;margin:0 .05em 0 .1em;vertical-align:-.1em}.chat-emoji-option .chat-twemoji,.chat-react-option .chat-twemoji{height:1.35em;width:1.35em;margin:0;vertical-align:middle}</style>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const branchForm = document.getElementById('clinic-branch-switcher');
    const branchToggle = document.getElementById('clinic-branch-toggle');
    const branchMenu = document.getElementById('clinic-branch-menu');
    const branchSearch = document.getElementById('clinic-branch-search');
    const branchOptions = [...document.querySelectorAll('.clinic-branch-option')];
    const branchEmpty = document.getElementById('clinic-branch-empty');
    const closeBranchMenu = () => {
        branchMenu?.classList.add('hidden');
        branchToggle?.setAttribute('aria-expanded', 'false');
    };
    branchToggle?.addEventListener('click', () => {
        const opening = branchMenu.classList.contains('hidden');
        branchMenu.classList.toggle('hidden', !opening);
        branchToggle.setAttribute('aria-expanded', String(opening));
        if (opening) setTimeout(() => branchSearch?.focus(), 0);
    });
    branchSearch?.addEventListener('input', () => {
        const query = branchSearch.value.trim().toLocaleLowerCase('es');
        let matches = 0;
        branchOptions.forEach(option => {
            const match = option.dataset.branchName.toLocaleLowerCase('es').includes(query);
            option.closest('li').classList.toggle('hidden', !match);
            if (match) matches++;
        });
        branchEmpty?.classList.toggle('hidden', matches > 0);
    });
    let changingBranch = false;
    branchOptions.forEach(option => option.addEventListener('click', async () => {
        if (!branchForm || changingBranch) return;
        changingBranch = true;
        branchToggle.disabled = true;
        closeBranchMenu();
        const data = new FormData(branchForm);
        data.set('branch_id', option.dataset.branchId);
        try {
            const response = await fetch(branchForm.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: data,
            });
            const result = await response.json();
            if (!response.ok || !result.redirect) {
                throw new Error(result.errors?.branch_id?.[0] || result.message || 'No se pudo cambiar de sede.');
            }
            // A branch switch refreshes the permission-dependent sidebar and account context.
            window.VitaNavigation.changeContext(result.redirect);
        } catch (error) {
            alert(error.message || 'No se pudo cambiar de sede. Intenta nuevamente.');
            changingBranch = false;
            branchToggle.disabled = false;
        }
    }));
    document.addEventListener('click', event => {
        if (branchForm && !branchForm.contains(event.target)) closeBranchMenu();
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeBranchMenu(); });

    const renderChatEmojis = element => {
        if (!element || !window.twemoji) return element;
        return window.twemoji.parse(element, { base: 'https://cdn.jsdelivr.net/gh/jdecked/twemoji@17.0.3/assets/', folder: 'svg', ext: '.svg', className: 'chat-twemoji' });
    };
    const dock = document.getElementById('vita-chat-dock');
    if (!dock) return;
    const updateChatViewport = () => {
        const viewport = window.visualViewport;
        const height = viewport?.height ?? window.innerHeight;
        const offset = viewport ? Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop) : 0;
        dock.style.setProperty('--chat-viewport-height', `${height}px`);
        dock.style.setProperty('--chat-keyboard-offset', `${offset}px`);
    };
    updateChatViewport();
    window.addEventListener('resize', updateChatViewport);
    window.visualViewport?.addEventListener('resize', updateChatViewport);
    window.visualViewport?.addEventListener('scroll', updateChatViewport);
    const tenant = dock.dataset.customer;
    const storageKey = `vita-chat-open-${tenant}`;
    const summaryUrl = dock.dataset.summaryUrl;
    const endpoint = (template, id) => template.replace('__ID__', encodeURIComponent(id));
    const recent = document.getElementById('chat-nav-recent');
    const badge = document.getElementById('chat-nav-unread');
    const panel = document.getElementById('vita-chat-panel');
    const chips = document.getElementById('vita-chat-chips');
    const overflowPanel = document.getElementById('vita-chat-overflow');
    const overflowList = document.getElementById('vita-chat-overflow-list');
    const messages = document.getElementById('vita-chat-messages');
    const form = document.getElementById('vita-chat-form');
    const readOnlyNotice = document.getElementById('vita-chat-readonly');
    const title = document.getElementById('vita-chat-title');
    const subtitle = document.getElementById('vita-chat-subtitle');
    const fullLink = document.getElementById('vita-chat-open-full');
    const forwardModal = document.getElementById('vita-chat-forward-modal');
    const forwardForm = document.getElementById('vita-chat-forward-form');
    const forwardSearch = document.getElementById('vita-chat-forward-search');
    const forwardAvailable = document.getElementById('vita-chat-forward-available');
    const forwardSelected = document.getElementById('vita-chat-forward-selected');
    const forwardEmpty = document.getElementById('vita-chat-forward-empty');
    const forwardCount = document.getElementById('vita-chat-forward-count');
    const forwardInputs = document.getElementById('vita-chat-forward-inputs');
    const forwardSubmit = document.getElementById('vita-chat-forward-submit');
    const forwardSearchWrap = document.getElementById('vita-forward-search-wrap');
    const forwardPeopleMode = document.getElementById('vita-forward-mode-people');
    const forwardChatsMode = document.getElementById('vita-forward-mode-chats');
    const replyPreview = document.getElementById('vita-chat-reply-preview');
    const dockEmojiPicker = document.getElementById('vita-chat-emoji-picker');
    const dockEmojiList = ['\u{1F600}','\u{1F603}','\u{1F604}','\u{1F601}','\u{1F606}','\u{1F605}','\u{1F602}','\u{1F642}','\u{1F643}','\u{1F609}','\u{1F60A}','\u{1F970}','\u{1F60D}','\u{1F929}','\u{1F618}','\u{1F617}','\u{1F61A}','\u{1F619}','\u{1F60B}','\u{1F61B}','\u{1F61C}','\u{1F92A}','\u{1F61D}','\u{1F911}','\u{1F917}','\u{1F92D}','\u{1FAE2}','\u{1FAE3}','\u{1F92B}','\u{1F914}','\u{1FAE1}','\u{1F910}','\u{1F928}','\u{1F610}','\u{1F611}','\u{1F636}','\u{1FAE5}','\u{1F60F}','\u{1F612}','\u{1F644}','\u{1F62C}','\u{1F925}','\u{1F60C}','\u{1F614}','\u{1F62A}','\u{1F924}','\u{1F634}','\u{1F637}','\u{1F912}','\u{1F915}','\u{1F922}','\u{1F92E}','\u{1F927}','\u{1F975}','\u{1F976}','\u{1F974}','\u{1F635}','\u{1F92F}','\u{1FAE0}','\u{1F60E}','\u{1F978}','\u{1F913}','\u{1F9D0}','\u{1F615}','\u{1FAE4}','\u{1F61F}','\u{1F641}','\u{2639}\u{FE0F}','\u{1F62E}','\u{1F62F}','\u{1F632}','\u{1F633}','\u{1F97A}','\u{1F979}','\u{1F626}','\u{1F627}','\u{1F628}','\u{1F630}','\u{1F625}','\u{1F622}','\u{1F62D}','\u{1F631}','\u{1F616}','\u{1F623}','\u{1F61E}','\u{1F613}','\u{1F629}','\u{1F62B}','\u{1F971}','\u{1F624}','\u{1F621}','\u{1F620}','\u{1F92C}','\u{1F44F}','\u{1F64C}','\u{1F450}','\u{1F932}','\u{1F64F}','\u{1F44D}','\u{1F44E}','\u{1F44A}','\u{270A}','\u{1F91B}','\u{1F91C}','\u{1F91D}','\u{1F44B}','\u{1F91A}','\u{1F590}\u{FE0F}','\u{270B}','\u{1F596}','\u{1F90C}','\u{1F90F}','\u{1F90F}','\u{270C}\u{FE0F}','\u{1F91E}','\u{1FAF0}','\u{1F91F}','\u{1F918}','\u{1F919}','\u{1F4AA}','\u{1F9BE}','\u{270D}\u{FE0F}','\u{1F485}','\u{1FAF6}','\u{2764}\u{FE0F}','\u{1F9E1}','\u{1F49B}','\u{1F49A}','\u{1F499}','\u{1F49C}','\u{1F5A4}','\u{1F90D}','\u{1F90E}','\u{1F494}','\u{1F495}','\u{1F49E}','\u{1F493}','\u{1F497}','\u{1F496}','\u{1F498}','\u{1F49D}','\u{1F49F}','\u{1F48C}','\u{1F4AC}','\u{1F5E8}\u{FE0F}','\u{1F5EF}\u{FE0F}','\u{1F4AD}','\u{1F4A4}','\u{1F440}','\u{1F441}\u{FE0F}','\u{1F9E0}','\u{1F9B7}','\u{1F9B4}','\u{1F440}','\u{1F443}','\u{1FAC0}','\u{1FAC1}','\u{1FA78}','\u{1FA79}','\u{1F9B6}','\u{1F9B5}','\u{1F9B8}','\u{1F9B9}','\u{1F9BA}','\u{1F9BB}','\u{1F9BC}','\u{1F9BD}','\u{1F48A}','\u{1F489}','\u{1F9EC}','\u{1F9A0}','\u{1F9EB}','\u{1F9EA}','\u{1F321}\u{FE0F}','\u{2600}\u{FE0F}','\u{1F324}\u{FE0F}','\u{1F308}','\u{2B50}','\u{1F31F}','\u{2728}','\u{26A1}','\u{1F525}','\u{1F4A7}','\u{1F30A}','\u{1F389}','\u{1F38A}','\u{1F382}','\u{1F381}','\u{1F3C6}','\u{1F947}','\u{2705}','\u{2611}\u{FE0F}','\u{274C}','\u{2753}','\u{2757}','\u{1F4AF}','\u{1F4CC}','\u{1F4CD}','\u{1F4C5}','\u{1F552}','\u{23F0}','\u{1F4DE}','\u{2709}\u{FE0F}','\u{1F3E5}','\u{1FA7B}','\u{1FA7C}','\u{1F9D1}\u{200D}\u{2695}\u{FE0F}','\u{1F436}','\u{1F431}','\u{1F43B}','\u{1F337}','\u{1F339}','\u{1F33B}','\u{1F34E}','\u{1F355}','\u{2615}','\u{1F957}','\u{1F370}','\u{1F697}','\u{2708}\u{FE0F}','\u{1F3E0}','\u{1F6CF}\u{FE0F}','\u{1F4DA}','\u{1F4BB}','\u{1F4F1}','\u{1F512}','\u{1F514}'];
    const recentEmojiStorageKey = `vita-chat-emojis-${Number(tenant)}-${Number(dock.dataset.userId)}`;
    const uniqueDockEmojiList = [...new Set(dockEmojiList)];
    const popularDockEmojis = ['😀','😂','😊','😍','🥰','👍','👏','🙏','❤️','🎉','✅','🤔'];
    const readRecentEmojis = () => { try { return JSON.parse(localStorage.getItem(recentEmojiStorageKey) || '[]').filter(emoji => uniqueDockEmojiList.includes(emoji)).slice(0, 12); } catch (_) { return []; } };
    const emojiButtons = (emojis, reaction = false) => emojis.map(emoji => `<button type="button" class="${reaction ? 'chat-react-option rounded p-1 text-base' : 'rounded-lg text-lg'} grid h-8 w-8 place-items-center hover:bg-gray-100" ${reaction ? 'data-quick-action="react"' : 'data-dock-emoji="' + emoji + '"'} data-emoji="${emoji}" aria-label="${emoji}">${emoji}</button>`).join('');
    const renderEmojiPicker = (picker, reaction = false) => {
        if (!picker) return;
        const recent = readRecentEmojis();
        const featured = recent.length ? recent : popularDockEmojis;
        picker.innerHTML = `<div class="col-span-full border-b border-gray-100 pb-1"><div class="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">${recent.length ? 'Usados recientemente' : 'Más usados'}</div><div class="grid grid-cols-8 gap-1 sm:grid-cols-10">${emojiButtons(featured, reaction)}</div></div><div class="col-span-full"><div class="mb-1 px-1 text-[10px] font-semibold uppercase tracking-wide text-gray-500">Todos los emojis</div><div class="grid grid-cols-8 gap-1 sm:grid-cols-10">${emojiButtons(uniqueDockEmojiList, reaction)}</div></div>`;
        renderChatEmojis(picker);
    };
    const rememberEmoji = emoji => {
        if (!uniqueDockEmojiList.includes(emoji)) return;
        try { localStorage.setItem(recentEmojiStorageKey, JSON.stringify([emoji, ...readRecentEmojis().filter(item => item !== emoji)].slice(0, 12))); } catch (_) {}
        renderEmojiPicker(dockEmojiPicker);
        messages.querySelectorAll('[data-quick-reaction-picker]').forEach(picker => renderEmojiPicker(picker, true));
    };
    if (dockEmojiPicker) renderEmojiPicker(dockEmojiPicker);
    renderChatEmojis(dockEmojiPicker);
    const reactionEmojis = uniqueDockEmojiList;
    let conversations = [];
    let activeId = null;
    let lastMessageId = 0;
    let forwardSourceConversationId = null;
    let forwardMode = 'people';
    let forwardSearchTimer = null;
    let forwardSearchRequest = 0;
    const selectedForwardTargets = new Map();
    const subscribed = new Set();
    let routeConversationRegistered = false;

    const openIds = () => {
        try { return JSON.parse(localStorage.getItem(storageKey) || '[]').map(Number).filter(Number.isFinite).slice(0, 30); }
        catch (_) { return []; }
    };
    const saveIds = ids => localStorage.setItem(storageKey, JSON.stringify([...new Set(ids)].slice(0, 30)));
    const safe = value => String(value ?? '');
    const paintReactionSummary = (container, reactions = []) => {
        container.replaceChildren();
        const canSend = conversations.find(chat => Number(chat.id) === Number(activeId))?.can_send !== false;
        reactions.forEach(reaction => {
            const chip = document.createElement(canSend ? 'button' : 'span');
            if (canSend) { chip.type = 'button'; chip.dataset.quickAction = 'react'; chip.dataset.emoji = reaction.emoji; }
            chip.className = `rounded-full border px-1.5 py-0.5 text-[10px] ${reaction.mine ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 bg-white'}`;
            const emoji = document.createElement('span'); emoji.textContent = reaction.emoji;
            const count = document.createElement('span'); count.textContent = String(reaction.count);
            chip.append(emoji, count);
            if (reaction.mine) { const remove = document.createElement('span'); remove.className = 'ml-0.5 font-bold text-gray-500'; remove.setAttribute('aria-label', 'Quitar reacción'); remove.textContent = '×'; chip.appendChild(remove); }
            container.appendChild(chip);
        });
        renderChatEmojis(container);
    };
    const makeMessage = item => {
        if (messages.querySelector(`[data-chat-message="${item.id}"]`)) return;
        const row = document.createElement('div'); row.dataset.chatMessage = item.id; row.className = `flex ${item.mine ? 'justify-end' : 'justify-start'}`;
        row.dataset.messageUser = safe(item.user);
        row.dataset.messageMine = item.mine ? '1' : '0';
        row.dataset.deleted = item.is_deleted ? '1' : '0';
        const stack = document.createElement('div'); stack.className = 'relative max-w-[92%]';
        const bubble = document.createElement('div'); bubble.className = `max-w-[88%] rounded-xl px-3 py-2 text-sm shadow-sm ${item.mine ? 'rounded-br-sm text-white' : 'rounded-bl-sm bg-white text-gray-800'}`;
        if (item.mine) bubble.style.background = @json(themeColor(600));
        if (!item.mine) { const author = document.createElement('p'); author.className = 'mb-1 text-[10px] font-semibold text-gray-500'; author.textContent = safe(item.user); bubble.appendChild(author); }
        if (!item.is_deleted && item.forwarded_from) { const forwarded = document.createElement('p'); forwarded.className = 'mb-1 text-[11px] italic opacity-75'; const icon = document.createElement('i'); icon.className = 'fa-solid fa-share mr-1'; forwarded.append(icon, document.createTextNode(`Reenviado de ${item.forwarded_from.user || 'Usuario'}`)); bubble.appendChild(forwarded); }
        if (!item.is_deleted && item.reply_to) { const quote = document.createElement('blockquote'); quote.className = 'mb-2 rounded border-l-2 border-current/40 bg-black/5 px-2 py-1 text-[10px]'; const who = document.createElement('strong'); who.className = 'block'; who.textContent = safe(item.reply_to.user); const text = document.createElement('span'); text.className = 'block max-h-8 overflow-hidden'; text.textContent = safe(item.reply_to.body); quote.append(who, text); bubble.appendChild(quote); }
        const body = document.createElement('p'); body.className = `whitespace-pre-wrap break-words ${item.is_deleted ? 'italic opacity-70' : ''}`; body.textContent = safe(item.body);
        const time = document.createElement('p'); time.className = `mt-1 text-right text-[9px] ${item.mine ? 'text-white/70' : 'text-gray-400'}`; time.textContent = safe(item.time);
        bubble.append(body, time); stack.appendChild(bubble);
        const actions = document.createElement('div'); actions.className = `mt-1 flex flex-wrap items-center gap-2 text-[10px] text-gray-500 ${item.mine ? 'justify-end' : 'justify-start'}`;
        const action = (name, icon, label) => { const button = document.createElement('button'); button.type = 'button'; button.dataset.quickAction = name; button.className = 'hover:text-gray-900'; const iconStyle = name === 'toggle-reactions' ? 'fa-regular' : 'fa-solid'; button.innerHTML = `<i class="${iconStyle} ${icon}"></i> ${label}`; actions.appendChild(button); };
        const activeConversation = conversations.find(chat => Number(chat.id) === Number(activeId));
        if (activeConversation?.can_send !== false && !item.is_deleted) {
            action('reply', 'fa-reply', 'Responder'); action('forward', 'fa-share', 'Reenviar'); action('toggle-reactions', 'fa-face-smile', '');
        }
        if (activeConversation?.can_delete && !item.is_deleted) action('delete', 'fa-trash-can', 'Eliminar');
        stack.appendChild(actions);
        if (!item.is_deleted && activeConversation?.can_send !== false) {
            const picker = document.createElement('div'); picker.dataset.quickReactionPicker = '1'; picker.className = 'absolute bottom-full right-0 z-20 hidden max-h-52 w-60 max-w-[85vw] grid grid-cols-6 gap-1 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl';
            renderEmojiPicker(picker, true);
            stack.appendChild(picker);
        }
        if (!item.is_deleted) { const summary = document.createElement('div'); summary.className = `mt-1 flex flex-wrap gap-1 ${item.mine ? 'justify-end' : ''}`; paintReactionSummary(summary, item.reactions || []); stack.appendChild(summary); }
        row.appendChild(stack); messages.appendChild(row); renderChatEmojis(row);
    };
    const initials = name => String(name || '').trim().split(/\s+/).filter(Boolean).slice(0, 2).map(part => part.charAt(0).toLocaleUpperCase()).join('') || '?';
    const renderForwardSelection = () => {
        forwardSelected.replaceChildren(); forwardInputs.replaceChildren();
        selectedForwardTargets.forEach(target => {
            const input = document.createElement('input'); input.type = 'hidden'; input.name = target.kind === 'person' ? 'recipient_ids[]' : 'conversation_ids[]'; input.value = target.id; forwardInputs.appendChild(input);
            const card = document.createElement('button'); card.type = 'button'; card.className = 'flex w-24 flex-col items-center gap-1 rounded-lg p-2 text-center transition hover:bg-gray-50'; card.title = `Quitar ${target.name}`;
            const avatar = document.createElement('span'); avatar.className = 'grid h-12 w-12 place-items-center rounded-full text-sm font-bold shadow-sm'; avatar.style.background = @json(themeColor(100)); avatar.style.color = @json(themeColor(700));
            if (target.kind === 'person') { avatar.textContent = initials(target.name); avatar.dataset.presenceUser = target.id; } else avatar.innerHTML = '<i class="fa-solid fa-comments"></i>';
            const name = document.createElement('span'); name.className = 'w-full truncate text-xs font-medium text-gray-700'; name.textContent = target.name;
            const type = document.createElement('span'); type.className = 'text-[10px] text-gray-400'; type.textContent = target.kind === 'person' ? 'Persona' : 'Conversación';
            card.append(avatar, name, type); card.addEventListener('click', () => { selectedForwardTargets.delete(`${target.kind}:${target.id}`); renderForwardSelection(); }); forwardSelected.appendChild(card);
        });
        if (!selectedForwardTargets.size) forwardSelected.appendChild(forwardEmpty);
        const count = selectedForwardTargets.size;
        forwardCount.textContent = count ? `${count} ${count === 1 ? 'destino seleccionado' : 'destinos seleccionados'}` : 'Ningún destino seleccionado';
        forwardSubmit.disabled = count === 0;
        forwardSubmit.classList.toggle('opacity-50', count === 0);
    };
    const renderForwardPeople = (users, suggestions = false) => {
        forwardAvailable.replaceChildren();
        if (suggestions) {
            const heading = document.createElement('p');
            heading.className = 'px-1 pb-1 text-xs font-semibold uppercase tracking-wide text-gray-500';
            heading.innerHTML = '<i class="fa-solid fa-star mr-1"></i>Personas más usadas';
            forwardAvailable.appendChild(heading);
        }
        users.forEach(user => {
            const key = `person:${user.id}`;
            if (selectedForwardTargets.has(key)) return;
            const option = document.createElement('button'); option.type = 'button'; option.className = 'flex w-full min-w-0 items-center gap-3 rounded-xl border border-gray-200 p-2.5 text-left transition hover:border-gray-300 hover:bg-gray-50';
            const avatar = document.createElement('span'); avatar.className = 'grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold'; avatar.style.background = @json(themeColor(100)); avatar.style.color = @json(themeColor(700)); avatar.textContent = initials(user.name); avatar.dataset.presenceUser = user.id;
            const details = document.createElement('span'); details.className = 'min-w-0 flex-1';
            const name = document.createElement('span'); name.className = 'block truncate text-sm font-medium text-gray-700'; name.textContent = user.name;
            const email = document.createElement('span'); email.className = 'block truncate text-xs text-gray-500'; email.textContent = user.email || 'Usuario de la clínica'; details.append(name, email);
            const add = document.createElement('i'); add.className = 'fa-solid fa-plus shrink-0 text-xs'; add.style.color = @json(themeColor(600)); option.append(avatar, details, add);
            option.addEventListener('click', () => { selectedForwardTargets.set(key, { kind: 'person', id: Number(user.id), name: user.name }); renderForwardSelection(); option.remove(); });
            forwardAvailable.appendChild(option);
        });
        const hasPeople = [...forwardAvailable.querySelectorAll('button')].length > 0;
        if (!hasPeople) { const empty = document.createElement('p'); empty.className = 'px-3 py-5 text-center text-sm text-gray-400'; empty.textContent = suggestions ? 'Aún no hay contactos frecuentes. Busca una persona por nombre o correo.' : 'No se encontraron personas.'; forwardAvailable.appendChild(empty); }
    };
    const renderForwardConversations = () => {
        forwardAvailable.replaceChildren();
        const targets = conversations.filter(item => item.can_send !== false && Number(item.id) !== Number(forwardSourceConversationId));
        targets.forEach(item => {
            const key = `conversation:${item.id}`;
            if (selectedForwardTargets.has(key)) return;
            const option = document.createElement('button'); option.type = 'button'; option.className = 'flex w-full min-w-0 items-center gap-3 rounded-xl border border-gray-200 p-2.5 text-left transition hover:border-gray-300 hover:bg-gray-50';
            const avatar = document.createElement('span'); avatar.className = 'grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm'; avatar.style.background = @json(themeColor(100)); avatar.style.color = @json(themeColor(700)); avatar.innerHTML = `<i class="fa-solid ${item.type === 'group' ? 'fa-user-group' : 'fa-comments'}"></i>`;
            const details = document.createElement('span'); details.className = 'min-w-0 flex-1';
            const name = document.createElement('span'); name.className = 'block truncate text-sm font-medium text-gray-700'; name.textContent = item.title;
            const meta = document.createElement('span'); meta.className = 'block truncate text-xs text-gray-500'; meta.textContent = `${item.type === 'group' ? 'Grupo' : 'Chat'}${item.latest_body ? ` · ${item.latest_body}` : ''}`; details.append(name, meta);
            const add = document.createElement('i'); add.className = 'fa-solid fa-plus shrink-0 text-xs'; add.style.color = @json(themeColor(600)); option.append(avatar, details, add);
            option.addEventListener('click', () => { selectedForwardTargets.set(key, { kind: 'conversation', id: Number(item.id), name: item.title }); renderForwardSelection(); option.remove(); });
            forwardAvailable.appendChild(option);
        });
        if (!forwardAvailable.children.length) { const empty = document.createElement('p'); empty.className = 'px-3 py-5 text-center text-sm text-gray-400'; empty.textContent = 'No hay otras conversaciones disponibles.'; forwardAvailable.appendChild(empty); }
    };
    const loadForwardPeople = async term => {
        const requestId = ++forwardSearchRequest;
        if (term && term.length < 2) {
            forwardAvailable.innerHTML = '<p class="px-3 py-5 text-center text-sm text-gray-400">Escribe al menos 2 caracteres.</p>';
            return;
        }
        forwardAvailable.innerHTML = '<p class="px-3 py-5 text-center text-sm text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Cargando personas...</p>';
        try {
            const response = await fetch(`${dock.dataset.userSearchUrl}?q=${encodeURIComponent(term)}`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error();
            const result = await response.json();
            if (requestId !== forwardSearchRequest || forwardMode !== 'people' || forwardSearch.value.trim() !== term) return;
            renderForwardPeople(result.data || [], result.suggestions === true);
        } catch (_) {
            if (requestId === forwardSearchRequest) forwardAvailable.innerHTML = '<p class="px-3 py-5 text-center text-sm text-red-500">No se pudieron cargar las personas. Intenta nuevamente.</p>';
        }
    };
    const setForwardMode = mode => {
        forwardMode = mode;
        forwardSearchRequest++;
        const people = mode === 'people';
        forwardPeopleMode.classList.toggle('text-white', people); forwardPeopleMode.classList.toggle('text-gray-600', !people); forwardPeopleMode.style.background = people ? @json(themeColor(600)) : 'transparent';
        forwardChatsMode.classList.toggle('text-white', !people); forwardChatsMode.classList.toggle('text-gray-600', people); forwardChatsMode.style.background = !people ? @json(themeColor(600)) : 'transparent';
        forwardSearchWrap.classList.toggle('hidden', !people);
        if (people) { forwardSearch.value = ''; loadForwardPeople(''); }
        else renderForwardConversations();
    };
    const openForward = async (messageId, sourceConversationId) => {
        if (!conversations.length) await refresh();
        document.getElementById('vita-chat-forward-message-id').value = messageId;
        forwardSourceConversationId = Number(sourceConversationId);
        selectedForwardTargets.clear(); renderForwardSelection(); setForwardMode('people');
        forwardModal.classList.remove('hidden'); forwardModal.classList.add('flex');
    };
    forwardPeopleMode.addEventListener('click', () => setForwardMode('people'));
    forwardChatsMode.addEventListener('click', () => setForwardMode('conversations'));
    forwardSearch.addEventListener('input', () => {
        clearTimeout(forwardSearchTimer); const term = forwardSearch.value.trim();
        forwardSearchTimer = setTimeout(() => loadForwardPeople(term), 250);
    });
    const paintMessages = items => {
        items.forEach(makeMessage);
        if (items.length) { lastMessageId = Math.max(lastMessageId, ...items.map(item => Number(item.id) || 0)); messages.scrollTop = messages.scrollHeight; }
    };
    window.addEventListener('vita-chat-message-deleted', event => {
        if (Number(activeId) !== Number(event.detail?.conversationId)) return;
        const row = messages.querySelector(`[data-chat-message="${Number(event.detail.messageId)}"]`);
        if (!row) return;
        if (event.detail.scope === 'me') row.remove();
        else {
            const body = row.querySelector('.whitespace-pre-wrap');
            if (body) { body.textContent = 'Este mensaje fue eliminado.'; body.classList.add('italic', 'opacity-70'); }
            row.querySelectorAll('[data-quick-action], [data-quick-reaction-picker]').forEach(element => element.remove());
        }
    });
    messages.addEventListener('click', async event => {
        const button = event.target.closest('[data-quick-action]');
        if (!button) return;
        const row = button.closest('[data-chat-message]');
        if (!row) return;
        const messageId = row.dataset.chatMessage;
        const action = button.dataset.quickAction;
        const activeConversation = conversations.find(chat => Number(chat.id) === Number(activeId));
        if (action === 'delete') {
            const mine = row.dataset.messageMine === '1';
            const choice = await Swal.fire({ icon: 'warning', title: 'Eliminar mensaje', text: mine ? 'Puedes quitarlo de tu vista o eliminarlo para todos.' : 'Este mensaje solo se quitará de tu vista.', showCancelButton: true, showDenyButton: mine, confirmButtonText: 'Eliminar para mí', denyButtonText: 'Eliminar para todos', cancelButtonText: 'Cancelar', confirmButtonColor: @json(themeColor(600)), denyButtonColor: '#dc2626' });
            if (choice.isDismissed) return;
            try {
                const url = dock.dataset.deleteUrlTemplate.replace('__ID__', encodeURIComponent(activeId)).replace('__MESSAGE__', encodeURIComponent(messageId));
                const response = await fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ scope: choice.isDenied ? 'everyone' : 'me' }) });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'No se pudo eliminar el mensaje.');
                if (choice.isDenied) { const body = row.querySelector('.whitespace-pre-wrap'); if (body) { body.textContent = 'Este mensaje fue eliminado.'; body.classList.add('italic', 'opacity-70'); } row.querySelectorAll('[data-quick-action], [data-quick-reaction-picker]').forEach(element => element.remove()); }
                else row.remove();
                window.dispatchEvent(new CustomEvent('vita-chat-message-deleted', { detail: { conversationId: Number(activeId), messageId: Number(messageId), scope: choice.isDenied ? 'everyone' : 'me' } }));
            } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: error.message }); }
            return;
        }
        if (action === 'react' && activeConversation?.can_send === false) return;
        if (action === 'toggle-reactions' && activeConversation?.can_send === false) return;
        if (action === 'reply') {
            const item = conversations.find(chat => Number(chat.id) === Number(activeId));
            const bubble = row.querySelector('.whitespace-pre-wrap');
            document.getElementById('vita-chat-reply-to').value = messageId;
            document.getElementById('vita-chat-reply-user').textContent = row.dataset.messageUser || (item?.title ?? 'mensaje');
            document.getElementById('vita-chat-reply-body').textContent = bubble?.textContent || '';
            replyPreview.classList.remove('hidden'); replyPreview.classList.add('flex');
            form.querySelector('textarea[name="message"]').focus();
        } else if (action === 'forward') {
            await openForward(messageId, activeId);
        } else if (action === 'toggle-reactions') {
            const picker = row.querySelector('[data-quick-reaction-picker]');
            if (picker) {
                const opening = picker.classList.contains('hidden');
                messages.querySelectorAll('[data-quick-reaction-picker]:not(.hidden)').forEach(openPicker => openPicker.classList.add('hidden'));
                if (opening) {
                    picker.classList.remove('hidden', 'top-full', 'mt-2');
                    picker.classList.add('bottom-full', 'mb-2');
                    const listRect = messages.getBoundingClientRect();
                    const rowRect = row.getBoundingClientRect();
                    const pickerHeight = picker.offsetHeight;
                    const roomAbove = rowRect.top - listRect.top;
                    const roomBelow = listRect.bottom - rowRect.bottom;
                    if (roomAbove < pickerHeight + 8 && roomBelow > roomAbove) {
                        picker.classList.remove('bottom-full', 'mb-2');
                        picker.classList.add('top-full', 'mt-2');
                    }
                }
            }
        } else if (action === 'react') {
            rememberEmoji(button.dataset.emoji);
            try {
                const url = dock.dataset.reactionUrlTemplate.replace('__ID__', encodeURIComponent(activeId)).replace('__MESSAGE__', encodeURIComponent(messageId));
                const response = await fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ emoji: button.dataset.emoji }) });
                const result = await response.json();
                if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo guardar la reacción.');
                paintReactionSummary(row.querySelector(':scope > div > div:last-child'), result.reactions);
                row.querySelector('[data-quick-reaction-picker]')?.classList.add('hidden');
            } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo reaccionar', text: error.message }); }
        }
    });
    document.getElementById('vita-chat-reply-cancel').addEventListener('click', () => {
        document.getElementById('vita-chat-reply-to').value = '';
        replyPreview.classList.add('hidden'); replyPreview.classList.remove('flex');
    });
    const closeForward = () => { forwardModal.classList.add('hidden'); forwardModal.classList.remove('flex'); };
    document.getElementById('vita-chat-forward-close').addEventListener('click', closeForward);
    document.getElementById('vita-chat-forward-cancel').addEventListener('click', closeForward);
    forwardModal.addEventListener('click', event => { if (event.target === forwardModal) closeForward(); });
    window.addEventListener('vita-chat-forward-open', event => openForward(event.detail.messageId, event.detail.conversationId));
    document.addEventListener('pointerdown', event => {
        if (panel.classList.contains('hidden') || dock.contains(event.target) || forwardModal.contains(event.target)) return;
        panel.classList.add('hidden');
        panel.classList.remove('block');
    });
    forwardForm.addEventListener('submit', async event => {
        event.preventDefault();
        const button = forwardForm.querySelector('button[type="submit"]'); button.disabled = true;
        try {
            const response = await fetch(dock.dataset.forwardUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json' }, body: new FormData(forwardForm) });
            const result = await response.json();
            if (!response.ok) throw new Error(Object.values(result.errors || {}).flat()[0] || result.message || 'No se pudo reenviar el mensaje.');
            closeForward(); Swal.fire({ icon: 'success', title: 'Mensaje reenviado', timer: 1200, showConfirmButton: false }); refresh();
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo reenviar', text: error.message }); }
        finally { button.disabled = false; }
    });
    const updatePinnedPatient = item => {
        const patientPanel = document.getElementById('vita-chat-patient');
        patientPanel.classList.toggle('hidden', !item?.patient);
        document.getElementById('vita-chat-patient-name').textContent = item?.patient || '';
        const rut = document.getElementById('vita-chat-patient-rut');
        rut.textContent = item?.patient_rut ? `RUT: ${item.patient_rut}` : '';
        rut.classList.toggle('hidden', !item?.patient_rut);
        const patientLink = document.getElementById('vita-chat-patient-link');
        patientLink.classList.toggle('hidden', !item?.patient_url);
        if (item?.patient_url) patientLink.href = item.patient_url;
        else patientLink.removeAttribute('href');
    };
    const openChat = async id => {
        const item = conversations.find(chat => Number(chat.id) === Number(id));
        if (!item) return;
        const numericId = Number(id);
        saveIds([numericId, ...openIds().filter(value => value !== numericId)]);
        overflowPanel.classList.add('hidden');
        activeId = numericId; lastMessageId = 0;
        updatePinnedPatient(item);
        title.textContent = item.title; subtitle.textContent = item.type === 'group' ? 'Conversación grupal' : (item.patient ? `Conversación sobre ${item.patient}` : 'Chat privado del equipo');
        document.getElementById('vita-chat-hide').classList.toggle('hidden', !item.can_delete);
        form.classList.toggle('hidden', item.can_send === false);
        readOnlyNotice.classList.toggle('hidden', item.can_send !== false);
        fullLink.href = `${dock.dataset.chatIndexUrl}?conversation=${encodeURIComponent(item.id)}`;
        messages.replaceChildren(); panel.classList.remove('hidden'); panel.classList.add('block');
        renderChips();
        try {
            const response = await fetch(`${endpoint(dock.dataset.messagesUrl, id)}?after=0`, {headers:{'Accept':'application/json'}});
            if (response.ok) paintMessages((await response.json()).data || []);
        } catch (_) {}
    };
    const renderChips = () => {
        const ids = openIds().filter(id => conversations.some(chat => Number(chat.id) === id));
        dock.classList.toggle('hidden', ids.length === 0);
        dock.classList.toggle('flex', ids.length > 0);
        chips.replaceChildren();
        const addChip = id => {
            const item = conversations.find(chat => Number(chat.id) === id);
            if (!item) return;
            const wrap = document.createElement('div'); wrap.className = 'flex max-w-48 shrink-0 items-center overflow-hidden rounded-lg border';
            const select = document.createElement('button'); select.type = 'button'; select.className = `truncate px-3 py-2 text-xs font-semibold ${activeId === id ? 'text-white' : 'text-gray-700 hover:bg-gray-50'}`; select.style.background = activeId === id ? @json(themeColor(600)) : 'white'; select.textContent = item.title; select.addEventListener('click', () => {
                if (Number(activeId) !== Number(id)) { openChat(id); return; }
                const opening = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !opening);
                panel.classList.toggle('block', opening);
            });
            const close = document.createElement('button'); close.type = 'button'; close.className = 'border-l px-2 text-gray-400 hover:bg-gray-100 hover:text-gray-700'; close.innerHTML = '<i class="fa-solid fa-xmark"></i>'; close.setAttribute('aria-label','Cerrar conversación'); close.addEventListener('click', () => { saveIds(openIds().filter(value => value !== id)); if (activeId === id) { activeId = null; panel.classList.add('hidden'); } renderChips(); });
            wrap.append(select, close); chips.appendChild(wrap);
        };
        ids.slice(0, 3).forEach(addChip);
        const hiddenIds = ids.slice(3);
        overflowList.replaceChildren();
        hiddenIds.forEach(id => {
            const item = conversations.find(chat => Number(chat.id) === id);
            if (!item) return;
            const select = document.createElement('button');
            select.type = 'button';
            select.className = 'block w-full border-b px-4 py-3 text-left transition hover:bg-gray-50';
            const name = document.createElement('span'); name.className = 'block truncate text-sm font-semibold text-gray-800'; name.textContent = item.title;
            const preview = document.createElement('span'); preview.className = 'mt-1 block truncate text-xs text-gray-500'; preview.textContent = item.latest_body || 'Sin mensajes';
            select.append(name, preview); select.addEventListener('click', () => openChat(id)); overflowList.appendChild(select);
        });
        if (hiddenIds.length) {
            const more = document.createElement('button');
            more.type = 'button';
            more.className = 'shrink-0 rounded-lg px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100';
            more.textContent = `+${hiddenIds.length} más`;
            more.setAttribute('aria-expanded', String(!overflowPanel.classList.contains('hidden')));
            more.addEventListener('click', () => overflowPanel.classList.toggle('hidden'));
            chips.appendChild(more);
        } else {
            overflowPanel.classList.add('hidden');
        }
    };
    const renderRecent = () => {
        recent.replaceChildren();
        if (!conversations.length) { const empty = document.createElement('p'); empty.className='px-4 py-7 text-center text-sm text-gray-500'; empty.textContent='No tienes mensajes todavía.'; recent.appendChild(empty); return; }
        conversations.slice(0, 8).forEach(item => {
            const row = document.createElement('div'); row.className = `flex items-center gap-2 border-b px-3 py-3 ${item.unread ? 'bg-blue-50' : 'bg-white'}`;
            const link = document.createElement('a'); link.className='min-w-0 flex-1 hover:underline'; link.href=`${dock.dataset.chatIndexUrl}?conversation=${encodeURIComponent(item.id)}`;
            const name=document.createElement('p'); name.className='truncate text-sm font-semibold text-gray-800'; name.textContent=item.title;
            const preview=document.createElement('p'); preview.className='truncate text-xs text-gray-500'; preview.textContent=`${item.latest_user ? item.latest_user + ': ' : ''}${item.latest_body || 'Sin mensajes'}`;
            const time=document.createElement('p'); time.className='mt-1 text-[10px] text-gray-400'; time.textContent=item.latest_at || '';
            link.append(name, preview, time);
            const actions=document.createElement('div'); actions.className='flex shrink-0 flex-col items-center gap-1';
            if(item.unread){const count=document.createElement('span');count.className='grid h-5 min-w-5 place-items-center rounded-full bg-blue-600 px-1 text-[10px] font-bold text-white';count.textContent=item.unread>99?'99+':item.unread;actions.appendChild(count);}
            const quick=document.createElement('button'); quick.type='button'; quick.className='rounded-md px-2 py-1 text-[10px] font-semibold hover:bg-gray-100'; quick.style.color=@json(themeColor(700)); quick.textContent='Chat rápido'; quick.addEventListener('click',()=>{saveIds([...openIds(),Number(item.id)]); openChat(item.id);}); actions.appendChild(quick);
            row.append(link,actions); recent.appendChild(row);
        });
    };
    const refresh = async () => {
        try {
            const response=await fetch(summaryUrl,{headers:{'Accept':'application/json'}}); if(!response.ok)return;
            const result=await response.json(); conversations=result.data||[];
            updatePinnedPatient(conversations.find(item => Number(item.id) === Number(activeId)));
            const echo = window.Echo;
            if (echo) conversations.forEach(item => {
                const channelName = `chat.${tenant}.${item.id}`;
                if (subscribed.has(channelName)) return;
                subscribed.add(channelName);
                echo.private(channelName).listen('.chat.message.sent', payload => {
                    const incoming = {
                        id: payload.id,
                        body: payload.body,
                        user: payload.user,
                        mine: Number(payload.user_id) === Number(dock.dataset.userId),
                        time: payload.time,
                        reply_to: payload.reply_to,
                        forwarded_from: payload.forwarded_from,
                    };
                    if (Number(activeId) === Number(payload.conversation_id)) paintMessages([incoming]);
                    refresh();
                }).listen('.chat.message.reaction', payload => {
                    if (Number(activeId) !== Number(payload.conversation_id)) return;
                    const row = messages.querySelector(`[data-chat-message="${Number(payload.message_id)}"]`);
                    const summary = row?.querySelector(':scope > div > div:last-child');
                    if (summary) paintReactionSummary(summary, payload.reactions || []);
                }).listen('.chat.message.deleted', payload => {
                    if (Number(activeId) !== Number(payload.conversation_id)) return;
                    const row = messages.querySelector(`[data-chat-message="${Number(payload.message_id)}"]`);
                    const body = row?.querySelector('.whitespace-pre-wrap');
                    if (body) { body.textContent = 'Este mensaje fue eliminado.'; body.classList.add('italic', 'opacity-70'); }
                    row?.querySelectorAll('[data-quick-action], [data-quick-reaction-picker]').forEach(element => element.remove());
                    refresh();
                });
            });
            const routeConversation = Number(new URLSearchParams(window.location.search).get('conversation'));
            if(!routeConversationRegistered && routeConversation && conversations.some(item=>Number(item.id)===routeConversation)){
                saveIds([...openIds(),routeConversation]); routeConversationRegistered=true;
            }
            if(result.unread>0){badge.textContent=result.unread>99?'99+':result.unread;badge.classList.remove('hidden');badge.classList.add('flex');}else{badge.classList.add('hidden');}
            renderRecent(); renderChips();
            if(activeId && !conversations.some(item=>Number(item.id)===activeId)){activeId=null;panel.classList.add('hidden');}
        } catch (_) {}
    };
    document.getElementById('vita-chat-minimize')?.addEventListener('click',()=>panel.classList.add('hidden'));
    document.getElementById('vita-chat-hide')?.addEventListener('click', async () => {
        const item = conversations.find(chat => Number(chat.id) === Number(activeId));
        if (!item || !item.can_delete) return;
        const isGroup = item.type === 'group';
        const choice = await Swal.fire({ icon: 'warning', title: 'Eliminar chat', text: isGroup ? 'Puedes quitar el chat de tu lista y seguir en el grupo, o salir del grupo y quitarlo.' : 'El chat se quitará solo de tu lista.', showCancelButton: true, showDenyButton: isGroup, confirmButtonText: 'Eliminar para mí', denyButtonText: 'Salir y eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: @json(themeColor(600)), denyButtonColor: '#dc2626' });
        if (choice.isDismissed) return;
        try {
            const response = await fetch(dock.dataset.hideUrlTemplate.replace('__ID__', encodeURIComponent(activeId)), { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content, 'Accept': 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ leave_group: choice.isDenied }) });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message || 'No se pudo eliminar el chat.');
            saveIds(openIds().filter(id => Number(id) !== Number(activeId))); activeId = null; panel.classList.add('hidden');
            if (new URL(window.VitaNavigation.currentUrl()).pathname.includes('/chat')) window.VitaNavigation.navigate(result.redirect); else await refresh();
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar el chat', text: error.message }); }
    });
    document.getElementById('vita-chat-emoji-toggle')?.addEventListener('click', () => dockEmojiPicker?.classList.toggle('hidden'));
    document.addEventListener('click', event => {
        const toggle = document.getElementById('vita-chat-emoji-toggle');
        if (!dockEmojiPicker || dockEmojiPicker.classList.contains('hidden')) return;
        if (dockEmojiPicker.contains(event.target) || toggle?.contains(event.target)) return;
        dockEmojiPicker.classList.add('hidden');
    });
    document.addEventListener('click', event => {
        const clickedToggle = event.target.closest('[data-quick-action="toggle-reactions"]');
        const clickedPicker = event.target.closest('[data-quick-reaction-picker]');
        messages.querySelectorAll('[data-quick-reaction-picker]:not(.hidden)').forEach(picker => {
            if (picker === clickedPicker || (clickedToggle && picker === clickedToggle.closest('[data-chat-message]')?.querySelector('[data-quick-reaction-picker]'))) return;
            picker.classList.add('hidden');
        });
    });
    dockEmojiPicker?.addEventListener('click', event => {
        const button = event.target.closest('[data-dock-emoji]');
        if (!button) return;
        rememberEmoji(button.dataset.dockEmoji);
        const field = form?.querySelector('textarea[name="message"]');
        if (!field) return;
        field.setRangeText(button.dataset.dockEmoji, field.selectionStart, field.selectionEnd, 'end');
        field.focus();
    });
    document.getElementById('vita-chat-overflow-close')?.addEventListener('click',()=>overflowPanel.classList.add('hidden'));
    document.getElementById('vita-chat-close')?.addEventListener('click',()=>{saveIds(openIds().filter(id=>id!==activeId));activeId=null;panel.classList.add('hidden');renderChips();});
    form?.addEventListener('submit',async event=>{
        event.preventDefault(); if(!activeId)return;
        const field=form.querySelector('textarea'); const button=form.querySelector('button'); if(!field.value.trim())return;
        button.disabled=true;
        try { const response=await fetch(endpoint(dock.dataset.sendUrl,activeId),{method:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]')?.content,'Accept':'application/json'},body:new FormData(form)}); const data=await response.json(); if(!response.ok)throw new Error(Object.values(data.errors||{}).flat()[0]||'No se pudo enviar.'); paintMessages([data.data]);field.value='';document.getElementById('vita-chat-reply-to').value='';replyPreview.classList.add('hidden');replyPreview.classList.remove('flex');refresh(); }
        catch(error){Swal.fire({icon:'error',title:'Mensaje no enviado',text:error.message});} finally{button.disabled=false;field.focus();}
    });
    form?.addEventListener('keydown', event => {
        const messageInput = event.target.closest('textarea[name="message"]');
        if (!messageInput || event.key !== 'Enter' || event.shiftKey || event.isComposing) return;
        event.preventDefault();
        if (messageInput.value.trim()) form.requestSubmit();
    });
    refresh();
    setInterval(async()=>{
        if(window.Echo?.connector?.pusher?.connection?.state==='connected')return;
        await refresh();
        if(!activeId||panel.classList.contains('hidden')||document.hidden)return;
        try{const response=await fetch(`${endpoint(dock.dataset.messagesUrl,activeId)}?after=${lastMessageId}`,{headers:{'Accept':'application/json'}});if(response.ok)paintMessages((await response.json()).data||[]);}catch(_){}
    },10000);
    window.addEventListener('focus', refresh);
});
</script>
@endpush
