@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Banners de la página pública</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <h3 class="mb-1 text-lg font-semibold text-gray-800">Agregar banner</h3>
            <div class="mb-5 rounded-lg border border-blue-200 bg-blue-50 p-4">
                <div class="flex items-start">
                    <i class="fa-solid fa-circle-info mt-1 mr-3 text-blue-600"></i>
                    <div>
                        <h3 class="font-semibold text-blue-800">Información</h3>
                        <p class="mt-1 text-sm text-blue-700">
                            Puedes registrar hasta <strong>5 banners</strong>.
                            Tienes {{ $banners->count() }} de 5 registrados; se cuentan también los inactivos.
                            Si alcanzas el límite, elimina un banner antes de agregar otro. Los banners activos aparecen en la portada y puedes ordenarlos desde la tabla.
                        </p>
                    </div>
                </div>
            </div>
            @error('banner_limit') <p class="mb-4 rounded-md bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</p> @enderror

            <form method="POST" action="{{ route('customers.banners.store', ['customer' => request()->route('customer')]) }}"
                enctype="multipart/form-data" class="grid grid-cols-1 items-start gap-4 md:grid-cols-12">
                @csrf
                <div class="md:col-span-6">
                    <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                    <input id="title" name="title" value="{{ old('title') }}" required maxlength="120"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-6">
                    <label for="subtitle" class="mb-1 block text-sm font-medium text-gray-700">Subtítulo (opcional)</label>
                    <input id="subtitle" name="subtitle" value="{{ old('subtitle') }}" maxlength="160"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('subtitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-12">
                    <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Descripción (opcional)</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="button_text" class="mb-1 block text-sm font-medium text-gray-700">Texto del botón (opcional)</label>
                    <input id="button_text" name="button_text" value="{{ old('button_text') }}" maxlength="60"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="button_destination" class="mb-1 block text-sm font-medium text-gray-700">¿A dónde llevará el botón?</label>
                    <select id="button_destination" name="button_destination" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="" @selected(old('button_destination', '') === '')>Sin botón</option>
                        <optgroup label="Páginas publicadas">
                            <option value="/" @selected(old('button_destination') === '/')>Inicio</option>
                            @foreach ($pages as $page)
                                @unless ($page->slug === 'inicio')
                                    @php($pageUrl = '/pages/' . $page->slug)
                                    <option value="{{ $pageUrl }}" @selected(old('button_destination') === $pageUrl)>{{ $page->title }}</option>
                                @endunless
                            @endforeach
                        </optgroup>
                        <optgroup label="Secciones de la portada">
                            <option value="#quienes-somos" @selected(old('button_destination') === '#quienes-somos')>Quiénes somos</option>
                            <option value="#servicios" @selected(old('button_destination') === '#servicios')>Servicios</option>
                            <option value="#especialidades" @selected(old('button_destination') === '#especialidades')>Especialidades</option>
                        </optgroup>
                        <option value="__custom__" @selected(old('button_destination') === '__custom__')>Enlace personalizado…</option>
                    </select>
                    @error('button_destination') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div id="button-custom-url-container" class="{{ old('button_destination') === '__custom__' ? '' : 'hidden' }} mt-2">
                        <input id="button_url_custom" name="button_url_custom" value="{{ old('button_url_custom') }}" maxlength="255"
                            placeholder="/ruta, #seccion o https://..."
                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @error('button_url_custom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <p class="mt-1 text-xs text-gray-500">El botón aparece cuando eliges un destino y escribes su texto.</p>
                </div>
                <div class="md:col-span-4">
                    <label for="image_alt" class="mb-1 block text-sm font-medium text-gray-700">Descripción de imagen (opcional)</label>
                    <input id="image_alt" name="image_alt" value="{{ old('image_alt') }}" maxlength="160"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @error('image_alt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-8">
                    <label for="image" class="mb-1 block text-sm font-medium text-gray-700">Imagen del banner</label>
                    <input id="image" name="image" type="file" accept="image/*" required
                        class="block w-full rounded-md border border-gray-300 text-sm text-gray-700 file:mr-4 file:rounded-l-md file:border-0 file:bg-gray-100 file:px-4 file:py-2">
                    <p class="mt-1 text-xs text-gray-500">Imagen de hasta 10 MB. Usa una imagen horizontal para la portada.</p>
                    @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-4">
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="1" @selected(old('status', '1') === '1')>Activo</option>
                        <option value="0" @selected(old('status') === '0')>Inactivo</option>
                    </select>
                </div>
                <div class="md:col-span-12">
                    <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">
                        <i class="fa-solid fa-plus mr-1"></i> Agregar banner
                    </button>
                </div>
            </form>
        </section>

        <section class="overflow-hidden rounded-lg bg-white p-6 shadow-sm">
            <div class="mb-4 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800">Banners registrados</h3>
                <span class="text-sm text-gray-500">{{ $banners->count() }} banner(s)</span>
            </div>
            @if ($banners->isEmpty())
                <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-gray-500">Aún no hay banners registrados.</div>
            @else
                <div class="overflow-x-auto">
                    <table id="banners-table" class="min-w-full divide-y divide-gray-200"
                        data-reorder-url="{{ route('customers.banners.reorder', ['customer' => request()->route('customer')]) }}"
                        data-toggle-status-url="{{ route('customers.banners.toggle-status', ['customer' => request()->route('customer'), 'id' => '__ID__']) }}">
                        <thead style="background-color: {{ themeColor(100) }}">
                            <tr>
                                <th class="px-3 py-3 text-center text-xs font-semibold uppercase text-gray-600">Mover</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Orden</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Imagen</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Título</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-600">Estado</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-600">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @foreach ($banners as $banner)
                                <tr data-id="{{ $banner->id }}">
                                    <td class="whitespace-nowrap px-3 py-3 text-center">
                                        <button type="button" class="banner-reorder-handle cursor-move text-gray-400 hover:text-gray-700" title="Arrastra para cambiar el orden" aria-label="Arrastrar para cambiar el orden"><i class="fa-solid fa-grip-vertical"></i></button>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600">{{ $banner->position }}</td>
                                    <td class="px-4 py-3"><img src="{{ asset('storage/' . $banner->image_path) }}" alt="{{ $banner->image_alt ?: $banner->title }}" class="h-14 w-24 rounded object-cover"></td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $banner->title }}</td>
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <button type="button" class="toggle-banner-status rounded px-2 py-1 text-xs text-white {{ $banner->status ? 'bg-green-500' : 'bg-red-500' }}" data-id="{{ $banner->id }}" data-status="{{ $banner->status ? 1 : 0 }}">{{ $banner->status ? 'Activo' : 'Inactivo' }}</button>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('customers.banners.edit', ['customer' => request()->route('customer'), 'id' => $banner->id]) }}" class="rounded-md bg-amber-100 px-3 py-1.5 text-sm font-medium text-amber-800 hover:bg-amber-200">Editar</a>
                                            <form method="POST" action="{{ route('customers.banners.destroy', ['customer' => request()->route('customer'), 'id' => $banner->id]) }}" data-swal-confirm="¿Eliminar este banner?">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="rounded-md bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200">Eliminar</button>
                                            </form>
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
        #banners-table.dataTable { border-collapse: separate !important; border-spacing: 0; border-radius: .75rem; overflow: hidden; }
        #banners-table.dataTable thead th:first-child { border-top-left-radius: .75rem; }
        #banners-table.dataTable thead th:last-child { border-top-right-radius: .75rem; }
        #banners-table .banner-reorder-handle { touch-action: none; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js"></script>
    <script>
        $(function () {
            const buttonDestination = document.getElementById('button_destination');
            const customUrlContainer = document.getElementById('button-custom-url-container');
            if (buttonDestination && customUrlContainer) {
                const customUrlInput = document.getElementById('button_url_custom');
                const toggleCustomUrl = () => {
                    const customSelected = buttonDestination.value === '__custom__';
                    customUrlContainer.classList.toggle('hidden', !customSelected);
                    if (!customSelected && customUrlInput) customUrlInput.value = '';
                };
                buttonDestination.addEventListener('change', toggleCustomUrl);
                toggleCustomUrl();
            }

            const tableElement = document.getElementById('banners-table');
            if (!tableElement) return;
            const table = $('#banners-table').DataTable({
                paging: false,
                info: false,
                order: [[1, 'asc']],
                rowReorder: { dataSrc: 1, selector: '.banner-reorder-handle' },
                columnDefs: [{ targets: [0, 5], orderable: false, searchable: false }],
                language: { url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }
            });
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const notify = (icon, title) => typeof Toast !== 'undefined'
                ? Toast.fire({ icon, title })
                : Swal.fire({ toast: true, position: 'top-end', icon, title, timer: 3000, showConfirmButton: false });

            $('#banners-table').on('click', '.toggle-banner-status', async function () {
                const button = this;
                button.disabled = true;
                try {
                    const url = tableElement.dataset.toggleStatusUrl.replace('__ID__', button.dataset.id);
                    const response = await fetch(url, { method: 'PATCH', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'No se pudo actualizar el estado.');
                    button.dataset.status = result.status ? '1' : '0';
                    button.textContent = result.status ? 'Activo' : 'Inactivo';
                    button.classList.toggle('bg-green-500', result.status);
                    button.classList.toggle('bg-red-500', !result.status);
                    notify('success', result.message);
                } catch (error) {
                    notify('error', error.message || 'No se pudo actualizar el estado.');
                } finally { button.disabled = false; }
            });

            table.on('row-reorder', async function (event, diff) {
                if (!diff.length) return;
                const changedPositions = new Map(diff.map(change => [Number(change.node.dataset.id), Number(change.newData)]));
                const orderedRows = [];
                table.rows({ search: 'none' }).every(function () {
                    const node = this.node();
                    const id = Number(node.dataset.id);
                    orderedRows.push({ id, position: changedPositions.get(id) ?? Number(this.data()[1]) });
                });
                orderedRows.sort((a, b) => a.position - b.position || a.id - b.id);
                const orderedIds = orderedRows.map(row => row.id);
                orderedIds.forEach((id, index) => {
                    const row = table.row((rowIndex, rowData, node) => Number(node.dataset.id) === id);
                    const data = row.data();
                    data[1] = index + 1;
                    row.data(data);
                });
                table.draw(false);

                try {
                    const response = await fetch(tableElement.dataset.reorderUrl, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ items: orderedIds })
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'No se pudo guardar el orden.');
                    notify('success', result.message);
                } catch (error) {
                    notify('error', error.message || 'No se pudo guardar el orden.');
                    window.location.reload();
                }
            });

            document.addEventListener('submit', async event => {
                const form = event.target.closest('form[data-swal-confirm]');
                if (!form) return;
                event.preventDefault();
                const result = await Swal.fire({ title: '¿Eliminar banner?', text: form.dataset.swalConfirm, icon: 'warning', showCancelButton: true, confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' });
                if (result.isConfirmed) HTMLFormElement.prototype.submit.call(form);
            });
        });
    </script>
@endpush
