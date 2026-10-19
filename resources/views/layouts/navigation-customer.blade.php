@php
    $currentColor = app('customer')->color ?? 'blue';
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

                <!-- Nombre Empresa -->
                <a href="/" class="flex items-center gap-2 text-xl font-bold" style="color: {{ themeColor(800) }}">

                    @if (isset($logosByLocation['panel']))
                        <img src="{{ asset('storage/' . $logosByLocation['panel']->url) }}" class="h-6">
                    @endif

                    <span>{{ app('customer')->name }}</span>
                </a>

            </div>

            <!-- DERECHA -->
            <div class="flex items-center">
            
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
        class="relative px-3 py-2 hover:brightness-75 transition">

        <i class="fa-solid fa-bell text-lg"></i>

        @if ($tenantUnreadNotificationCount > 0)
            <span class="absolute -top-1 -right-1 min-w-5 h-5 px-1 flex items-center justify-center bg-red-500 text-white text-xs font-bold rounded-full">
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



                <a href="/" style="color: {{ themeColor(600) }}" class="px-5 hover:brightness-75 transition"
                    target="_blanc">
                    <i class="fa-solid fa-globe"></i>
                </a>

                @php
                    $isDoctor = auth()->check() && auth()->user()->roles()->where('roles.name', 'Doctor')->exists();
                @endphp
                @unless ($isDoctor)
                <!--color del sistema-->
                <div x-data="{ open: false }" class="relative">

                    <!-- TRIGGER -->
                    <button @click="open = !open" style="color: {{ themeColor(600) }}"
                        class="px-3 py-2 hover:brightness-75 transition">
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
                @endunless
                <x-dropdown align="right" width="48">

                    <x-slot name="trigger">
                        <button 
                            class="inline-flex items-center px-3 py-2 text-sm font-medium hover:brightness-75 transition">
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

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault();
                                         this.closest('form').submit();">
                                Cerrar Sesión
                            </x-dropdown-link>
                        </form>

                    </x-slot>

                </x-dropdown>

            </div>

        </div>
    </div>
</nav>
