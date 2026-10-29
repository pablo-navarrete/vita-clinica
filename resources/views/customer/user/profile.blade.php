@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi Perfil</h2>
@endsection

@push('scripts')
    <script>
        function MyScheduleManager() {
            return {
                url: '/my-schedules/data?branch={{ $activeBranch?->id }}',
                loading: false,
                schedules: [],
                error: '',
                days: {
                    0: 'Domingo',
                    1: 'Lunes',
                    2: 'Martes',
                    3: 'Miércoles',
                    4: 'Jueves',
                    5: 'Viernes',
                    6: 'Sábado'
                },
                async load() {
                    this.loading = true;
                    this.error = '';
                    try {
                        const response = await fetch(this.url, {
                            cache: 'no-store',
                            headers: { 'Accept': 'application/json' }
                        });
                        const data = await response.json();
                        if (!response.ok) throw new Error(data.message || 'No se pudieron cargar tus horarios.');
                        this.schedules = data;
                    } catch (error) {
                        this.error = error.message;
                    } finally {
                        this.loading = false;
                    }
                },
                formatTime(time) {
                    return time ? time.substring(0, 5) : '--:--';
                }
            };
        }    </script>
@endpush

@section('content')
    <div class="py-8">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">No se guardaron los cambios. Revisa lo siguiente:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('success'))
                <div role="status" class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('customers.profile.update', ['customer' => request()->route('customer')]) }}" method="POST">
                @csrf
                @method('PATCH')

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-user mr-2"></i>Datos personales
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Actualiza la información de contacto de tu cuenta.</p>
                    </div>

                    <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                        <div>
                            <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre completo</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" required maxlength="255" autocomplete="tel"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="rut" class="mb-1 block text-sm font-medium text-gray-700">RUT</label>
                            @if ($isClinicAdministrator)
                                <input id="rut" name="rut" type="text" value="{{ old('rut', $user->rut) }}" required maxlength="255"
                                       class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                                @error('rut')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            @else
                                <input id="rut" type="text" value="{{ $user->rut }}" disabled
                                       class="w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600">
                                <p class="mt-1 text-xs text-gray-500">Para cambiar el RUT, contacta al administrador.</p>
                            @endif
                        </div>
                    </div>
                </section>

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-user-shield mr-2"></i>Roles asignados
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Tus roles están organizados según las sedes donde los tienes asignados.</p>
                    </div>
                    <div class="space-y-4 p-6">
                        @forelse ($roleGroups as $group)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-gray-500"></i>
                                    <h4 class="font-semibold text-gray-800">{{ $group['branch']->name }}</h4>
                                    @if (! $group['branch']->status)
                                        <span class="text-xs text-gray-500">(Inactiva)</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($group['roles'] as $role)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm text-green-700">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-sm text-gray-500">Sin roles asignados en esta sede.</span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <span class="text-sm text-gray-500">No tienes sedes asignadas.</span>
                        @endforelse
                    </div>
                </section>

                @if ($isDoctor || $isClinicAdministrator)
                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm"
                         x-data="MyScheduleManager()"
                         x-init="load()">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-regular fa-calendar-days mr-2"></i>Mis horarios de atención
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Sede: <strong>{{ $activeBranch?->name ?? 'Sin sede activa' }}</strong>. Estos horarios son asignados y modificados por el administrador de la clínica.</p>
                    </div>

                    <div class="p-6">
                        <div x-show="error" x-cloak role="alert" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" x-text="error"></div>

                        <div x-show="loading" class="flex justify-center py-8 text-gray-500">
                            <i class="fa-solid fa-spinner fa-spin mr-2 mt-1"></i>Cargando tus horarios…
                        </div>

                        <div x-show="!loading && schedules.length === 0" x-cloak class="mt-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 p-6 text-center text-sm text-gray-500">
                            Aún no tienes horarios registrados.
                        </div>

                        <div x-show="!loading && schedules.length > 0" x-cloak class="mt-5 space-y-3">
                            <template x-for="schedule in schedules" :key="schedule.id">
                                <div class="flex flex-col gap-3 rounded-lg border border-gray-200 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full" style="background-color: {{ themeColor(100) }}; color: {{ themeColor(700) }}">
                                            <i class="fa-regular fa-calendar-days"></i>
                                        </span>
                                        <div>
                                            <p class="font-semibold text-gray-800" x-text="days[schedule.day_of_week]"></p>
                                            <p class="text-sm text-gray-600"><span x-text="formatTime(schedule.start_time)"></span> – <span x-text="formatTime(schedule.end_time)"></span></p>
                                            <p x-show="schedule.future_appointments_count > 0" class="mt-1 text-xs text-amber-700">
                                                Tiene <span x-text="schedule.future_appointments_count"></span> cita(s) futura(s).
                                            </p>
                                        </div>
                                    </div>

                                </div>
                            </template>
                        </div>
                    </div>
                </section>
                @endif

                @if ($isDoctor)
                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-stethoscope mr-2"></i>Información profesional
                        </h3>
                    </div>

                    <div class="p-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Especialidades en {{ $activeBranch?->name ?? 'la sede actual' }}</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            @forelse($specialties as $specialty)
                                <span class="mr-2 mb-2 inline-flex items-center rounded-full px-3 py-1 text-sm"
                                      style="background-color: {{ themeColor(100) }}; color: {{ themeColor(700) }}">
                                    {{ $specialty->name }}
                                </span>
                            @empty
                                <p class="text-sm text-gray-500">No tienes especialidades asignadas.</p>
                            @endforelse
                            <p class="mt-2 text-xs text-gray-500">
                                <i class="fa-solid fa-lock mr-1"></i>
                                Para agregar o modificar especialidades, contacta al administrador.
                            </p>
                        </div>
                    </div>
                </section>
                @endif

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-lock mr-2"></i>Contraseña
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Cambiarla es opcional. Activa la opción para ingresar una contraseña nueva.</p>
                    </div>

                    <div class="p-6" x-data="{ changePassword: {{ $errors->has('password') || $errors->has('current_password') ? 'true' : 'false' }} }">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="changePassword" class="rounded border-gray-300">
                            Quiero cambiar mi contraseña
                        </label>

                        <div x-cloak x-show="changePassword" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="current_password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña actual</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="current_password" name="current_password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Nueva contraseña</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="mb-6 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <i class="fa-solid fa-circle-info mt-1 text-gray-500"></i>
                    <p class="text-sm text-gray-600">Las especialidades y los permisos de la cuenta siguen siendo administrados por la clínica.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" style="background-color: {{ themeColor(500) }}"
                            class="rounded-lg px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-90">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
