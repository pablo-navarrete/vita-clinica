<!-- Overlay móvil -->
<div x-show="open" x-transition.opacity @click="open = false" class="fixed inset-0 bg-black/50 z-30 md:hidden">
</div>

<!-- Sidebar -->
<aside
    class="fixed md:static inset-y-0 left-0 z-40
           w-64 text-white
           overflow-y-auto
           transform transition-transform duration-300 ease-in-out
           md:translate-x-0"
    style="background-color: {{ themeColor(800) }}"
    :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

    <nav class="p-4 space-y-2">

        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h6 class="text-base font-bold">
                Administración Médica
            </h6>
        </div>

        <a href="/dashboard"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('dashboard*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-chart-line w-5"></i>
            <span>Panel</span>
        </a>

        <a href="/patient"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('patient*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user-plus w-5"></i>
            <span>Pacientes</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition">
            <i class="fa-solid fa-calendar-days w-5"></i>
            <span>Citas</span>
        </a>

        <a href="/professional"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('professional*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user-doctor w-5"></i>
            <span>Profesionales</span>
        </a>

        <a href="/assistant"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('assistant*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-calendar-days w-5"></i>
            <span>Asistentes</span>
        </a>

        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">
                Administración Web
            </h2>
        </div>

        <a href="/logo"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('logo*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-calendar-days w-5"></i>
            <span>Logo</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition">
            <i class="fa-solid fa-file-lines w-5"></i>
            <span>Menu</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition">
            <i class="fa-solid fa-file-lines w-5"></i>
            <span>Banner</span>
        </a>

        <a href="#" class="flex items-center gap-3 px-4 py-3 rounded-lg hover:bg-white/10 transition">
            <i class="fa-solid fa-file-lines w-5"></i>
            <span>Footer</span>
        </a>

    </nav>

</aside>
