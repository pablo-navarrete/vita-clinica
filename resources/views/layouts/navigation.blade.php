<header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" @click="sidebarOpen = true"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100 md:hidden"
                aria-label="Abrir menú">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>
        <div class="min-w-0">
            <p class="truncate text-sm font-semibold text-gray-800 sm:text-base">Administración de Vita Clínica</p>
            <p class="hidden text-xs text-gray-500 sm:block">Panel de superadministrador</p>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
        <button type="button" data-viti-trash aria-label="Soltar aquí para ocultar el avatar de Viti" title="Soltar aquí para ocultar a Viti"
                class="viti-nav-trash inline-flex h-10 w-10 items-center justify-center rounded-lg text-red-600 transition">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
        </button>
        <button type="button" data-viti-nav-toggle aria-label="Abrir asistente Viti" title="Hablar con Viti"
                class="viti-nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-gray-100">
            <span class="viti-nav-face" aria-hidden="true">@include('components.viti-robot')</span>
        </button>

        <div x-data="{ accountMenuOpen: false }" class="relative ml-1">
        <button type="button" @click="accountMenuOpen = !accountMenuOpen"
                class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm text-gray-700 transition hover:bg-gray-100 sm:px-3"
                aria-label="Abrir menú de cuenta">
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 font-semibold text-indigo-700">
                {{ strtoupper(mb_substr(Auth::user()->name, 0, 1)) }}
            </span>
            <span class="hidden max-w-40 truncate font-medium sm:block">{{ Auth::user()->name }}</span>
            <i class="fa-solid fa-chevron-down hidden text-xs text-gray-400 sm:block"></i>
        </button>

        <div x-cloak x-show="accountMenuOpen" @click.outside="accountMenuOpen = false" x-transition
             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
            <div class="border-b border-gray-100 px-4 py-3">
                <p class="truncate text-sm font-semibold text-gray-800">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
            </div>
            <a href="{{ route('superadmin.profile.index') }}"
               class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                <i class="fa-regular fa-user w-4 text-gray-500"></i>Mi perfil
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50">
                    <i class="fa-solid fa-right-from-bracket w-4"></i>Cerrar sesión
                </button>
            </form>
        </div>
        </div>
    </div>
</header>

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
</style>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const vitiNavToggle = document.querySelector('[data-viti-nav-toggle]');
        const vitiAssistantToggle = document.querySelector('[data-assistant-toggle]');
        const vitiAssistant = document.querySelector('[data-vita-assistant]');
        if (!vitiNavToggle || !vitiAssistantToggle || !vitiAssistant) return;

        vitiNavToggle.addEventListener('click', () => {
            try { sessionStorage.removeItem(`vita-assistant-avatar-hidden:${window.location.hostname}:${vitiAssistant.dataset.persistConversation || 'guest'}`); } catch (_) {}
            vitiAssistant.classList.remove('is-avatar-hidden');
            vitiAssistantToggle.click();
        });
    });
</script>
