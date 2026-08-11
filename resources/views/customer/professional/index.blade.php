@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Profesionales') }}
    </h2>
@endsection
@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900" x-data="ProfessionalModal()" x-ref="professionalComponent">
                    <div class="flex gap-2 mb-6 border-b">
                        <button @click="tab = 'active'" class="px-4 py-2 font-medium border-b-2 transition"
                            :class="tab === 'active'
                                ?
                                'border-blue-500 text-blue-600' :
                                'border-transparent text-gray-500 hover:text-gray-700'">
                            <i class="fa-solid fa-user-doctor mr-1"></i>
                            Profesionales
                        </button>

                        <button @click="tab = 'trash'" class="px-4 py-2 font-medium border-b-2 transition"
                            :class="tab === 'trash'
                                ?
                                'border-red-500 text-red-600' :
                                'border-transparent text-gray-500 hover:text-gray-700'">
                            <i class="fa-solid fa-trash-can mr-1"></i>
                            Papelera
                        </button>
                    </div>
                    <!-- TABLA Activos-->
                    <div class="overflow-x-auto" x-show="tab === 'active'">

                        <!-- BOTÓN -->
                        <div class="flex justify-end mb-4">
                            <button @click="create()" style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus mr-1"></i>
                                Nuevo
                            </button>
                        </div>

                        <table id="professional-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Especialidad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                        </table>

                    </div>

                    <!-- TABLA PAPELERA -->
                    <div class="overflow-x-auto" x-show="tab === 'trash'">



                        <table id="professional-trash-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Especialidad</th>
                                    <th>Eliminado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                        </table>

                    </div>

                    <!-- MODAL CREAR / EDITAR -->
                    <div x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] flex flex-col">

                            <!-- HEADER -->
                            <div class="px-6 py-4 border-b rounded-t-lg"
                                style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar Profesional' : 'Crear Nuevo Profesional'">
                                </h2>
                            </div>

                            <!-- BODY -->
                            <div class="p-6 overflow-y-auto flex-1">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    {{-- Nombre --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Nombre
                                        </label>

                                        <input type="text" x-model="form.name" @input="clearError('name')"
                                            class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.name">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.name[0]"></p>
                                        </template>

                                    </div>

                                    {{-- Rut --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Rut
                                        </label>

                                        <input type="text" x-model="form.rut" @input="clearError('rut')"
                                            class="w-full rounded-lg border-gray-300"
                                            placeholder="con punto y guion ej. 99.999.999-9">

                                        <template x-if="errors.rut">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.rut[0]"></p>
                                        </template>

                                    </div>

                                    {{-- Celular --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Celular
                                        </label>

                                        <input type="text" x-model="form.phone" @input="clearError('phone')"
                                            class="w-full rounded-lg border-gray-300" placeholder="ej. +569 56565566">

                                        <template x-if="errors.phone">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.phone[0]"></p>
                                        </template>

                                    </div>

                                    {{-- Correo --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Correo
                                        </label>

                                        <input type="email" x-model="form.email" @input="clearError('email')"
                                            class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.email">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.email[0]"></p>
                                        </template>

                                    </div>

                                    {{-- Contraseña --}}
                                    {{-- Password --}}
                                    <div x-show="!isEdit" x-data="{ showPassword: false }">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Contraseña
                                        </label>

                                        <div class="relative">

                                            <input :type="showPassword ? 'text' : 'password'" x-model="form.password"
                                                @input="clearError('password')"
                                                class="w-full rounded-lg border-gray-300 pr-10">

                                            <button type="button" @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700">

                                                <i
                                                    :class="showPassword ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'"></i>

                                            </button>

                                        </div>

                                        <template x-if="errors.password">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.password[0]"></p>
                                        </template>

                                    </div>

                                    {{-- Estado --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Estado
                                        </label>

                                        <select x-model="form.status" class="w-full rounded-lg border-gray-300">

                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>

                                        </select>

                                    </div>

                                    {{-- Especialidades --}}
                                    <div class="md:col-span-2">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Especialidades
                                        </label>

                                        {{-- Tags seleccionados --}}
                                        <div id="specialtyList" class="flex flex-wrap gap-2 mb-3">

                                        </div>

                                        {{-- Select --}}
                                        <select id="specialtySelect" class="w-full rounded-lg border-gray-300">

                                            <option value="">
                                                Seleccione una especialidad
                                            </option>

                                            @foreach ($specialties as $specialty)
                                                <option value="{{ $specialty->id }}">
                                                    {{ $specialty->name }}
                                                </option>
                                            @endforeach

                                        </select>

                                    </div>

                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end gap-2">

                                <button @click="close()"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                                    Cancelar

                                </button>

                                <button @click="submit()" style="background-color: {{ themeColor(500) }}"
                                    class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                                    Guardar

                                </button>

                            </div>

                        </div>

                    </div>

                </div>
            </div>
        </div>
    </div>


    <!-- MODAL HORARIOS -->
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
                    Horarios del Profesional
                </h2>

                <p class="text-sm text-gray-600 mt-1" x-text="professional?.name"></p>

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

                    <template x-for="schedule in schedules" :key="schedule.id">

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

                                    <p class="text-sm text-gray-500">

                                        <span x-text="schedule.start_time.substring(0, 5)"></span>

                                        <span class="mx-1">-</span>

                                        <span x-text="schedule.end_time.substring(0, 5)"></span>

                                    </p>

                                </div>

                            </div>

                            <!-- BOTÓN ELIMINAR -->
                            <button class="text-red-500 hover:text-red-700 transition" @click="remove(schedule.id)"
                                title="Eliminar horario">
                                <i class="fa-solid fa-trash"></i>
                            </button>

                        </div>

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



    <!-- MODAL VER PROFESIONAL -->

    <div x-cloak x-data="ProfessionalViewModal()" x-ref="viewProfessionalComponent" x-show="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b rounded-t-lg flex-shrink-0"
                style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                <h2 class="text-lg font-bold text-gray-800">
                    Información del Profesional
                </h2>
            </div>


            <!-- BODY -->
            <div class="p-6 overflow-y-auto flex-1">

                <!-- INFORMACIÓN GENERAL -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-5">

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Nombre
                        </label>

                        <p class="mt-1 text-gray-900 font-medium" x-text="professional?.name || '-'"></p>
                    </div>

                    <!-- RUT -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Rut
                        </label>

                        <p class="mt-1 text-gray-900" x-text="professional?.rut || '-'"></p>
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Celular
                        </label>

                        <p class="mt-1 text-gray-900" x-text="professional?.phone || '-'"></p>
                    </div>

                    <!-- Correo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Correo
                        </label>

                        <p class="mt-1 text-gray-900 break-words" x-text="professional?.email || '-'"></p>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Estado
                        </label>

                        <div class="mt-1">

                            <span class="inline-block px-2 py-1 rounded text-xs text-white"
                                :class="professional?.status == 1 ?
                                    'bg-green-500' :
                                    'bg-red-500'"
                                x-text="professional?.status == 1
                            ? 'Activo'
                            : 'Inactivo'"></span>

                        </div>
                    </div>

                    <!-- ESPECIALIDADES -->
                    <div class="md:col-span-2">

                        <label class="block text-sm font-medium text-gray-500 mb-2">
                            Especialidades
                        </label>

                        <div class="flex flex-wrap gap-2">

                            <template x-if="professional?.specialties?.length">

                                <template x-for="specialty in professional.specialties" :key="specialty.id">

                                    <span
                                        class="inline-flex items-center bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-sm"
                                        x-text="specialty.name"></span>

                                </template>

                            </template>


                            <template x-if="!professional?.specialties?.length">

                                <span class="text-gray-500 text-sm">
                                    Sin especialidades
                                </span>

                            </template>

                        </div>

                    </div>

                    <!-- SEPARADOR -->
                    <div class="md:col-span-2 border-t pt-2"></div>

                    <!-- HORARIOS -->
                    <div class="md:col-span-2">

                        <!-- TÍTULO -->
                        <div class="flex items-center justify-between mb-3">

                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Horarios de atención
                                </label>
                            </div>


                            <!-- LOADING -->
                            <template x-if="loading">

                                <i class="fa-solid fa-spinner fa-spin text-gray-400"></i>

                            </template>

                        </div>


                        <!-- SIN HORARIOS -->
                        <template x-if="!loading && schedules.length === 0">

                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 text-center">

                                <i class="fa-regular fa-calendar-xmark text-gray-400 text-2xl mb-2"></i>

                                <p class="text-sm text-gray-500">
                                    No hay horarios registrados.
                                </p>

                            </div>

                        </template>


                        <!-- LISTA DE HORARIOS -->
                        <template x-if="!loading && schedules.length > 0">

                            <!-- CONTENEDOR CON SCROLL -->
                            <div
                                class="border border-gray-200 rounded-lg bg-gray-50 p-3 max-h-64 overflow-y-auto space-y-2">

                                <template x-for="schedule in schedules" :key="schedule.id">

                                    <div
                                        class="bg-white border border-gray-200 rounded-lg px-4 py-3 flex items-center justify-between shadow-sm">

                                        <!-- INFORMACIÓN -->
                                        <div class="flex items-center gap-3 min-w-0">

                                            <!-- ICONO -->
                                            <div class="w-9 h-9 rounded-full flex-shrink-0 flex items-center justify-center"
                                                style="background-color: {{ themeColor(100) }}">

                                                <i class="fa-regular fa-calendar-days"
                                                    style="color: {{ themeColor(600) }}"></i>

                                            </div>


                                            <!-- DÍA Y HORA -->
                                            <div class="min-w-0">

                                                <p class="font-medium text-gray-800" x-text="days[schedule.day_of_week]">
                                                </p>

                                                <p class="text-sm text-gray-500">

                                                    <span x-text="formatTime(schedule.start_time)"></span>

                                                    <span class="mx-1">
                                                        -
                                                    </span>

                                                    <span x-text="formatTime(schedule.end_time)"></span>

                                                </p>

                                            </div>

                                        </div>

                                    </div>

                                </template>

                            </div>

                        </template>

                    </div>

                </div>

            </div>


            <!-- FOOTER -->
            <div class="px-6 py-4 border-t flex justify-end flex-shrink-0 bg-white">

                <button @click="close()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded transition">
                    Cerrar
                </button>

            </div>

        </div>


    </div>
@endsection

@push('scripts')
    <script>
        $(function() {
            // table
            let tableActive = $('#professional-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/professional-data',
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                responsive: false,

                columns: [{
                        data: null,
                        render: function(data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        },
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'specialties_html',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {

                            return `
                                    <button 
                                        class="toggle-status px-2 py-1 rounded text-xs text-white
                                        ${data == 1 ? 'bg-green-500' : 'bg-red-500'}"
                                        data-id="${row.id}"
                                        data-status="${data}">
                                        
                                        ${data == 1 ? 'Activo' : 'Inactivo'}
                                    </button>
                                `;
                        }
                    },


                    {
                        data: null,
                        orderable: false,
                        searchable: false,

                        render: function(data, type, row) {
                            return `<button class="schedule-btn text-orange-500 mr-2" title="Horarios">
                                        <i class="fa-regular fa-calendar-days"></i>
                                    </button>
                                    <button class="edit-professional-btn text-blue-500 mr-2">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>
                                    <button class="view-professional-btn text-yellow-500 mr-2" title="Ver profesional">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                    <button class="delete-btn text-red-500 mr-2">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                `;
                        }
                    }
                ]
            });

let tableTrash = $('#professional-trash-table').DataTable({
    processing: true,
    serverSide: true,
    ajax: '/professional-trash-data',

    language: {
        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
    },

    responsive: false,

    columns: [
        {
            data: null,
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
            orderable: false,
            searchable: false
        },

        {
            data: 'name'
        },

        {
            data: 'specialties_html',
            orderable: false,
            searchable: false
        },

        {
            data: 'deleted_at',
            render: function(data) {

                if (!data) {
                    return '-';
                }

                return new Date(data).toLocaleString('es-CL');
            }
        },

        {
            data: null,
            orderable: false,
            searchable: false,

            render: function(data, type, row) {

                return `

                    <button class="view-professional-btn text-yellow-500 mr-2" title="Ver profesional">
                                        <i class="fa-regular fa-eye"></i>
                    </button>
                    <button
                        class="restore-professional-btn text-green-500 mr-3"
                        title="Restaurar profesional">

                        <i class="fa-solid fa-rotate-left"></i>

                    </button>

                    <button
                        class="force-delete-professional-btn text-red-600"
                        title="Eliminar permanentemente">

                        <i class="fa-solid fa-trash-can"></i>

                    </button>
                `;
            }
        }
    ]
});

            // EDITAR
            $('#professional-table').on('click', '.edit-professional-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('edit-professional', {
                    detail: data
                }));
            });


            // VER PROFESIONAL
            $('#professional-table').on('click', '.view-professional-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('view-professional', {
                    detail: data
                }));
            });


            // VER PROFESIONAL DESDE PAPELERA
            $('#professional-trash-table').on('click', '.view-professional-btn', function() {

                let data = tableTrash.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('view-professional', {
                    detail: data
                }));
            });
            // CAMBIAR ESTADO
            $('#professional-table').on('click', '.toggle-status', function() {

                let id = $(this).data('id');

                let currentStatus = $(this).data('status');

                let newStatus = currentStatus == 1 ? 0 : 1;

                fetch(`/professional/${id}/toggle-status`, {

                        method: 'PATCH',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]').content
                        },

                        body: JSON.stringify({
                            status: newStatus
                        })

                    })
                    .then(res => res.json())
                    .then(data => {

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Estado actualizado',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        $('#professional-table').DataTable().ajax.reload(null, false);
                    });
            });



            //eliminar logico
            $('#professional-table').on('click', '.delete-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                Swal.fire({
                    title: '¿Eliminar Profesional?',
                    text: "Esto lo movera a la papelera.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    confirmButtonColor: '#dc2626',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {

                    if (!result.isConfirmed) return;

                    fetch(`/professional/${data.id}`, {
                            method: 'DELETE',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content
                            }
                        })
                        .then(res => res.json())
                        .then(() => {

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Profesional eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            $('#professional-table').DataTable().ajax.reload(null, false);
                            $('#professional-trash-table').DataTable().ajax.reload(null, false);
                        });

                });

            });

            //horarios
            $('#professional-table').on('click', '.schedule-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('professional-schedule', {
                    detail: data
                }));

            });

