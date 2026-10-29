<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Roles y permisos</h2>
                <p class="mt-1 text-sm text-gray-500">Define qué módulos puede consultar y administrar cada rol.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <h3 class="mb-3 text-base font-semibold text-gray-800">Crear rol</h3>
                <form method="POST" action="{{ route('superadmin.roles.store') }}" class="flex flex-col gap-3 sm:flex-row">
                    @csrf
                    <input name="name" value="{{ old('name') }}" maxlength="80" required placeholder="Nombre del nuevo rol" class="min-h-11 flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Crear rol</button>
                </form>
            </section>

            <div class="grid gap-5 xl:grid-cols-2">
                @foreach ($roles as $role)
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                            <div>
                                <h3 class="font-semibold text-gray-900">{{ $role->name }}</h3>
                                <p class="mt-1 text-xs text-gray-500">{{ $role->users_count }} usuario(s) con este rol</p>
                            </div>
                            @if ($role->id <= 4)
                                <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Rol base</span>
                            @else
                                <form method="POST" action="{{ route('superadmin.roles.destroy', $role) }}" onsubmit="return confirm('¿Eliminar este rol?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('superadmin.roles.update', $role) }}">
                            @csrf @method('PUT')
                            <div class="grid gap-4 p-5 sm:grid-cols-2">
                                @foreach ($permissions as $module => $modulePermissions)
                                    <fieldset class="rounded-lg border border-gray-200 p-3">
                                        <legend class="px-1 text-sm font-semibold text-gray-700">{{ $module }}</legend>
                                        <div class="space-y-2">
                                            @foreach ($modulePermissions as $permission)
                                                <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-600">
                                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}"
                                                        @checked($role->permissions->contains('id', $permission->id))
                                                        class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                                    <span>{{ $permission->name }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </fieldset>
                                @endforeach
                            </div>
                            <div class="flex justify-end border-t border-gray-100 px-5 py-4">
                                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Guardar permisos</button>
                            </div>
                        </form>
                    </section>
                @endforeach
            </div>

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-5 py-4">
                    <h3 class="font-semibold text-gray-900">Asignar roles a usuarios</h3>
                    <p class="mt-1 text-sm text-gray-500">La asignación se limita a usuarios de clínicas; el rol Super Administrador no aparece aquí.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Usuario</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Clínica</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Rol</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-5 py-3">
                                        <div class="text-sm font-medium text-gray-800">{{ $user->name }}</div>
                                        <div class="text-xs text-gray-500">{{ $user->email }}</div>
                                    </td>
                                    <td class="px-5 py-3 text-sm text-gray-600">{{ $user->customer?->name ?? 'Clínica no disponible' }}</td>
                                    <td class="px-5 py-3">
                                        <form id="user-role-{{ $user->id }}" method="POST" action="{{ route('superadmin.users.role.update', $user) }}">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="users_page" value="{{ $users->currentPage() }}">
                                            <select name="role_id" class="min-w-44 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                @foreach ($assignableRoles as $assignableRole)
                                                    <option value="{{ $assignableRole->id }}" @selected((int) $user->roles->first()?->id === (int) $assignableRole->id)>{{ $assignableRole->name }}</option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <button type="submit" form="user-role-{{ $user->id }}" class="rounded-lg bg-indigo-600 px-3 py-2 text-xs font-semibold text-white hover:bg-indigo-700">Guardar</button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No hay usuarios de clínicas registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($users->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4">{{ $users->withQueryString()->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
