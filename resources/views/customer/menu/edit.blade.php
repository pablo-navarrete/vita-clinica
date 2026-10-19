@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Editar elemento del menú</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-3xl sm:px-6 lg:px-8">
        <section class="rounded-lg bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('customers.menus.update', ['customer' => request()->route('customer'), 'id' => $menu->id]) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre</label>
                    <input id="name" name="name" value="{{ old('name', $menu->name) }}" required maxlength="80"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @if ($menu->websitePage)
                        <p class="mt-1 text-xs text-gray-500">Este nombre también será el título principal de la página.</p>
                    @endif
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @unless ($menu->websitePage)
                <div>
                    <label for="url" class="mb-1 block text-sm font-medium text-gray-700">Destino</label>
                    <input id="url" name="url" value="{{ old('url', $menu->url) }}" required maxlength="255"
                        class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        placeholder="Inicio, Servicios, Contacto o una URL">
                    <p class="mt-1 text-xs text-gray-500">Puedes escribir Inicio, Quiénes somos, Servicios, Especialidades o Contacto; también acepta /ruta, #seccion y direcciones http:// o https://.</p>
                    @error('url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @endunless

                @if ($menu->websitePage?->slug === 'inicio')
                    <input type="hidden" name="status" value="1">
                    <p class="rounded-md bg-blue-50 px-4 py-3 text-sm text-blue-800">Inicio es la página principal y siempre permanecerá activa. Puedes editar su contenido desde el editor de página.</p>
                @else
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <label for="status" class="mb-1 block text-sm font-medium text-gray-700">Estado</label>
                        <select id="status" name="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="1" @selected((string) old('status', (int) $menu->status) === '1')>Activo</option>
                            <option value="0" @selected((string) old('status', (int) $menu->status) === '0')>Inactivo</option>
                        </select>
                    </div>
                </div>
                @endif

                <div class="flex justify-end gap-3 border-t pt-5">
                    <a href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}"
                        class="rounded-md bg-gray-100 px-4 py-2 font-medium text-gray-700 hover:bg-gray-200">Volver</a>
                    <button type="submit" class="rounded-md px-4 py-2 font-semibold text-white hover:brightness-90"
                        style="background-color: {{ themeColor(600) }}">Guardar cambios</button>
                </div>
            </form>
        </section>
    </div>
@endsection
