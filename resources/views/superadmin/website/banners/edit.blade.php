<x-app-layout>
<x-slot name="header">
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar banner</h2>
</x-slot>
<div class="p-6">

@if($errors->any())<div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('superadmin.banners.update', ['id' => $banner->id]) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @csrf @method('PUT')
                @php
                    $pageUrls = $pages->reject(fn ($page) => $page->slug === 'inicio')->map(fn ($page) => $page->url)->all();
                    $knownUrls = array_merge(['/'], $pageUrls, ['#funcionalidades', '#preguntas', '#contacto']);
                    $savedDestination = $banner->button_url ?? '';
                    if ($savedDestination !== '' && !in_array($savedDestination, $knownUrls, true)) {
                        $savedDestination = '__custom__';
                    }
                    $selectedDestination = old('button_destination', $savedDestination);
                    $customDestination = old('button_url_custom', $selectedDestination === '__custom__' ? $banner->button_url : '');
                @endphp
                <div>
                    <label for="title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                    <input id="title" name="title" value="{{ old('title', $banner->title) }}" required maxlength="120" class="w-full rounded-md border-gray-300 shadow-sm">
                    @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="subtitle" class="mb-1 block text-sm font-medium text-gray-700">Subtítulo (opcional)</label>
                    <input id="subtitle" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" maxlength="160" class="w-full rounded-md border-gray-300 shadow-sm">
                    @error('subtitle') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div class="md:col-span-2">
                    <label for="description" class="mb-1 block text-sm font-medium text-gray-700">Descripción (opcional)</label>
                    <textarea id="description" name="description" rows="3" maxlength="2000" class="w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $banner->description) }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="button_text" class="mb-1 block text-sm font-medium text-gray-700">Texto del botón</label>
                    <input id="button_text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" maxlength="60" class="w-full rounded-md border-gray-300 shadow-sm">
                    @error('button_text') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="button_destination" class="mb-1 block text-sm font-medium text-gray-700">¿A dónde llevará el botón?</label>
                    <select id="button_destination" name="button_destination" class="vita-banner-select w-full rounded-md border-gray-300 shadow-sm">
                        <option value="" @selected($selectedDestination === '')>Sin botón</option>
                        <optgroup label="Páginas publicadas">
                            <option value="/" @selected($selectedDestination === '/')>Inicio</option>
                            @foreach ($pages as $page)
                                @unless ($page->slug === 'inicio')
                                    @php($pageUrl = $page->url)
                                    <option value="{{ $pageUrl }}" @selected($selectedDestination === $pageUrl)>{{ $page->title }}</option>
                                @endunless
                            @endforeach
                        </optgroup>
                        <optgroup label="Secciones de la portada">
                            <option value="#funcionalidades" @selected($selectedDestination === '#funcionalidades')>Funcionalidades</option>
                            <option value="#preguntas" @selected($selectedDestination === '#preguntas')>Preguntas frecuentes</option>
                            <option value="#contacto" @selected($selectedDestination === '#contacto')>Contacto</option>
                        </optgroup>
                        <option value="__custom__" @selected($selectedDestination === '__custom__')>Enlace personalizado…</option>
                    </select>
                    @error('button_destination') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    <div id="button-custom-url-container" class="{{ $selectedDestination === '__custom__' ? '' : 'hidden' }} mt-2">
                        <input id="button_url_custom" name="button_url_custom" value="{{ $customDestination }}" maxlength="255" placeholder="/ruta, #seccion o https://..." class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('button_url_custom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label for="image" class="mb-1 block text-sm font-medium text-gray-700">Imagen del banner <span class="font-normal text-gray-500">(opcional, deja la actual si no quieres cambiarla)</span></label>
                    <input id="image" name="image" type="file" accept="image/*" class="sr-only">
                    <label id="banner-image-dropzone" for="image" class="mt-2 flex min-h-48 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 bg-gray-50 px-5 py-6 text-center transition hover:border-blue-400 hover:bg-blue-50">
                        <img id="banner-image-preview" src="{{ asset('storage/' . $banner->image_path) }}" alt="Vista previa de la imagen del banner" class="mb-3 max-h-48 max-w-full rounded-lg object-cover shadow-sm">
                        <span id="banner-image-placeholder" class="hidden">
                            <i class="fa-solid fa-cloud-arrow-up mb-2 text-3xl text-gray-400"></i>
                            <span class="block text-sm font-semibold text-gray-700">Arrastra una imagen aquí o haz clic</span>
                        </span>
                        <span id="banner-image-filename" class="mt-1 text-xs text-gray-500">Haz clic para cambiar la imagen</span>
                    </label>
                    @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="image_alt" class="mb-1 block text-sm font-medium text-gray-700">Descripción de imagen</label>
                    <input id="image_alt" name="image_alt" value="{{ old('image_alt', $banner->image_alt) }}" maxlength="160" class="w-full rounded-md border-gray-300 shadow-sm">
                    @error('image_alt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @include('components.banner-alignment-fields', ['alignmentFieldClass' => 'md:col-span-2'])
                @include('components.banner-content-field', ['imageOnly' => old('image_only', $banner->image_only ?? false)])
                @include('components.banner-overlay-field', ['darkOverlay' => old('dark_overlay', $banner->dark_overlay ?? true)])
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select id="status" name="status" class="vita-banner-select w-full rounded-md border-gray-300 shadow-sm">
                        <option value="1" @selected((string) old('status', (int) $banner->status) === '1')>Activo</option>
                        <option value="0" @selected((string) old('status', (int) $banner->status) === '0')>Inactivo</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 border-t pt-5 md:col-span-2">
                    <a href="{{ route('superadmin.website.section', ['section' => 'banners']) }}" class="rounded-md bg-gray-100 px-4 py-2 font-medium text-gray-700 hover:bg-gray-200">Volver</a>
                    <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar cambios</button>
                </div>
            </form>
        </section>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const imageInput = document.getElementById('image');
            const imageDropzone = document.getElementById('banner-image-dropzone');
            const imagePreview = document.getElementById('banner-image-preview');
            const imagePlaceholder = document.getElementById('banner-image-placeholder');
            const imageFilename = document.getElementById('banner-image-filename');
            if (imageInput && imageDropzone && imagePreview && imagePlaceholder && imageFilename) {
                let previewUrl = null;
                const showImage = file => {
                    if (!file || !file.type.startsWith('image/')) return;
                    if (previewUrl) URL.revokeObjectURL(previewUrl);
                    previewUrl = URL.createObjectURL(file);
                    imagePreview.src = previewUrl;
                    imagePreview.classList.remove('hidden');
                    imagePlaceholder.classList.add('hidden');
                    imageFilename.textContent = file.name;
                };
                imageInput.addEventListener('change', () => showImage(imageInput.files[0]));
                imageDropzone.addEventListener('dragover', event => {
                    event.preventDefault();
                    imageDropzone.classList.add('border-blue-500', 'bg-blue-50');
                });
                imageDropzone.addEventListener('dragleave', () => imageDropzone.classList.remove('border-blue-500', 'bg-blue-50'));
                imageDropzone.addEventListener('drop', event => {
                    event.preventDefault();
                    imageDropzone.classList.remove('border-blue-500', 'bg-blue-50');
                    const file = event.dataTransfer.files[0];
                    if (!file || !file.type.startsWith('image/')) return;
                    const transfer = new DataTransfer();
                    transfer.items.add(file);
                    imageInput.files = transfer.files;
                    showImage(file);
                });
            }

            const destination = document.getElementById('button_destination');
            const customUrl = document.getElementById('button-custom-url-container');
            if (!destination || !customUrl) return;
            const customUrlInput = document.getElementById('button_url_custom');
            const toggleCustomUrl = () => {
                const customSelected = destination.value === '__custom__';
                customUrl.classList.toggle('hidden', !customSelected);
                if (!customSelected && customUrlInput) customUrlInput.value = '';
            };
            destination.addEventListener('change', toggleCustomUrl);
            toggleCustomUrl();
        });
    </script>
@endpush
</x-app-layout>
