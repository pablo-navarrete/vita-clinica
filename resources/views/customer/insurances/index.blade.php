@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gestión de Previsiones') }}
        </h2>
@endsection

@section('content')

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900" x-data="InsurancesModal()" x-ref="insurancesComponent">

                    <!-- TABLA Activos-->
                    <div class="overflow-x-auto" x-show="tab === 'active'">

                        <!-- BOTÓN -->
                        <div class="flex justify-end mb-4">
                            <button
                                @click="create()"
                                style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus"></i>
                                Nuevo
                            </button>
                        </div>

                        <table id="insurances-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                        </table>

                    </div>

                    <!-- MODAL CREAR / EDITAR -->
                    <div x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-md">

                            <!-- HEADER -->
                            <div
                                class="px-6 py-4 border-b rounded-t-lg"
                                style="
                                    background-color: {{ themeColor(100) }};
                                    border-color: {{ themeColor(200) }};
                                ">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar Previsión' : 'Crear Nueva Previsión'">
                                </h2>
                            </div>

                            <!-- BODY -->
                            <div class="px-6 py-4 space-y-4">

                                <!-- NOMBRE -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre Previsión
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
                let tableActive  = $('#insurances-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '/insurances-data',
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    responsive: false,

                    columns: [
                    {
                        data: null,
                        render: function (data, type, row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        },
                        orderable: false,
                        searchable: false
                    },
                        {
                            data: 'name'
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
                                    <button class="edit-insurances-btn text-blue-500 mr-2">
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
                $('#insurances-table').on('click', '.edit-insurances-btn', function() {

                    let data = tableActive.row($(this).closest('tr')).data();

                    window.dispatchEvent(new CustomEvent('edit-insurances', {
                        detail: data
                    }));
                });

             


                // CAMBIAR ESTADO
                $('#insurances-table').on('click', '.toggle-status', function() {

                    let id = $(this).data('id');

                    let currentStatus = $(this).data('status');

                    let newStatus = currentStatus == 1 ? 0 : 1;

                    fetch(`/insurances/${id}/toggle-status`, {

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

                        $('#insurances-table').DataTable().ajax.reload(null, false);
                    });
                });

              

                //eliminar logico
                $('#insurances-table').on('click', '.delete-btn', function() {

                    let data = tableActive.row($(this).closest('tr')).data();

                    Swal.fire({
                        title: '¿Eliminar Previsión?',
                        text: "Esto lo eliminará del sistema permanentemente.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        confirmButtonColor: '#dc2626',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {

                        if (!result.isConfirmed) return;

                        fetch(`/insurances/${data.id}`, {
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
                                title: 'Previsión eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            $('#insurances-table').DataTable().ajax.reload(null, false);
                        });

                    });

                });

            

            });

            function InsurancesModal() {

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

                        fetch(this.isEdit ? `/insurances/${this.form.id}` : '/insurances', {

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
                                        'Actualizado correctamente' :
                                        'Creado correctamente',
                                    showConfirmButton: false,
                                    timer: 2000
                                });

                                this.close();

                                $('#insurances-table').DataTable().ajax.reload();
                            });
                    }
                }
            }

            // EVENTO EDITAR
            window.addEventListener('edit-insurances', e => {

                Alpine
                    .$data(document.querySelector('[x-ref="insurancesComponent"]'))
                    .edit(e.detail);
            });

          
        </script>


@endpush