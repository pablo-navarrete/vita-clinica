        <form data-plan-form method="POST" action="{{ $plan->exists ? route('superadmin.billing.plans.update', $plan) : route('superadmin.billing.plans.store') }}" class="bg-white">
            @csrf @if($plan->exists) @method('PUT') @endif
            <input type="hidden" name="revision" value="{{ $revision ?? 0 }}">
            <div class="space-y-4 px-6 py-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <label class="block text-sm font-medium">Nombre<input name="name" required maxlength="255" value="{{ old('name', $plan->name) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="block text-sm font-medium">Código estable<input name="code" required maxlength="80" value="{{ old('code', $plan->code) }}" @readonly($plan->exists) class="mt-1 w-full rounded-lg border-gray-300"><span class="mt-1 block text-xs text-gray-500">Letras, números, guion o guion bajo. No cambia después de crear el plan.</span></label>
                <label class="block text-sm font-medium">Orden<input name="position" type="number" min="0" required value="{{ old('position', $plan->position) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="block text-sm font-medium">Contratación<select name="active" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option><option value="1" @selected((string)old('active', $plan->exists ? (int)$plan->active : '') === '1')>Disponible en catálogo</option><option value="0" @selected((string)old('active', $plan->exists ? (int)$plan->active : '') === '0')>Inactiva</option></select></label>
            </div>
            <label class="block text-sm font-medium">Descripción<textarea name="description" rows="3" maxlength="5000" class="mt-1 w-full rounded-lg border-gray-300">{{ old('description', $plan->description) }}</textarea></label>
            <div data-plan-conditions class="space-y-5">
                @include('superadmin.billing.partials.plan-conditions')
            </div>
            <p class="text-sm text-gray-500">Los cambios se guardan en el plan. El historial anterior se conserva automáticamente. La programación de renovaciones y avisos por correo se habilitará al incorporar las suscripciones.</p>
            </div><div class="flex justify-end gap-3 border-t px-6 py-4"><a href="{{ route('superadmin.billing.plans.index') }}" data-plan-modal-close class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">Cancelar</a><button class="bg-blue-500 hover:bg-blue-700 text-white px-4 py-2 rounded">Guardar plan</button></div>
        </form>
@once
    @push('styles')
        <style>
            [data-plan-form] input:disabled,
            [data-plan-form] select:disabled,
            [data-plan-form] textarea:disabled,
            [data-plan-form] fieldset:disabled input,
            [data-plan-form] fieldset:disabled select,
            [data-plan-form] fieldset:disabled textarea,
            [data-plan-form] input[readonly] {
                background-color: #f3f4f6;
                border-color: #e5e7eb;
                color: #4b5563;
                cursor: not-allowed;
                opacity: 1;
            }
        </style>
    @endpush
@endonce
