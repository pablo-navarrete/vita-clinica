<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Condiciones y contrato</h2></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('superadmin.billing.conditions.update') }}" class="rounded-lg bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <input type="hidden" name="revision" value="{{ old('revision',$policy->id ?? 0) }}">
            <h3 class="mb-2 text-lg font-semibold">Condiciones generales de VITA</h3>
            <p class="mb-5 text-sm text-gray-500">Este texto se muestra a los administradores de las clínicas. Cada publicación conserva una versión histórica. No reemplaza las condiciones del plan contratado ni registra aceptación o autoriza cargos. Modificar el texto no implementa vencimientos, recargos o restricciones en el sistema.</p>
            <label for="policy-title" class="mb-1 block text-sm font-medium text-gray-700">Título</label>
            <input id="policy-title" name="title" required maxlength="160" value="{{ old('title',$policy->title ?? 'Condiciones generales de contratación') }}" class="mb-5 w-full rounded-md border-gray-300 shadow-sm">
            @error('title')<p class="mb-3 text-sm text-red-600">{{ $message }}</p>@enderror
            <label for="policy-body" class="mb-2 block text-sm font-medium text-gray-700">Reglas y contrato general</label>
            <textarea id="policy-body" name="body" required maxlength="50000" rows="18" class="w-full rounded-md border-gray-300 shadow-sm">{{ old('body',$policy->body ?? '') }}</textarea>
            @error('body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
            @if($policy)<p class="mt-3 text-xs text-gray-500">Versión publicada #{{ $policy->id }}.</p>@endif
            <div class="mt-5 flex justify-end"><button class="rounded-md px-5 py-2 font-semibold text-white hover:brightness-90" style="background-color:{{ themeColor(600) }}">Publicar condiciones</button></div>
        </form>
    </div></div>
</x-app-layout>
