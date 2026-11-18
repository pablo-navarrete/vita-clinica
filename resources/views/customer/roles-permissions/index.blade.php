@extends('layouts.menu-customer')

@section('header')
    <div>
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Roles y permisos</h2>
        <p class="mt-1 text-sm text-gray-500">Administra los accesos de los usuarios de {{ app('customer')->name }}.</p>
    </div>
@endsection

@section('content')
<div class="pb-6">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        
        
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <h3 class="mb-3 text-base font-semibold text-gray-800">Crear rol para esta clínica</h3>
            <form method="POST" action="{{ route('customers.roles-permissions.store', ['customer' => request()->route('customer')]) }}" class="flex flex-col gap-3 sm:flex-row">
                @csrf
                <input name="name" value="{{ old('name') }}" maxlength="80" required placeholder="Ej.: Recepción" class="min-h-11 flex-1 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                <button class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">Crear rol</button>
            </form>
        </section>

        <div class="grid gap-5 xl:grid-cols-2">
            @foreach ($roles as $role)
                <section class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4">
                        <div><h3 class="font-semibold text-gray-900">{{ $role->name }}</h3><p class="mt-1 text-xs text-gray-500">{{ $role->users_count }} usuario(s)</p></div>
                        @if (!in_array($role->name, ['Administrador', 'Doctor', 'Secretaria'], true))
                            <form method="POST" action="{{ route('customers.roles-permissions.destroy', ['customer' => request()->route('customer'), 'roleId' => $role->id]) }}" data-swal-confirm="¿Eliminar el rol {{ $role->name }}? Esta acción no se puede deshacer.">@csrf @method('DELETE')<button class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Eliminar</button></form>
                        @else
                            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600">Rol base</span>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('customers.roles-permissions.update', ['customer' => request()->route('customer'), 'roleId' => $role->id]) }}">
                        @csrf @method('PUT')
                        <div class="grid gap-4 p-5 sm:grid-cols-2">
                            @foreach ($permissions as $module => $modulePermissions)
                                <fieldset class="rounded-lg border border-gray-200 p-3"><legend class="px-1 text-sm font-semibold text-gray-700">{{ $module }}</legend><div class="space-y-2">
                                    @foreach ($modulePermissions as $permission)
                                        <label class="flex cursor-pointer items-start gap-2 text-sm text-gray-600"><input type="checkbox" name="permissions[]" value="{{ $permission->id }}" @checked($role->permissions->contains('id', $permission->id)) class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"><span>{{ $permission->name }}</span></label>
                                    @endforeach
                                </div></fieldset>
                            @endforeach
                        </div>
                        <div class="flex justify-end border-t border-gray-100 px-5 py-4"><button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Guardar permisos</button></div>
                    </form>
                </section>
            @endforeach
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('submit', async event => {
        const form = event.target.closest('form[data-swal-confirm]');
        if (!form) return;

        event.preventDefault();
        const result = await Swal.fire({
            title: '¿Eliminar rol?',
            text: form.dataset.swalConfirm,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#dc2626',
            cancelButtonColor: '#6b7280'
        });

        if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
    });
</script>
@endpush
