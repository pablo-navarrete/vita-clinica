@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Gestión de Logos
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="LogoModal()" x-ref="logoComponent">
                <div class="mb-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <div class="flex items-start">
                        <i class="fa-solid fa-circle-info text-blue-600 mt-1 mr-3"></i>

                        <div>
                            <h3 class="font-semibold text-blue-800">
                                Información
                            </h3>

                            <p class="text-sm text-blue-700 mt-1">
                                Solo se permiten como máximo <strong>5 logos</strong>. Si ya alcanzaste el límite,
                                deberás eliminar uno existente antes de registrar uno nuevo.
                            </p>
                        </div>
                    </div>
                </div>
                <!-- BOTÓN CREAR -->
                <div class="flex justify-end mb-4">
                    @if ($logosCount >= 5)
                        <button disabled style="background-color: {{ themeColor(300) }}"
                            class=" text-white font-bold py-2 px-4 rounded">
                            <i class="fa-solid fa-circle-plus"></i>
                            Nuevo
                        </button>
                    @else
                        <button @click="create()" style="background-color: {{ themeColor(600) }}"
                            class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                            <i class="fa-solid fa-circle-plus"></i>
                            Nuevo
                        </button>
                    @endif

                </div>
                <div class="overflow-x-auto">
                    <!-- TABLE -->
                    <table id="logo-table" class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                        <thead style="background-color: {{ themeColor(100) }}">
                            <tr>
                                <th>#</th>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Ubicaciones</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                    </table>
                </div>


                <!-- MODAL -->
                <div x-cloak x-show="open"
                    class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">

                    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg">

                        <!-- HEADER -->
                        <div class="px-6 py-4 border-b">
                            <h2 class="text-lg font-bold" x-text="isEdit ? 'Editar Logo' : 'Nuevo Logo'"></h2>
                        </div>

                        <!-- BODY -->
                        <div class="px-6 py-4 space-y-4">

                            <!-- IMAGEN -->
                            <div class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:bg-gray-50"
                                @dragover.prevent @drop.prevent="handleDrop($event)" @click="$refs.fileInput.click()">

                                <input type="file" class="hidden" x-ref="fileInput" accept="image/*"
                                    @change="handleFile($event)">

                                <template x-if="!previewUrl">
                                    <div class="text-gray-500">
                                        <i class="fa-solid fa-cloud-arrow-up text-2xl"></i>
                                        <p>Arrastra una imagen aquí o haz click</p>
                                    </div>
                                </template>

                                <template x-if="previewUrl">
                                    <div>
                                        <p class="text-sm text-green-600 mb-2">Imagen lista</p>
                                        <img :src="previewUrl" class="mx-auto max-h-32 rounded">
                                    </div>
                                </template>

                            </div>

                            <!-- NOMBRE -->
                            <div>
                                <label class="block text-sm font-medium mb-1">Nombre</label>

                                <input type="text" x-model="form.name" class="w-full border rounded px-3 py-2">
                            </div>

                            <!-- UBICACIONES -->
                            <div class="border rounded-lg p-4 bg-gray-50">

                                <label class="block text-sm font-medium mb-3 text-gray-700">
                                    Ubicaciones
                                </label>

                                <div class="grid grid-cols-2 gap-2">

                                    <template x-for="loc in locationsList" :key="loc">

                                        <label
                                            class="flex items-center gap-2 p-2 rounded-md border bg-white hover:bg-gray-100 cursor-pointer">

                                            <input type="checkbox" :value="loc" x-model="form.locations"
                                                class="rounded">

                                            <span class="text-sm" x-text="loc"></span>

                                        </label>

                                    </template>

                                </div>

                            </div>

                            <!-- ESTADO -->
                            <div>
                                <label class="block text-sm font-medium mb-1">Estado</label>

                                <select x-model="form.status" class="w-full border rounded px-3 py-2">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>

                        </div>

                        <!-- FOOTER -->
                        <div class="px-6 py-4 border-t flex justify-end gap-2">

                            <button @click="close()" class="bg-gray-500 text-white px-4 py-2 rounded">
                                Cancelar
                            </button>

                            <button @click="submit()" style="background-color: {{ themeColor(500) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                Guardar
                            </button>

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

            let table = $('#logo-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '/logo-data',
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                responsive: false,
                autoWidth: false,

                columns: [

                    {
                        data: null,
                        name: 'index',
                        orderable: false,
                        searchable: false,
                        render: (d, t, r, m) =>
                            m.row + m.settings._iDisplayStart + 1
                    },

                    {
                        data: 'url',
                        render: function(data) {

                            if (!data) return '';

                            let path = data.replace(/^\/?storage\//, '');

                            return `
                                <div class="w-16 h-16 flex items-center justify-center border rounded bg-gray-50">
                                    <img src="/storage/${path}"
                                        class="preview-logo cursor-pointer max-w-full max-h-full object-contain">
                                </div>
                            `;
                        }
                    },

                    {
                        data: 'name'
                    },

                    {
                        data: 'locations',
                        orderable: false,
                        searchable: false,
                        render: function(data) {
                            return data;
                        }
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
                                <button class="edit-assistant-btn text-blue-500 mr-2" data-id="${row.id}">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>

                                <button class="delete-btn text-red-500 mr-2" data-id="${row.id}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>               
                            `;
                        }
                    }
                ]
            });

            // STATUS
            $('#logo-table').on('click', '.toggle-status', function() {

                let id = $(this).data('id');

                fetch(`/logo/${id}/toggle-status`, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            'Accept': 'application/json'
                        }
                    })
                    .then(async response => {

                        const data = await response.json();

                        if (!response.ok) {
                            throw data;
                        }

                        location.reload();

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Estado actualizado',
                            showConfirmButton: false,
                            timer: 1500
                        });

                    })
                    .catch(error => {

                        Swal.fire({
                            icon: 'warning',
                            title: 'No se puede activar',
                            text: error.message ||
                                'Ya existe otro logo activo en una de las ubicaciones.'
                        });


                    });
            });

            //eliminar fisico
            $('#logo-table').on('click', '.delete-btn', function() {

                let id = $(this).data('id');

                Swal.fire({
                    title: '¿Eliminar Logo?',
                    text: "Esto lo eliminará del sistema permanentemente.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Sí, eliminar',
                    confirmButtonColor: '#dc2626',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {

                    if (!result.isConfirmed) return;

                    fetch(`/logo/${id}`, {
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
                                title: 'Logo eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            location.reload();
                        });

                });

            });


            $('#logo-table').on('click', '.edit-assistant-btn', function() {

                let data = table.row($(this).closest('tr')).data();

                window.dispatchEvent(new CustomEvent('edit-logo', {
                    detail: data
                }));
            });

            $('#logo-table').on('click', '.preview-logo', function() {

                Swal.fire({
                    html: `
                        <div class="flex justify-center">
                            <img src="${$(this).attr('src')}"
                                style="
                                    width: 600px;
                                    height: 400px;
                                    object-fit: contain;
                                ">
                        </div>
                    `,
                    width: '750px',
                    showCloseButton: true,
                    showConfirmButton: false
                });

            });
        });

        function LogoModal() {
            return {

                open: false,
                isEdit: false,
                previewUrl: null,
                errors: {},
                errorMessage: '',
                locationsList: ['header', 'footer', 'panel', 'login'],

                form: {
                    id: null,
                    name: '',
                    image: null,
                    locations: [],
                    status: '1'
                },

                // =========================
                // FILE HANDLERS
                // =========================
                handleFile(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    this.form.image = file;
                    this.previewUrl = URL.createObjectURL(file);
                },

                handleDrop(event) {
                    const file = event.dataTransfer.files[0];
                    if (!file) return;

                    this.form.image = file;
                    this.previewUrl = URL.createObjectURL(file);
                },

                clearImage() {
                    this.form.image = null;
                    this.previewUrl = null;
                },

                // =========================
                // CREATE
                // =========================
                create() {
                    this.isEdit = false;
                    this.open = true;

                    this.previewUrl = null;

                    this.form = {
                        id: null,
                        name: '',
                        image: null,
                        locations: [],
                        status: '1'
                    };
                },

                // =========================
                // EDIT
                // =========================
                edit(data) {

                    this.isEdit = true;
                    this.open = true;

                    this.previewUrl = data.url ?
                        `/storage/${data.url}` :
                        null;

                    this.form = {
                        id: data.id,
                        name: data.name,
                        image: null,
                        locations: data.locations_array ?? [],
                        status: String(data.status)
                    };
                },

                // =========================
                // CLOSE
                // =========================
                close() {
                    this.open = false;

                    // limpiar estado para evitar bugs al reabrir
                    this.previewUrl = null;
                    this.isEdit = false;
                },

                // =========================
                // SUBMIT
                // =========================
                submit() {

                    this.errors = {};
                    this.errorMessage = '';

                    let fd = new FormData();

                    fd.append('name', this.form.name);
                    fd.append('status', this.form.status);



                    if (this.form.image) {
                        fd.append('image', this.form.image);
                    }

                    this.form.locations.forEach(l => {
                        fd.append('locations[]', l);
                    });

                    let url = this.isEdit ?
                        `/logo/${this.form.id}` :
                        '/logo';

                    fetch(url, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
                            body: fd
                        })
                        .then(async (r) => {

                            const data = await r.json();

                            // ERROR (VALIDATION O LOGIC ERROR)
                            if (!r.ok) {

                                // Laravel validation errors
                                if (data.errors) {
                                    this.errors = data.errors;
                                }

                                if (data.message) {
                                    this.errorMessage = data.message;

                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: data.message
                                    });
                                }

                                return;
                            }

                            // SUCCESS
                            Swal.fire({
                                toast: true,
                                icon: 'success',
                                title: this.isEdit ? 'Actualizado' : 'Creado',
                                timer: 1500,
                                showConfirmButton: false
                            });

                            this.close();
                            location.reload();
                        })
                        .catch(err => {

                            this.errorMessage = 'Error inesperado';

                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error inesperado del servidor'
                            });
                        });
                }
            }
        }

        window.addEventListener('edit-logo', e => {

            Alpine
                .$data(document.querySelector('[x-ref="logoComponent"]'))
                .edit(e.detail);
        });
    </script>
@endpush
