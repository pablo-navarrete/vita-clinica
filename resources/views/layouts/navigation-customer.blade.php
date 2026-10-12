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
<div x-data="{ open: false }" class="relative">

    <!-- TRIGGER -->
    <button @click="open = !open"
        style="color: {{ themeColor(600) }}"
        class="relative px-3 py-2 hover:brightness-75 transition">

        <i class="fa-solid fa-bell text-lg"></i>

       
            <span
                class="absolute -top-1 -right-1 min-w-5 h-5 px-1
                       flex items-center justify-center
                       bg-red-500 text-white text-xs font-bold
                       rounded-full">
                99
            </span>
       

    </button>

    <!-- DROPDOWN notificaciones -->
    <div x-show="open"
    x-cloak
        @click.outside="open = false"
        x-transition
        class="absolute right-0 mt-2 w-48 bg-white shadow-lg rounded-lg z-50 overflow-hidden">

        <div class="px-4 py-2 text-sm font-semibold text-gray-700 border-b">
            Notificaciones
        </div>

        <form method="POST">
            @csrf

            <div class="p-4 mb-2">
                <p class="text-gray-500">no hay notificaciones</p>
            </div>

            <div class="border-t mt-2 pt-2 px-4 mb-2">
                <button type="submit"
                    style="background-color: {{ themeColor(600) }}"
                    class="w-full text-white py-2 rounded hover:brightness-90 transition">
                    Mostrar todas
                </button>
            </div>
        </form>

    </div>
</div>



                <a href="/" style="color: {{ themeColor(600) }}" class="px-5 hover:brightness-75 transition"
                    target="_blanc">
                    <i class="fa-solid fa-globe"></i>
                </a>
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
                        <x-dropdown-link href="#">

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
