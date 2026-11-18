@php
    $definition = old('definition', $definition ?? []);
    $features = \App\Services\Billing\BillingCapabilities::FEATURES;
    $limits = \App\Services\Billing\BillingCapabilities::LIMITS;
    $quotas = \App\Services\Billing\BillingCapabilities::QUOTAS;
    $simulatorDefaults = app(\App\Services\Billing\PaymentFeeService::class)->simulatorDefaults();
@endphp
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Precios finales en CLP</h3><p class="mb-4 text-sm text-gray-500">El precio anual es independiente. Indica el total final a cobrar y explica su tratamiento tributario en las condiciones.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    @foreach(['month' => 'Mensual', 'year' => 'Anual'] as $interval => $label)
                        <div class="space-y-4 rounded-lg border p-4">
                            <input type="hidden" name="definition[prices][{{ $interval }}][enabled]" value="0">
                            <label class="inline-flex items-center gap-2 font-semibold"><input type="checkbox" name="definition[prices][{{ $interval }}][enabled]" value="1" @checked(data_get($definition,"prices.$interval.enabled")) class="rounded border-gray-300">{{ $label }}</label>
                            <div data-net-price data-period="{{ $interval }}" data-save-url="{{ route('superadmin.billing.fees.defaults') }}" class="rounded-lg bg-gray-50 p-3">
                                <div class="mb-3 grid grid-cols-2 gap-3">
                                    <label class="text-sm">Comisión Flow (%)<input data-net-rate type="number" min="0" max="99" step="0.0001" value="{{ $simulatorDefaults->simulator_rate_units === null ? '' : $simulatorDefaults->simulator_rate_units / 10000 }}" placeholder="Ej. 2,89" class="mt-1 w-full rounded-lg border-gray-300"></label>
                                    <label class="text-sm">IVA de la comisión (%)<input data-net-vat type="number" min="0" max="100" step="0.0001" value="{{ $simulatorDefaults->simulator_vat_units === null ? '' : $simulatorDefaults->simulator_vat_units / 10000 }}" placeholder="Ej. 19" class="mt-1 w-full rounded-lg border-gray-300"></label>
                                </div>
                                <button data-net-save type="button" class="mb-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium hover:bg-gray-100 disabled:opacity-50">Guardar porcentajes</button>
                                <p data-net-save-status role="status" class="mb-3 text-xs text-gray-600"></p>
                                <label class="block text-sm font-medium">Quiero recibir después de Flow (CLP)<input data-net-target type="number" min="0" max="1000000000000" step="1" placeholder="Ej. 50000" class="mt-1 w-full rounded-lg border-gray-300"></label>
                                <p data-net-result aria-live="polite" class="mt-2 whitespace-pre-line text-sm text-gray-600">Ingresa el monto, la comisión y su IVA para calcular el precio.</p>
                                <p class="mt-2 text-xs text-gray-500">Guarda los porcentajes como valores predeterminados para futuros planes. No modifica precios existentes ni configura cobros en Flow. Simulación sin cargo fijo, impuestos propios de VITA ni sedes adicionales.</p>
                            </div>
                            <label class="block text-sm">Precio base a cobrar al cliente (CLP)<input type="number" min="0" step="1" name="definition[prices][{{ $interval }}][amount]" value="{{ old("prices.$interval.amount", data_get($definition,"prices.$interval.amount")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                            <label class="block text-sm">Precio por sede adicional / {{ $label }} (CLP)<input type="number" min="0" step="1" name="definition[prices][{{ $interval }}][additional_branch_amount]" value="{{ old("prices.$interval.additional_branch_amount", data_get($definition,"prices.$interval.additional_branch_amount")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                        </div>
                    @endforeach
                </div>
            </section>
            @include('superadmin.billing.partials.fee-estimate')
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Sedes, prueba y gracia</h3><div class="grid gap-5 md:grid-cols-3">
                @foreach(['included_branches' => 'Sedes incluidas', 'trial_days' => 'Días de prueba', 'grace_days' => 'Días de gracia'] as $key => $label)
                    <label class="block text-sm">{{ $label }}<input type="number" min="0" step="1" name="definition[{{ $key }}]" required value="{{ old($key,data_get($definition,$key)) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                @endforeach
            </div></section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Límites</h3><p class="mb-4 text-sm text-gray-500">Cero significa ninguna unidad. Marca «Sin límite» explícitamente. Usuarios y profesionales se cuentan una vez por clínica o una vez por sede, según el alcance elegido.</p>
                <div class="space-y-4">@foreach($limits as $code => $label)
                    <div class="grid items-end gap-4 rounded-lg border p-4 md:grid-cols-3">
                        <div><p class="mb-2 font-medium">{{ $label }}</p><input type="hidden" name="definition[limits][{{ $code }}][unlimited]" value="0"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="definition[limits][{{ $code }}][unlimited]" value="1" @checked(data_get($definition,"limits.$code.unlimited")) class="rounded border-gray-300">Sin límite</label></div>
                        <label class="text-sm">Cantidad<input type="number" min="0" step="1" name="definition[limits][{{ $code }}][value]" value="{{ old("limits.$code.value",data_get($definition,"limits.$code.value")) }}" class="mt-1 w-full rounded-lg border-gray-300 disabled:bg-gray-100"></label>
                        <label class="text-sm">Alcance<select name="definition[limits][{{ $code }}][scope]" required class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach($code === 'branches' ? ['customer' => 'Toda la clínica'] : ['customer' => 'Toda la clínica','branch' => 'Cada sede'] as $value => $text)<option value="{{ $value }}" @selected(old("limits.$code.scope",data_get($definition,"limits.$code.scope")) === $value)>{{ $text }}</option>@endforeach</select></label>
                    </div>
                @endforeach</div>
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Funcionalidades incluidas</h3><p class="mb-4 text-sm text-gray-500">Selecciona funciones reales de VITA. Esta configuración no concede permisos a usuarios ni activa restricciones.</p><div class="grid gap-3 sm:grid-cols-2">@foreach($features as $code => $label)<label class="inline-flex items-center gap-2 rounded-lg border p-3 text-sm"><input type="checkbox" name="definition[features][]" value="{{ $code }}" @checked(in_array($code,old('_definition_form') ? old('features',[]) : data_get($definition,'features',[]))) class="rounded border-gray-300">{{ $label }}</label>@endforeach</div></section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-3 text-lg font-semibold">Cuotas de consumo</h3><p class="mb-4 text-sm text-gray-500">Configuración contractual preparada para medición futura. No registra consumo ni genera cobros por excedentes. Una cuota no configurada no se interpreta automáticamente como ilimitada.</p>
                <div class="space-y-4">@foreach($quotas as $metric => $quota)
                    <div class="space-y-4 rounded-lg border p-4">
                        <input type="hidden" name="definition[quotas][{{ $metric }}][enabled]" value="0"><label class="inline-flex items-center gap-2 font-medium"><input type="checkbox" name="definition[quotas][{{ $metric }}][enabled]" value="1" @checked(data_get($definition,"quotas.$metric.enabled")) class="rounded border-gray-300">{{ $quota['label'] }} ({{ $quota['unit'] }})</label>
                        <fieldset class="grid gap-4 sm:grid-cols-2 disabled:opacity-50">
                            <div><input type="hidden" name="definition[quotas][{{ $metric }}][unlimited]" value="0"><label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="definition[quotas][{{ $metric }}][unlimited]" value="1" @checked(data_get($definition,"quotas.$metric.unlimited")) class="rounded border-gray-300">Sin límite</label></div>
                            <label class="text-sm">Cantidad<input type="number" min="0" step="1" name="definition[quotas][{{ $metric }}][quantity]" value="{{ old("quotas.$metric.quantity",data_get($definition,"quotas.$metric.quantity")) }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                            <label class="text-sm">Renovación<select name="definition[quotas][{{ $metric }}][reset_period]" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach($quota['periods'] as $period)<option value="{{ $period }}" @selected(old("quotas.$metric.reset_period",data_get($definition,"quotas.$metric.reset_period")) === $period)>{{ ['month'=>'Mensual','billing_cycle'=>'Cada período contratado','none'=>'No se reinicia: ocupación actual'][$period] }}</option>@endforeach</select></label>
                            <label class="text-sm">Política futura de excedentes<select name="definition[quotas][{{ $metric }}][overage_policy]" class="mt-1 w-full rounded-lg border-gray-300"><option value="">Selecciona</option>@foreach(['notify'=>'Avisar sin bloquear','block'=>'Bloquear nuevo consumo al agotar'] as $value => $text)<option value="{{ $value }}" @selected(old("quotas.$metric.overage_policy",data_get($definition,"quotas.$metric.overage_policy")) === $value)>{{ $text }}</option>@endforeach</select></label>
                        </fieldset>
                    </div>
                @endforeach</div>
            </section>
            <section class="rounded-xl bg-white p-6 shadow-sm"><label class="block font-semibold">Condiciones comerciales<textarea name="definition[terms]" rows="6" required maxlength="20000" class="mt-3 w-full rounded-lg border-gray-300">{{ old('terms',data_get($definition,'terms')) }}</textarea></label><p class="mt-2 text-sm text-gray-500">Explica impuestos, alcance, cobro por sedes y condiciones de prueba/gracia. Promociones y excepciones por clínica se incorporarán en una etapa posterior.</p></section>
