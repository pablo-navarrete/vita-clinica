    <div x-cloak x-data="ProfessionalScheduleModal()" x-ref="scheduleComponent" x-show="show"
        class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b rounded-t-lg"
                style="
                                    background-color: {{ themeColor(100) }};
                                    border-color: {{ themeColor(200) }};
                                ">

                <h2 class="text-lg font-bold">
                    Horarios del usuario
                </h2>

                <p class="text-sm text-gray-600 mt-1" x-text="professional?.name"></p>
                @if ($isHeadquarters && $branches->count() > 1)
                    <label class="mt-3 block text-xs font-medium text-gray-600">Sede de los horarios</label>
                    <select x-model="branchId" @change="changeBranch()" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                @else
                    <p class="mt-2 text-xs font-medium text-gray-600">Sede: {{ $activeBranch?->name ?? 'Sin sede seleccionada' }}</p>
                @endif

            </div>

            <!-- BODY -->
            <div class="p-6 space-y-4 overflow-y-auto flex-1">

                <!-- Día -->
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Día
                    </label>

                    <select x-model="form.day_of_week" class="w-full rounded-lg border-gray-300">

                        <option value="">Seleccione</option>
                        <option value="1">Lunes</option>
                        <option value="2">Martes</option>
                        <option value="3">Miércoles</option>
                        <option value="4">Jueves</option>
                        <option value="5">Viernes</option>
                        <option value="6">Sábado</option>
                        <option value="0">Domingo</option>

                    </select>

                </div>

                <!-- Desde -->
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Desde
                    </label>

                    <input type="time" x-model="form.start_time" class="w-full rounded-lg border-gray-300">

                </div>

                <!-- Hasta -->
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Hasta
                    </label>

                    <input type="time" x-model="form.end_time" class="w-full rounded-lg border-gray-300">

                </div>

                <div>

                    <button @click="save()" style="background-color: {{ themeColor(600) }}"
                        class="text-white px-4 py-2 rounded">

                        Agregar horario

                    </button>

                </div>

                <hr>

                <!-- Aquí después mostraremos los horarios -->
                <hr>

                <!-- Loader -->
                <div x-show="loading" class="flex justify-center py-6">
                    <i class="fa-solid fa-spinner fa-spin text-2xl text-gray-500"></i>
                </div>

                <!-- Horarios -->
                <div x-show="!loading">

                    <!-- SIN HORARIOS -->
                    <template x-if="!loading && schedules.length === 0">

                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 text-center">

                            <i class="fa-regular fa-calendar-xmark text-gray-400 text-2xl mb-2"></i>

                            <p class="text-sm text-gray-500">
                                No hay horarios registrados.
                            </p>

                        </div>

                    </template>

                    <template x-for="group in scheduleGroups()" :key="group.branch">
                        <section class="mb-4 last:mb-0">
                            <h4 class="mb-2 rounded-md bg-gray-100 px-3 py-2 text-sm font-semibold text-gray-700" x-text="group.branch"></h4>
                            <template x-for="schedule in group.items" :key="schedule.id">
                        <div class="flex items-center justify-between border rounded-lg p-3 mb-2">

                            <!-- ICONO + INFORMACIÓN -->
                            <div class="flex items-center gap-3">

                                <!-- ICONO -->
                                <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center"
                                    style="background-color: {{ themeColor(100) }}">
                                    <i class="fa-regular fa-calendar-days" style="color: {{ themeColor(600) }}"></i>
                                </div>

                                <!-- DÍA Y HORARIO -->
                                <div>

                                    <p class="font-medium text-gray-800" x-text="days[schedule.day_of_week]"></p>
                                    <p class="text-xs text-gray-500" x-text="schedule.branch_name"></p>

                                    <p class="text-sm text-gray-500">

                                        <span x-text="schedule.start_time.substring(0, 5)"></span>

                                        <span class="mx-1">-</span>

                                        <span x-text="schedule.end_time.substring(0, 5)"></span>

                                    </p>

                                    <p x-show="schedule.future_appointments_count > 0"
                                        class="mt-1 text-xs text-amber-700">
                                        Tiene <span x-text="schedule.future_appointments_count"></span>
                                        cita(s) futura(s).
                                    </p>

                                </div>

                            </div>

                            <!-- BOTÓN ELIMINAR -->
                            <div class="flex items-center gap-3">
                                <button x-show="canManageSchedule(schedule)" class="text-red-500 hover:text-red-700 transition" @click="remove(schedule)" title="Eliminar horario">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                                <span x-show="!canManageSchedule(schedule)" class="text-xs text-gray-400">Solo consulta</span>
                            </div>

                        </div>
                            </template>
                        </section>
                    </template>

                </div>

            </div>

            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end">

                <button @click="close()" class="bg-gray-600 text-white px-4 py-2 rounded">

                    Cerrar

                </button>

            </div>

        </div>

    </div>