$('#professional-trash-table').on(
    'click',
    '.restore-professional-btn',
    function() {

        let data = tableTrash
            .row($(this).closest('tr'))
            .data();

        Swal.fire({
            title: '¿Restaurar Profesional?',
            text: 'El profesional volverá a la lista de profesionales.',
            icon: 'question',

            showCancelButton: true,

            confirmButtonText: 'Sí, restaurar',
            confirmButtonColor: '#16a34a',

            cancelButtonText: 'Cancelar'
        }).then((result) => {

            if (!result.isConfirmed) return;

            fetch(`/professional/${data.id}/restore`, {

                method: 'PATCH',

                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',

                    'X-CSRF-TOKEN':
                        document.querySelector(
                            'meta[name="csrf-token"]'
                        ).content
                }

            })
            .then(async res => {

                const response = await res.json();

                if (!res.ok) {
                    throw new Error(
                        response.message ||
                        'No se pudo restaurar el profesional.'
                    );
                }

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Profesional restaurado',
                    showConfirmButton: false,
                    timer: 1500
                });

                tableTrash.ajax.reload(null, false);

                tableActive.ajax.reload(null, false);
            })
            .catch(error => {

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: error.message
                });

            });

        });
    }
);


        });


        // EVENTO VER PROFESIONAL
        window.addEventListener('view-professional', e => {

            Alpine
                .$data(document.querySelector('[x-ref="viewProfessionalComponent"]'))
                .openModal(e.detail);

        });

        

        function ProfessionalViewModal() {

            return {

                open: false,

                loading: false,

                professional: null,

                schedules: [],

                days: {
                    0: 'Domingo',
                    1: 'Lunes',
                    2: 'Martes',
                    3: 'Miércoles',
                    4: 'Jueves',
                    5: 'Viernes',
                    6: 'Sábado'
                },

                openModal(data) {

                    this.professional = data;

                    this.schedules = [];

                    this.open = true;

                    this.loadSchedules();
                },

                loadSchedules() {

                    if (!this.professional?.id) {
                        return;
                    }

                    this.loading = true;

                    fetch(`/professional/${this.professional.id}/schedule`, {
                            method: 'GET',

                            headers: {
                                'Accept': 'application/json'
                            }
                        })
                        .then(async res => {

                            const data = await res.json();

                            if (!res.ok) {
                                throw new Error(
                                    data.message || 'No se pudieron cargar los horarios.'
                                );
                            }

                            this.schedules = data;

                        })
                        .catch(error => {

                            console.error(error);

                            this.schedules = [];

                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: error.message
                            });

                        })
                        .finally(() => {

                            this.loading = false;

                        });
                },

                formatTime(time) {

                    if (!time) {
                        return '-';
                    }

                    return time.substring(0, 5);
                },

                close() {

                    this.open = false;

                    this.professional = null;

                    this.schedules = [];

                    this.loading = false;
                }
            }
        }

        function ProfessionalModal() {

            return {
                tab: 'active',
                activeLoaded: false,
                trashLoaded: false,
                open: false,
                isEdit: false,
                appDomain: '{{ config('app.domain') }}',


                form: {
                    id: null,
                    name: '',
                    email: '',
                    password: '',
                    specialties: [],
                    status: '1',
                    rut: '',
                    phone: ''
                },

                errors: {
                    name: null,
                },

                clearError(field) {

                    if (this.errors[field]) {
                        this.errors[field] = null;
                    }
                },

                create() {

                    this.isEdit = false;

                    this.form = {
                        id: null,
                        name: '',
                        email: '',
                        password: '',
                        specialties: [],
                        status: '1',
                        rut: '',
                        phone: ''
                    };
                    selectedSpecialties = [];

                    document.getElementById('specialtyList').innerHTML = '';

                    document.getElementById('specialtySelect').value = '';

                    this.errors = {};

                    this.open = true;
                },

                edit(data) {

                    this.isEdit = true;

                    this.form = {
                        id: Number(data.id),
                        name: data.name,
                        email: data.email,
                        rut: data.rut,
                        phone: data.phone,
                        password: '',
                        specialties: data.specialties.map(s => String(s.id)),
                        status: String(data.status)
                    };

                    this.errors = {};

                    this.open = true;
                },




                close() {

                    this.open = false;

                    this.errors = {};

                    this.form = {
                        id: null,
                        name: '',
                        email: '',
                        password: '',
                        specialties: [],
                        status: '1',
                        rut: '',
                        phone: ''
                    };

                    // limpiar especialidades visuales
                    selectedSpecialties = [];

                    document.getElementById('specialtyList').innerHTML = '';

                    document.getElementById('specialtySelect').value = '';
                },

                submit() {

                    fetch(this.isEdit ? `/professional/${this.form.id}` : '/professional', {

                            method: this.isEdit ? 'PUT' : 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content
                            },

                            body: JSON.stringify({
                                ...this.form
                            })
                        })

                        .then(async res => {

                            const data = await res.json();

                            // ERROR
                            if (!res.ok) {

                                this.errors = data.errors || {};

                                return;
                            }

                            // LIMPIAR
                            this.errors = {};

                            // TOAST
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: this.isEdit ?
                                    'Actualizado correctamente' : 'Creado correctamente',
                                showConfirmButton: false,
                                timer: 2000
                            });

                            this.close();

                            $('#professional-table').DataTable().ajax.reload();
                        });
                }
            }
        }

        // EVENTO EDITAR
        window.addEventListener('edit-professional', e => {

            Alpine
                .$data(document.querySelector('[x-ref="professionalComponent"]'))
                .edit(e.detail);
        });

        //evento horario
        window.addEventListener('professional-schedule', e => {

            Alpine
                .$data(document.querySelector('[x-ref="scheduleComponent"]'))
                .open(e.detail);

        });


        function ProfessionalScheduleModal() {

            return {

                show: false,
                loading: false,
                professional: null,

                schedules: [],
                days: {
                    0: 'Domingo',
                    1: 'Lunes',
                    2: 'Martes',
                    3: 'Miércoles',
                    4: 'Jueves',
                    5: 'Viernes',
                    6: 'Sábado'
                },
                form: {
                    day_of_week: '',
                    start_time: '',
                    end_time: ''
                },

                open(data) {
                    this.professional = data;
                    this.show = true;

                    this.loadSchedules();
                },

                close() {
                    this.show = false;
                    this.professional = null;

                    this.schedules = [];

                    this.form = {
                        day_of_week: '',
                        start_time: '',
                        end_time: ''
                    };
                },

                save() {

                    fetch(`/professional/${this.professional.id}/schedule`, {

                            method: 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },

                            body: JSON.stringify(this.form)

                        })
                        .then(async res => {

                            const data = await res.json();

                            if (!res.ok) {

                                let message = 'Ocurrió un error al guardar el horario';


                                if (data.message) {

                                    message = data.message;

                                }


                                if (data.errors) {

                                    message = Object.values(data.errors)
                                        .flat()
                                        .join('<br>');

                                }


                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    html: message,
                                    confirmButtonText: 'Aceptar'
                                });

                                return;
                            }

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Horario agregado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            // Limpiar formulario
                            this.form = {
                                day_of_week: '',
                                start_time: '',
                                end_time: ''
                            };

                            // Recargar horarios
                            this.loadSchedules();

                        });

                },

                loadSchedules() {

                    this.loading = true;

                    fetch(`/professional/${this.professional.id}/schedule`)
                        .then(res => res.json())
                        .then(data => {
                            this.schedules = data;
                        })
                        .finally(() => {
                            this.loading = false;
                        });

                },

                remove(id) {

                    fetch(`/professional/schedule/${id}`, {

                            method: 'DELETE',

                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            }

                        })
                        .then(res => res.json())
                        .then(() => {

                            this.loadSchedules();

                        });

                }
            }
        }

        const specialtySelect = document.getElementById('specialtySelect');
        const specialtyList = document.getElementById('specialtyList');


        let selectedSpecialties = [];


        specialtySelect.addEventListener('change', function() {

            let id = this.value;
            let name = this.options[this.selectedIndex].text;


            if (!id) return;


            // evitar repetidos
            if (selectedSpecialties.includes(id)) {
                this.value = '';
                return;
            }


            selectedSpecialties.push(id);

            let component = Alpine.$data(document.querySelector('[x-ref="professionalComponent"]'));

            component.form.specialties = selectedSpecialties;


            let div = document.createElement('div');

            div.className =
                'specialty-item inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-3 py-2 rounded-full';


            div.innerHTML = `

                <span>
                    ${name}
                </span>

                <input 
                    type="hidden"
                    name="specialties[]"
                    value="${id}">

                <button 
                    type="button"
                    class="remove-specialty">

                    <i class="fa-solid fa-xmark"></i>

                </button>

            `;


            specialtyList.appendChild(div);


            this.value = '';

        });



        document.addEventListener('click', function(e) {


            const btn = e.target.closest('.remove-specialty');


            if (!btn) return;


            const item = btn.closest('.specialty-item');


            const id = item.querySelector('input').value;


            selectedSpecialties =
                selectedSpecialties.filter(x => x != id);
            let component = Alpine.$data(document.querySelector('[x-ref="professionalComponent"]'));

            component.form.specialties = selectedSpecialties;

            item.remove();


        });
    </script>
@endpush
