<!-- Overlay móvil -->
<div x-show="open" x-cloak x-transition.opacity @click="open = false" class="fixed inset-0 bg-black/50 z-40 md:hidden">
</div>

<!-- Sidebar -->
<aside id="clinic-sidebar"
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
            const key = 'vita-sidebar-compact:{{ app('customer')->id }}:{{ auth()->id() }}';
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
    @click="if (window.matchMedia('(max-width: 767px)').matches && $event.target.closest('a[href]')) open = false"
    class="fixed inset-y-0 left-0 z-50 flex h-screen w-64 shrink-0 flex-col text-white
           overflow-hidden
           transform transition-transform duration-300 ease-in-out
           -translate-x-full md:sticky md:top-0 md:h-screen md:translate-x-0"
    style="background-color: {{ themeColor(800) }}"
    :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'">

    @php
        $clinicName = (string) app('customer')->name;
    @endphp
    <div class="flex h-16 shrink-0 items-center border-b px-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
        <a href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}" @click="open = false" class="flex w-full min-w-0 items-center justify-center gap-2 text-center font-extrabold uppercase leading-tight" style="color: {{ themeColor(800) }}">
            @if (isset($logosByLocation['panel']))
                <img src="{{ asset('storage/' . $logosByLocation['panel']->url) }}" class="h-9 w-9 shrink-0 object-contain">
            @else
                <span class="clinic-compact-initial" aria-hidden="true">{{ mb_substr($clinicName, 0, 1) }}</span>
            @endif
            <span class="clinic-sidebar-name vita-workspace-title min-w-0 text-center" title="{{ $clinicName }}">{{ $clinicName }}</span>
        </a>
    </div>

    <div class="clinic-compact-toolbar">
        <button type="button" @click="compact = !compact" :aria-expanded="!compact" aria-controls="clinic-sidebar"
            :title="compact ? 'Expandir menú' : 'Compactar menú'" :aria-label="compact ? 'Expandir menú' : 'Compactar menú'"
            class="clinic-compact-toggle">
            <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
        </button>
    </div>

    <nav @scroll="preview = false" class="clinic-sidebar-scroll min-h-0 flex-1 space-y-2 overflow-y-auto p-4">

        @php
            $clinicRoleQuery = auth()->user()?->roles()->where('roles.customer_id', app('customer')->id);
            $isClinicAdmin = $clinicRoleQuery?->where('roles.name', 'Administrador')->exists() ?? false;
            $isDoctor = auth()->check()
                && !$isClinicAdmin
                && auth()->user()->roles()->where('roles.customer_id', app('customer')->id)->where('roles.name', 'Doctor')->exists();
        @endphp

        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Administración Médica</h2>
        </div>

        <a href="{{ route('customers.dashboard.index', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('dashboard*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-chart-line w-5"></i>
            <span>Panel</span>
        </a>

        @if($isClinicAdmin || $isDoctor)
        <a href="{{ route('customers.directory.reviews', ['customer' => request()->route('customer')]) }}" class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.directory.reviews*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}"><i class="fa-solid fa-star w-5" aria-hidden="true"></i><span>Opiniones de pacientes</span></a>
        @endif

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



        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Mi cuenta</h2>
        </div>
        <a href="{{ route('customers.profile.index', ['customer' => request()->route('customer')]) }}"
            @click="open = false"
            @if(request()->routeIs('customers.profile.index')) aria-current="page" @endif
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.profile.index') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-user w-5" aria-hidden="true"></i>
            <span>Mi perfil</span>
        </a>
        @if((int) auth()->user()->customer_id === (int) app('customer')->id)
            @if(app(\App\Policies\ClinicBillingPolicy::class)->manage(auth()->user(), (int)app('customer')->id))
            <a href="{{ route('customers.subscription.index',['customer'=>request()->route('customer')]) }}" @click="open = false"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.subscription.index') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-credit-card w-5" aria-hidden="true"></i><span>Mi suscripción</span>
            </a>
            <a href="{{ route('customers.subscription.conditions',['customer'=>request()->route('customer')]) }}" @click="open = false"
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.subscription.conditions') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-file-contract w-5" aria-hidden="true"></i><span>Condiciones y contrato</span>
            </a>
            <a href="{{ route('customers.directory.edit',['customer'=>request()->route('customer')]) }}" @click="open = false" class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.directory.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}"><i class="fa-solid fa-building w-5" aria-hidden="true"></i><span>Directorio de VITA</span></a>
            @endif
            <a href="{{ route('customers.my-schedules.index', ['customer' => request()->route('customer')]) }}" @click="open = false"
                @if(request()->routeIs('customers.my-schedules.*')) aria-current="page" @endif
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.my-schedules.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-clock w-5" aria-hidden="true"></i>
                <span>Mis horarios</span>
            </a>
        @endif
        @if(app(\App\Services\Signatures\ProfessionalSignatureService::class)->canManage(auth()->user(), (int) app('customer')->id))
            <a href="{{ route('customers.signature.index', ['customer' => request()->route('customer')]) }}"
                @click="open = false"
                @if(request()->routeIs('customers.signature.*')) aria-current="page" @endif
                class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->routeIs('customers.signature.*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
                <i class="fa-solid fa-signature w-5" aria-hidden="true"></i>
                <span>Firma electrónica</span>
            </a>
        @endif

        
        @unless ($isDoctor)
        <div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Configuración del sistema</h2>
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

          <a href="{{ route('customers.mail-settings.edit', ['customer' => request()->route('customer')]) }}"
            class="flex items-center gap-3 px-4 py-3 rounded-lg transition {{ request()->is('mail-settings*') ? 'bg-white/20 text-white shadow-md' : 'hover:bg-white/10 text-slate-200' }}">
            <i class="fa-solid fa-envelope-circle-check w-5"></i>
            <span>Notificaciones a pacientes</span>
        </a>

        
<div class="h-16 flex items-center justify-center border-b" style="border-color: {{ themeColor(700) }}">
            <h2 class="text-base font-bold">Administración Web</h2>
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
    <template x-teleport="body">
        <div id="clinic-sidebar-preview" x-show="preview && compact" x-cloak x-transition.opacity.duration.150ms
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
    #clinic-sidebar-preview { position: fixed; z-index: 100; width: max-content; min-width: 180px; max-width: min(280px, calc(100vw - 108px)); padding: 6px; border: 1px solid {{ themeColor(100) }}; border-radius: 12px; background: #fff; box-shadow: 0 8px 28px rgb(15 23 42 / .14); }
    #clinic-sidebar-preview::before { content: ''; position: absolute; top: 22px; left: -5px; width: 9px; height: 9px; background: #fff; border-left: 1px solid {{ themeColor(100) }}; border-bottom: 1px solid {{ themeColor(100) }}; transform: rotate(45deg); }
    #clinic-sidebar-preview a { position: relative; display: flex; align-items: center; gap: 10px; padding: 8px; color: {{ themeColor(800) }}; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; transition: background-color 150ms ease; }
    #clinic-sidebar-preview a:hover, #clinic-sidebar-preview a:focus-visible { background: {{ themeColor(50) }}; outline: 2px solid {{ themeColor(200) }}; outline-offset: -2px; }
    #clinic-sidebar-preview .clinic-preview-icon { display: grid; place-items: center; width: 30px; height: 30px; flex-shrink: 0; background: {{ themeColor(50) }}; border-radius: 8px; color: {{ themeColor(700) }}; }
    #clinic-sidebar-preview .clinic-preview-label { flex: 1; overflow-wrap: anywhere; }
    @media (max-width: 767px) { #clinic-sidebar-preview { display: none !important; } }
    @media (prefers-reduced-motion: reduce) { #clinic-sidebar-preview, #clinic-sidebar-preview a { transition: none !important; } }
    #clinic-sidebar { transition: width 180ms ease, transform 180ms ease; }
    #clinic-sidebar .clinic-compact-initial { display: none; }
    #clinic-sidebar .clinic-compact-toolbar { display: none; position: absolute; top: 82px; right: -14px; z-index: 60; }
    #clinic-sidebar .clinic-compact-toggle { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 1px solid {{ themeColor(200) }}; border-radius: 50%; color: {{ themeColor(700) }}; background: #fff; box-shadow: 0 2px 6px rgb(15 23 42 / .12); cursor: pointer; transition: background-color 150ms ease, box-shadow 150ms ease; }
    #clinic-sidebar .clinic-compact-toggle i { font-size: 10px; transition: transform 150ms ease; }
    #clinic-sidebar .clinic-compact-toggle:hover { background: {{ themeColor(50) }}; box-shadow: 0 3px 9px rgb(15 23 42 / .16); }
    #clinic-sidebar .clinic-compact-toggle:focus-visible { outline: 2px solid {{ themeColor(500) }}; outline-offset: 3px; }
    #clinic-sidebar[data-compact="true"] .clinic-compact-toggle i { transform: rotate(180deg); }
    @media (min-width: 768px) {
        #clinic-sidebar { overflow: visible; }
        #clinic-sidebar .clinic-compact-toolbar { display: flex; }
        #clinic-sidebar[data-compact="true"] { width: 80px; }
        #clinic-sidebar[data-compact="true"] .clinic-sidebar-name,
        #clinic-sidebar[data-compact="true"] nav a > span,
        #clinic-sidebar[data-compact="true"] nav > div { display: none; }
        #clinic-sidebar[data-compact="true"] .clinic-compact-initial { display: inline; font-size: 22px; }
        #clinic-sidebar[data-compact="true"] nav { padding: 12px 8px; }
        #clinic-sidebar[data-compact="true"] nav a { justify-content: center; gap: 0; padding: 12px; }
        #clinic-sidebar[data-compact="true"] nav a > i { flex-shrink: 0; text-align: center; }
    }
    @media (prefers-reduced-motion: reduce) { #clinic-sidebar { transition: none; } }
    .clinic-sidebar-name { display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden; overflow-wrap: anywhere; }
    .clinic-sidebar-scroll { scrollbar-width: thin; scrollbar-color: {{ themeColor(600) }} {{ themeColor(800) }}; scrollbar-gutter: stable; }
    .clinic-sidebar-scroll::-webkit-scrollbar { width: 8px; }
    .clinic-sidebar-scroll::-webkit-scrollbar-track { background: {{ themeColor(800) }}; }
    .clinic-sidebar-scroll::-webkit-scrollbar-thumb { background: {{ themeColor(600) }}; border: 2px solid {{ themeColor(800) }}; border-radius: 9999px; }
    .clinic-sidebar-scroll::-webkit-scrollbar-thumb:hover { background: {{ themeColor(500) }}; }
</style>
