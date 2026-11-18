

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi Perfil</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto w-full space-y-6 px-4 sm:px-6 lg:px-8" style="width: calc(100% - 3rem);">
            @if ($errors->any())
                <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">No se pudieron guardar los cambios:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('superadmin.profile.update') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                @include('components.identification-fields', ['kind'=>'user'])

                <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800"><i class="fa-solid fa-user mr-2 text-indigo-600"></i>Datos personales</h3>
                        <p class="mt-1 text-sm text-gray-500">Información de tu cuenta de Vita Clínica.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                        <div>
                            <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre completo</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" maxlength="255" autocomplete="tel" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700">Tipo de cuenta</label>
                            <div class="flex h-[42px] items-center">
                                <span class="inline-flex rounded-full bg-violet-100 px-3 py-1 text-sm font-semibold text-violet-800">Super Administrador</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800"><i class="fa-solid fa-lock mr-2 text-indigo-600"></i>Cambiar contraseña</h3>
                        <p class="mt-1 text-sm text-gray-500">Deja estos campos vacíos si no deseas cambiarla.</p>
                    </div>
                    <div class="grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label for="current_password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña actual</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Nueva contraseña</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-6 py-3 font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
