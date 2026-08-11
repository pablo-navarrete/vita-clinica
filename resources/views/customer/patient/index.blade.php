@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Pacientes') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900" x-data="PatientModal()" x-ref="patientComponent">

                    <!-- TABLA Activos-->
                    <div>
                        <!-- BOTÓN -->
                        <div class="flex justify-end mb-4">
                     
                         <button
                            @click="create()"
                            style="background-color: {{ themeColor(600) }}"
                            class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                            <i class="fa-solid fa-circle-plus mr-1"></i>
                            Nuevo
                        </button>
                        
                        </div>
                        <table id="patient-table" class="min-w-full display responsive rounded-xl ">
                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Rut</th>
                                    <th>Teléfono</th>
                                    <th>Email</th>
                                    <th>Sexo</th>
                                    <th>Previsión</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                        </table>
                    </div>

                    <!-- MODAL CREAR / EDITAR PACIENTE -->
                    <div x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">

                            <!-- HEADER -->
                            <div class="px-6 py-4 border-b rounded-t-lg " style="background-color: {{ themeColor(100) }}">
                                <h2 class="text-lg font-bold" x-text="isEdit ? 'Editar Paciente' : 'Nuevo Paciente'">
                                </h2>
                            </div>

                            <!-- BODY -->
                            <div class="p-6 space-y-4 overflow-y-auto flex-1">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                                    <!-- NOMBRE -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Nombre Completo *
                                        </label>

                                        <input type="text" x-model="form.name" @input="clearError('name')"
                                            class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.name">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.name[0]"></p>
                                        </template>
                                    </div>

                                    <!-- RUT -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            RUT *
                                        </label>

                                        <input type="text" x-model="form.rut" @input="clearError('rut')"
                                            class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.rut">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.rut[0]"></p>
                                        </template>
                                    </div>

                                    <!-- FECHA NACIMIENTO -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Fecha Nacimiento
                                        </label>

                                        <input type="date" x-model="form.birth_date" @input="clearError('birth_date')"
                                            max="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.birth_date">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.birth_date[0]"></p>
                                        </template>
                                    </div>

                                    <!-- SEXO -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Sexo
                                        </label>

                                        <select x-model="form.gender" class="w-full rounded-lg border-gray-300">

                                            <option value="Masculino">Masculino</option>
                                            <option value="Femenino">Femenino</option>
                                            <option value="Otro">Otro</option>
                                        </select>
                                    </div>

                                    <!-- TELÉFONO -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Teléfono
                                        </label>

                                        <input type="text" x-model="form.phone" @input="clearError('phone')"
                                            maxlength="20" class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.phone">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.phone[0]"></p>
                                        </template>
                                    </div>

                                    <!-- EMAIL -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Correo Electrónico
                                        </label>

                                        <input type="email" x-model="form.email" @input="clearError('email')"
                                            class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.email">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.email[0]"></p>
                                        </template>
                                    </div>

                                    <!-- DIRECCIÓN -->
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Dirección
                                        </label>

                                        <input type="text" x-model="form.address" @input="clearError('address')"
                                            maxlength="255" class="w-full rounded-lg border-gray-300">

                                        <template x-if="errors.address">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.address[0]"></p>
                                        </template>
                                    </div>
                                    <!-- prevision -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Previsión
                                        </label>

                                        <select x-model="form.insurance_id" class="w-full rounded-lg border-gray-300">
                                            @foreach ($insurances as $insurance )
                                                <option value="{{ $insurance->id }}">{{ $insurance->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <!-- ESTADO -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Estado
                                        </label>

                                        <select x-model="form.status" class="w-full rounded-lg border-gray-300">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                    </div>

                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end gap-2">

                                <button @click="close()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                    Cancelar
                                </button>

                               <button
                                    @click="submit()"
                                    style="background-color: {{ themeColor(600) }}"
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
            let tableActive = $('#patient-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/patient-data',
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                responsive: true,
                autoWidth: false,

                columns: [

                    {
                        data: null,
                        render: (data, type, row, meta) =>
                            meta.row + meta.settings._iDisplayStart + 1,
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'name'
                    },
                    {
                        data: 'rut'
                    },
                    {
                        data: 'phone'
                    },
                    {
                        data: 'email'
                    },
                    {
                        data: 'gender',
                       
                    },
                    {
                        data: 'insurance',
                       
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
                                <div x-data="{ open: false }" class="relative">

                                    <button
                                        @click="open = !open"
                                        class="px-2 py-1 rounded hover:bg-gray-100">

                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                    </button>

                                    <div
                                        x-show="open"
                                        @click.outside="open = false"
                                        x-transition
                                        class="absolute right-0 mt-2 w-52 bg-white border rounded-lg shadow-lg z-50">

                                        <button
                                            class="view-patient-btn w-full text-left px-4 py-2 hover:bg-gray-100"
                                            data-id="${row.id}">
                                            <i class="fa-solid fa-user mr-2"></i>
                                            Ver ficha
                                        </button>

                                        <button
                                            class="edit-patient-btn w-full text-left px-4 py-2 hover:bg-gray-100"
                                            data-id="${row.id}">
                                            <i class="fa-solid fa-pen mr-2"></i>
                                            Editar
                                        </button>

                                        <button
                                            class="appointment-btn w-full text-left px-4 py-2 hover:bg-gray-100"
                                            data-id="${row.id}">
                                            <i class="fa-solid fa-calendar-days mr-2"></i>
                                            Agendar cita
                                        </button>

                                    

                                        <hr>

                                        <button
                                            class="delete-btn w-full text-left px-4 py-2 text-red-600 hover:bg-red-50"
                                            data-id="${row.id}">
                                            <i class="fa-solid fa-trash mr-2"></i>
                                            Eliminar
                                        </button>

                                    </div>

                                </div>
                            `;
                        }
                    }

                ]
            });



            // EDITAR
            $('#patient-table').on('click', '.edit-patient-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('edit-patient', {
                    detail: data
                }));
            });




            // CAMBIAR ESTADO
            $('#patient-table').on('click', '.toggle-status', function() {

                let id = $(this).data('id');

                let currentStatus = $(this).data('status');

                let newStatus = currentStatus == 1 ? 0 : 1;

                fetch(`/patient/${id}/toggle-status`, {

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

                        $('#patient-table').DataTable().ajax.reload(null, false);
                    });
            });



            //eliminar logico
            $('#patient-table').on('click', '.delete-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data();

                Swal.fire({
                    title: '¿Eliminar Paciente?',
                    text: "Esto lo eliminará del sistema permanentemente.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    confirmButtonColor: '#dc2626',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {

                    if (!result.isConfirmed) return;

                    fetch(`/patient/${data.id}`, {
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
                                title: 'Paciente eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            $('#patient-table').DataTable().ajax.reload(null, false);
                        });

                });

            });

            $(document).on('click', '.view-patient-btn', function () {
                const patientId = $(this).data('id');

                window.location.href = `/patients/${patientId}/medical-record`;
            });

        });

        function PatientModal() {

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
                    rut: '',
                    birth_date: '',
                    gender: 'Masculino',
                    phone: '',
                    email: '',
                    address: '',
                    status: '1',
                    insurance_id: '{{ $insurances->first()->id ?? '' }}'
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
                        rut: '',
                        birth_date: '',
                        gender: 'Masculino',
                        phone: '',
                        email: '',
                        address: '',
                        status: '1',
                        insurance_id: '{{ $insurances->first()->id ?? '' }}'
                    };

                    this.errors = {};

                    this.open = true;
                },

                edit(data) {

                    this.isEdit = true;

                    this.form = {
                        id: Number(data.id),
                        name: data.name,
                        rut: data.rut,
                        birth_date: data.birth_date,
                        gender: data.gender,
                        phone: data.phone,
                        email: data.email,
                        address: data.address,
                        status: String(data.status),
                        insurance_id: String(data.insurance_id)
                    };

                    this.errors = {};

                    this.open = true;
                },




                close() {

                    this.open = false;

                    this.errors = {};
                },

                submit() {
                      
                    fetch(this.isEdit ? `/patient/${this.form.id}` : '/patient', {

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

                            $('#patient-table').DataTable().ajax.reload();
                        });
                }
            }
        }

        // EVENTO EDITAR
        window.addEventListener('edit-patient', e => {

            Alpine
                .$data(document.querySelector('[x-ref="patientComponent"]'))
                .edit(e.detail);
        });
    </script>
@endpush
