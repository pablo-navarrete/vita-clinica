@extends('layouts.menu-customer')

@push('styles')
    <style>
        .profile-personal-fields:disabled input {
            background-color: #f3f4f6;
            border-color: #e5e7eb;
            color: #4b5563;
            cursor: not-allowed;
            opacity: 1;
        }
    </style>
@endpush

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">Mi Perfil</h2>
@endsection

@section('content')
    <div class="pb-8">
        <div class="max-w-8xl mx-auto px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">No se guardaron los cambios. Revisa lo siguiente:</p>
                    <ul class="mt-2 list-inside list-disc">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            

            <form x-data="{ form: @js($identificationForm), isEdit: true, errors: {} }" action="{{ route('customers.profile.update', ['customer' => request()->route('customer')]) }}" method="POST">
                @csrf
                @method('PATCH')

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-user mr-2"></i>Datos personales
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $isClinicAdministrator ? 'Actualiza la información de contacto de tu cuenta.' : 'Solo el administrador de tu sede puede modificar tus datos personales.' }}</p>
                    </div>

                    @unless($isClinicAdministrator)
                        <div class="mx-6 mt-5 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
                            <i class="fa-solid fa-lock mt-0.5" aria-hidden="true"></i>
                            <p>No puedes modificar tus datos personales. Solo el administrador de tu sede puede actualizarlos.</p>
                        </div>
                    @endunless
                    <fieldset @disabled(!$isClinicAdministrator) class="profile-personal-fields grid grid-cols-1 gap-5 p-6 md:grid-cols-2">
                        <div>
                            <label for="name" class="mb-1 block text-sm font-medium text-gray-700">Nombre completo</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required maxlength="255" autocomplete="name"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="email" class="mb-1 block text-sm font-medium text-gray-700">Correo electrónico</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required maxlength="255" autocomplete="email"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <div>
                            <label for="phone" class="mb-1 block text-sm font-medium text-gray-700">Teléfono</label>
                            <input id="phone" name="phone" type="tel" value="{{ old('phone', $user->phone) }}" required maxlength="255" autocomplete="tel"
                                   class="w-full rounded-lg border-gray-300 focus:border-transparent focus:ring-2">
                            @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        @include('components.identification-fields', ['kind'=>'user'])
                    </fieldset>
                </section>

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-user-shield mr-2"></i>Roles asignados
                        </h3>
                        @if($canManageOwnAssignments)
                            <button type="button" data-open-assignment="roles" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-pen mr-2" aria-hidden="true"></i>Administrar roles</button>
                        @endif
                        </div>
                        <p class="mt-1 text-sm text-gray-600">{{ $isClinicAdministrator ? 'Tus roles están organizados según las sedes donde los tienes asignados.' : 'Roles asignados en tu sede actual.' }}</p>
                    </div>
                    <div class="space-y-4 p-6">
                        @forelse ($roleGroups as $group)
                            <div class="rounded-lg border border-gray-200 p-4">
                                <div class="mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-gray-500"></i>
                                    <h4 class="font-semibold text-gray-800">{{ $group['branch']->name }}</h4>
                                    @if (! $group['branch']->status)
                                        <span class="text-xs text-gray-500">(Inactiva)</span>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse ($group['roles'] as $role)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-sm text-green-700">{{ $role->name }}</span>
                                    @empty
                                        <span class="text-sm text-gray-500">Sin roles asignados en esta sede.</span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <span class="text-sm text-gray-500">No tienes sedes asignadas.</span>
                        @endforelse
                    </div>
                </section>

