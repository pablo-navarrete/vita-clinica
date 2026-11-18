<!-- Overlay móvil -->
<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false" class="fixed inset-0 bg-black/50 z-40 md:hidden">
</div>

<!-- Sidebar -->
<aside id="superadmin-sidebar"
    x-data="{
        compact: false,
        preview: false, previewLabel: '', previewHref: '', previewIcon: '', previewTop: 0, previewLeft: 0, previewTimer: null,
        showPreview(event) {
            const link = event.target.closest('nav a[href]');
            if (!this.compact || !window.matchMedia('(min-width: 768px)').matches || !link) return;
            clearTimeout(this.previewTimer);
            const rect = link.getBoundingClientRect();
            this.previewLabel = link.textContent.trim();
            this.previewHref = link.href;
            this.previewIcon = link.querySelector('i')?.className || 'fa-solid fa-arrow-right';
            this.previewTop = Math.max(8, Math.min(rect.top + rect.height / 2 - 28, window.innerHeight - 72));
            this.previewLeft = this.$el.getBoundingClientRect().right + 12;
            this.preview = true;
        },
        hidePreview() { clearTimeout(this.previewTimer); this.previewTimer = setTimeout(() => this.preview = false, 180); },
        keepPreview() { clearTimeout(this.previewTimer); },
        destroy() { clearTimeout(this.previewTimer); },
        init() {
            const key = 'vita-superadmin-sidebar-compact:{{ auth()->id() }}';
            try { this.compact = localStorage.getItem(key) === 'true'; } catch (_) {}
            this.$watch('compact', value => { this.preview = false; try { localStorage.setItem(key, String(value)); } catch (_) {} });
            this.$el.querySelectorAll('nav a[href]').forEach(link => {
                const name = link.textContent.trim();
                link.setAttribute('aria-label', name);
            });
        }
    }"
    :data-compact="compact"
    @mouseover="showPreview($event)" @mouseout="hidePreview()"
    @focusin="showPreview($event)" @focusout="hidePreview()"
    @resize.window="preview = false" @keydown.escape.window="preview = false"
    @click="if (window.matchMedia('(max-width: 767px)').matches && $event.target.closest('a[href]')) sidebarOpen = false"
    class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 shrink-0 flex-col text-white
           overflow-hidden
           transform transition-transform duration-300 ease-in-out
           -translate-x-full md:sticky md:top-0 md:h-screen md:translate-x-0"
    style="background-color: {{ themeColor(800) }}"
    :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

    @php
        $clinicName = 'VITA';
    @endphp
    <div class="flex h-16 shrink-0 items-center border-b px-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
        <a href="{{ route('dashboard.index') }}" @click="sidebarOpen = false" class="flex w-full min-w-0 items-center justify-center gap-2 text-center font-extrabold uppercase leading-tight" style="color: {{ themeColor(800) }}">
            <img src="{{ asset('storage/' . ($logosByLocation['panel']->url ?? 'admin/logos/logo-vita-3.png')) }}" class="h-9 w-9 shrink-0 object-contain" alt="Logo VITA">
            <span class="superadmin-sidebar-name vita-workspace-title min-w-0 text-center" title="{{ $clinicName }}">{{ $clinicName }}</span>
        </a>
    </div>

    <div class="clinic-compact-toolbar">
        <button type="button" @click="compact = !compact" :aria-expanded="!compact" aria-controls="superadmin-sidebar"
            :title="compact ? 'Expandir menú' : 'Compactar menú'" :aria-label="compact ? 'Expandir menú' : 'Compactar menú'"
            class="clinic-compact-toggle">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>

    <nav @scroll="preview = false" aria-label="Administración de VITA" class="superadmin-sidebar-scroll min-h-0 flex-1 space-y-2 overflow-y-auto p-4">
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Administración VITA</h2>
        </div>
        <a href="{{ route('dashboard.index') }}" @click="sidebarOpen = false"
            @if(request()->routeIs('dashboard.index', 'superadmin.dashboard.statistics')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('dashboard.index', 'superadmin.dashboard.statistics') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-chart-line w-5" aria-hidden="true"></i><span>Panel</span>
        </a>
        <a href="{{ route('superadmin.customer') }}" @click="sidebarOpen = false"
            @if(request()->routeIs('superadmin.customer', 'superadmin.customers.*')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.customer', 'superadmin.customers.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-hospital w-5" aria-hidden="true"></i><span>Clientes</span>
        </a>
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}"><h2 class="text-base font-bold">Gestión comercial</h2></div>
        <a href="{{ route('superadmin.directory.index') }}" @click="sidebarOpen = false" class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.directory.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}"><i class="fa-solid fa-building w-5" aria-hidden="true"></i><span>Directorio de clientes</span></a>
        <a href="{{ route('superadmin.billing.conditions.edit') }}" @click="sidebarOpen = false" class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.billing.conditions.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}"><i class="fa-solid fa-file-contract w-5" aria-hidden="true"></i><span>Condiciones y contrato</span></a>
        <a href="{{ route('superadmin.billing.plans.index') }}" @click="sidebarOpen = false"
            @if(request()->routeIs('superadmin.billing.plans.*','superadmin.billing.versions.*')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.billing.plans.*','superadmin.billing.versions.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-layer-group w-5" aria-hidden="true"></i><span>Planes</span>
        </a>
        <a href="{{ route('superadmin.billing.subscriptions.index') }}" @click="sidebarOpen = false"
            @if(request()->routeIs('superadmin.billing.subscriptions.*')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.billing.subscriptions.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-calendar-check w-5" aria-hidden="true"></i><span>Suscripciones</span>
        </a>
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}"><h2 class="text-base font-bold">Administración Web</h2></div>
        @foreach(config('vita_website_sections') as $webSection => $webDefinition)
            <a href="{{ route('superadmin.website.section', ['section' => $webSection]) }}" @click="sidebarOpen = false"
                @if(request()->route('section') === $webSection) aria-current="page" @endif
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->route('section') === $webSection ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid {{ $webDefinition['icon'] }} w-5" aria-hidden="true"></i><span>{{ $webDefinition['title'] }}</span>
            </a>
        @endforeach
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Mi cuenta</h2>
        </div>
        <a href="{{ route('superadmin.profile.index') }}" @click="sidebarOpen = false"
            @if(request()->routeIs('superadmin.profile.*')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('superadmin.profile.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user w-5" aria-hidden="true"></i><span>Perfil</span>
        </a>
    </nav>
    <template x-teleport="body">
        <div id="superadmin-sidebar-preview" x-show="preview && compact" x-cloak x-transition.opacity.duration.150ms
            :style="{ top: previewTop + 'px', left: previewLeft + 'px' }"
            @mouseenter="keepPreview()" @mouseleave="hidePreview()" @focusin="keepPreview()" @focusout="hidePreview()">
            <a :href="previewHref" @click="preview = false" :aria-label="previewLabel">
                <span class="clinic-preview-icon"><i :class="previewIcon" aria-hidden="true"></i></span>
                <span class="clinic-preview-label" x-text="previewLabel"></span>
            </a>
        </div>
    </template>
</aside>

<style>
    #superadmin-sidebar-preview { position: fixed; z-index: 100; width: max-content; min-width: 180px; max-width: min(280px, calc(100vw - 108px)); padding: 6px; border: 1px solid {{ themeColor(100) }}; border-radius: 12px; background: #fff; box-shadow: 0 8px 28px rgb(15 23 42 / .14); }
    #superadmin-sidebar-preview::before { content: ''; position: absolute; top: 22px; left: -5px; width: 9px; height: 9px; background: #fff; border-left: 1px solid {{ themeColor(100) }}; border-bottom: 1px solid {{ themeColor(100) }}; transform: rotate(45deg); }
    #superadmin-sidebar-preview a { position: relative; display: flex; align-items: center; gap: 10px; padding: 8px; color: {{ themeColor(800) }}; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; transition: background-color 150ms ease; }
    #superadmin-sidebar-preview a:hover, #superadmin-sidebar-preview a:focus-visible { background: {{ themeColor(50) }}; outline: 2px solid {{ themeColor(200) }}; outline-offset: -2px; }
    #superadmin-sidebar-preview .clinic-preview-icon { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; background: {{ themeColor(50) }}; border-radius: 8px; color: {{ themeColor(700) }}; }
    #superadmin-sidebar-preview .clinic-preview-label { flex: 1; overflow-wrap: anywhere; }
    @media (max-width: 767px) { #superadmin-sidebar-preview { display: none !important; } }
    @media (prefers-reduced-motion: reduce) { #superadmin-sidebar-preview, #superadmin-sidebar-preview a { transition: none !important; } }
    #superadmin-sidebar { transition: width 180ms ease, transform 180ms ease; }
    #superadmin-sidebar .clinic-compact-initial { display: none; }
    #superadmin-sidebar .clinic-compact-toolbar { display: none; position: absolute; top: 82px; right: -14px; z-index: 60; }
    #superadmin-sidebar .clinic-compact-toggle { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid {{ themeColor(200) }}; border-radius: 50%; color: {{ themeColor(700) }}; background: #fff; box-shadow: 0 2px 6px rgb(15 23 42 / .12); cursor: pointer; transition: background-color 150ms ease, box-shadow 150ms ease; }
    #superadmin-sidebar .clinic-compact-toggle i { font-size: 10px; transition: transform 150ms ease; }
    #superadmin-sidebar .clinic-compact-toggle:hover { background: {{ themeColor(50) }}; box-shadow: 0 3px 9px rgb(15 23 42 / .16); }
    #superadmin-sidebar .clinic-compact-toggle:focus-visible { outline: 2px solid {{ themeColor(500) }}; outline-offset: 3px; }
    #superadmin-sidebar[data-compact="true"] .clinic-compact-toggle i { transform: rotate(180deg); }
    @media (min-width: 768px) {
        #superadmin-sidebar { overflow: visible; }
        #superadmin-sidebar .clinic-compact-toolbar { display: flex; }
        #superadmin-sidebar[data-compact="true"] { width: 80px; }
        #superadmin-sidebar[data-compact="true"] .superadmin-sidebar-name,
        #superadmin-sidebar[data-compact="true"] nav a > span,
        #superadmin-sidebar[data-compact="true"] nav > div { display: none; }
        #superadmin-sidebar[data-compact="true"] .clinic-compact-initial { display: inline; font-size: 22px; }
        #superadmin-sidebar[data-compact="true"] nav { padding: 12px 8px; }
        #superadmin-sidebar[data-compact="true"] nav a { justify-content: center; gap: 0; padding: 12px; }
        #superadmin-sidebar[data-compact="true"] nav a > i { flex-shrink: 0; text-align: center; }
    }
    @media (prefers-reduced-motion: reduce) { #superadmin-sidebar { transition: none; } }
    .superadmin-sidebar-name { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; overflow-wrap: anywhere; }
    .superadmin-sidebar-scroll { scrollbar-width: thin; scrollbar-color: {{ themeColor(600) }} {{ themeColor(800) }}; scrollbar-gutter: stable; }
    .superadmin-sidebar-scroll::-webkit-scrollbar { width: 8px; }
    .superadmin-sidebar-scroll::-webkit-scrollbar-track { background: {{ themeColor(800) }}; }
    .superadmin-sidebar-scroll::-webkit-scrollbar-thumb { background: {{ themeColor(600) }}; border: 2px solid {{ themeColor(800) }}; border-radius: 9999px; }
    .superadmin-sidebar-scroll::-webkit-scrollbar-thumb:hover { background: {{ themeColor(500) }}; }
</style>
