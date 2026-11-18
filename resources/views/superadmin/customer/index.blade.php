<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Gestión de Clientes') }}
        </h2>
    </x-slot>

    <div class="p-6">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
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
                    <div class="min-w-0 max-w-full" x-show="tab === 'active'">
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
                    <div class="min-w-0 max-w-full" x-show="tab === 'trash'">
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
                        class="fixed inset-0 flex items-center justify-center bg-black bg-opacity-50 z-50 p-4">

                        <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-lg bg-white shadow-lg">

                            <!-- HEADER -->
                            <div class="shrink-0 px-6 py-4 border-b rounded-t-lg bg-blue-100">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar Cliente' : 'Crear Nuevo Cliente'"></h2>
                            </div>

                            <!-- BODY -->
                            <div class="vita-customer-form-grid min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4 grid gap-4">

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
                                <!-- DOMINIO -->
                                <div class="col-span-2">
                                    <label class="text-sm">Dominio</label>
                                    <input type="text"
                                        :value="generateDomain()"
                                        class="w-full border rounded px-3 py-2 bg-gray-100 text-gray-500"
                                        disabled>
                                </div>
                                @include('superadmin.customer.partials.operating-scope')
                                @include('components.identification-fields', ['kind'=>'customer'])

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
                                        data-vita-phone x-model="form.phone"
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

                                <div class="col-span-2 rounded-lg border border-gray-200 bg-gray-50 p-4" x-show="!isEdit || adminBranches.length">
                                    <h3 class="text-sm font-semibold text-gray-800">Administrador general del cliente</h3>
                                    <p class="mt-1 text-xs text-gray-500">Podrá administrar todas las sedes de este cliente, incluidas las que se creen posteriormente.</p>
                                    <div class="mt-3" x-show="isEdit && !administrators.length && !form.create_admin">
                                        <p class="mb-2 text-sm text-gray-600">Este cliente no tiene un administrador general asignado.</p>
                                        <button type="button" @click="selectAdministrator(''); form.create_admin = true" class="rounded bg-blue-500 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"><i class="fa-solid fa-user-plus mr-1" aria-hidden="true"></i> Agregar administrador</button>
                                    </div>
                                    <div class="mt-3" x-show="isEdit && administrators.length > 1">
                                        <label for="customer-admin-select" class="mb-1 block text-sm">Administrador a editar</label>
                                        <select id="customer-admin-select" :value="form.admin_user_id" @change="selectAdministrator($event.target.value)" class="w-full rounded border-gray-300">
                                            <template x-for="admin in administrators" :key="admin.id"><option :value="admin.id" x-text="admin.name + ' · ' + admin.email"></option></template>
                                        </select>
                                    </div>
                                    <template x-if="errors.admin_user_id"><p class="mt-1 text-xs text-red-500" x-text="errors.admin_user_id[0]"></p></template>
                                    <div class="vita-customer-fields-grid mt-4 grid gap-4" x-show="!isEdit || form.admin_user_id || form.create_admin">
                                        <template x-for="field in adminFields" :key="field.key">
                                            <div>
                                                <label :for="'customer-' + field.key" class="mb-1 block text-sm" x-text="isEdit && !form.create_admin && field.type === 'password' ? field.label + ' (opcional)' : field.label"></label>
                                                <input :id="'customer-' + field.key" :type="field.type" x-model="form[field.key]"
                                                    @input="clearError(field.key)" :autocomplete="field.type === 'password' ? 'new-password' : 'off'"
                                                    :class="errors[field.key] ? 'border-red-500' : 'border-gray-300'" class="w-full rounded border px-3 py-2">
                                                <template x-if="errors[field.key]"><p class="mt-1 text-xs text-red-500" x-text="errors[field.key][0]"></p></template>
                                            </div>
                                        </template>
                                    </div>
                                    <div class="mt-4" x-show="!isEdit || form.admin_user_id || form.create_admin">@include('components.identification-fields', ['prefix'=>'admin_','kind'=>'user'])</div>
                                    <p class="mt-3 text-xs text-gray-500" x-show="!isEdit || form.admin_user_id || form.create_admin" x-text="isEdit && !form.create_admin ? 'Deja la contraseña vacía para conservar la actual. Si la cambias, debe tener al menos 8 caracteres y confirmarse.' : 'La contraseña debe tener al menos 8 caracteres. El correo es el que utilizará para iniciar sesión.'"></p>
                                </div>
                                <p class="col-span-2 text-sm text-gray-500" x-show="isEdit && !adminBranches.length">Primero crea o activa una sede para asignar un administrador.</p>

                                <div class="col-span-2 rounded-lg border bg-gray-50 p-4" x-show="!isEdit">
                                    <label class="block text-sm font-semibold">Asignar plan (opcional)</label>
                                    <select x-model="form.billing_plan_price_id" class="mt-2 w-full rounded-lg border-gray-300"><option value="">Asignar más adelante</option>@foreach($billingPlans as $billingPlan)@foreach($billingPlan->currentVersion?->prices ?? [] as $planPrice)<option value="{{ $planPrice->id }}">{{ $billingPlan->name }} · {{ $planPrice->interval === 'month' ? 'Mensual' : 'Anual' }} · ${{ number_format($planPrice->amount,0,',','.') }}</option>@endforeach @endforeach</select>
                                    <div x-show="form.billing_plan_price_id" class="vita-customer-fields-grid mt-3 grid gap-3">
                                        <label class="text-sm">Inicio<input type="date" x-model="form.subscription_starts_on" class="mt-1 w-full rounded-lg border-gray-300"></label>
                                        <label class="text-sm">Sedes contratadas<input type="number" min="1" x-model="form.subscription_branches" class="mt-1 w-full rounded-lg border-gray-300"></label>
                                        <label class="text-sm">Modalidad prevista<select x-model="form.subscription_payment_mode" class="mt-1 w-full rounded-lg border-gray-300"><option value="automatic">Automática con Flow</option><option value="manual">Manual</option></select></label>
                                    </div>
                                    <p class="mt-2 text-xs text-gray-500">Crea la suscripción junto con el cliente. No autoriza cargos ni restringe el acceso. La prueba gratuita puede configurarse al asignar desde Suscripciones.</p>
                                    <template x-for="key in ['billing_plan_price_id','subscription_starts_on','subscription_branches','subscription_payment_mode','branch_quantity','subscription']"><p class="mt-1 text-xs text-red-600" x-text="errors[key]?.[0] || ''"></p></template>
                                </div>
                                <div class="col-span-2" x-show="isEdit"><a :href="form.subscription_url" class="font-semibold text-blue-600 hover:underline">Asignar plan / Ver suscripción</a><p class="text-xs text-gray-500">Gestiona las condiciones desde la suscripción para conservar el historial y evitar cobros duplicados.</p></div>

                               

                            </div>

                            <!-- FOOTER -->
                            <div class="shrink-0 px-6 py-4 border-t flex justify-end gap-2">

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

                    @include('superadmin.customer.partials.view-modal')

                </div>
            </div>
        </div>
    </div>

    @push('styles')
        <style>
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) { min-width:0; max-width:100%; }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .customer-table-scroll { clear:both; width:100%; overflow-x:auto; border-radius:.75rem; }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_info { padding-top:.75rem; color:#6b7280; }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate { padding-top:.5rem; }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate .paginate_button {
                min-width:2.25rem; margin:0 .15rem; padding:.4rem .65rem;
                border:1px solid transparent !important; border-radius:.5rem; color:#374151 !important;
            }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate .paginate_button:hover {
                border-color:#d1d5db !important; background:#f3f4f6 !important; color:#111827 !important;
            }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate .paginate_button.current {
                border-color:{{ themeColor(600) }} !important; background:{{ themeColor(600) }} !important; color:#fff !important;
            }
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate .paginate_button.disabled,
            :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate .paginate_button.disabled:hover {
                border-color:transparent !important; background:transparent !important; color:#9ca3af !important; cursor:not-allowed;
            }
            @media(max-width:640px) {
                :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_info,
                :is(#customer-table_wrapper, #customer-table-trash_wrapper) .dataTables_paginate { float:none; text-align:center; }
            }
        </style>
    @endpush
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
                    dom: 'lf<"customer-table-scroll"t>ip',
                    pagingType: 'full_numbers',

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
                                    <button type="button" class="customer-actions-btn px-2 py-1 rounded hover:bg-gray-100" aria-label="Acciones del cliente" title="Acciones" aria-haspopup="menu" aria-expanded="false" aria-controls="customer-actions-menu"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                                    <span hidden><button type="button" class="edit-btn"></button><button type="button" class="view-btn"></button><button type="button" class="delete-btn"></button></span>
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
                    dom: 'lf<"customer-table-scroll"t>ip',
                    pagingType: 'full_numbers',

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
                                    <button type="button" class="customer-actions-btn px-2 py-1 rounded hover:bg-gray-100" aria-label="Acciones del cliente" title="Acciones" aria-haspopup="menu" aria-expanded="false" aria-controls="customer-actions-menu"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>
                                    <span hidden><button type="button" class="restore-btn" data-id="${row.id}"></button><button type="button" class="view-btn"></button><button type="button" class="delete-btn"></button></span>
                            `
                        }
                    ]
                });

                // Menú flotante fuera del contenedor de la tabla para evitar recortes.
                const actionsMenu = document.createElement('div');
                actionsMenu.id = 'customer-actions-menu';
                actionsMenu.className = 'hidden fixed w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-[9999] overflow-hidden';
                actionsMenu.setAttribute('role', 'menu');
                actionsMenu.setAttribute('aria-label', 'Acciones del cliente');
                actionsMenu.innerHTML = `
                    <button type="button" role="menuitem" data-action="view" class="w-full text-left px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-eye mr-2" aria-hidden="true"></i>Ver cliente</button>
                    <button type="button" role="menuitem" data-action="edit" class="w-full text-left px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-pen-to-square mr-2" aria-hidden="true"></i>Editar</button>
                    <a role="menuitem" data-link="login" target="_blank" rel="noopener noreferrer" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-globe mr-2" aria-hidden="true"></i>Ir al cliente</a>
                    <a role="menuitem" data-link="branches" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-location-dot mr-2" aria-hidden="true"></i>Gestionar sedes</a>
                    <a role="menuitem" data-link="subscription" class="block px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-credit-card mr-2" aria-hidden="true"></i>Plan y suscripción</a>
                    <button type="button" role="menuitem" data-action="restore" class="w-full text-left px-4 py-2 hover:bg-gray-100"><i class="fa-solid fa-rotate-left mr-2" aria-hidden="true"></i>Restaurar</button>
                    <button type="button" role="menuitem" data-action="delete" class="w-full text-left px-4 py-2 hover:bg-red-50 text-red-600 border-t border-gray-100"><i class="fa-solid fa-trash-can mr-2" aria-hidden="true"></i>Eliminar</button>
                `;
                actionsMenu.style.maxHeight = 'calc(100vh - 16px)';
                actionsMenu.style.overflowY = 'auto';
                document.body.append(actionsMenu);
                let selectedActionRow = null;
                let actionTrigger = null;
                const closeActions = (restoreFocus = false) => {
                    actionsMenu.classList.add('hidden');
                    actionTrigger?.setAttribute('aria-expanded', 'false');
                    if (restoreFocus) actionTrigger?.focus();
                    selectedActionRow = null;
                };
                $('#customer-table, #customer-table-trash').on('click', '.customer-actions-btn', function(event) {
                    event.preventDefault();
                    event.stopPropagation();
                    if (actionTrigger === this && !actionsMenu.classList.contains('hidden')) return closeActions();
                    closeActions();
                    const row = this.closest('tr');
                    const trash = this.closest('table').id === 'customer-table-trash';
                    const data = (trash ? tableTrash : tableActive).row(row).data();
                    if (!data) return;
                    actionTrigger = this;
                    selectedActionRow = row;
                    actionsMenu.querySelector('[data-action="edit"]').hidden = trash;
                    actionsMenu.querySelector('[data-action="restore"]').hidden = !trash;
                    actionsMenu.querySelector('[data-link="branches"]').hidden = trash;
                    actionsMenu.querySelector('[data-link="subscription"]').hidden = trash;
                    actionsMenu.querySelector('[data-link="subscription"]').href = data.subscription_url;
                    const loginLink = actionsMenu.querySelector('[data-link="login"]');
                    const domain = String(data.domain || '');
                    loginLink.hidden = !/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/i.test(domain);
                    loginLink.href = `${location.protocol}//${domain}.{{ config('app.domain') }}/login`;
                    actionsMenu.querySelector('[data-link="branches"]').href = `/customers/${encodeURIComponent(data.id)}/branches`;
                    actionsMenu.querySelectorAll('[role="menuitem"]').forEach(item => item.style.display = item.hidden ? 'none' : '');
                    actionsMenu.classList.remove('hidden');
                    this.setAttribute('aria-expanded', 'true');
                    const rect = this.getBoundingClientRect();
                    const width = actionsMenu.offsetWidth;
                    const height = actionsMenu.offsetHeight;
                    actionsMenu.style.left = `${Math.max(8, Math.min(rect.right - width, window.innerWidth - width - 8))}px`;
                    actionsMenu.style.top = `${Math.max(8, Math.min(rect.bottom + 5, window.innerHeight - height - 8))}px`;
                    if (event.detail === 0) actionsMenu.querySelector('[role="menuitem"]:not([hidden])')?.focus();
                });
                actionsMenu.addEventListener('click', event => {
                    const item = event.target.closest('[role="menuitem"]');
                    if (!item) return;
                    const row = selectedActionRow;
                    closeActions();
                    if (row && item.dataset.action) $(row).find(`.${item.dataset.action}-btn`).trigger('click');
                });
                document.addEventListener('click', event => {
                    if (!actionsMenu.contains(event.target) && !event.target.closest('.customer-actions-btn')) closeActions();
                });
                document.addEventListener('keydown', event => {
                    if (event.key === 'Escape' && !actionsMenu.classList.contains('hidden')) closeActions(true);
                });
                actionsMenu.addEventListener('keydown', event => {
                    if (!['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) return;
                    event.preventDefault();
                    const items = [...actionsMenu.querySelectorAll('[role="menuitem"]:not([hidden])')];
                    let index = items.indexOf(document.activeElement);
                    index = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (index + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
                    items[index]?.focus();
                });
                window.addEventListener('resize', () => closeActions());
                document.addEventListener('scroll', event => {
                    if (!actionsMenu.contains(event.target)) closeActions();
                }, true);
                $('#customer-table, #customer-table-trash').on('draw.dt', () => closeActions());

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
                    administrators: [],
                    adminBranches: [],

                    adminFields: [
                        { key: 'admin_name', label: 'Nombre del administrador', type: 'text' },
                        { key: 'admin_email', label: 'Correo de acceso', type: 'email' },
                        { key: 'admin_phone', label: 'Teléfono del administrador', type: 'tel' },
                        { key: 'admin_password', label: 'Contraseña', type: 'password' },
                        { key: 'admin_password_confirmation', label: 'Confirmar contraseña', type: 'password' },
                    ],

                    form: {
                        admin_name: '', admin_rut: '', admin_email: '', admin_phone: '', admin_password: '', admin_password_confirmation: '',
                        billing_plan_price_id: '', subscription_starts_on: '', subscription_branches: 1, subscription_payment_mode: 'automatic',
                        id: null,
                        name: '',
                        legal_name: '',
                        operating_country_code: 'CL', operating_declaration_accepted: false, operating_declaration: null, operating_review_status: 'pending', operating_review_note: '',
                        rut: '',
                        ...VitaIdentification.defaults('customer'), ...VitaIdentification.defaults('user','admin_'),
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
                        this.identificationLoadFailed = false;
                        this.administrators = [];
                        this.adminBranches = [];

                        this.form = {
                            billing_plan_price_id: '', subscription_starts_on: '', subscription_branches: 1, subscription_payment_mode: 'automatic',
                            admin_name: '', admin_rut: '', admin_email: '', admin_phone: '', admin_password: '', admin_password_confirmation: '',
                            id: null,
                            name: '',
                            legal_name: '',
                        operating_country_code: 'CL', operating_declaration_accepted: false, operating_declaration: null, operating_review_status: 'pending', operating_review_note: '',
                            rut: '',
                        ...VitaIdentification.defaults('customer'), ...VitaIdentification.defaults('user','admin_'),
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
                            ...VitaIdentification.defaults('customer'), ...VitaIdentification.defaults('user','admin_'), ...data,
                            operating_country_code: 'CL', operating_declaration_accepted: false,
                            operating_review_status: data.operating_review?.status || 'pending', operating_review_note: data.operating_review?.note || ''
                        };
                        this.identificationLoadFailed = false;
                        VitaIdentification.load(`/customers/${data.id}/identification`, this.form).catch(error => { this.identificationLoadFailed=true; Swal.fire({icon:'error',text:error.message}); });
                        this.administrators = data.administrators || [];
                        this.adminBranches = data.admin_branches || [];
                        this.selectAdministrator(this.administrators[0]?.id);

                        this.errors = {};

                        this.open = true;
                    },

                    selectAdministrator(id) {
                        this.form.create_admin = false;
                        const admin = this.administrators.find(item => Number(item.id) === Number(id));
                        this.form.admin_user_id = admin?.id || '';
                        Object.assign(this.form, VitaIdentification.defaults('user','admin_'));
                        if(admin) VitaIdentification.load(`/customers/${this.form.id}/administrators/${admin.id}/identification`,this.form,'admin_').catch(error => {this.identificationLoadFailed=true; Swal.fire({icon:'error',text:error.message});});
                        this.form.admin_name = admin?.name || '';
                        this.form.admin_rut = admin?.rut || '';
                        this.form.admin_email = admin?.email || '';
                        this.form.admin_phone = admin?.phone || '';
                        this.form.admin_password = '';
                        this.form.admin_password_confirmation = '';
                        this.adminFields.forEach(field => this.clearError(field.key));
                    },

                    // NUEVO MÉTODO VER
                    view(data) {

                        this.viewData = {
                            ...data
                        };
                        if(!data.deleted_at) VitaIdentification.load(`/customers/${data.id}/identification`,this.viewData).catch(error=>Swal.fire({icon:'error',text:error.message}));

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
                        this.form.admin_password = '';
                        this.form.admin_password_confirmation = '';

                        this.errors = {};
                    },

                    submit() {
                        if(this.identificationLoadFailed || VitaIdentification.isLoading(this.form)) return;

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
                                    rut: undefined, admin_rut: undefined,
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