@if ($isDoctor || $isClinicAdministrator)
                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-stethoscope mr-2"></i>Información profesional
                        </h3>
                        @if($canManageOwnAssignments)
                            <button type="button" data-open-assignment="specialties" class="shrink-0 rounded-lg px-3 py-2 text-sm font-semibold text-white" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-pen mr-2" aria-hidden="true"></i>Administrar especialidades</button>
                        @endif
                        </div>
                    </div>

                    @if($isClinicAdministrator)
                    <div class="space-y-4 p-6" data-specialties-by-branch>
                        @forelse($specialtyGroups as $group)
                            <div class="rounded-lg border border-gray-200 p-4" data-specialty-branch="{{ $group['branch']->id }}">
                                <div class="mb-3 flex items-center gap-2">
                                    <i class="fa-solid fa-location-dot text-gray-500" aria-hidden="true"></i>
                                    <h4 class="font-semibold text-gray-800">{{ $group['branch']->name }}</h4>
                                    @if(!$group['branch']->status)<span class="text-xs text-gray-500">(Inactiva)</span>@endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @forelse($group['specialties'] as $specialty)
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-sm" style="background-color: {{ themeColor(100) }}; color: {{ themeColor(700) }}">{{ $specialty->name }}</span>
                                    @empty
                                        <span class="text-sm text-gray-500">Sin especialidades asignadas en esta sede.</span>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <span class="text-sm text-gray-500">No tienes sedes asignadas.</span>
                        @endforelse
                    </div>
                    @else
                    <div class="p-6">
                        <label class="mb-2 block text-sm font-medium text-gray-700">Especialidades en {{ $activeBranch?->name ?? 'la sede actual' }}</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            @forelse($specialties as $specialty)
                                <span class="mr-2 mb-2 inline-flex items-center rounded-full px-3 py-1 text-sm"
                                      style="background-color: {{ themeColor(100) }}; color: {{ themeColor(700) }}">
                                    {{ $specialty->name }}
                                </span>
                            @empty
                                <p class="text-sm text-gray-500">No tienes especialidades asignadas.</p>
                            @endforelse
                            @unless($isClinicAdministrator)
                            <p class="mt-2 text-xs text-gray-500">
                                <i class="fa-solid fa-lock mr-1"></i>
                                Para agregar o modificar especialidades, contacta al administrador.
                            </p>
                            @endunless
                        </div>
                    </div>
                    @endif
                </section>
                @endif

                <section class="mb-6 overflow-hidden rounded-xl bg-white shadow-sm">
                    <div class="border-b px-6 py-4" style="background-color: {{ themeColor(100) }}; border-color: {{ themeColor(200) }}">
                        <h3 class="font-semibold text-gray-800">
                            <i class="fa-solid fa-lock mr-2"></i>Contraseña
                        </h3>
                        <p class="mt-1 text-sm text-gray-600">Cambiarla es opcional. Activa la opción para ingresar una contraseña nueva.</p>
                    </div>

                    <div class="p-6" x-data="{ changePassword: {{ $errors->has('password') || $errors->has('current_password') ? 'true' : 'false' }} }">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" x-model="changePassword" class="rounded border-gray-300">
                            Quiero cambiar mi contraseña
                        </label>

                        <div x-cloak x-show="changePassword" class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                            <div class="md:col-span-2">
                                <label for="current_password" class="mb-1 block text-sm font-medium text-gray-700">Contraseña actual</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="current_password" name="current_password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar contraseña actual' : 'Mostrar contraseña actual'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password" class="mb-1 block text-sm font-medium text-gray-700">Nueva contraseña</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="password" name="password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar nueva contraseña' : 'Mostrar nueva contraseña'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-gray-700">Confirmar nueva contraseña</label>
                                <div x-data="{ showPassword: false }" class="relative">
                                    <input id="password_confirmation" name="password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"
                                           :disabled="!changePassword" :required="changePassword"
                                           class="w-full rounded-lg border-gray-300 pr-11 focus:border-transparent focus:ring-2">
                                    <button type="button" x-on:click="showPassword = !showPassword" :disabled="!changePassword"
                                            :aria-label="showPassword ? 'Ocultar confirmación de contraseña' : 'Mostrar confirmación de contraseña'"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-500 hover:text-gray-700 disabled:cursor-not-allowed">
                                        <i class="fa-solid" :class="showPassword ? 'fa-eye-slash' : 'fa-eye'" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="mb-6 flex items-start gap-3 rounded-lg border border-gray-200 bg-gray-50 p-4">
                    <i class="fa-solid fa-circle-info mt-1 text-gray-500"></i>
                    <p class="text-sm text-gray-600">Las especialidades y los permisos de la cuenta siguen siendo administrados por la clínica.</p>
                </div>

                <div class="flex justify-end">
                    <button type="submit" style="background-color: {{ themeColor(500) }}"
                            class="rounded-lg px-6 py-3 font-semibold text-white shadow-sm transition hover:brightness-90">
                        <i class="fa-solid fa-floppy-disk mr-2"></i>Guardar cambios
                    </button>
                </div>
            </form>
            @if($canManageOwnAssignments)
                @include('customer.user.partials.profile-assignments')
            @endif
        </div>
    </div>
@endsection
