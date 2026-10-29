<div x-cloak x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-slate-950/50 md:hidden"></div>

<aside x-cloak :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 flex w-64 shrink-0 flex-col overflow-y-auto bg-slate-900 text-white transition-transform duration-300 ease-in-out md:sticky md:top-0 md:h-screen">
    <div class="flex h-16 items-center justify-between border-b border-white/10 px-5">
        <a href="{{ route('dashboard.index') }}" class="flex items-center gap-3" aria-label="Panel de Vita Clínica">
            <img src="{{ asset('storage/admin/logos/logo-vita-3.png') }}" alt="" class="h-9 w-9 rounded-lg bg-white object-contain p-1">
            <span class="text-sm font-bold tracking-wide">VITA CLÍNICA</span>
        </a>
        <button type="button" @click="sidebarOpen = false" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 md:hidden" aria-label="Cerrar menú">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <nav class="flex-1 space-y-6 px-3 py-5">
        <section>
            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Plataforma</p>
            <a href="{{ route('dashboard.index') }}"
               @click="sidebarOpen = false"
               @class([
                   'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                   'bg-indigo-500 text-white shadow-sm' => request()->routeIs('dashboard.index', 'superadmin.dashboard.statistics'),
                   'text-slate-300 hover:bg-white/10 hover:text-white' => !request()->routeIs('dashboard.index', 'superadmin.dashboard.statistics'),
               ])>
                <i class="fa-solid fa-chart-line w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
        </section>

        <section>
            <p class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-400">Gestión</p>
            <a href="{{ route('superadmin.customer') }}"
               @click="sidebarOpen = false"
               @class([
                   'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                   'bg-indigo-500 text-white shadow-sm' => request()->routeIs('superadmin.customer', 'superadmin.customers.*'),
                   'text-slate-300 hover:bg-white/10 hover:text-white' => !request()->routeIs('superadmin.customer', 'superadmin.customers.*'),
               ])>
                <i class="fa-solid fa-hospital w-5 text-center"></i>
                <span>Clínicas</span>
            </a>
        </section>
    </nav>

    <div class="border-t border-white/10 p-4">
        <p class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</p>
        <p class="mt-0.5 truncate text-xs text-slate-400">Superadministrador</p>
    </div>
</aside>
