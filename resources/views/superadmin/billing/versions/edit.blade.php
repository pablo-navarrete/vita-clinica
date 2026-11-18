<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">{{ $version->plan->name }} · Borrador {{ $version->number }}</h2></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">@include('superadmin.billing.partials.errors')
        <form method="POST" action="{{ route('superadmin.billing.versions.update', $version) }}" class="space-y-6">
            @csrf @method('PUT')
            <input type="hidden" name="_definition_form" value="1">
            <p class="rounded-lg border border-blue-100 bg-blue-50 p-4 text-sm text-blue-800">Define todos los valores comerciales. Nada se aplica a clínicas ni genera cobros en esta etapa.</p>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Precios finales en CLP</h3><p class="mb-4 text-sm text-gray-500">El precio anual es independiente. Indica el total final a cobrar y explica su tratamiento tributario en las condiciones.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach(['month' => 'Mensual', 'year' => 'Anual'] as $interval => $label)
                        <div class="space-y-4 rounded-lg border p-4" x-data="{ enabled: {{ old("prices.$interval.enabled", data_get($definition,"prices.$interval.enabled",0)) ? 'true' : 'false' }} }">
                            <input type="hidden" name="prices[{{ $interval }}][enabled]" value="0">
                            <label class="inline-flex items-center gap-2 font-semibold"><input type="checkbox" name="prices[{{ $interval }}][enabled]" value="1" x-model="enabled" class="rounded border-gray-300">{{ $label }}</label>
                            <label class="block text-sm">Precio base (CLP)<input type="number" min="0" step="1" name="prices[{{ $interval }}][amount]" :disabled="!enabled" :required="enabled" value="{{ old("prices.$interval.amount", data_get($definition,"prices.$interval.amount")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                            <label class="block text-sm">Precio por sede adicional / {{ $label }} (CLP)<input type="number" min="0" step="1" name="prices[{{ $interval }}][additional_branch_amount]" :disabled="!enabled" :required="enabled" value="{{ old("prices.$interval.additional_branch_amount", data_get($definition,"prices.$interval.additional_branch_amount")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                        </div>
                    @endforeach
                </div>
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Sedes, prueba y gracia</h3><div class="grid gap-5 md:grid-cols-3">
                @foreach(['included_branches' => 'Sedes incluidas', 'trial_days' => 'Días de prueba', 'grace_days' => 'Días de gracia'] as $key => $label)
                    <label class="block text-sm">{{ $label }}<input type="number" min="0" step="1" name="{{ $key }}" required value="{{ old($key,data_get($definition,$key)) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                @endforeach
            </div></section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Límites</h3><p class="mb-4 text-sm text-gray-500">Cero significa ninguna unidad. Marca «Sin límite» explícitamente. Usuarios y profesionales se cuentan una vez por clínica o una vez por sede, según el alcance elegido.</p>
                <div class="space-y-4">@foreach($limits as $code => $label)
                    <div class="grid items-end gap-4 rounded-lg border p-4 md:grid-cols-3" x-data="{ unlimited: {{ old("limits.$code.unlimited",data_get($definition,"limits.$code.unlimited",0)) ? 'true' : 'false' }} }">
                        <div><p class="mb-2 font-medium">{{ $label }}</p><input type="hidden" name="limits[{{ $code }}][unlimited]" value="0"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="limits[{{ $code }}][unlimited]" value="1" x-model="unlimited" class="rounded border-gray-300">Sin límite</label></div>
                        <label class="text-sm">Cantidad<input type="number" min="0" step="1" name="limits[{{ $code }}][value]" :disabled="unlimited" :required="!unlimited" value="{{ old("limits.$code.value",data_get($definition,"limits.$code.value")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                        <label class="text-sm">Alcance<select name="limits[{{ $code }}][scope]" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach($code === 'branches' ? ['customer' => 'Toda la clínica'] : ['customer' => 'Toda la clínica','branch' => 'Cada sede'] as $value => $text)<option value="{{ $value }}" @selected(old("limits.$code.scope",data_get($definition,"limits.$code.scope")) === $value)>{{ $text }}</option>@endforeach</select></label>
                    </div>
                @endforeach</div>
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Funcionalidades incluidas</h3><p class="mb-4 text-sm text-gray-500">Selecciona funciones reales de VITA. Esta configuración no concede permisos a usuarios ni activa restricciones.</p><div class="grid gap-3 sm:grid-cols-2">@foreach($features as $code => $label)<label class="inline-flex items-center gap-2 rounded-lg border p-3 text-sm"><input type="checkbox" name="features[]" value="{{ $code }}" @checked(in_array($code,old('_definition_form') ? old('features',[]) : data_get($definition,'features',[]))) class="rounded border-gray-300">{{ $label }}</label>@endforeach</div></section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Cuotas de consumo</h3><p class="mb-4 text-sm text-gray-500">Configuración contractual preparada para medición futura. No registra consumo ni genera cobros por excedentes. Una cuota no configurada no se interpreta automáticamente como ilimitada.</p>
                <div class="space-y-4">@foreach($quotas as $metric => $quota)
                    <div class="space-y-4 rounded-lg border p-4" x-data="{ enabled: {{ old("quotas.$metric.enabled",data_get($definition,"quotas.$metric.enabled",0)) ? 'true' : 'false' }}, unlimited: {{ old("quotas.$metric.unlimited",data_get($definition,"quotas.$metric.unlimited",0)) ? 'true' : 'false' }} }">
                        <input type="hidden" name="quotas[{{ $metric }}][enabled]" value="0"><label class="inline-flex items-center gap-2 font-medium"><input type="checkbox" name="quotas[{{ $metric }}][enabled]" value="1" x-model="enabled" class="rounded border-gray-300">{{ $quota['label'] }} ({{ $quota['unit'] }})</label>
                        <fieldset :disabled="!enabled" class="grid gap-4 sm:grid-cols-2 disabled:opacity-50">
                            <div><input type="hidden" name="quotas[{{ $metric }}][unlimited]" value="0"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="quotas[{{ $metric }}][unlimited]" value="1" x-model="unlimited" class="rounded border-gray-300">Sin límite</label></div>
                            <label class="text-sm">Cantidad<input type="number" min="0" step="1" name="quotas[{{ $metric }}][quantity]" :disabled="!enabled || unlimited" :required="enabled && !unlimited" value="{{ old("quotas.$metric.quantity",data_get($definition,"quotas.$metric.quantity")) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Renovación<select name="quotas[{{ $metric }}][reset_period]" :required="enabled" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach($quota['periods'] as $period)<option value="{{ $period }}" @selected(old("quotas.$metric.reset_period",data_get($definition,"quotas.$metric.reset_period")) === $period)>{{ ['month'=>'Mensual','billing_cycle'=>'Cada período contratado','none'=>'No se reinicia: ocupación actual'][$period] }}</option>@endforeach</select></label>
                            <label class="text-sm">Política futura de excedentes<select name="quotas[{{ $metric }}][overage_policy]" :required="enabled" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach(['notify'=>'Avisar sin bloquear','block'=>'Bloquear nuevo consumo al agotar'] as $value => $text)<option value="{{ $value }}" @selected(old("quotas.$metric.overage_policy",data_get($definition,"quotas.$metric.overage_policy")) === $value)>{{ $text }}</option>@endforeach</select></label>
                        </fieldset>
                    </div>
                @endforeach</div>
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><label class="block font-semibold">Condiciones comerciales<textarea name="terms" rows="6" required maxlength="20000" class="mt-3 w-full rounded-lg border-gray-300">{{ old('terms',data_get($definition,'terms')) }}</textarea></label><p class="mt-2 text-sm text-gray-500">Explica impuestos, alcance, cobro por sedes y condiciones de prueba/gracia. Promociones y excepciones por clínica se incorporarán en una etapa posterior.</p></section>
            <div class="flex justify-end gap-3"><a href="{{ route('superadmin.billing.plans.show',$version->plan) }}" class="rounded-lg bg-gray-100 px-4 py-2">Cancelar</a><button class="rounded-lg px-4 py-2 text-white" style="background-color: {{ themeColor(600) }}">Guardar y revisar</button></div>
        </form>
    </div>
    </div>
</x-app-layout>
