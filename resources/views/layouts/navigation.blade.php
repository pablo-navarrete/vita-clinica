<header class="sticky top-0 z-30 flex h-16 shrink-0 items-center justify-between border-b px-4 shadow-sm sm:px-6" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
    <div class="flex min-w-0 items-center gap-3">
        <button type="button" @click="sidebarOpen = true"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-gray-600 transition hover:bg-gray-100 md:hidden"
                aria-label="Abrir menú">
            <i class="fa-solid fa-bars text-lg"></i>
        </button>

        <button type="button" data-viti-trash aria-label="Soltar aquí para ocultar el avatar de Viti" title="Soltar aquí para ocultar a Viti"
                class="viti-nav-trash inline-flex h-10 w-10 items-center justify-center rounded-lg text-red-600 transition">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
        </button>

    </div>

    <div class="flex shrink-0 items-center gap-1 sm:gap-2">
        <button type="button" data-viti-nav-toggle aria-label="Abrir asistente Viti" title="Hablar con Viti"
                style="color: {{ themeColor(600) }}" class="viti-nav-toggle inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-gray-100">
            <span class="viti-nav-face" aria-hidden="true">@include('components.viti-robot')</span>
        </button>
        <a href="{{ route('vita.website') }}" target="_blank" rel="noopener noreferrer" aria-label="Ver web pública de VITA" title="Ver web pública de VITA" class="inline-flex h-10 w-10 items-center justify-center rounded-lg transition hover:bg-white/60" style="color: {{ themeColor(600) }}"><i class="fa-solid fa-globe" aria-hidden="true"></i></a>
        <div x-data="{ accountMenuOpen: false }" class="relative ml-1" @keydown.escape.window="accountMenuOpen = false">
        <button type="button" @click="accountMenuOpen = !accountMenuOpen"
                class="inline-flex h-10 w-10 items-center justify-center rounded-full transition hover:bg-white/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                style="--tw-ring-color: {{ themeColor(500) }}" :aria-expanded="accountMenuOpen" aria-label="Abrir menú de cuenta" title="{{ Auth::user()->name }}">
            <span class="rounded-full flex items-center justify-center font-semibold" style="background-color: {{ themeColor(50) }}; color: {{ themeColor(800) }}; border: 1px solid {{ themeColor(300) }}; width: 34px; height: 34px; font-size: 14px; line-height: 1; box-shadow: 0 1px 3px rgba(15, 23, 42, .08);">
                @php
                    $avatarWords = preg_split('/\s+/u', trim(Auth::user()->name), -1, PREG_SPLIT_NO_EMPTY);
                    $avatarInitials = count($avatarWords) > 1
                        ? mb_substr($avatarWords[0], 0, 1) . mb_substr($avatarWords[1], 0, 1)
                        : mb_substr($avatarWords[0] ?? '', 0, 2);
                @endphp
                {{ mb_strtoupper($avatarInitials) }}
            </span>
        </button>

        <div x-cloak x-show="accountMenuOpen" @click.outside="accountMenuOpen = false" x-transition
             class="absolute right-0 mt-2 w-56 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
            <div class="border-b border-gray-100 px-4 py-3" style="background-color: {{ themeColor(50) }}">
                <p class="truncate text-sm font-semibold text-gray-800">{{ Auth::user()->name }}</p>
                <p class="truncate text-xs text-gray-500">{{ Auth::user()->email }}</p>
            </div>
            <a href="{{ route('superadmin.profile.index') }}"
               class="flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 transition hover:bg-gray-50">
                <i class="fa-regular fa-user w-4 text-gray-500"></i>Mi perfil
            </a>
            <form method="POST" action="{{ route('logout') }}" style="margin: 0; padding: 0;">
                @csrf
                <button type="submit" class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm text-red-600 transition hover:bg-red-50">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 16px; height: 16px; flex-shrink: 0;"><path d="M9 4H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h4M14 8l4 4-4 4M8 12h10"/></svg>Cerrar sesión
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
