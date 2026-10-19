@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Footer del sitio web</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('success'))<div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>@endif
        <form method="POST" action="{{ route('customers.website-footer.update', ['customer' => request()->route('customer')]) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">Contenido del footer</h3>
                <p class="mb-5 text-sm text-gray-600">Edita la información de contacto o desactiva el footer para quitarlo del sitio público.</p>
                <input type="hidden" name="enabled" value="0">
                <label class="mb-5 inline-flex items-center gap-2 font-medium text-gray-700">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $footer->enabled ?? true)) class="rounded border-gray-300">
                    Mostrar footer en el sitio público
                </label>
                <div class="mb-5">
                    <input type="hidden" name="show_social_links" value="0">
                    <label class="inline-flex items-center gap-2 font-medium text-gray-700">
                        <input type="checkbox" name="show_social_links" value="1" @checked(old('show_social_links', $footer->show_social_links ?? true)) class="rounded border-gray-300">
                        Mostrar redes sociales agregadas en el footer
                    </label>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="footer-title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
                        <input id="footer-title" name="title" value="{{ old('title', $footer->title) }}" maxlength="120" class="w-full rounded-md border-gray-300 shadow-sm" placeholder="{{ app('customer')->name }}">
                        @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="footer-phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                        <input id="footer-phone" name="phone" value="{{ old('phone', $footer->phone) }}" maxlength="40" class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="footer-email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                        <input id="footer-email" name="email" type="email" value="{{ old('email', $footer->email) }}" maxlength="160" class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="footer-address" class="mb-1 block text-sm font-medium text-gray-700">Dirección</label>
                        <input id="footer-address" name="address" value="{{ old('address', $footer->address) }}" maxlength="255" class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('address')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-2">
                        <label for="footer-description" class="mb-1 block text-sm font-medium text-gray-700">Descripción</label>
                        <textarea id="footer-description" name="description" rows="4" maxlength="1200" class="w-full rounded-md border-gray-300 shadow-sm">{{ old('description', $footer->description) }}</textarea>
                        @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
            <div class="flex flex-wrap justify-between gap-3 pb-6">
                <a href="{{ route('customers.menus.index', ['customer' => request()->route('customer')]) }}" class="rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700">Volver al menú</a>
                <button type="submit" class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar footer</button>
            </div>
        </form>
    </div>
@endsection
