@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Barra superior del sitio web</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if (session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">{{ session('success') }}</div>
        @endif
        <form method="POST" action="{{ route('customers.website-topbar.update', ['customer' => request()->route('customer')]) }}" class="space-y-6">
            @csrf
            @method('PUT')
            <section class="rounded-lg bg-white p-6 shadow-sm">
                <h3 class="mb-1 text-lg font-semibold text-gray-800">Contenido de la franja superior</h3>
                <p class="mb-5 text-sm text-gray-600">Esta franja aparece sobre la navegación pública. Las redes sociales y sus enlaces se administran en el módulo Redes sociales.</p>

                <input type="hidden" name="enabled" value="0">
                <label class="mb-5 inline-flex items-center gap-2 font-medium text-gray-700">
                    <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $topbar->enabled ?? true)) class="rounded border-gray-300">
                    Mostrar la franja superior en el sitio público
                </label>

                <div class="mb-5">
                    <input type="hidden" name="show_social_links" value="0">
                    <label class="inline-flex items-center gap-2 font-medium text-gray-700">
                        <input type="checkbox" name="show_social_links" value="1" @checked(old('show_social_links', $topbar->show_social_links ?? false)) class="rounded border-gray-300">
                        Mostrar redes sociales configuradas
                    </label>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="topbar-phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                        <input id="topbar-phone" name="phone" value="{{ old('phone', $topbar->phone) }}" maxlength="40" class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="topbar-email" class="mb-1 block text-sm font-medium text-gray-700">Correo</label>
                        <input id="topbar-email" name="email" type="email" value="{{ old('email', $topbar->email) }}" maxlength="160" class="w-full rounded-md border-gray-300 shadow-sm">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
            <div class="flex justify-end pb-6">
                <button type="submit" class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">Guardar barra superior</button>
            </div>
        </form>
    </div>
@endsection
