@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Usuarios') }}
    </h2>
@endsection
@section('content')
    <div class="pb-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6 text-gray-900" x-data="ProfessionalModal()" x-ref="professionalComponent">

                    <!-- TABLA Activos-->
                    <div class="overflow-x-auto">

                        <!-- BOTÓN -->
                        <div class="flex justify-end mb-4">
                            <button @click="create()" style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">
                                <i class="fa-solid fa-circle-plus mr-1"></i>
                                Nuevo usuario
                            </button>
                        </div>

                        <table id="professional-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Sedes</th>
                                    <th>Especialidad</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>

                        </table>

                    </div>

                    <div id="professional-actions-menu" class="hidden fixed z-[9999] w-56 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg">
                        <button type="button" id="user-menu-view" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-regular fa-eye mr-2 w-4"></i>Ver usuario</button>
                        <button type="button" id="user-menu-edit" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-pen-to-square mr-2 w-4"></i>Editar usuario</button>
                        @if ($isHeadquarters && $branches->isNotEmpty())
                            <button type="button" id="user-menu-branches" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-location-dot mr-2 w-4"></i>Editar sedes</button>
                        @endif
                        <button type="button" id="user-menu-roles" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-user-shield mr-2 w-4"></i>Editar roles</button>
                        <button type="button" id="user-menu-specialties" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-stethoscope mr-2 w-4"></i>Editar especialidades</button>
                        <button type="button" id="user-menu-absences" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-calendar-xmark mr-2 w-4"></i>Ausencias</button>
                        <button type="button" id="user-menu-schedule" class="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100"><i class="fa-regular fa-calendar-days mr-2 w-4"></i>Horarios</button>
                        <hr id="user-menu-delete-divider">
                        <button type="button" id="user-menu-delete" class="w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><i class="fa-solid fa-trash-can mr-2 w-4"></i>Eliminar usuario</button>
                    </div>

                    <div id="user-branches-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4">
                        <div class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
                            <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 id="user-branches-modal-title" class="text-lg font-bold text-gray-800">Editar sedes autorizadas</h2>
                            </div>
                            <div class="p-6">
                                <p class="mb-4 text-sm text-gray-600">Selecciona las sedes donde este usuario podrá iniciar sesión.</p>
                                <div id="user-branches-options" class="flex flex-wrap gap-3 rounded-lg border border-gray-200 p-3">
                                    @foreach ($branches as $branch)
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                            <input type="checkbox" class="user-branch-option rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="{{ $branch->id }}">
                                            <span>{{ $branch->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p id="user-branches-error" class="mt-2 hidden text-sm text-red-600"></p>
                            </div>
                            <div class="flex justify-end gap-2 border-t px-6 py-4">
                                <button type="button" id="user-branches-cancel" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</button>
                                <button type="button" id="user-branches-save" style="background-color: {{ themeColor(600) }}" class="rounded px-4 py-2 text-sm font-medium text-white hover:brightness-90">Guardar sedes</button>
                            </div>
                        </div>
                    </div>

                    <div id="user-roles-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4">
                        <div class="w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl">
                            <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 id="user-roles-modal-title" class="text-lg font-bold text-gray-800">Editar roles</h2>
                            </div>
                            <div class="p-6">
                                @if ($isHeadquarters)
                                <div class="mb-5">
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Roles por sede</h3>
                                    <div id="user-roles-branch-groups" class="max-h-44 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3">
                                        <p class="text-sm text-gray-500">Cargando roles asignados…</p>
                                    </div>
                                </div>
                                <div class="mb-4 border-t border-gray-200"></div>
                                @else
                                    <div id="user-roles-branch-groups" class="hidden"></div>
                                @endif
                                <h3 class="mb-3 text-sm font-semibold text-gray-700">Editar roles de una sede</h3>
                                <label for="user-roles-branch" class="mb-2 block text-sm font-medium text-gray-700">Sede</label>
                                <select id="user-roles-branch" class="mb-4 w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></select>
                                <p class="mb-4 text-sm text-gray-600">Los roles seleccionados aplican solo en esta sede. Los permisos se combinan entre todos sus roles.</p>
                                <div id="user-roles-options" class="flex flex-wrap gap-3 rounded-lg border border-gray-200 p-3">
                                    @foreach ($roles as $role)
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                            <input type="checkbox" class="user-role-modal-option rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="{{ $role->id }}">
                                            <span>{{ $role->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p id="user-roles-error" class="mt-2 hidden text-sm text-red-600"></p>
                            </div>
                            <div class="flex justify-end gap-2 border-t px-6 py-4">
                                <button type="button" id="user-roles-cancel" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</button>
                                <button type="button" id="user-roles-save" style="background-color: {{ themeColor(600) }}" class="rounded px-4 py-2 text-sm font-medium text-white hover:brightness-90">Guardar roles</button>
                            </div>
                        </div>
                    </div>

                    <div id="user-specialties-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4">
                        <div class="w-full max-w-xl overflow-hidden rounded-lg bg-white shadow-xl">
                            <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 id="user-specialties-title" class="text-lg font-bold text-gray-800">Especialidades por sede</h2>
                            </div>
                            <div class="max-h-[70vh] space-y-4 overflow-y-auto p-6">
                                @if ($isHeadquarters)
                                <div>
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Asignaciones actuales</h3>
                                    <div id="user-specialties-groups" class="max-h-40 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-3"><p class="text-sm text-gray-500">Cargando especialidades…</p></div>
                                </div>
                                <div class="border-t border-gray-200"></div>
                                @else
                                <div id="user-specialties-groups" class="hidden"></div>
                                @endif
                                <div>
                                    <label for="user-specialties-branch" class="mb-2 block text-sm font-medium text-gray-700">Sede a editar</label>
                                    <select id="user-specialties-branch" class="w-full rounded-lg border-gray-300 text-sm"></select>
                                </div>
                                <p id="user-specialties-description" class="text-sm text-gray-600">Selecciona las especialidades que este usuario atenderá en la sede elegida.</p>
                                <div>
                                    <label for="user-specialties-select" class="mb-2 block text-sm font-medium text-gray-700">Buscar y seleccionar especialidad</label>
                                    <select id="user-specialties-select" class="w-full rounded-lg border-gray-300 text-sm">
                                        <option value="">Busca una especialidad</option>
                                    </select>
                                </div>
                                <div>
                                    <h3 class="mb-2 text-sm font-semibold text-gray-700">Especialidades en esta sede</h3>
                                    <div id="user-specialties-options" class="flex min-h-12 flex-wrap gap-2 rounded-lg border border-gray-200 bg-white p-3">
                                        <span class="text-sm text-gray-500">Cargando especialidades…</span>
                                    </div>
                                </div>
                                <p id="user-specialties-error" class="hidden text-sm text-red-600"></p>
                            </div>
                            <div class="flex justify-end gap-2 border-t px-6 py-4">
                                <button type="button" id="user-specialties-cancel" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cancelar</button>
                                <button type="button" id="user-specialties-save" style="background-color: {{ themeColor(600) }}" class="rounded px-4 py-2 text-sm font-medium text-white hover:brightness-90">Guardar especialidades</button>
                            </div>
                        </div>
                    </div>

                    <div id="user-absences-modal" class="fixed inset-0 z-[60] hidden items-center justify-center bg-black/50 p-4">
                        <div class="w-full max-w-2xl overflow-hidden rounded-lg bg-white shadow-xl">
                            <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 id="user-absences-title" class="text-lg font-bold text-gray-800">Ausencias</h2>
                            </div>
                            <div class="max-h-[70vh] space-y-5 overflow-y-auto p-6">
                                <p class="text-sm text-gray-600">Registra vacaciones, permisos u otras ausencias. En profesionales, las fechas registradas se bloquearán para nuevas citas. Las citas existentes no se cancelan automáticamente.</p>
                                <div class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 p-4 sm:grid-cols-2">
                                    <div>
                                        <label for="user-absence-type" class="mb-1 block text-sm font-medium text-gray-700">Tipo</label>
                                        <select id="user-absence-type" class="w-full rounded-lg border-gray-300">
                                            <option value="vacaciones">Vacaciones</option>
                                            <option value="permiso">Permiso</option>
                                            <option value="otro">Otro</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="user-absence-start" class="mb-1 block text-sm font-medium text-gray-700">Desde</label>
                                        <input id="user-absence-start" type="date" class="w-full rounded-lg border-gray-300">
                                    </div>
                                    <div>
                                        <label for="user-absence-end" class="mb-1 block text-sm font-medium text-gray-700">Hasta</label>
                                        <input id="user-absence-end" type="date" class="w-full rounded-lg border-gray-300">
                                    </div>
                                    <div>
                                        <label for="user-absence-notes" class="mb-1 block text-sm font-medium text-gray-700">Observación</label>
                                        <input id="user-absence-notes" type="text" maxlength="1000" class="w-full rounded-lg border-gray-300" placeholder="Opcional">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <p id="user-absence-error" class="hidden text-sm text-red-600"></p>
                                        <button type="button" id="user-absence-save" style="background-color: {{ themeColor(600) }}" class="mt-2 rounded px-4 py-2 text-sm font-medium text-white hover:brightness-90"><i class="fa-solid fa-plus mr-1"></i>Registrar ausencia</button>
                                    </div>
                                </div>
                                <div>
                                    <h3 class="mb-2 text-sm font-semibold text-gray-800">Ausencias registradas</h3>
                                    <div id="user-absences-list" class="space-y-2"></div>
                                </div>
                            </div>
                            <div class="flex justify-end border-t px-6 py-4">
                                <button type="button" id="user-absences-close" class="rounded bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Cerrar</button>
                            </div>
                        </div>
                    </div>



                    <!-- MODAL CREAR / EDITAR -->
                    <div id="professionalModal" x-cloak x-show="open"
                        class="fixed inset-0 flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                        <div class="bg-white rounded-lg shadow-lg w-full max-w-3xl max-h-[90vh] flex flex-col">

                            <!-- HEADER -->
                            <div class="px-6 py-4 border-b rounded-t-lg"
                                style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                                <h2 class="text-lg font-bold"
                                    x-text="isEdit ? 'Editar usuario' : 'Crear usuario'">
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

                                    @include('components.identification-fields', ['kind'=>'user'])

                                    {{-- Celular --}}
                                    <div>

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Celular
                                        </label>

                                        <input type="text" data-vita-phone x-model="form.phone" @input="clearError('phone')"
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
                                    <div x-data="{ showPassword: false }">

                                        <label class="block text-sm font-medium text-gray-700 mb-1">
                                            Contraseña
                                        </label>

                                        <div class="relative">

                                            <input :type="showPassword ? 'text' : 'password'" x-model="form.password"
                                                @input="clearError('password')"
                                                class="w-full rounded-lg border-gray-300 pr-10"
                                                :placeholder="isEdit ? '••••••••' : ''">

                                            {{-- OJO SOLO SI ESTÁ ESCRIBIENDO UNA NUEVA CONTRASEÑA --}}
                                            <button x-show="form.password.length > 0" type="button"
                                                @click="showPassword = !showPassword"
                                                class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-500 hover:text-gray-700">

                                                <i
                                                    :class="showPassword
                                                        ?
                                                        'fa-regular fa-eye-slash' :
                                                        'fa-regular fa-eye'">
                                                </i>

                                            </button>

                                        </div>

                                        <template x-if="errors.password">
                                            <p class="text-xs text-red-500 mt-1" x-text="errors.password[0]">
                                            </p>
                                        </template>

                                        <template x-if="isEdit">
                                            <p class="text-xs text-gray-500 mt-1">
                                                Deja vacío para mantener la contraseña actual.
                                            </p>
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

                                    <div class="md:col-span-2" x-show="!isEdit">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Roles del usuario</label>
                                        <div class="flex flex-wrap gap-3 rounded-lg border border-gray-200 p-3">
                                            @foreach ($roles as $role)
                                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                                    <input type="checkbox" value="{{ $role->id }}" x-model="form.role_ids" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span>{{ $role->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        <template x-if="errors.role_ids"><p class="text-xs text-red-500 mt-1" x-text="errors.role_ids[0]"></p></template>
                                    </div>

                                    @if ($branches->isNotEmpty())
                                        <div id="user-branches-field" class="md:col-span-2" x-show="!isEdit" x-cloak>
                                            <label class="block text-sm font-medium text-gray-700 mb-2">Sedes autorizadas</label>
                                            <div class="flex flex-wrap gap-3 rounded-lg border border-gray-200 p-3">
                                                @foreach ($branches as $branch)
                                                    <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                                        <input type="checkbox" value="{{ $branch->id }}" x-model="form.branch_ids" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                        <span>{{ $branch->name }}</span>
                                                    </label>
                                                @endforeach
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">El usuario solo podrá iniciar sesión en las sedes asignadas. El Administrador general conserva acceso a todas.</p>
                                            <template x-if="errors.branch_ids"><p class="text-xs text-red-500 mt-1" x-text="errors.branch_ids[0]"></p></template>
                                        </div>
                                    @endif

                                    {{-- Especialidades --}}
                                    <div class="md:col-span-2">

                                        <label class="block text-sm font-medium text-gray-700 mb-1" x-text="isEdit ? 'Especialidades de la sede actual' : 'Especialidades para una sede'"></label>
                                        <div class="mb-3" x-show="!isEdit">
                                            <label for="specialtyBranchSelect" class="mb-1 block text-xs font-medium text-gray-600">Sede de esta asignación</label>
                                            <select id="specialtyBranchSelect" x-model="form.specialty_branch_id" x-effect="if (form.branch_ids.length && !form.branch_ids.map(String).includes(String(form.specialty_branch_id))) form.specialty_branch_id = String(form.branch_ids[0])" class="w-full rounded-lg border-gray-300 text-sm">
                                                @foreach ($branches as $branch)
                                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <template x-if="errors.specialty_branch_id"><p class="mb-2 text-xs text-red-500" x-text="errors.specialty_branch_id[0]"></p></template>
                                        <p class="mb-2 text-xs text-gray-500">Esta asignación solo corresponde a la sede elegida. Puedes agregar especialidades para otras sedes desde Acciones → Editar especialidades.</p>

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

                                        <template x-if="errors.specialties"><p class="text-xs text-red-500 mt-1" x-text="errors.specialties[0]"></p></template>

                                    </div>

                                </div>

                            </div>

                            <!-- FOOTER -->
                            <div class="px-6 py-4 border-t flex justify-end gap-2">

                                <button @click="close()" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

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
    @include('customer.user.partials.schedule-modal')
    <!-- MODAL VER PROFESIONAL -->

    <div x-cloak x-data="ProfessionalViewModal()" x-ref="viewProfessionalComponent" x-show="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col overflow-hidden">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b rounded-t-lg flex-shrink-0"
                style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }};">
                <h2 class="text-lg font-bold text-gray-800">
                    Información del usuario
                </h2>
            </div>


            <!-- BODY -->
            <div class="p-6 overflow-y-auto flex-1">

                <!-- INFORMACIÓN GENERAL -->
                <div class="space-y-4">

                    <section class="rounded-xl border border-gray-200 bg-gray-50/50 p-4">
                        <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-700">Datos personales</h3>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 md:grid-cols-2">

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
                            Identificación
                        </label>

                        <p class="mt-1 text-gray-900" x-text="professional?.identification_display || professional?.rut || '-'"></p>
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label class="block text-sm font-medium text-gray-500">
                            Celular
                        </label>

                        <p data-phone-display class="mt-1 text-gray-900" x-text="professional?.phone || '-'"></p>
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
                        </div>
                    </section>

                    <!-- INFORMACIÓN AGRUPADA POR SEDE -->
                    <section class="md:col-span-2 overflow-hidden rounded-xl border border-gray-200 bg-gray-50/50">
                        <div class="border-b border-gray-200 px-4 pt-3">
                            <div class="flex gap-2 overflow-x-auto" role="tablist" aria-label="Información por sede">
                                <template x-for="branch in professional?.branches_data || []" :key="branch.id">
                                    <button type="button" role="tab" :aria-selected="activeBranchId === Number(branch.id)"
                                        @click="activeBranchId = Number(branch.id)"
                                        class="shrink-0 rounded-t-lg border-b-2 px-4 py-2 text-sm font-medium transition"
                                        :class="activeBranchId === Number(branch.id) ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-gray-500 hover:bg-white hover:text-gray-700'"
                                        x-text="branch.name"></button>
                                </template>
                                <template x-if="!professional?.branches_data?.length">
                                    <span class="px-2 py-2 text-sm text-gray-500">Sin sedes asignadas</span>
                                </template>
                            </div>
                        </div>
                        <div class="space-y-4 p-4" role="tabpanel">
                            <div>
                                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Estado en esta sede</h3>
                                <span class="inline-block rounded px-2 py-1 text-xs text-white"
                                    :class="activeBranchStatus() === 1 ? 'bg-green-500' : 'bg-red-500'"
                                    x-text="activeBranchStatus() === 1 ? 'Activo' : 'Inactivo'"></span>
                            </div>
                            <div>
                                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Roles</h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="role in branchRoles()" :key="role.id">
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-1 text-xs text-green-700" x-text="role.name"></span>
                                    </template>
                                    <template x-if="!branchRoles().length"><span class="text-sm text-gray-500">Sin roles asignados</span></template>
                                </div>
                            </div>
                            <div>
                                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-700">Especialidades</h3>
                                <div class="flex flex-wrap gap-2">
                                    <template x-for="specialty in branchSpecialties()" :key="specialty.id">
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-sm text-blue-700" x-text="specialty.name"></span>
                                    </template>
                                    <template x-if="!branchSpecialties().length"><span class="text-sm text-gray-500">Sin especialidades asignadas</span></template>
                                </div>
                            </div>
                            <div>
                                <div class="mb-2 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-700">Horarios de atención</h3>
                                    <template x-if="loading"><i class="fa-solid fa-spinner fa-spin text-gray-400"></i></template>
                                </div>
                                <template x-if="!loading && branchSchedules().length">
                                    <div class="max-h-56 space-y-2 overflow-y-auto rounded-lg border border-gray-200 bg-white p-3">
                                        <template x-for="schedule in branchSchedules()" :key="schedule.id">
                                            <div class="flex items-center gap-3 rounded-lg border border-gray-100 px-3 py-2">
                                                <i class="fa-regular fa-calendar-days text-indigo-600"></i>
                                                <div><p class="text-sm font-medium text-gray-800" x-text="days[schedule.day_of_week]"></p>
                                                    <p class="text-sm text-gray-500"><span x-text="formatTime(schedule.start_time)"></span> – <span x-text="formatTime(schedule.end_time)"></span></p></div>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                                <template x-if="!loading && !branchSchedules().length"><p class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-500">No hay horarios registrados en esta sede.</p></template>
                            </div>
                        </div>
                    </section>

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

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #professionalModal .select2-container {
            width: 100% !important;
        }

        #professionalModal .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }

        #professionalModal .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }

        #professionalModal .select2-container .select2-selection__arrow {
            height: 40px;
        }

        #user-roles-modal .select2-container {
            width: 100% !important;
        }

        #user-roles-modal .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }

        #user-roles-modal .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }

        #user-roles-modal .select2-container .select2-selection__arrow {
            height: 40px;
        }

        #user-specialties-modal .select2-container { width: 100% !important; }
        #user-specialties-modal .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }
        #user-specialties-modal .select2-container .select2-selection__rendered { padding-left: 0.75rem; color: #111827; }
        #user-specialties-modal .select2-container .select2-selection__arrow { height: 40px; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
        const clinicUserRoles = @json($roles->values());
        const defaultSpecialtyBranchId = @json((int) ($effectiveBranchId ?? ($branches->first()?->id ?? 0)));

        $(function() {
            $('#specialtySelect').select2({
                width: '100%',
                placeholder: 'Seleccione una especialidad',
                minimumResultsForSearch: 0,
                dropdownParent: $('#professionalModal'),
                language: {
                    noResults: () => 'No se encontraron especialidades',
                    searching: () => 'Buscando...'
                }
            });

            $('#user-roles-branch').select2({
                width: '100%',
                placeholder: 'Buscar sede',
                minimumResultsForSearch: 0,
                dropdownParent: $('#user-roles-modal'),
                language: {
                    noResults: () => 'No se encontraron sedes',
                    searching: () => 'Buscando...'
                }
            });

            $('#user-specialties-branch').select2({
                width: '100%',
                placeholder: 'Buscar sede',
                minimumResultsForSearch: 0,
                dropdownParent: $('#user-specialties-modal'),
                language: {
                    noResults: () => 'No se encontraron sedes',
                    searching: () => 'Buscando...'
                }
            });
            $('#user-specialties-select').select2({
                width: '100%',
                placeholder: 'Busca una especialidad',
                minimumResultsForSearch: 0,
                dropdownParent: $('#user-specialties-modal'),
                language: {
                    noResults: () => 'No se encontraron especialidades',
                    searching: () => 'Buscando...'
                }
            });
        });

        $(function() {
            // table
            let tableActive = $('#professional-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json(route('customers.users.data', ['customer' => request()->route('customer')])),
                language: {
                    url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                },
                responsive: false,
                order: [],

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
                        data: 'email'
                    },
                    {
                        data: 'branches_html',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'specialties_html',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        render: function(data, type, row) {
                            const status = Number(row.branch_status || 0);

                            return `
                                <button
                                    type="button"
                                    class="toggle-status rounded px-2 py-1 text-xs text-white ${status === 1 ? 'bg-green-500' : 'bg-red-500'}"
                                    data-id="${row.user_id}"
                                    data-branch-id="${Number(defaultSpecialtyBranchId || 0)}"
                                    data-status="${status}">
                                    ${status === 1 ? 'Activo' : 'Inactivo'}
                                </button>
                            `;
                        }
                    },


                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            const isClinicAdministrator = (row.roles || []).some(role =>
                                ['administrador', 'administrador de sede'].includes(String(role.name || '').trim().toLocaleLowerCase())
                            );
                            const canDelete = Number(row.appointments_count) === 0 && !isClinicAdministrator;
                            const hasDoctorRole = (row.roles || []).some(role => role.name === 'Doctor');

                            return `
                                <button type="button" class="user-actions-btn px-2 py-1 rounded hover:bg-gray-100" data-id="${row.user_id}" aria-label="Acciones de ${$('<div>').text(row.name || 'usuario').html()}">
                                    <i class="fa-solid fa-ellipsis-vertical"></i>
                                </button>
                                <span class="hidden" aria-hidden="true">
                                    ${showScheduleManagement ? `<button class="schedule-btn" data-id="${row.user_id}"></button>` : ''}
                                    <button class="edit-roles-btn" data-id="${row.user_id}"></button>
                                    <button class="edit-specialties-btn" data-id="${row.user_id}"></button>
                                    <button class="edit-professional-btn" data-id="${row.user_id}"></button>
                                    <button class="view-professional-btn" data-id="${row.user_id}"></button>
                                    ${canDelete ? `<button class="delete-btn" data-id="${row.user_id}"></button>` : ''}
                                </span>
                            `;
                        }
                    }
                ]
            });

            const showScheduleManagement = @json($showScheduleManagement);
            const professionalActionsMenu = $('#professional-actions-menu');
            let selectedProfessionalActionRow = null;

            $('#professional-table').on('click', '.user-actions-btn', function(event) {
                event.preventDefault();
                event.stopPropagation();

                const rowApi = tableActive.row($(this).closest('tr'));
                const user = rowApi.data();
                if (!user) return;

                if (!professionalActionsMenu.hasClass('hidden') && selectedProfessionalActionRow?.[0] === rowApi.node()) {
                    professionalActionsMenu.addClass('hidden');
                    selectedProfessionalActionRow = null;
                    return;
                }

                selectedProfessionalActionRow = $(rowApi.node());
                const roles = user.roles || [];
                const isClinicAdministrator = roles.some(role => String(role.name || '').trim().toLocaleLowerCase() === 'administrador');
                $('#user-menu-schedule').toggle(showScheduleManagement);
                $('#user-menu-absences').show();
                $('#user-menu-delete, #user-menu-delete-divider').toggle(Number(user.appointments_count) === 0 && !isClinicAdministrator);

                const rect = this.getBoundingClientRect();
                professionalActionsMenu.removeClass('hidden');
                const menuWidth = professionalActionsMenu.outerWidth();
                const menuHeight = professionalActionsMenu.outerHeight();
                const left = Math.max(8, Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8));
                const top = rect.bottom + menuHeight <= window.innerHeight
                    ? rect.bottom + 4
                    : Math.max(8, rect.top - menuHeight - 4);
                professionalActionsMenu.css({ top: `${top}px`, left: `${left}px` });
            });

            function runSelectedProfessionalAction(actionClass) {
                if (!selectedProfessionalActionRow?.length) return;
                const action = selectedProfessionalActionRow.find(`.${actionClass}`).first();
                professionalActionsMenu.addClass('hidden');
                if (action.length) action.trigger('click');
            }

            $('#user-menu-view').on('click', () => runSelectedProfessionalAction('view-professional-btn'));
            $('#user-menu-edit').on('click', () => runSelectedProfessionalAction('edit-professional-btn'));
            $('#user-menu-roles').on('click', () => runSelectedProfessionalAction('edit-roles-btn'));
            $('#user-menu-specialties').on('click', () => runSelectedProfessionalAction('edit-specialties-btn'));
            $('#user-menu-schedule').on('click', () => runSelectedProfessionalAction('schedule-btn'));
            $('#user-menu-absences').on('click', () => {
                const user = selectedProfessionalActionRow?.length
                    ? tableActive.row(selectedProfessionalActionRow[0]).data()
                    : null;
                professionalActionsMenu.addClass('hidden');
                if (user) openUserAbsencesModal(user);
            });
            $('#user-menu-delete').on('click', () => runSelectedProfessionalAction('delete-btn'));
            $('#user-menu-branches').on('click', () => {
                const user = selectedProfessionalActionRow?.length
                    ? tableActive.row(selectedProfessionalActionRow[0]).data()
                    : null;
                professionalActionsMenu.addClass('hidden');
                if (!user) return;
                openUserBranchesModal(user);
            });

            const userBranchesModal = $('#user-branches-modal');
            let selectedBranchUserId = null;

            const userAbsencesModal = $('#user-absences-modal');
            let selectedAbsenceUserId = null;
            const absenceTypes = { vacaciones: 'Vacaciones', permiso: 'Permiso', otro: 'Otro' };

            function renderUserAbsences(absences) {
                const list = $('#user-absences-list').empty();
                if (!absences.length) {
                    list.append($('<p class="rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">No hay ausencias registradas.</p>'));
                    return;
                }
                absences.forEach(absence => {
                    const row = $('<div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-200 p-3">');
                    const details = $('<div class="min-w-0">');
                    details.append($('<p class="font-medium text-gray-800">').text(absenceTypes[absence.type] || absence.type));
                    details.append($('<p class="text-sm text-gray-600">').text(`${absence.starts_on} al ${absence.ends_on}`));
                    if (absence.notes) details.append($('<p class="text-sm text-gray-500">').text(absence.notes));
                    const remove = $('<button type="button" class="rounded px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">').text('Eliminar').attr('data-absence-id', absence.id);
                    row.append(details, remove);
                    list.append(row);
                });
            }

            async function loadUserAbsences() {
                $('#user-absences-list').html('<p class="text-sm text-gray-500">Cargando...</p>');
                const response = await fetch(`/users/${selectedAbsenceUserId}/absences`, { headers: { 'Accept': 'application/json' } });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'No se pudieron cargar las ausencias.');
                $('#user-absences-title').text(`Ausencias de ${body.user.name}`);
                renderUserAbsences(body.absences || []);
            }

            async function openUserAbsencesModal(user) {
                selectedAbsenceUserId = Number(user.user_id || user.id);
                $('#user-absence-error').addClass('hidden').text('');
                $('#user-absence-start, #user-absence-end, #user-absence-notes').val('');
                userAbsencesModal.removeClass('hidden').addClass('flex');
                $('body').addClass('overflow-hidden');
                try {
                    await loadUserAbsences();
                } catch (error) {
                    $('#user-absences-list').text(error.message || 'No se pudieron cargar las ausencias.');
                }
            }

            function closeUserAbsencesModal() {
                userAbsencesModal.addClass('hidden').removeClass('flex');
                $('body').removeClass('overflow-hidden');
                selectedAbsenceUserId = null;
            }

            $('#user-absences-close').on('click', closeUserAbsencesModal);
            userAbsencesModal.on('click', function(event) {
                if (event.target === this) closeUserAbsencesModal();
            });
            $('#user-absence-save').on('click', async function() {
                if (!selectedAbsenceUserId) return;
                const errorBox = $('#user-absence-error');
                errorBox.addClass('hidden').text('');
                const saveButton = this;
                saveButton.disabled = true;
                try {
                    const response = await fetch(`/users/${selectedAbsenceUserId}/absences`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            type: $('#user-absence-type').val(),
                            starts_on: $('#user-absence-start').val(),
                            ends_on: $('#user-absence-end').val(),
                            notes: $('#user-absence-notes').val()
                        })
                    });
                    const body = await response.json();
                    if (!response.ok) {
                        errorBox.removeClass('hidden').text(Object.values(body.errors || {}).flat()[0] || body.message || 'No se pudo registrar la ausencia.');
                        return;
                    }
                    await loadUserAbsences();
                    tableActive.ajax.reload(null, false);
                    if (body.conflicting_appointments > 0) {
                        Swal.fire({ icon: 'warning', title: 'Ausencia registrada', text: `Hay ${body.conflicting_appointments} cita(s) existente(s) en esas fechas. Debes reprogramarlas o cancelarlas; las ausencias bloquean las nuevas reservas.` });
                    } else {
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Ausencia registrada', showConfirmButton: false, timer: 1800 });
                    }
                    $('#user-absence-start, #user-absence-end, #user-absence-notes').val('');
                } catch (error) {
                    errorBox.removeClass('hidden').text(error.message || 'No se pudo registrar la ausencia.');
                } finally {
                    saveButton.disabled = false;
                }
            });

            $('#user-absences-list').on('click', 'button[data-absence-id]', async function() {
                const absenceId = this.dataset.absenceId;
                const result = await Swal.fire({ icon: 'warning', title: '¿Eliminar ausencia?', text: 'Al eliminarla, las fechas volverán a estar disponibles para nuevas citas.', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar' });
                if (!result.isConfirmed || !selectedAbsenceUserId) return;
                try {
                    const response = await fetch(`/users/${selectedAbsenceUserId}/absences/${absenceId}`, {
                        method: 'DELETE',
                        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
                    });
                    const body = await response.json();
                    if (!response.ok) throw new Error(body.message || 'No se pudo eliminar la ausencia.');
                    await loadUserAbsences();
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Ausencia eliminada', showConfirmButton: false, timer: 1600 });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo eliminar la ausencia.' });
                }
            });

            function openUserBranchesModal(user) {
                selectedBranchUserId = Number(user.user_id || user.id);
                $('#user-branches-modal-title').text(`Sedes de ${user.name || 'usuario'}`);
                $('#user-branches-error').addClass('hidden').text('');

                let branchIds = user.branch_ids ?? [];
                if (typeof branchIds === 'string') {
                    try { branchIds = JSON.parse(branchIds); } catch (_) { branchIds = branchIds.trim() ? [branchIds] : []; }
                }
                if (branchIds && typeof branchIds === 'object' && !Array.isArray(branchIds)) branchIds = Object.values(branchIds);
                if (!Array.isArray(branchIds)) branchIds = branchIds === null || branchIds === undefined ? [] : [branchIds];
                const selectedIds = new Set(branchIds.map(id => String(id)));
                userBranchesModal.find('.user-branch-option').each(function() {
                    this.checked = selectedIds.has(String(this.value));
                });

                userBranchesModal.removeClass('hidden').addClass('flex');
                $('body').addClass('overflow-hidden');
            }

            function closeUserBranchesModal() {
                userBranchesModal.addClass('hidden').removeClass('flex');
                $('body').removeClass('overflow-hidden');
                selectedBranchUserId = null;
            }

            $('#user-branches-cancel').on('click', closeUserBranchesModal);
            userBranchesModal.on('click', function(event) {
                if (event.target === this) closeUserBranchesModal();
            });
            $('#user-branches-save').on('click', async function() {
                if (!selectedBranchUserId) return;
                const saveButton = this;
                const branchIds = userBranchesModal.find('.user-branch-option:checked').map(function() {
                    return Number(this.value);
                }).get();
                const errorBox = $('#user-branches-error');
                saveButton.disabled = true;

                try {
                    const response = await fetch(`/users/${selectedBranchUserId}/branches`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ branch_ids: branchIds })
                    });
                    const responseBody = await response.json();
                    if (!response.ok) {
                        const message = Object.values(responseBody.errors || {}).flat()[0] || responseBody.message || 'No se pudieron guardar las sedes.';
                        errorBox.removeClass('hidden').text(message);
                        return;
                    }

                    closeUserBranchesModal();
                    tableActive.ajax.reload(null, false);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Sedes actualizadas', showConfirmButton: false, timer: 1800 });
                } catch (error) {
                    errorBox.removeClass('hidden').text(error.message || 'Ocurrió un error al guardar las sedes.');
                } finally {
                    saveButton.disabled = false;
                }
            });

            const userRolesModal = $('#user-roles-modal');
            let selectedRoleUserId = null;
            let selectedRoleBranchId = null;
            const initialRoleBranchId = Number(@json($effectiveBranchId ?? 0));
            const canChooseRoleBranch = @json($isHeadquarters);

            async function loadBranchRoles(branchId) {
                const response = await fetch(`/users/${selectedRoleUserId}/roles?branch_id=${branchId}`, { headers: { 'Accept': 'application/json' } });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'No se pudieron cargar los roles de la sede.');
                renderBranchRoleGroups(body.groups || []);
                const selectedRoleIds = new Set((body.roles || []).map(role => String(role.id)));
                userRolesModal.find('.user-role-modal-option').each(function() {
                    this.checked = selectedRoleIds.has(String(this.value));
                });
            }

            function renderBranchRoleGroups(groups) {
                const container = $('#user-roles-branch-groups').empty();
                if (!groups.length) {
                    container.append($('<p>').addClass('text-sm text-gray-500').text('No hay roles asignados en las sedes disponibles.'));
                    return;
                }

                groups.forEach(group => {
                    const section = $('<div>').addClass('rounded-md border border-gray-200 bg-white p-3');
                    section.append($('<h4>').addClass('mb-2 text-sm font-medium text-gray-800').text(group.branch_name));
                    const badges = $('<div>').addClass('flex flex-wrap gap-1.5');
                    if (group.roles?.length) {
                        group.roles.forEach(role => badges.append(
                            $('<span>').addClass('rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-700').text(role.name)
                        ));
                    } else {
                        badges.append($('<span>').addClass('text-xs text-gray-500').text('Sin roles asignados'));
                    }
                    section.append(badges);
                    container.append(section);
                });
            }

            async function openUserRolesModal(user) {
                selectedRoleUserId = Number(user.user_id || user.id);
                $('#user-roles-modal-title').text(`Roles de ${user.name || 'usuario'}`);
                if (canChooseRoleBranch) {
                    $('#user-roles-branch-groups').prev('h3').text('Roles por sede');
                }
                $('#user-roles-error').addClass('hidden').text('');
                const branches = (user.branches_data || []).filter(branch =>
                    canChooseRoleBranch || Number(branch.id) === initialRoleBranchId
                );
                const branchOptions = branches.map(branch => `<option value="${Number(branch.id)}">${$('<div>').text(branch.name).html()}</option>`).join('');
                $('#user-roles-branch').html(branchOptions);
                $('#user-roles-branch').prop('disabled', !canChooseRoleBranch);
                $('#user-roles-branch').prev('label').text(canChooseRoleBranch ? 'Sede' : 'Sede activa');
                selectedRoleBranchId = branches.some(branch => Number(branch.id) === initialRoleBranchId)
                    ? initialRoleBranchId : Number(branches[0]?.id || 0);
                $('#user-roles-branch').val(String(selectedRoleBranchId)).trigger('change.select2');
                userRolesModal.removeClass('hidden').addClass('flex');
                $('body').addClass('overflow-hidden');
                try {
                    if (!selectedRoleBranchId) throw new Error('Este usuario no tiene sedes asignadas.');
                    await loadBranchRoles(selectedRoleBranchId);
                } catch (error) {
                    $('#user-roles-error').removeClass('hidden').text(error.message);
                }
            }

            function closeUserRolesModal() {
                userRolesModal.addClass('hidden').removeClass('flex');
                $('body').removeClass('overflow-hidden');
                selectedRoleUserId = null;
            }

            $('#user-roles-cancel').on('click', closeUserRolesModal);
            $('#user-roles-branch').on('change', async function() {
                selectedRoleBranchId = Number(this.value);
                $('#user-roles-error').addClass('hidden').text('');
                try { await loadBranchRoles(selectedRoleBranchId); }
                catch (error) { $('#user-roles-error').removeClass('hidden').text(error.message); }
            });
            userRolesModal.on('click', function(event) {
                if (event.target === this) closeUserRolesModal();
            });
            $('#user-roles-save').on('click', async function() {
                if (!selectedRoleUserId) return;
                const saveButton = this;
                const roleIds = userRolesModal.find('.user-role-modal-option:checked').map(function() {
                    return Number(this.value);
                }).get();
                const errorBox = $('#user-roles-error');
                errorBox.addClass('hidden').text('');
                if (!roleIds.length) {
                    errorBox.removeClass('hidden').text('Selecciona al menos un rol.');
                    return;
                }

                saveButton.disabled = true;
                try {
                    const response = await fetch(`/users/${selectedRoleUserId}/roles`, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ role_ids: roleIds, branch_id: selectedRoleBranchId })
                    });
                    const responseBody = await response.json();
                    if (!response.ok) {
                        const message = Object.values(responseBody.errors || {}).flat()[0] || responseBody.message || 'No se pudieron guardar los roles.';
                        errorBox.removeClass('hidden').text(message);
                        return;
                    }

                    closeUserRolesModal();
                    tableActive.ajax.reload(null, false);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Roles actualizados', showConfirmButton: false, timer: 1800 });
                } catch (error) {
                    errorBox.removeClass('hidden').text(error.message || 'Ocurrió un error al guardar los roles.');
                } finally {
                    saveButton.disabled = false;
                }
            });

            const userSpecialtiesModal = $('#user-specialties-modal');
            let selectedSpecialtyUserId = null;
            let selectedSpecialtyBranchId = null;
            let specialtyCatalog = [];
            let selectedSpecialtyIds = [];
            const canChooseSpecialtyBranch = @json($isHeadquarters);

            function renderUserSpecialtyGroups(groups) {
                const container = $('#user-specialties-groups').empty();
                if (!groups.length) {
                    container.append($('<p>').addClass('text-sm text-gray-500').text('Sin especialidades asignadas.'));
                    return;
                }
                groups.forEach(group => {
                    const card = $('<div>').addClass('rounded-md border border-gray-200 bg-white p-3');
                    card.append($('<h4>').addClass('mb-2 text-sm font-medium text-gray-800').text(group.branch_name));
                    const badges = $('<div>').addClass('flex flex-wrap gap-1.5');
                    if (group.specialties?.length) group.specialties.forEach(item => badges.append(
                        $('<span>').addClass('rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700').text(item.name)
                    ));
                    else badges.append($('<span>').addClass('text-xs text-gray-500').text('Sin especialidades asignadas'));
                    card.append(badges);
                    container.append(card);
                });
            }

            async function loadUserBranchSpecialties(branchId) {
                selectedSpecialtyBranchId = Number(branchId);
                const response = await fetch(`/users/${selectedSpecialtyUserId}/specialties?branch_id=${selectedSpecialtyBranchId}`, { headers: { 'Accept': 'application/json' } });
                const body = await response.json();
                if (!response.ok) throw new Error(body.message || 'No se pudieron cargar las especialidades.');
                renderUserSpecialtyGroups(body.groups || []);
                specialtyCatalog = body.specialties || [];
                selectedSpecialtyIds = (body.assigned || []).map(Number);
                const specialtySelect = $('#user-specialties-select').empty().append($('<option>').val('').text('Selecciona una especialidad'));
                specialtyCatalog.forEach(specialty => specialtySelect.append($('<option>').val(specialty.id).text(specialty.name)));
                specialtySelect.val('').trigger('change.select2');
                renderSelectedSpecialties(!body.can_assign);
                if (!body.specialties?.length) specialtySelect.append($('<option>').val('').text('No hay especialidades activas'));
                $('#user-specialties-save').prop('disabled', !body.can_assign);
                if (!body.can_assign) $('#user-specialties-options').prepend($('<p>').addClass('w-full text-sm text-amber-700').text('Asigna primero el rol Doctor a este usuario en esta sede.'));
            }

            function renderSelectedSpecialties(disabled = false) {
                const container = $('#user-specialties-options').empty();
                if (!selectedSpecialtyIds.length) {
                    container.append($('<span>').addClass('text-sm text-gray-500').text('Aún no hay especialidades asignadas en esta sede.'));
                    return;
                }
                selectedSpecialtyIds.forEach(id => {
                    const specialty = specialtyCatalog.find(item => Number(item.id) === Number(id));
                    if (!specialty) return;
                    const badge = $('<span>').addClass('inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700');
                    badge.append($('<span>').text(specialty.name));
                    badge.append($('<button>').attr({ type: 'button', 'aria-label': `Quitar ${specialty.name}` }).addClass('user-specialty-remove rounded-full px-1 text-blue-700 hover:bg-blue-200 disabled:cursor-not-allowed').prop('disabled', disabled).attr('data-id', id).text('×'));
                    container.append(badge);
                });
            }

            $('#user-specialties-select').on('change', function() {
                const id = Number($('#user-specialties-select').val());
                if (!id) return;
                if (!selectedSpecialtyIds.includes(id)) {
                    selectedSpecialtyIds.push(id);
                    renderSelectedSpecialties();
                }
                $(this).val('').trigger('change.select2');
            });
            $('#user-specialties-options').on('click', '.user-specialty-remove', function() {
                selectedSpecialtyIds = selectedSpecialtyIds.filter(id => id !== Number(this.dataset.id));
                renderSelectedSpecialties();
            });

            async function openUserSpecialtiesModal(user) {
                selectedSpecialtyUserId = Number(user.user_id || user.id);
                $('#user-specialties-title').text(`Especialidades de ${user.name || 'usuario'}`);
                $('#user-specialties-error').addClass('hidden').text('');
                const branches = (user.branches_data || []).filter(branch =>
                    canChooseSpecialtyBranch || Number(branch.id) === initialRoleBranchId
                );
                $('#user-specialties-branch').html(branches.map(branch => `<option value="${Number(branch.id)}">${$('<div>').text(branch.name).html()}</option>`).join(''));
                $('#user-specialties-branch').prop('disabled', !canChooseSpecialtyBranch).trigger('change.select2');
                $('#user-specialties-branch').prev('label').text(canChooseSpecialtyBranch ? 'Sede a editar' : 'Sede activa');
                $('#user-specialties-description').text(canChooseSpecialtyBranch
                    ? 'Selecciona las especialidades que este usuario atenderá en la sede elegida.'
                    : 'Se muestran y editan únicamente las especialidades asignadas en esta sede.');
                selectedSpecialtyBranchId = branches.some(branch => Number(branch.id) === initialRoleBranchId) ? initialRoleBranchId : Number(branches[0]?.id || 0);
                $('#user-specialties-branch').val(String(selectedSpecialtyBranchId)).trigger('change.select2');
                userSpecialtiesModal.removeClass('hidden').addClass('flex');
                $('body').addClass('overflow-hidden');
                try {
                    if (!selectedSpecialtyBranchId) throw new Error('Este usuario no tiene sedes asignadas.');
                    await loadUserBranchSpecialties(selectedSpecialtyBranchId);
                } catch (error) {
                    $('#user-specialties-error').removeClass('hidden').text(error.message);
                }
            }

            function closeUserSpecialtiesModal() {
                userSpecialtiesModal.addClass('hidden').removeClass('flex');
                $('body').removeClass('overflow-hidden');
                selectedSpecialtyUserId = null;
            }

            $('#professional-table').on('click', '.edit-specialties-btn', function() {
                const user = tableActive.row($(this).closest('tr')).data();
                if (user) openUserSpecialtiesModal({ ...user, id: Number(this.dataset.id) });
            });
            $('#user-specialties-branch').on('change', async function() {
                $('#user-specialties-error').addClass('hidden').text('');
                try { await loadUserBranchSpecialties(this.value); }
                catch (error) { $('#user-specialties-error').removeClass('hidden').text(error.message); }
            });
            $('#user-specialties-cancel').on('click', closeUserSpecialtiesModal);
            userSpecialtiesModal.on('click', event => { if (event.target === userSpecialtiesModal[0]) closeUserSpecialtiesModal(); });
            $('#user-specialties-save').on('click', async function() {
                if (!selectedSpecialtyUserId || !selectedSpecialtyBranchId) return;
                if (this.disabled) return;
                const button = this;
                const specialtyIds = selectedSpecialtyIds;
                const error = $('#user-specialties-error').addClass('hidden').text('');
                button.disabled = true;
                try {
                    const response = await fetch(`/users/${selectedSpecialtyUserId}/specialties`, {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify({ branch_id: selectedSpecialtyBranchId, specialty_ids: specialtyIds })
                    });
                    const body = await response.json();
                    if (!response.ok) throw new Error(Object.values(body.errors || {}).flat()[0] || body.message || 'No se pudieron guardar las especialidades.');
                    closeUserSpecialtiesModal();
                    tableActive.ajax.reload(null, false);
                    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Especialidades actualizadas', showConfirmButton: false, timer: 1800 });
                } catch (errorMessage) {
                    error.removeClass('hidden').text(errorMessage.message || 'Ocurrió un error al guardar las especialidades.');
                } finally { button.disabled = false; }
            });

            $(document).on('click.professionalActions', function(event) {
                if (!$(event.target).closest('#professional-actions-menu, .user-actions-btn').length) {
                    professionalActionsMenu.addClass('hidden');
                    selectedProfessionalActionRow = null;
                }
            });
            $(document).on('keydown.professionalActions', function(event) {
                if (event.key === 'Escape') {
                    professionalActionsMenu.addClass('hidden');
                    selectedProfessionalActionRow = null;
                    if (!userBranchesModal.hasClass('hidden')) closeUserBranchesModal();
                    if (!userRolesModal.hasClass('hidden')) closeUserRolesModal();
                    if (!userSpecialtiesModal.hasClass('hidden')) closeUserSpecialtiesModal();
                }
            });

            $('#professional-table').on('click', '.edit-roles-btn', function() {
                const user = tableActive.row($(this).closest('tr')).data() || {};
                const userId = Number(this.dataset.id);
                if (!Number.isInteger(userId) || userId < 1) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo identificar al usuario de esta fila. Actualiza la tabla e inténtalo nuevamente.' });
                    return;
                }
                openUserRolesModal({ ...user, user_id: userId });
                return;

                const currentRoleIds = (user?.roles || []).map(role => Number(role.id));
                const roleOptions = clinicUserRoles.map(role => {
                    const checked = currentRoleIds.includes(Number(role.id)) ? 'checked' : '';
                    const safeName = $('<div>').text(role.name).html();
                    return `<label class="flex items-center gap-3 rounded-lg border border-gray-200 px-4 py-3 text-left cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" class="user-role-option rounded border-gray-300 text-indigo-600" value="${role.id}" ${checked}>
                        <span class="text-sm font-medium text-gray-700">${safeName}</span>
                    </label>`;
                }).join('');

                Swal.fire({
                    title: 'Roles de ' + (user?.name || 'usuario'),
                    html: `<div class="max-h-72 space-y-2 overflow-y-auto text-left">${roleOptions}</div><p class="mt-3 text-xs text-gray-500">Puedes seleccionar más de un rol, por ejemplo Doctor y Administrador.</p>`,
                    showCancelButton: true,
                    confirmButtonText: 'Guardar roles',
                    cancelButtonText: 'Cancelar',
                    confirmButtonColor: '{{ themeColor(600) }}',
                    preConfirm: () => {
                        const selected = [...Swal.getPopup().querySelectorAll('.user-role-option:checked')].map(input => Number(input.value));
                        if (!selected.length) {
                            Swal.showValidationMessage('Selecciona al menos un rol.');
                            return false;
                        }
                        return selected;
                    }
                }).then(async result => {
                    if (!result.isConfirmed) return;
                    try {
                        const response = await fetch(`/users/${userId}/roles`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                            },
                            body: JSON.stringify({ role_ids: result.value })
                        });
                        const responseBody = await response.text();
                        let data = {};
                        try {
                            data = responseBody ? JSON.parse(responseBody) : {};
                        } catch (_) {
                            data = { message: responseBody.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300) };
                        }
                        if (!response.ok) {
                            const validationMessage = Object.values(data.errors || {}).flat()[0];
                            throw new Error(data.message || validationMessage || `No se pudieron actualizar los roles (HTTP ${response.status}).`);
                        }
                        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: 'Roles actualizados', showConfirmButton: false, timer: 1800 });
                        tableActive.ajax.reload(null, false);
                    } catch (error) {
                        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
                    }
                });
            });



            // EDITAR
            $('#professional-table').on('click', '.edit-professional-btn', function() {

                const userId = Number(this.dataset.id);
                let data = tableActive.row($(this).closest('tr')).data();
                if (!data || !data.name) {
                    data = tableActive.rows().data().toArray().find(row => Number(row.user_id) === userId);
                }
                data = data ? { ...data, id: userId } : { id: userId };

                window.dispatchEvent(new CustomEvent('edit-professional', {
                    detail: data
                }));
            });


            $('#professional-table').on('click', '.delete-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data() || {};
                data.id = Number(this.dataset.id);

                let id = data.id;
                let name = data.name;

                Swal.fire({
                    title: '¿Eliminar usuario?',
                    html: `Esto lo eliminará del sistema permanentemente.
        `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {

                    if (!result.isConfirmed) {
                        return;
                    }

                    fetch(`/users/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]'
                                ).content
                            }
                        })
                        .then(async res => {

                            const data = await res.json();

                            if (!res.ok) {
                                throw new Error(
                                    data.message ||
                                    'No se pudo eliminar el usuario.'
                                );
                            }

                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Usuario eliminado',
                                text: data.message ||
                                    'El usuario fue eliminado correctamente.',
                                showConfirmButton: false,
                                timer: 2000
                            });

                            tableActive.ajax.reload(null, false);
                        })
                        .catch(error => {

                            Swal.fire({
                                icon: 'error',
                                title: 'No se puede eliminar',
                                text: error.message
                            });

                        });

                });

            });

            // VER PROFESIONAL
            $('#professional-table').on('click', '.view-professional-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data() || {};
                data.id = Number(this.dataset.id);

                window.dispatchEvent(new CustomEvent('view-professional', {
                    detail: data
                }));
            });


            // CAMBIAR ESTADO
            $('#professional-table').on('click', '.toggle-status', function() {

                let id = $(this).data('id');

                let currentStatus = $(this).data('status');
                let branchId = Number($(this).data('branch-id'));

                let newStatus = currentStatus == 1 ? 0 : 1;

                fetch(`/users/${id}/toggle-status`, {

                        method: 'PATCH',

                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector(
                                'meta[name="csrf-token"]').content
                        },

                        body: JSON.stringify({
                            status: newStatus,
                            branch_id: branchId
                        })

                    })
                    .then(async res => {
                        const data = await res.json();
                        if (!res.ok) throw new Error(data.message || 'No se pudo actualizar el estado del usuario.');

                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Estado actualizado',
                            showConfirmButton: false,
                            timer: 1500
                        });

                        $('#professional-table').DataTable().ajax.reload(null, false);
                    })
                    .catch(error => Swal.fire({
                        icon: 'warning',
                        title: 'No se puede desactivar',
                        text: error.message,
                        confirmButtonText: 'Entendido'
                    }));
            });





            //horarios
            $('#professional-table').on('click', '.schedule-btn', function() {

                let data = tableActive.row($(this).closest('tr')).data() || {};
                data.id = Number(this.dataset.id);

                window.dispatchEvent(new CustomEvent('professional-schedule', {
                    detail: data
                }));

            });




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

                activeBranchId: null,

                isHeadquarters: @json($isHeadquarters),

                activeBranchStatus() {
                    const branch = this.professional?.branches_data?.find(item => Number(item.id) === Number(this.activeBranchId));
                    return Number(branch?.status || 0);
                },

                schedules: [],

                branchRoles() {
                    return this.professional?.roles_by_branch?.find(group => Number(group.branch_id) === Number(this.activeBranchId))?.roles || [];
                },

                branchSpecialties() {
                    return this.professional?.specialties_by_branch?.find(group => Number(group.branch_id) === Number(this.activeBranchId))?.specialties || [];
                },

                branchSchedules() {
                    return this.schedules.filter(schedule => Number(schedule.clinic_branch_id) === Number(this.activeBranchId));
                },

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
                    VitaIdentification.load(`/users/${data.id}/identification`,this.professional).catch(error=>Swal.fire({icon:'error',text:error.message}));

                    this.schedules = [];
                    this.activeBranchId = Number(data?.branches_data?.[0]?.id || 0);

                    this.open = true;

                    this.loadSchedules();
                },

                loadSchedules() {

                    if (!this.professional?.id) {
                        return;
                    }

                    this.loading = true;

                    const scheduleQuery = this.isHeadquarters
                        ? '?all_branches=1'
                        : `?branch_id=${encodeURIComponent(this.activeBranchId || '')}`;

                    fetch(`/professional/${this.professional.id}/schedule${scheduleQuery}`, {
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
                open: false,
                isEdit: false,
                appDomain: '{{ config('app.domain') }}',


                form: {
                    id: null,
                    name: '',
                    email: '',
                    password: '',
                    role_ids: [],
                    branch_ids: [],
                    specialties: [],
                    specialty_branch_id: String(defaultSpecialtyBranchId || ''),
                    status: '1',
                    rut: '',
                    ...VitaIdentification.defaults(),
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

                isDoctorSelected() {
                    const doctorRole = clinicUserRoles.find(role => role.name === 'Doctor');
                    return !!doctorRole && (this.form.role_ids || []).map(String).includes(String(doctorRole.id));
                },

                create() {

                    this.isEdit = false;
                    this.identificationLoadFailed = false;

                    this.form = {
                        id: null,
                        name: '',
                        email: '',
                        password: '',
                        role_ids: [],
                        branch_ids: [],
                        specialties: [],
                        specialty_branch_id: String(defaultSpecialtyBranchId || ''),
                        status: '1',
                        rut: '',
                    ...VitaIdentification.defaults(),
                        phone: ''
                    };
                    selectedSpecialties = [];

                    document.getElementById('specialtyList').innerHTML = '';

                    $('#specialtySelect').val('').trigger('change');

                    this.errors = {};

                    this.open = true;
                },

                edit(data) {

                    this.identificationLoadFailed = false;

                    this.isEdit = true;

                    const specialties = Array.isArray(data.branch_specialties) ?
                        data.branch_specialties : (Array.isArray(data.specialties) ? data.specialties : []);
                    let branchIds = data.branch_ids ?? [];
                    if (typeof branchIds === 'string') {
                        try {
                            branchIds = JSON.parse(branchIds);
                        } catch (_) {
                            branchIds = branchIds.trim() ? [branchIds] : [];
                        }
                    }
                    if (branchIds && typeof branchIds === 'object' && !Array.isArray(branchIds)) {
                        branchIds = Object.values(branchIds);
                    }
                    if (!Array.isArray(branchIds)) {
                        branchIds = branchIds === null || branchIds === undefined ? [] : [branchIds];
                    }

                    this.form = {
                        id: Number(data.id),
                        name: data.name ?? '',
                        email: data.email ?? '',
                        ...VitaIdentification.defaults(),
                        rut: data.rut ?? '',
                        phone: data.phone ?? '',
                        password: '',
                        role_ids: (data.roles || []).map(role => String(role.id)),
                        branch_ids: branchIds.map(id => String(id)),
                        specialties: specialties.map(s => String(s.id)),
                        specialty_branch_id: String(defaultSpecialtyBranchId || ''),
                        status: String(data.status ?? 1)
                    };

                    this.errors = {};

                    VitaIdentification.load(`/users/${data.id}/identification`,this.form).catch(error => {this.identificationLoadFailed=true; Swal.fire({icon:'error',text:error.message});});

                    // Guardar especialidades seleccionadas
                    selectedSpecialties = specialties.map(s => String(s.id));

                    // Limpiar lista visual
                    const specialtyList = document.getElementById('specialtyList');

                    specialtyList.innerHTML = '';

                    // Pintar especialidades existentes
                    specialties.forEach(specialty => {

                        const id = String(specialty.id);
                        const name = specialty.name;

                        const div = document.createElement('div');

                        div.className =
                            'specialty-item inline-flex items-center gap-2 bg-blue-100 text-blue-700 px-3 py-2 rounded-full';

                        div.innerHTML = `
                                        <span>
                                            ${name}
                                        </span>

                                        <input
                                            type="hidden"
                                            value="${id}">

                                        <button
                                            type="button"
                                            class="remove-specialty">

                                            <i class="fa-solid fa-xmark"></i>

                                        </button>
                                    `;

                        specialtyList.appendChild(div);
                    });

                    // Resetear select
                    $('#specialtySelect').val('').trigger('change');

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
                        role_ids: [],
                        branch_ids: [],
                        specialties: [],
                        specialty_branch_id: String(defaultSpecialtyBranchId || ''),
                        status: '1',
                        rut: '',
                    ...VitaIdentification.defaults(),
                        phone: ''
                    };

                    // limpiar especialidades visuales
                    selectedSpecialties = [];

                    document.getElementById('specialtyList').innerHTML = '';

                    $('#specialtySelect').val('').trigger('change');
                },

                submit() {
                    if(this.identificationLoadFailed || VitaIdentification.isLoading(this.form))return;

                    const payload = { ...this.form };
                    delete payload.rut;
                    if (this.isEdit) {
                        delete payload.role_ids;
                        delete payload.branch_ids;
                    }

                    fetch(this.isEdit ? `/users/${this.form.id}` : '/users', {

                            method: this.isEdit ? 'PUT' : 'POST',

                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector(
                                    'meta[name="csrf-token"]').content
                            },

                            body: JSON.stringify(payload)
                        })

                        .then(async res => {

                            const data = await res.json();

                            // ERROR
                            if (!res.ok) {
                                this.errors = data.errors || {};
                                const firstError = Object.values(this.errors).flat()[0] || data.message;
                                if (firstError) {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'No se pudo guardar el usuario',
                                        text: firstError,
                                        confirmButtonText: 'Entendido'
                                    });
                                }
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
                        })
                        .catch(error => {
                            Swal.fire({
                                icon: 'error',
                                title: 'No se pudo guardar el usuario',
                                text: error.message || 'Ocurrió un error de conexión. Inténtalo nuevamente.',
                                confirmButtonText: 'Entendido'
                            });
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


        @include('customer.user.partials.schedule-script')
        const specialtySelect = document.getElementById('specialtySelect');
        const specialtyList = document.getElementById('specialtyList');


        let selectedSpecialties = [];


        $(specialtySelect).on('change', function() {

            let id = this.value;
            let name = this.options[this.selectedIndex].text;


            if (!id) return;


            // evitar repetidos
            if (selectedSpecialties.includes(id)) {
                $(this).val('').trigger('change');
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


            $(this).val('').trigger('change');

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
