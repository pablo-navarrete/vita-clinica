@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi Perfil</h2>
@endsection

@push('scripts')
    <script>
        function MyScheduleManager() {
            return {
                urls: {
                    index: '/my-schedules/data',
                    store: '/my-schedules',
                    remove: '/my-schedules/__ID__',
                    affected: '/my-schedules/__ID__/affected-appointments'
                },
                loading: false,
                saving: false,
                removing: null,
                schedules: [],
                error: '',
                form: { day_of_week: '', start_time: '', end_time: '' },
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
                        const response = await fetch(this.urls.index, {
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
                },

                async add() {
                    this.error = '';

                    if (!this.form.day_of_week || !this.form.start_time || !this.form.end_time) {
                        this.error = 'Selecciona el día y las horas de inicio y término.';
                        return;
                    }

                    this.saving = true;
                    try {
                        const response = await fetch(this.urls.store, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify(this.form)
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            const message = data.errors ? Object.values(data.errors).flat().join(' ') : data.message;
                            throw new Error(message || 'No se pudo agregar el horario.');
                        }

                        this.form = { day_of_week: '', start_time: '', end_time: '' };
                        await this.load();
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Horario agregado',
                            showConfirmButton: false,
                            timer: 1800
                        });
                    } catch (error) {
                        this.error = error.message;
                    } finally {
                        this.saving = false;
                    }
                },

                async remove(schedule) {
                    const affectedUrl = this.urls.affected.replace('__ID__', schedule.id);
                    if (schedule.future_appointments_count > 0) {
                        const result = await Swal.fire({
                            icon: 'warning',
                            title: 'Horario con citas agendadas',
                            text: `Este horario tiene ${schedule.future_appointments_count} cita(s) futura(s). Debes gestionarlas antes de eliminarlo.`,
                            showCancelButton: true,
                            confirmButtonText: 'Ver citas afectadas',
                            cancelButtonText: 'Cerrar',
                            confirmButtonColor: '{{ themeColor(600) }}'
                        });
                        if (result.isConfirmed) window.location.href = affectedUrl;
                        return;
                    }

                    const result = await Swal.fire({
                        icon: 'warning',
                        title: '¿Eliminar horario?',
                        text: `Se eliminará el horario del día ${this.days[schedule.day_of_week]} de ${this.formatTime(schedule.start_time)} a ${this.formatTime(schedule.end_time)}.`,
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar',
                        confirmButtonColor: '#dc2626'
                    });
                    if (!result.isConfirmed) return;

                    this.removing = schedule.id;
                    this.error = '';
                    try {
                        const deleteUrl = this.urls.remove.replace('__ID__', schedule.id);
                        const response = await fetch(deleteUrl, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }
                        });
                        const data = await response.json();
                        if (!response.ok) {
                            if (data.has_appointments) {
                                const confirm = await Swal.fire({
                                    icon: 'warning',
                                    title: 'Hay citas agendadas',
                                    text: data.message,
                                    showCancelButton: true,
                                    confirmButtonText: 'Ver citas afectadas',
                                    cancelButtonText: 'Cerrar',
                                    confirmButtonColor: '{{ themeColor(600) }}'
                                });
                                if (confirm.isConfirmed) window.location.href = affectedUrl;
                                return;
                            }
                            throw new Error(data.message || 'No se pudo eliminar el horario.');
                        }

                        await this.load();
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Horario eliminado',
                            showConfirmButton: false,
                            timer: 1800
                        });
                    } catch (error) {
                        this.error = error.message;
                    } finally {
                        this.removing = null;
                    }
                }
            };
        }
    </script>
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
                            <input id="rut" type="text" value="{{ $user->rut }}" disabled
                                   class="w-full rounded-lg border-gray-200 bg-gray-100 text-gray-600">
                            <p class="mt-1 text-xs text-gray-500">El RUT no se puede modificar desde el perfil.</p>
                        </div>
                    </div>
                </section>

                @if ($isDoctor)
                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm"
                         x-data="MyScheduleManager()"
                         x-init="load()">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-regular fa-calendar-days mr-2"></i>Mis horarios de atención
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Administra tu disponibilidad semanal. Los cambios solo afectan tus propios horarios.</p>
                    </div>

                    <div class="p-6">
                        <div class="grid grid-cols-1 items-end gap-4 md:grid-cols-4">
                            <div>
                                <label for="schedule-day" class="mb-1 block text-sm font-medium text-gray-700">Día</label>
                                <select id="schedule-day" x-model="form.day_of_week" class="w-full rounded-lg border-gray-300">
                                    <option value="">Selecciona un día</option>
                                    <option value="1">Lunes</option>
                                    <option value="2">Martes</option>
                                    <option value="3">Miércoles</option>
                                    <option value="4">Jueves</option>
                                    <option value="5">Viernes</option>
                                    <option value="6">Sábado</option>
                                    <option value="0">Domingo</option>
                                </select>
                            </div>
                            <div>
                                <label for="schedule-start" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
                                <input id="schedule-start" type="time" x-model="form.start_time" class="w-full rounded-lg border-gray-300">
                            </div>
                            <div>
                                <label for="schedule-end" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
                                <input id="schedule-end" type="time" x-model="form.end_time" class="w-full rounded-lg border-gray-300">
                            </div>
                            <button type="button" @click="add()" :disabled="saving"
                                    style="background-color: {{ themeColor(600) }}"
                                    class="rounded-lg px-4 py-2.5 font-semibold text-white transition hover:brightness-90 disabled:opacity-60">
                                <i class="fa-solid fa-plus mr-1"></i>
                                <span x-text="saving ? 'Guardando…' : 'Agregar horario'"></span>
                            </button>
                        </div>

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
                                    <button type="button" @click="remove(schedule)" :disabled="removing === schedule.id"
                                            class="self-end rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 disabled:opacity-60 sm:self-auto">
                                        <i class="fa-regular fa-trash-can mr-1"></i>Eliminar
                                    </button>
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
                        <label class="mb-2 block text-sm font-medium text-gray-700">Especialidades asignadas</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            @forelse(($user->specialties ?? collect()) as $specialty)
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
