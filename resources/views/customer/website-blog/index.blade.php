@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Blog de la web pública</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="rounded-xl bg-white p-5 shadow-sm sm:p-6">
            <div class="space-y-5 rounded-xl border border-blue-100 bg-blue-50/70 p-5 sm:p-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Visibilidad del blog</h3>
                    <p class="mt-1 text-sm text-gray-600">Al ocultarlo, el enlace y los artículos dejan de estar disponibles para los visitantes. Tus publicaciones se conservan.</p>
                </div>
                <form method="POST" enctype="multipart/form-data" action="{{ route('customers.website-blog.settings', ['customer' => request()->route('customer')]) }}" class="grid gap-5">
                    @csrf @method('PUT')
                    <input type="hidden" name="enabled" value="0">
                    <div class="grid gap-5 md:grid-cols-2 md:items-start">
                        <div>
                            <label for="blog-title" class="mb-1 block text-sm font-medium text-gray-700">Nombre que se mostrará en la web</label>
                            <input id="blog-title" name="title" value="{{ old('title', $setting->title ?? 'Blog') }}" maxlength="80" required class="w-full rounded-md border-gray-300 uppercase shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="blog-banner" class="mb-1 block text-sm font-medium text-gray-700">Imagen del banner <span class="font-normal text-gray-500">(opcional, máximo 10 MB)</span></label>
                    <input id="blog-banner" type="file" name="banner" accept="image/*" class="sr-only">
                    <label id="blog-banner-dropzone" for="blog-banner" class="mt-2 flex min-h-36 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-white px-5 py-6 text-center transition hover:border-blue-400 hover:bg-blue-50">
                        @if($setting->banner_path)
                            <img src="{{ asset('storage/' . $setting->banner_path) }}" alt="" class="mb-3 h-20 max-w-full rounded-lg object-cover shadow-sm">
                            <span class="text-sm font-semibold text-gray-700">Haz clic para cambiar el banner</span>
                        @else
                            <i class="fa-solid fa-cloud-arrow-up mb-2 text-3xl text-gray-400"></i>
                            <span class="text-sm font-semibold text-gray-700">Arrastra una imagen aquí o haz clic</span>
                        @endif
                        <span id="blog-banner-filename" class="mt-1 text-xs text-gray-500">JPG, PNG o WEBP</span>
                    </label>
                            @error('banner') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-medium text-gray-700 md:col-span-2">
                            <input type="checkbox" name="enabled" value="1" @checked($setting->enabled) class="rounded border-gray-300" style="color: {{ themeColor(600) }}">
                            Mostrar blog en la web
                        </label>
                        @if($setting->banner_path)
                            <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600"><input type="checkbox" name="remove_banner" value="1" class="rounded border-gray-300"> Quitar imagen actual</label>
                        @endif
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-blue-100 pt-4">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $setting->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $setting->enabled ? 'Visible' : 'Oculto' }}</span>
                        <button class="rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm" style="background: {{ themeColor(600) }}">Guardar configuración</button>
                    </div>
                </form>
            </div>
            @error('enabled') <p class="mt-3 text-sm text-red-600">{{ $message }}</p> @enderror
        </section>

        <section class="overflow-hidden rounded-xl bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4 sm:px-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Artículos</h3>
                    <p class="text-sm text-gray-500">Solo las publicaciones activas se muestran en la web.</p>
                </div>
                <a href="{{ route('customers.website-blog.create', ['customer' => request()->route('customer')]) }}" class="inline-flex items-center rounded-md px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:brightness-90" style="background: {{ themeColor(600) }}">
                    <i class="fa-solid fa-plus mr-1"></i> Nuevo artículo
                </a>
            </div>
            <div class="p-6 text-gray-900">
                <div class="overflow-x-auto">
                    <table id="website-blog-table" data-blog-enabled="{{ $setting->enabled ? '1' : '0' }}" class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                    <thead style="background-color: {{ themeColor(100) }}">
                        <tr><th class="px-5 py-3">#</th><th class="px-5 py-3">Artículo</th><th class="px-5 py-3">Estado</th><th class="px-5 py-3">Fecha</th><th class="px-5 py-3 text-right">Acciones</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($posts as $post)
                            <tr>
                                <td></td>
                                <td class="px-5 py-4">
                                    <div class="flex items-center gap-3">
                                        @if ($post->image_path)<img src="{{ asset('storage/' . $post->image_path) }}" alt="" class="h-12 w-16 rounded-md object-cover">@endif
                                        <div><div class="font-semibold text-gray-900">{{ $post->title }}</div><div class="text-xs text-gray-500">/blog/{{ $post->slug }}</div></div>
                                    </div>
                                </td>
                                <td class="px-5 py-4">
                                    <form method="POST" class="blog-status-form" action="{{ route('customers.website-blog.toggle-status', ['customer' => request()->route('customer'), 'id' => $post->id]) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit" data-status="{{ $post->status ? '1' : '0' }}" class="rounded px-2 py-1 text-xs text-white {{ $post->status ? 'bg-green-500' : 'bg-red-500' }}" title="Cambiar estado">
                                            {{ $post->status ? 'Publicado' : 'No publicado' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="whitespace-nowrap px-5 py-4 text-gray-600">{{ $post->published_at?->format('d/m/Y') ?? $post->created_at->format('d/m/Y') }}</td>
                                <td class="whitespace-nowrap px-5 py-4 text-left">
                                    <div class="inline-flex flex-row items-center justify-start blog-actions">
                                        <a target="_blank" rel="noopener" class="blog-view-action text-yellow-500 mr-2" title="Vista previa del artículo" aria-label="Vista previa del artículo" href="{{ route('customers.website-blog.preview', ['customer' => request()->route('customer'), 'id' => $post->id, 'public_view' => 1]) }}"><i class="fa-regular fa-eye"></i></a>
                                        <a class="text-blue-500 mr-2" title="Editar" href="{{ route('customers.website-blog.edit', ['customer' => request()->route('customer'), 'id' => $post->id]) }}"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <form method="POST" action="{{ route('customers.website-blog.destroy', ['customer' => request()->route('customer'), 'id' => $post->id]) }}" data-swal-confirm="¿Eliminar este artículo?">@csrf @method('DELETE')<button class="text-red-500 mr-2" title="Eliminar"><i class="fa-solid fa-trash-can"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('styles')
    <style>
        #website-blog-table .blog-actions { direction: ltr; flex-direction: row; justify-content: flex-start; }
        #website-blog-table .blog-actions form { display: inline-flex; margin: 0; }
        #website-blog-table_wrapper .dataTables_length,
        #website-blog-table_wrapper .dataTables_filter { margin-bottom: 1rem; }
        #website-blog-table_wrapper .dataTables_length label,
        #website-blog-table_wrapper .dataTables_filter label { color: #374151; font-weight: 500; }
        #website-blog-table_wrapper .dataTables_length select,
        #website-blog-table_wrapper .dataTables_filter input {
            min-height: 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background-color: #fff;
        }
        #website-blog-table_wrapper .dataTables_filter input { margin-left: 0.5rem; padding: 0.4rem 0.75rem; }
        #website-blog-table_wrapper .dataTables_length select { margin: 0 0.4rem; padding: 0.35rem 1.75rem 0.35rem 0.65rem; }
        #website-blog-table_wrapper .dataTables_info { padding-top: 0.75rem; color: #6b7280; }
        #website-blog-table_wrapper .dataTables_paginate { padding-top: 0.5rem; }
        #website-blog-table_wrapper .dataTables_paginate .paginate_button {
            min-width: 2.25rem;
            margin: 0 0.15rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid transparent !important;
            border-radius: 0.5rem;
            color: #374151 !important;
        }
        #website-blog-table_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #d1d5db !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
        }
        #website-blog-table_wrapper .dataTables_paginate .paginate_button.current {
            border-color: {{ themeColor(600) }} !important;
            background: {{ themeColor(600) }} !important;
            color: #fff !important;
        }
        #website-blog-table_wrapper .dataTables_paginate .paginate_button.disabled,
        #website-blog-table_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            border-color: transparent !important;
            background: transparent !important;
            color: #9ca3af !important;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            #website-blog-table_wrapper .dataTables_length,
            #website-blog-table_wrapper .dataTables_filter { float: none; text-align: left; }
            #website-blog-table_wrapper .dataTables_filter input { width: min(14rem, 60vw); }
            #website-blog-table_wrapper .dataTables_info,
            #website-blog-table_wrapper .dataTables_paginate { float: none; text-align: center; }
        }
    </style>
