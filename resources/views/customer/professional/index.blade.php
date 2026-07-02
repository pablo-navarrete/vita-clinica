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

                    <!-- TABLA Activos-->
                    <div class="overflow-x-auto" x-show="tab === 'active'">

                        <!-- BOTÓN -->
                        <div class="flex justify-end mb-4">
                            <button
                                @click="create()"
                                style="background-color: {{ themeColor(500) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus"></i>
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

                    <!-- MODAL CREAR / EDITAR -->
                    <div x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 overflow-visible">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-md relative overflow-visible">

                            <!-- HEADER -->
                            <div
                                class="px-6 py-4 border-b rounded-t-lg"
                                style="
                                    background-color: {{ themeColor(100) }};
                                    border-color: {{ themeColor(200) }};
                                ">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar Profesional' : 'Crear Nuevo Profesional'">
                                </h2>
                            </div>

                            <!-- BODY -->
                            <div class="px-6 py-4 space-y-4 overflow-visible">

                                <!-- NOMBRE -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre del Profesional
                                    </label>

                                    <input
                                        type="text"
                                        x-model="form.name"
                                        @input="clearError('name')"
                                        :class="errors.name ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full rounded-md border px-3 py-2 focus:ring focus:ring-blue-200">

                                    <template x-if="errors.name">
                                        <p class="mt-1 text-xs text-red-500" x-text="errors.name[0]"></p>
                                    </template>
                                </div>

                                <!-- ESPECIALIDAD -->
                                <div x-data="specialtySelect()" class="relative overflow-visible">

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Especialidad
                                    </label>

                                    <input
                                        type="text"
                                        x-model="search"
                                        @focus="open = true"
                                        @click="open = true"
                                        placeholder="Buscar especialidad..."
                                        class="w-full rounded-md border px-3 py-2">

                                    <template x-if="errors.specialty">
                                        <p class="mt-1 text-xs text-red-500" x-text="errors.specialty[0]"></p>
                                    </template>

                                    <!-- DROPDOWN -->
                                    <div
                                        x-show="open"
                                        @click.away="open = false"
                                        class="absolute top-full left-0 w-full mt-1 bg-white border rounded-md max-h-60 overflow-auto z-[9999]">

                                        <template x-for="item in filtered()" :key="item">
                                            <div
                                                @click="form.specialty = item; search = item; open = false"
                                                class="px-3 py-2 cursor-pointer hover:bg-gray-100"
                                                x-text="item">
                                            </div>
                                        </template>

                                    </div>

                                </div>

                                <!-- ESTADO -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Estado
                                    </label>

                                    <select
                                        x-model="form.status"
                                        class="w-full rounded-md border border-gray-300 px-3 py-2">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end gap-2">

                                <button
                                    @click="close()"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                    Cancelar
                                </button>

                                <button
                                    @click="submit()"
                                    style="background-color: {{ themeColor(500) }}"
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
                        data: 'specialty'
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
                            return `
                                    <button class="edit-professional-btn text-blue-500 mr-2">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button class="delete-btn text-red-500 mr-2">
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
                    text: "Esto lo eliminará del sistema permanentemente.",
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
                        });

                });

            });



        });

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
                    specialty: '',
                    status: '1'
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
                        specialty: '',
                        status: '1'
                    };

                    this.errors = {};

                    this.open = true;
                },

                edit(data) {

                    this.isEdit = true;

                    this.form = {
                        id: Number(data.id),
                        name: data.name,
                        specialty: data.specialty,
                        status: String(data.status)
                    };

                    this.errors = {};

                    this.open = true;
                },




                close() {

                    this.open = false;

                    this.errors = {};
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
    </script>

    <script>
        function specialtySelect() {
            return {
                open: false,
                search: '',

                specialties: [
                    'Cardiología',
                    'Pediatría',
                    'Medicina General',
                    'Neurología',
                    'Dermatología',
                    'Ginecología',
                    'Traumatología',
                    'Psiquiatría',
                    'Oftalmología',
                    'Otorrinolaringología',
                    'Urología',
                    'Oncología',
                    'Anestesiología',
                    'Endocrinología',
                    'Gastroenterología',
                    'Nefrología',
                    'Reumatología',
                    'Fisioterapia',
                    'Odontología',
                    'Nutrición',
                    'Psicología',
                    'Enfermería',
                    'Medicina Interna',

                    // Ingeniería
                    "Ingeniería Civil",
                    "Ingeniería Informática",
                    "Ingeniería Industrial",
                    "Ingeniería Comercial",

                    // Sociales
                    "Derecho",
                    "Trabajo Social",
                    "Periodismo",

                    // Educación
                    "Pedagogía",
                    "Educación Parvularia",

                    // Administración
                    "Contabilidad",
                    "Administración de Empresas",

                    "Otros"
                ],

                filtered() {
                    return this.specialties.filter(item =>
                        item.toLowerCase().includes(this.search.toLowerCase())
                    );
                }
            }
        }
    </script>
@endpush
