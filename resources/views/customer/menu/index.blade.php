@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Menú del sitio web</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('error'))
            <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800">{{ session('error') }}</div>
        @endif
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-1 text-lg font-semibold text-gray-800">Crear página en blanco</h3>
            <p class="mb-5 text-sm text-gray-500">La página se agregará al menú público y luego podrás construirla con secciones, textos e imágenes.</p>

            <form method="POST" action="{{ route('customers.menus.store', ['customer' => request()->route('customer')]) }}"
                class="grid grid-cols-1 items-end gap-4 md:grid-cols-12">
                @csrf
                <div class="md:col-span-10">
                    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre de la página y del enlace</label>
                    <input id="name" name="name" value="{{ old('name') }}" required maxlength="80"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Ej. Quiénes somos">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1" @selected(old('status', '1') === '1')>Activo</option>
                        <option value="0" @selected(old('status') === '0')>Inactivo</option>
                    </select>
                </div>
                <div class="md:col-span-12">
                    <button id="create-menu-button" type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90"
                        style="background-color: {{ themeColor(600) }}">
                        <i class="fa-solid fa-plus mr-1"></i> Crear página
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Páginas del menú</h3>
                <span class="text-sm text-gray-500">{{ $menus->count() }} elemento(s)</span>
            </div>

            @if ($menus->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">
                    Aún no hay páginas. Crea una para comenzar a armar el sitio público.
                </div>
            @else
                <div class="overflow-x-auto">
                    <table id="menus-table" class="min-w-full divide-y divide-gray-200"
                        data-reorder-url="{{ route('customers.menus.reorder', ['customer' => request()->route('customer')]) }}"
                        data-toggle-status-url="{{ route('customers.menus.toggle-status', ['customer' => request()->route('customer'), 'id' => '__ID__']) }}">
                        <thead style="background-color: {{ themeColor(100) }}">
                            <tr>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-600">Mover</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Orden</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Nombre</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Página</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="menu-sortable-list" class="divide-y divide-gray-100 bg-white">
                            @foreach ($menus as $menu)
                                <tr data-id="{{ $menu->id }}" @if ($menu->websitePage?->slug === 'inicio') data-home="1" @endif>
                                    <td class="whitespace-nowrap px-3 py-3 text-center">
                                        @if ($menu->websitePage?->slug === 'inicio')
                                            <span class="text-gray-400" title="Inicio siempre permanece en la posición 1" aria-label="Posición fija"><i class="fa-solid fa-lock"></i></span>
                                        @else
                                            <button type="button" class="reorder-handle cursor-move text-gray-400 hover:text-gray-700"
                                                title="Arrastra para cambiar el orden" aria-label="Arrastrar para cambiar el orden">
                                                <i class="fa-solid fa-grip-vertical"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $menu->position }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ $menu->name }}</td>
                                    <td class="max-w-sm truncate px-4 py-3 text-sm text-gray-600">
                                        @if ($menu->websitePage?->slug === 'inicio')
                                            <span class="mr-2 rounded-full bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-800">Página principal</span>/
                                        @else
                                            {{ $menu->websitePage ? '/pages/' . $menu->websitePage->slug : $menu->url }}
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        @if ($menu->websitePage?->slug === 'inicio')
                                            <span class="rounded px-2 py-1 text-xs text-white bg-green-500">Activo</span>
                                        @else
                                            <button type="button" class="toggle-menu-status rounded px-2 py-1 text-xs text-white {{ $menu->status ? 'bg-green-500' : 'bg-red-500' }}"
                                                data-id="{{ $menu->id }}" data-status="{{ $menu->status ? 1 : 0 }}">
                                                {{ $menu->status ? 'Activo' : 'Inactivo' }}
                                            </button>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            @if ($menu->websitePage)
                                                <a href="{{ route('customers.website-pages.edit', ['customer' => request()->route('customer'), 'id' => $menu->websitePage->id]) }}"
                                                    class="rounded-md bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-200">Editar página</a>
                                                @unless ($menu->websitePage->slug === 'inicio')
                                                    <a href="{{ route('customers.menus.edit', ['customer' => request()->route('customer'), 'id' => $menu->id]) }}"
                                                        class="rounded-md bg-gray-100 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-200">Editar menú</a>
                                                @endunless
                                            @else
                                                <a href="{{ route('customers.menus.edit', ['customer' => request()->route('customer'), 'id' => $menu->id]) }}"
                                                    class="rounded-md bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-200">Editar enlace</a>
                                            @endif
                                            @unless ($menu->websitePage?->slug === 'inicio')
                                                <form method="POST" action="{{ route('customers.menus.destroy', ['customer' => request()->route('customer'), 'id' => $menu->id]) }}"
                                                    data-swal-confirm="¿Eliminar esta página del menú?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200">Eliminar</button>
                                                </form>
                                            @endunless
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css">
    <style>
        #menus-table.dataTable {
            border-collapse: separate !important;
            border-spacing: 0;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        #menus-table.dataTable thead th:first-child { border-top-left-radius: 0.75rem; }
        #menus-table.dataTable thead th:last-child { border-top-right-radius: 0.75rem; }
        #menus-table tbody tr.dt-rowReorder-moving { background-color: #f3f4f6; }
        #menus-table .reorder-handle { touch-action: none; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js"></script>
    <script>
        $(function () {
            const tableElement = document.getElementById('menus-table');
            if (!tableElement) return;

            const table = $('#menus-table').DataTable({
                paging: false,
                info: false,
                order: [[1, 'asc']],
                rowReorder: { dataSrc: 1, selector: '.reorder-handle' },
                columnDefs: [
                    { targets: [0, 5], orderable: false, searchable: false },
                    { targets: 0, className: 'text-center' }
                ],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
            });

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const showMessage = (icon, title) => {
                if (typeof Toast !== 'undefined') return Toast.fire({ icon, title });
                if (window.Swal) return Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 3000, showConfirmButton: false });
                window.alert(title);
            };

            $('#menus-table').on('click', '.toggle-menu-status', async function () {
                const button = this;
                const url = tableElement.dataset.toggleStatusUrl.replace('__ID__', button.dataset.id);
                button.disabled = true;
                try {
                    const response = await fetch(url, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'No se pudo actualizar el estado.');

                    const active = result.status;
                    button.dataset.status = active ? '1' : '0';
                    button.textContent = active ? 'Activo' : 'Inactivo';
                    button.classList.toggle('bg-green-500', active);
                    button.classList.toggle('bg-red-500', !active);
                    showMessage('success', result.message);
                } catch (error) {
                    showMessage('error', error.message || 'No se pudo actualizar el estado.');
                } finally {
                    button.disabled = false;
                }
            });

            table.on('row-reorder', async function (event, diff) {
                if (!diff.length) return;

                // RowReorder entrega la posición nueva de cada fila movida.
                // Usamos esos valores antes de redibujar, porque el orden inicial
                // de DataTables todavía puede conservar el orden anterior.
                const changedPositions = new Map(diff.map(change => [
                    Number(change.node.dataset.id),
                    Number(change.newData)
                ]));
                const orderedRows = [];

                table.rows({ search: 'none' }).every(function () {
                    const node = this.node();
                    const id = Number(node.dataset.id);
                    const position = changedPositions.has(id)
                        ? changedPositions.get(id)
                        : Number(this.data()[1]);
                    orderedRows.push({ id, position });
                });

                orderedRows.sort((a, b) => a.position - b.position || a.id - b.id);
                const orderedIds = orderedRows.map(row => row.id);
                const homeId = Number(tableElement.querySelector('tbody tr[data-home="1"]')?.dataset.id || 0);
                if (homeId) {
                    const homeIndex = orderedIds.indexOf(homeId);
                    if (homeIndex > -1) {
                        orderedIds.splice(homeIndex, 1);
                        orderedIds.unshift(homeId);
                    }
                }

                // Normalizar los valores para que siempre queden 1, 2, 3...
                orderedIds.forEach((id, index) => {
                    const row = table.row((rowIndex, rowData, node) => Number(node.dataset.id) === id);
                    const rowData = row.data();
                    rowData[1] = index + 1;
                    row.data(rowData);
                });
                table.draw(false);

                try {
                    const response = await fetch(tableElement.dataset.reorderUrl, {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ items: orderedIds })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'No se pudo guardar el orden.');
                    showMessage('success', result.message);
                } catch (error) {
                    showMessage('error', error.message || 'No se pudo guardar el orden.');
                    window.location.reload();
                }
            });
        });

        document.addEventListener('submit', async (event) => {
            const form = event.target.closest('form[data-swal-confirm]');
            if (!form) return;

            event.preventDefault();
            const result = await Swal.fire({
                title: '¿Eliminar página?',
                text: form.dataset.swalConfirm,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#dc2626'
            });

            if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
        });
    </script>
@endpush