@endpush

@push('scripts')
    <script>
        $(function () {
            const bannerInput = document.getElementById('blog-banner');
            const bannerDropzone = document.getElementById('blog-banner-dropzone');
            const bannerFilename = document.getElementById('blog-banner-filename');
            let bannerPreviewUrl = null;

            if (bannerInput && bannerFilename) {
                bannerInput.addEventListener('change', () => {
                    const file = bannerInput.files[0];
                    bannerFilename.textContent = file?.name || 'JPG, PNG o WEBP';

                    if (!file || !bannerDropzone || !file.type.startsWith('image/')) return;

                    if (bannerPreviewUrl) URL.revokeObjectURL(bannerPreviewUrl);
                    bannerPreviewUrl = URL.createObjectURL(file);

                    let preview = document.getElementById('blog-banner-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.id = 'blog-banner-preview';
                        preview.className = 'mb-3 h-24 max-w-full rounded-lg object-cover shadow-sm';
                        preview.alt = 'Vista previa del banner seleccionado';
                        bannerDropzone.prepend(preview);
                    }

                    preview.src = bannerPreviewUrl;
                    preview.classList.remove('hidden');
                    bannerDropzone.querySelectorAll('i, span:not(#blog-banner-filename), img:not(#blog-banner-preview)')
                        .forEach(element => element.classList.add('hidden'));
                });
            }

            if (bannerInput && bannerDropzone) {
                bannerDropzone.addEventListener('dragover', event => {
                    event.preventDefault();
                    bannerDropzone.classList.add('border-blue-500', 'bg-blue-50');
                });
                bannerDropzone.addEventListener('dragleave', () => {
                    bannerDropzone.classList.remove('border-blue-500', 'bg-blue-50');
                });
                bannerDropzone.addEventListener('drop', event => {
                    event.preventDefault();
                    bannerDropzone.classList.remove('border-blue-500', 'bg-blue-50');
                    if (!event.dataTransfer.files.length) return;

                    const transfer = new DataTransfer();
                    transfer.items.add(event.dataTransfer.files[0]);
                    bannerInput.files = transfer.files;
                    bannerInput.dispatchEvent(new Event('change', { bubbles: true }));
                });
            }

            const table = document.getElementById('website-blog-table');
            if (!table) return;

            $('#website-blog-table').DataTable({
                responsive: false,
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                pagingType: 'full_numbers',
                autoWidth: false,
                order: [],
                columnDefs: [
                    {
                        targets: 0,
                        orderable: false,
                        searchable: false,
                        render: function (_data, _type, _row, meta) {
                            return meta.row + meta.settings._iDisplayStart + 1;
                        }
                    },
                    { targets: 4, orderable: false, searchable: false }
                ],
                language: {
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ artículos',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ artículos',
                    infoEmpty: 'No hay artículos para mostrar',
                    infoFiltered: '(filtrado de _MAX_ artículos)',
                    zeroRecords: 'No se encontraron artículos',
                    emptyTable: 'Aún no hay artículos. Crea uno para comenzar.',
                    paginate: {
                        first: 'Primera',
                        last: 'Última',
                        next: 'Siguiente',
                        previous: 'Anterior'
                    }
                }
            });
        });

        document.addEventListener('submit', async event => {
            const statusForm = event.target.closest('form.blog-status-form');
            if (statusForm) {
                event.preventDefault();
                const button = statusForm.querySelector('button[type="submit"]');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
                button.disabled = true;

                try {
                    const response = await fetch(statusForm.action, {
                        method: 'PATCH',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                    });
                    const result = await response.json();
                    if (!response.ok) throw new Error(result.message || 'No se pudo actualizar el estado.');

                    const isPublished = Boolean(result.status);
                    button.dataset.status = isPublished ? '1' : '0';
                    button.textContent = isPublished ? 'Publicado' : 'No publicado';
                    button.classList.toggle('bg-green-500', isPublished);
                    button.classList.toggle('bg-red-500', !isPublished);

                    const row = statusForm.closest('tr');
                    const dateCell = row?.cells[3];
                    if (dateCell && result.published_at) {
                        dateCell.textContent = result.published_at;
                        $('#website-blog-table').DataTable().cell(dateCell).data(result.published_at);
                    }


                    if (window.Toast) Toast.fire({ icon: 'success', title: result.message });
                    else Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: result.message, timer: 3000, showConfirmButton: false });
                } catch (error) {
                    Swal.fire({ icon: 'error', title: 'No se pudo actualizar el estado', text: error.message });
                } finally {
                    button.disabled = false;
                }
                return;
            }

            const form = event.target.closest('form[data-swal-confirm]');
            if (!form) return;

            event.preventDefault();
            const result = await Swal.fire({
                title: '¿Eliminar artículo?',
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



