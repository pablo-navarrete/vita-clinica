@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar banner</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('customers.banners.update', ['customer' => request()->route('customer'), 'id' => $banner->id]) }}" enctype="multipart/form-data" class="grid grid-cols-1 gap-5 md:grid-cols-2">
                @csrf @method('PUT')
                @php
                    $pageUrls = $pages->reject(fn ($page) => $page->slug === 'inicio')->map(fn ($page) => '/pages/' . $page->slug)->all();
                    $knownUrls = array_merge(['/'], $pageUrls, ['#quienes-somos', '#servicios', '#especialidades']);
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
                    <select id="button_destination" name="button_destination" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="" @selected($selectedDestination === '')>Sin botón</option>
                        <optgroup label="Páginas publicadas">
                            <option value="/" @selected($selectedDestination === '/')>Inicio</option>
                            @foreach ($pages as $page)
                                @unless ($page->slug === 'inicio')
                                    @php($pageUrl = '/pages/' . $page->slug)
                                    <option value="{{ $pageUrl }}" @selected($selectedDestination === $pageUrl)>{{ $page->title }}</option>
                                @endunless
                            @endforeach
                        </optgroup>
                        <optgroup label="Secciones de la portada">
                            <option value="#quienes-somos" @selected($selectedDestination === '#quienes-somos')>Quiénes somos</option>
                            <option value="#servicios" @selected($selectedDestination === '#servicios')>Servicios</option>
                            <option value="#especialidades" @selected($selectedDestination === '#especialidades')>Especialidades</option>
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
                    <img src="{{ asset('storage/' . $banner->image_path) }}" alt="{{ $banner->image_alt ?: $banner->title }}" class="mb-3 h-48 w-full rounded-lg object-cover">
                    <label for="image" class="mb-1 block text-sm font-medium text-gray-700">Cambiar imagen (opcional)</label>
                    <input id="image" name="image" type="file" accept="image/*" class="block w-full rounded-md border border-gray-300 text-sm file:mr-4 file:border-0 file:bg-gray-100 file:px-4 file:py-2">
                    @error('image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="image_alt" class="mb-1 block text-sm font-medium text-gray-700">Descripción de imagen</label>
                    <input id="image_alt" name="image_alt" value="{{ old('image_alt', $banner->image_alt) }}" maxlength="160" class="w-full rounded-md border-gray-300 shadow-sm">
                    @error('image_alt') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                    <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="1" @selected((string) old('status', (int) $banner->status) === '1')>Activo</option>
                        <option value="0" @selected((string) old('status', (int) $banner->status) === '0')>Inactivo</option>
                    </select>
                </div>
                <div class="flex justify-end gap-3 border-t pt-5 md:col-span-2">
                    <a href="{{ route('customers.banners.index', ['customer' => request()->route('customer')]) }}" class="rounded-md bg-gray-100 px-4 py-2 font-medium text-gray-700 hover:bg-gray-200">Volver</a>
                    <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar cambios</button>
                </div>
            </form>
        </section>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
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
