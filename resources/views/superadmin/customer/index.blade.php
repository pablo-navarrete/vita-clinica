<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gestión de Clientes') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900" x-data="customerModal()" x-ref="customerComponent">

                    <!-- TABS -->
                    <div class="flex gap-2 mb-4">
                        <button @click="tab = 'active'"
                            :class="tab === 'active' ? 'bg-blue-500 text-white' : 'bg-gray-200'"
                            class="px-4 py-2 rounded">
                            <i class="fa-regular fa-circle-check"></i>
                            Activos
                        </button>

                        <button @click="tab = 'trash'"
                            :class="tab === 'trash' ? 'bg-red-500 text-white' : 'bg-gray-200'"
                            class="px-4 py-2 rounded">
                            <i class="fa-solid fa-trash-can"></i>
                            Papelera
                        </button>
                       
                    </div>

                    

                    <!-- TABLA Activos-->
                    <div class="overflow-x-auto" x-show="tab === 'active'">
                        <!-- BOTÓN -->
                    <div class="flex justify-end mb-4">
                        <button @click="create()"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fa-solid fa-circle-plus"></i>
                            Nuevo
                        </button>
                    </div>
                        <table id="customer-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                            <thead class="bg-blue-100">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Dominio</th>
                                    <th>Estado</th>
                                    <th>Pago</th>
                                    <th>Último pago</th>
                                    <th>Vence</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <!-- TABLA papelera-->
                    <div class="overflow-x-auto" x-show="tab === 'trash'">
                        <table id="customer-table-trash"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                            <thead class="bg-blue-100">
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Dominio</th>
                                    <th>Estado</th>
                                    <th>Pago</th>
                                    <th>Último pago</th>
                                    <th>Vence</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                    <!-- MODAL CREAR / EDITAR -->
                    <div x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl">

                            <!-- HEADER -->
                            <div class="px-6 py-4 border-b rounded-t-lg bg-blue-100">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar Cliente' : 'Crear Nuevo Cliente'"></h2>
                            </div>

                            <!-- BODY -->
                            <div class="px-6 py-4 grid grid-cols-2 gap-4">

                                <!-- NOMBRE -->
                                <div>
                                    <label class="text-sm">Nombre del Cliente</label>

                                    <input type="text"
                                        x-model="form.name"
                                        @input="clearError('name')"
                                        :class="errors.name ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.name">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.name[0]"></p>
                                    </template>
                                </div>
                                <!-- razon social-->
                                <div>
                                    <label class="text-sm">Razón Social</label>

                                    <input type="text"
                                        x-model="form.legal_name"
                                        @input="clearError('legal_name')"
                                        :class="errors.legal_name ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.legal_name">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.legal_name[0]"></p>
                                    </template>
                                </div>
                                <!-- HOLDER -->
                                <div>
                                    <label class="text-sm">Sostenedor</label>

                                    <input type="text"
                                        x-model="form.holder"
                                        @input="clearError('holder')"
                                        :class="errors.holder ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.holder">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.holder[0]"></p>
                                    </template>
                                </div>
                                <!-- RUT -->
                                <div>
                                    <label class="text-sm">RUT</label>

                                    <input type="text"
                                        x-model="form.rut"
                                        @input="clearError('rut')"
                                        :class="errors.rut ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.rut">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.rut[0]"></p>
                                    </template>
                                </div>

                                <!-- EMAIL -->
                                <div>
                                    <label class="text-sm">Email</label>

                                    <input type="email"
                                        x-model="form.email"
                                        @input="clearError('email')"
                                        :class="errors.email ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.email">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.email[0]"></p>
                                    </template>
                                </div>

                                <!-- TELÉFONO -->
                                <div>
                                    <label class="text-sm">Teléfono</label>

                                    <input type="text"
                                        x-model="form.phone"
                                        @input="clearError('phone')"
                                        :class="errors.phone ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.phone">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.phone[0]"></p>
                                    </template>
                                </div>

                                <!-- DIRECCIÓN -->
                                <div>
                                    <label class="text-sm">Dirección</label>

                                    <input type="text"
                                        x-model="form.address"
                                        @input="clearError('address')"
                                        :class="errors.address ? 'border-red-500' : 'border-gray-300'"
                                        class="w-full border rounded px-3 py-2">

                                    <template x-if="errors.address">
                                        <p class="text-red-500 text-xs mt-1"
                                            x-text="errors.address[0]"></p>
                                    </template>
                                </div>

                                <!-- ESTADO -->
                                <div>
                                    <label class="text-sm">Estado</label>

                                    <select x-model="form.status"
                                        class="w-full border rounded px-3 py-2">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>

                                <!-- DOMINIO -->
                                <div class="col-span-2">
                                    <label class="text-sm">Dominio</label>

                                    <input type="text"
                                        :value="generateDomain()"
                                        class="w-full border rounded px-3 py-2 bg-gray-100 text-gray-500"
                                        disabled>
                                </div>

                               

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end gap-2">

                                <button @click="close()"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                    Cancelar
                                </button>

                                <button @click="submit()"
                                    class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                    Guardar
                                </button>

                            </div>

                        </div>
                    </div>

                    <!-- MODAL VER -->
                    <div x-cloak x-show="viewOpen"
                        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 ">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl">

                            <!-- HEADER -->
                            <div class="px-6 py-4 border-b flex justify-between items-center rounded-t-lg bg-blue-100">
                                <h2 class="text-xl font-bold text-gray-800">
                                    Información del Cliente
                                </h2>

                                <button @click="closeView()"
                                    class="text-gray-500 hover:text-gray-700 text-xl">
                                    ✕
                                </button>
                            </div>

                            <!-- BODY -->
                            <div class="p-6 grid grid-cols-2 gap-6">

                                <div>
                                    <p class="text-sm text-gray-500">Identificador (ID)</p>
                                    <p class="font-semibold break-words" x-text="viewData.id"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Nombre</p>
                                    <p class="font-semibold break-words" x-text="viewData.name"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Razón Social</p>
                                    <p class="font-semibold break-words" x-text="viewData.legal_name"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Sostenedor</p>
                                    <p class="font-semibold break-words" x-text="viewData.holder"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="font-semibold break-words" x-text="viewData.email"></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">RUT</p>
                                    <p class="font-semibold break-words" x-text="viewData.rut"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Teléfono</p>
                                    <p class="font-semibold break-words" x-text="viewData.phone"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Dirección</p>
                                    <p class="font-semibold break-words" x-text="viewData.address"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Dominio</p>
                                    <p class="font-semibold break-words" x-text="viewData.domain"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Estado</p>

                                    <span x-show="viewData.status == 1"
                                        class="bg-green-500 text-white px-2 py-1 rounded text-xs">
                                        Activo
                                    </span>

                                    <span x-show="viewData.status == 0"
                                        class="bg-red-500 text-white px-2 py-1 rounded text-xs">
                                        Inactivo
                                    </span>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Estado de Pago</p>

                                    <span x-show="viewData.payment_status == 1"
                                        class="bg-blue-500 text-white px-2 py-1 rounded text-xs">
                                        Pagado
                                    </span>

                                    <span x-show="viewData.payment_status == 0"
                                        class="bg-gray-500 text-white px-2 py-1 rounded text-xs">
                                        Pendiente
                                    </span>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Último Pago</p>
                                    <p class="font-semibold" x-text="viewData.paid_at ?? '-'"></p>
                                </div>

                                <div>
                                    <p class="text-sm text-gray-500">Vencimiento</p>
                                    <p class="font-semibold" x-text="viewData.expires_at ?? '-'"></p>
                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end">
                                <button @click="closeView()"
                                    class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">
                                    Cerrar
                                </button>
                            </div>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function() {
                // table activos
                let tableActive  = $('#customer-table').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '/customer-data',
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    responsive: false,

                    columns: [{
                            data: 'id'
                        },
                        {
                            data: 'name'
                        },
                        {
                            data: 'domain',
                                render: function(data) {
                                    return data + '.{{ config('app.domain') }}';
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
                            data: 'payment_status',
                            render: function(data, type, row) {

                                return `
                                    <button 
                                        class="toggle-payment-status px-2 py-1 rounded text-xs text-white
                                        ${data == 1 ? 'bg-blue-500' : 'bg-gray-500'}"
                                        data-id="${row.id}"
                                        data-payment-status="${data}">
                                        
                                        ${data == 1 ? 'Pagado' : 'Pendiente'}
                                    </button>
                                `;
                            }
                        },

                        {
                            data: 'paid_at',
                            render: function(data) {

                                if (!data) return '-';

                                return new Date(data).toLocaleDateString('es-CL');
                            }
                        },

                        {
                            data: 'expires_at',
                            render: function(data) {

                                if (!data) return '-';

                                return new Date(data).toLocaleDateString('es-CL');
                            }
                        },

                        {
                            data: null,
                            orderable: false,
                            searchable: false,

                             render: function(data, type, row) {
                                return `
                                    <button class="edit-btn text-blue-500 mr-2">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </button>

                                    <button class="delete-btn text-red-500 mr-2">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>

                                    <a href="//${row.domain}.{{ config('app.domain') }}/login" target="_blank" rel="noopener noreferrer"
                                    class="text-green-500 mr-2">
                                        <i class="fa-solid fa-globe"></i>
                                    </a>

                                    <button class="view-btn text-yellow-500 mr-2">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                `;
                            }
                        }
                    ]
                });
                //table papelera
                 let tableTrash  = $('#customer-table-trash').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: '/customer-trash',
                    language: {
                        url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    responsive: false,

                    columns: [{
                            data: 'id'
                        },
                        {
                            data: 'name'
                        },
                        {
                            data: 'domain'
                        },

                        {
                            data: 'status',
                            render: function(data, type, row) {

                                return `
                                    <span 
                                        class="toggle-status px-2 py-1 rounded text-xs text-white
                                        ${data == 1 ? 'bg-green-500' : 'bg-red-500'}"
                                        data-id="${row.id}"
                                        data-status="${data}">
                                        
                                        ${data == 1 ? 'Activo' : 'Inactivo'}
                                    </span>
                                `;
                            }
                        },

                        {
                            data: 'payment_status',
                            render: function(data, type, row) {

                                return `
                                    <span 
                                        class="toggle-payment-status px-2 py-1 rounded text-xs text-white
                                        ${data == 1 ? 'bg-blue-500' : 'bg-gray-500'}"
                                        data-id="${row.id}"
                                        data-payment-status="${data}">
                                        
                                        ${data == 1 ? 'Pagado' : 'Pendiente'}
                                    </span>
                                `;
                            }
                        },

                        {
                            data: 'paid_at',
                            render: function(data) {

                                if (!data) return '-';

                                return new Date(data).toLocaleDateString('es-CL');
                            }
                        },

                        {
                            data: 'expires_at',
                            render: function(data) {

                                if (!data) return '-';

                                return new Date(data).toLocaleDateString('es-CL');
                            }
                        },

                        {
                            data: null,
                            orderable: false,
                            searchable: false,

                           render: (data, type, row) => `
                                <button class="restore-btn text-blue-500 mr-2" data-id="${row.id}">
                                    <i class="fa-regular fa-circle-check"></i>
                                </button>

                                <button class="delete-btn text-red-500 mr-2">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>

                                <a href="//${row.domain}/login" target="_blank" rel="noopener noreferrer"
                                class="text-green-500 mr-2">
                                    <i class="fa-solid fa-globe"></i>
                                </a>

                                <button class="view-btn text-yellow-500 mr-2">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            `
                        }
                    ]
                });

                // EDITAR
                $('#customer-table').on('click', '.edit-btn', function() {

                    let data = tableActive.row($(this).closest('tr')).data();

                    window.dispatchEvent(new CustomEvent('edit-customer', {
                        detail: data
                    }));
                });

                // VER tabla activos
                $('#customer-table').on('click', '.view-btn', function() {

                    let data = tableActive.row($(this).closest('tr')).data();

                    window.dispatchEvent(new CustomEvent('view-customer', {
                        detail: data
                    }));
                });

                 // VER tabla papelera
                $('#customer-table-trash').on('click', '.view-btn', function() {

                    let data = tableTrash.row($(this).closest('tr')).data();

                    window.dispatchEvent(new CustomEvent('view-customer', {
                        detail: data
                    }));
                });

                // CAMBIAR ESTADO
                $('#customer-table').on('click', '.toggle-status', function() {

                    let id = $(this).data('id');

                    let currentStatus = $(this).data('status');

                    let newStatus = currentStatus == 1 ? 0 : 1;

                    fetch(`/customers/${id}/toggle-status`, {

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

                        $('#customer-table').DataTable().ajax.reload(null, false);
                    });
                });

                 // CAMBIAR ESTADO PAGO
                $('#customer-table').on('click', '.toggle-payment-status', function() {

                    let id = $(this).data('id');

                    let currentPaymentStatus = $(this).data('payment-status');

                    let newPaymentStatus = currentPaymentStatus == 1 ? 0 : 1;

                    fetch(`/customers/${id}/toggle-payment-status`, {

                        method: 'PATCH',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]').content
                        },

                        body: JSON.stringify({
                            payment_status: newPaymentStatus
                        })

                    })
                    .then(res => res.json())
                    .then(data => {

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Estado de pago actualizado',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        $('#customer-table').DataTable().ajax.reload(null, false);
                    });
                });

                //eliminar cliente logico
                $('#customer-table').on('click', '.delete-btn', function() {

                    let data = tableActive.row($(this).closest('tr')).data();

                    Swal.fire({
                        title: '¿Eliminar Cliente?',
                        text: "Esto lo ocultará del sistema (se puede recuperar).",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {

                        if (!result.isConfirmed) return;

                        fetch(`/customers/${data.id}`, {
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
                                title: 'Cliente eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            $('#customer-table').DataTable().ajax.reload(null, false);
                            $('#customer-table-trash').DataTable().ajax.reload(null, false);
                        });

                    });

                });

                //restaurar Cliente
                $('#customer-table-trash').on('click', '.restore-btn', function () {

                    let id = $(this).data('id');

                    fetch(`/customers/${id}/restore`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        }
                    })
                    .then(res => res.json())
                    .then(() => {

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Cliente restaurado',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        $('#customer-table-trash').DataTable().ajax.reload(null, false);
                        $('#customer-table').DataTable().ajax.reload(null, false);
                    });
                });
                //eliminar Cliente permanente
                $('#customer-table-trash').on('click', '.delete-btn', function() {

                    let data = tableTrash.row($(this).closest('tr')).data();

                    Swal.fire({
                        title: '¿Eliminar Cliente permanentemente?',
                        text: 'Esto lo eliminará de forma permanente del sistema. Esta acción no se puede deshacer.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {

                        if (!result.isConfirmed) return;

                        fetch(`/customers/${data.id}`, {
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
                                title: 'Cliente eliminado',
                                showConfirmButton: false,
                                timer: 1500
                            });

                            $('#customer-table').DataTable().ajax.reload(null, false);
                            $('#customer-table-trash').DataTable().ajax.reload(null, false);
                        });

                    });

                });

            });

            function customerModal() {

                return {
                    tab: 'active',
                    activeLoaded: false,
                    trashLoaded: false,
                    open: false,
                    isEdit: false,
                    appDomain: '{{ config('app.domain') }}',

                    // MODAL VER
                    viewOpen: false,

                    viewData: {},

                    form: {
                        id: null,
                        name: '',
                        legal_name: '',
                        rut: '',
                        email: '',
                        phone: '',
                        address: '',
                        holder: '',
                        status: '1'
                    },

                    errors: {
                        name: null,
                        legal_name: null,
                        rut: null,
                        email: null,
                        phone: null,
                        address: null,
                        holder: null
                    },
                    changeTab(tab) {
                        this.tab = tab;

                        if (tab === 'active' && !this.activeLoaded) {
                            this.initActiveTable();
                            this.activeLoaded = true;
                        }

                        if (tab === 'trash' && !this.trashLoaded) {
                            this.initTrashTable();
                            this.trashLoaded = true;
                        }
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
                            legal_name: '',
                            rut: '',
                            email: '',
                            phone: '',
                            address: '',
                            holder: '',
                            status: '1'
                        };

                        this.errors = {};

                        this.open = true;
                    },

                    edit(data) {

                        this.isEdit = true;

                        this.form = {
                            ...data
                        };

                        this.errors = {};

                        this.open = true;
                    },

                    // NUEVO MÉTODO VER
                    view(data) {

                        this.viewData = {
                            ...data
                        };

                        this.viewOpen = true;
                    },

                    closeView() {

                        this.viewOpen = false;

                        this.viewData = {};
                    },

                    generateDomain() {

                        if (!this.form.name) return '';

                        return this.form.name
                            .toLowerCase()
                            .normalize("NFD")
                            .replace(/[\u0300-\u036f]/g, "")
                            .replace(/\s+/g, '') + '.' + this.appDomain;;
                    },

                    close() {

                        this.open = false;

                        this.errors = {};
                    },

                    submit() {

                        fetch(this.isEdit ? `/customers/${this.form.id}` : '/customers', {

                                method: this.isEdit ? 'PUT' : 'POST',

                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector(
                                        'meta[name="csrf-token"]').content
                                },

                                body: JSON.stringify({
                                    ...this.form,
                                    domain: this.generateDomain()
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

                                $('#customer-table').DataTable().ajax.reload();
                            });
                    }
                }
            }

            // EVENTO EDITAR
            window.addEventListener('edit-customer', e => {

                Alpine
                    .$data(document.querySelector('[x-ref="customerComponent"]'))
                    .edit(e.detail);
            });

            // EVENTO VER
            window.addEventListener('view-customer', e => {

                Alpine
                    .$data(document.querySelector('[x-ref="customerComponent"]'))
                    .view(e.detail);
            });
        </script>
    @endpush

</x-app-layout>