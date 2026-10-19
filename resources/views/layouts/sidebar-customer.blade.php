<!-- Overlay móvil -->
<div x-show="open" x-cloak x-transition.opacity @click="open = false" class="fixed inset-0 bg-black/50 z-30 md:hidden">
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

        @php
            $isDoctor = auth()->check() && auth()->user()->roles()->where('roles.name', 'Doctor')->exists();
        @endphp

        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h6 class="text-base font-bold">
                
                Administración Médica
            </h6>
        </div>

        <a href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('dashboard*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-chart-line w-5"></i>
            <span>Panel</span>
        </a>

        <a href="{{ route('customers.patient.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('patient*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user-plus w-5"></i>
            <span>Pacientes</span>
        </a>

        <a href="{{ route('customers.appointments.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('appointments*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-regular fa-calendar-days w-5"></i>
            <span>Agenda</span>
        </a>

        @unless ($isDoctor)
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
             
            <h2 class="text-base font-bold">
             
                Configuración del sistema
            </h2>
        </div>
        <a href="{{ route('customers.professional.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('professional*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user-doctor w-5"></i>
            <span>Profesionales</span>
        </a>

         <a href="{{ route('customers.insurances.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('insurances*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-shield-heart w-5"></i>
            <span>Previsiones</span>
        </a>
         <a href="{{ route('customers.specialties.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('specialties*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-stethoscope w-5"></i>
            <span>Especialidades</span>
        </a>
      
        <div class="h-16 flex items-center  justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">
             
                Administración Web
            </h2>
        </div>

        <a href="{{ route('customers.logo.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('logo*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-image w-5"></i>
            <span>Logo</span>
        </a>

        <a href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('menus*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-bars w-5"></i>
            <span>Menu</span>
        </a>

        <a href="{{ route('customers.banners.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('banners*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-images w-5"></i>
            <span>Banners</span>
        </a>

        <a href="{{ route('customers.website-footer.edit', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('website-footer*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-shoe-prints w-5"></i>
            <span>Footer</span>
        </a>
        <a href="{{ route('customers.website-topbar.edit', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('website-topbar*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-bars-staggered w-5"></i>
            <span>Barra superior</span>
        </a>
        <a href="{{ route('customers.website-socials.edit', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('website-socials*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-brands fa-instagram w-5"></i>
            <span>Redes sociales</span>
        </a>

          <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Auditoría</h2>
        </div>
        <a href="{{ route('customers.activity-history.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('activity-history*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-clock-rotate-left w-5"></i>
            <span>Historial</span>
        </a>
        @endunless

    </nav>

</aside>
