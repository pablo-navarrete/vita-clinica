<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Sedes de {{ $customer->name }}</h2>
            <a href="{{ route('superadmin.customer') }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">Volver a clínicas</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
            @endif

            <div class="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <div class="mb-6 rounded-lg border border-gray-200 p-5">
                        <h3 class="mb-4 text-lg font-semibold text-gray-800">Agregar sede</h3>
                        <form method="POST" action="{{ route('superadmin.customers.branches.store', $customer) }}" class="grid gap-4 md:grid-cols-3">
                            @csrf
                            <div>
                                <label for="branch-name" class="mb-1 block text-sm font-medium text-gray-700">Nombre de la sede</label>
                                <input id="branch-name" name="name" value="{{ old('name') }}" required maxlength="255" class="w-full rounded border border-gray-300 px-3 py-2" placeholder="Ej. Sede Centro">
                                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="branch-address" class="mb-1 block text-sm font-medium text-gray-700">Dirección</label>
                                <input id="branch-address" name="address" value="{{ old('address') }}" maxlength="255" class="w-full rounded border border-gray-300 px-3 py-2" placeholder="Calle y número">
                                @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label for="branch-phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                                <input id="branch-phone" name="phone" value="{{ old('phone') }}" maxlength="50" class="w-full rounded border border-gray-300 px-3 py-2" placeholder="Teléfono de contacto">
                                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>
                            <div class="md:col-span-3">
                                <button type="submit" class="rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700">
                                    <i class="fa-solid fa-circle-plus mr-1"></i> Agregar sede
                                </button>
                            </div>
                        </form>
                    </div>

                    <h3 class="mb-4 text-lg font-semibold text-gray-800">Sedes registradas</h3>
                    @if ($branches->isEmpty())
                        <p class="mb-4 rounded-md bg-gray-50 p-4 text-sm text-gray-600">Esta clínica no tiene sedes. El inicio de sesión y las citas seguirán funcionando sin solicitar una selección.</p>
                    @endif
                    <div class="overflow-x-auto">
                        <table id="branches-table" class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                            <thead class="bg-blue-100">
                            <tr>
                                <th>#</th>
                                <th>Sede</th>
                                <th>Dirección</th>
                                <th>Teléfono</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach ($branches as $branch)
                                <tr>
                                    <td></td>
                                    <td>{{ $branch->name }}</td>
                                    <td>{{ $branch->address ?: '—' }}</td>
                                    <td>{{ $branch->phone ?: '—' }}</td>
                                    <td>
                                        <button type="button"
                                            class="toggle-branch-status px-2 py-1 rounded text-xs text-white {{ $branch->status ? 'bg-green-500' : 'bg-red-500' }}"
                                            data-url="{{ route('superadmin.customers.branches.status', [$customer, $branch->id]) }}"
                                            data-id="{{ $branch->id }}"
                                            data-status="{{ (int) $branch->status }}">
                                            {{ $branch->status ? 'Activa' : 'Inactiva' }}
                                        </button>
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('superadmin.customers.branches.destroy', [$customer, $branch->id]) }}" class="inline branch-delete-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="delete-btn mr-2 text-red-500" title="Quitar sede" aria-label="Quitar sede">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($branches->isNotEmpty())
                        <p class="mt-3 text-sm text-gray-500">Las sedes inactivas no aparecerán al iniciar sesión ni estarán disponibles para nuevas citas. La clínica debe conservar al menos una sede activa.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@push('scripts')
<script>
    $(function () {
        @if (session('error'))
            Swal.fire({
                icon: 'error',
                title: 'No se pudo realizar la acción',
                text: @json(session('error')),
                confirmButtonText: 'Entendido'
            });
        @endif

        $('#branches-table').DataTable({
            responsive: true,
            paging: true,
            searching: true,
            lengthChange: true,
            info: true,
            pageLength: 10,
            lengthMenu: [5, 10, 25, 50, 100],
            dom: '<"flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4"l f>rt<"flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mt-4"i p>',
            // Evita resaltar permanentemente la columna Sede como columna ordenada.
            order: [],
            columnDefs: [
                {
                    targets: 0,
                    searchable: false,
                    orderable: false,
                    render: function (data, type, row, meta) {
                        return meta.row + meta.settings._iDisplayStart + 1;
                    }
                },
                { targets: 5, orderable: false, searchable: false }
            ],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json',
                emptyTable: 'No hay sedes registradas',
                zeroRecords: 'No se encontraron sedes'
            }
        });

        $('#branches-table').on('submit', '.branch-delete-form', function (event) {
            event.preventDefault();
            const form = this;

            Swal.fire({
                title: '¿Quitar esta sede?',
                text: 'Las citas históricas se conservarán.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Quitar',
                cancelButtonText: 'Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });

        $('#branches-table').on('click', '.toggle-branch-status', async function () {
            const button = this;
            const currentStatus = Number(button.dataset.status);
            const nextStatus = currentStatus === 1 ? 0 : 1;

            try {
                const response = await fetch(button.dataset.url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: nextStatus })
                });
                const result = await response.json();

                if (!response.ok) throw new Error(result.message || 'No se pudo actualizar el estado.');

                button.dataset.status = String(nextStatus);
                button.textContent = nextStatus === 1 ? 'Activa' : 'Inactiva';
                button.classList.toggle('bg-green-500', nextStatus === 1);
                button.classList.toggle('bg-red-500', nextStatus === 0);

                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'success',
                    title: 'Estado actualizado',
                    showConfirmButton: false,
                    timer: 1500
                });
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'No se pudo cambiar el estado',
                    text: error.message,
                    confirmButtonText: 'Entendido'
                });
            }
        });
    });
</script>
@endpush
</x-app-layout>
