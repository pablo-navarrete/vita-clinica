<!-- Overlay móvil -->
<div x-show="open" x-cloak x-transition.opacity @click="open = false" class="fixed inset-0 bg-black/50 z-40 md:hidden">
</div>

<!-- Sidebar -->
<aside
    @click="if (window.matchMedia('(max-width: 767px)').matches && $event.target.closest('a[href]')) open = false"
    class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 shrink-0 flex-col text-white
           overflow-hidden
           transform transition-transform duration-300 ease-in-out
           -translate-x-full md:sticky md:top-0 md:h-screen md:translate-x-0"
    style="background-color: {{ themeColor(800) }}"
    :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

    @php
        $clinicName = (string) app('customer')->name;
        $clinicNameFontSize = max(14, min(20, 20 - max(0, mb_strlen($clinicName) - 12) * 0.45));
    @endphp
    <div class="flex h-16 shrink-0 items-center border-b px-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
        <a href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}" @click="open = false" class="flex w-full min-w-0 items-center justify-center gap-2 text-center font-extrabold uppercase leading-tight" style="color: {{ themeColor(800) }}">
            @if (isset($logosByLocation['panel']))
                <img src="{{ asset('storage/' . $logosByLocation['panel']->url) }}" class="h-9 w-9 shrink-0 object-contain">
            @endif
            <span class="clinic-sidebar-name min-w-0 text-center" style="font-size: {{ $clinicNameFontSize }}px" title="{{ $clinicName }}">{{ $clinicName }}</span>
        </a>
    </div>

    <nav class="clinic-sidebar-scroll min-h-0 flex-1 space-y-2 overflow-y-auto p-4">

        @php
            $clinicRoleQuery = auth()->user()?->roles()->where('roles.customer_id', app('customer')->id);
            $isClinicAdmin = $clinicRoleQuery?->where('roles.name', 'Administrador')->exists() ?? false;
            $isDoctor = auth()->check()
                && !$isClinicAdmin
                && auth()->user()->roles()->where('roles.customer_id', app('customer')->id)->where('roles.name', 'Doctor')->exists();
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



        @php
            $attentionBranch = \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), (int) app('customer')->id);
        @endphp
        @if (\App\Support\ClinicBranchContext::isSuperAdministrator(auth()->user()) || ($attentionBranch && (auth()->user()->hasClinicRole('Doctor', (int) app('customer')->id, (int) $attentionBranch->id)
            || auth()->user()->hasClinicRole('Administrador', (int) app('customer')->id, (int) $attentionBranch->id))
            && \App\Support\ClinicBranchContext::hasPermissionInBranch(auth()->user(), (int) app('customer')->id, (int) $attentionBranch->id, 'medical_records.view')))
            <a href="{{ route('customers.encounters.mine', ['customer' => request()->route('customer')]) }}" @click="open = false"
                @if(request()->is('my-encounters')) aria-current="page" @endif
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('my-encounters') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-notes-medical w-5" aria-hidden="true"></i>
                <span>{{ \App\Support\ClinicBranchContext::isSuperAdministrator(auth()->user()) ? 'Atenciones' : 'Mis atenciones' }}</span>
            </a>
        @endif

        <a href="{{ route('customers.chat.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('chat*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-comments w-5"></i>
            <span>Chat interno</span>
        </a>

        @php
            $notificationBranch = \App\Support\ClinicBranchContext::defaultBranch(auth()->user(), (int) app('customer')->id);
            $canViewNotifications = \App\Support\ClinicBranchContext::isSuperAdministrator(auth()->user())
                || ($notificationBranch && \App\Support\ClinicBranchContext::hasPermissionInBranch(
                    auth()->user(), (int) app('customer')->id, (int) $notificationBranch->id, 'notifications.view'
                ));
        @endphp
        @if ($canViewNotifications)
            <a href="{{ route('customers.notifications.index', ['customer' => request()->route('customer')]) }}"
                @click="open = false"
                @if(request()->is('notifications*')) aria-current="page" @endif
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('notifications*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-bell w-5" aria-hidden="true"></i>
                <span>Notificaciones</span>
            </a>
        @endif



        <a href="{{ route('customers.profile.index', ['customer' => request()->route('customer')]) }}"
            @click="open = false"
            @if(request()->is('profile*')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('profile*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user w-5" aria-hidden="true"></i>
            <span>Perfil</span>
        </a>

        @unless ($isDoctor)
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
             
            <h2 class="text-base font-bold">
             
                Configuración del sistema
            </h2>
        </div>
        <a href="{{ route('customers.users.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('users*', 'professional*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-users w-5"></i>
            <span>Usuarios</span>
        </a>

        <a href="{{ route('customers.roles-permissions.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('roles-permissions*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user-shield w-5"></i>
            <span>Roles y permisos</span>
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

          <a href="{{ route('customers.mail-settings.edit', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('mail-settings*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-envelope-circle-check w-5"></i>
            <span>Notificaciones a pacientes</span>
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

        <a href="{{ route('customers.website-blog.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('website-blog*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-newspaper w-5"></i>
            <span>Blog</span>
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

<style>
    .clinic-sidebar-name {
        display: -webkit-box;
        -webkit-box-orient: vertical;
        -webkit-line-clamp: 2;
        overflow: hidden;
        overflow-wrap: anywhere;
    }

    .clinic-sidebar-scroll {
        scrollbar-width: thin;
        scrollbar-color: {{ themeColor(600) }} {{ themeColor(800) }};
        scrollbar-gutter: stable;
    }

    .clinic-sidebar-scroll::-webkit-scrollbar {
        width: 8px;
    }

    .clinic-sidebar-scroll::-webkit-scrollbar-track {
        background: {{ themeColor(800) }};
    }

    .clinic-sidebar-scroll::-webkit-scrollbar-thumb {
        background: {{ themeColor(600) }};
        border: 2px solid {{ themeColor(800) }};
        border-radius: 9999px;
    }

    .clinic-sidebar-scroll::-webkit-scrollbar-thumb:hover {
        background: {{ themeColor(500) }};
    }
</style>
