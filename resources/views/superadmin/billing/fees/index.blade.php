<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Comisiones de pago</h2></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @include('superadmin.billing.partials.errors')
        @foreach($alerts as $alert)
            <div role="alert" class="rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                <p class="font-semibold">Revisar tarifa y precios</p><p class="mt-2">{{ $alert->message }}</p>
                @if($alert->kind==='flow_difference')<p class="mt-2 text-xs">Ambiente: {{ $alert->environment }} · Orden Flow: {{ $alert->details['flow_order'] ?? '—' }} · Descuento estimado: ${{ number_format($alert->details['expected_deduction'] ?? 0,0,',','.') }} · Observado: ${{ number_format($alert->details['observed_deduction'] ?? 0,0,',','.') }}</p>@endif
                @if($alert->kind==='subscription_review')<a href="{{ route('superadmin.billing.subscriptions.show',$alert->details['customer_subscription_id']) }}" class="mt-2 inline-block text-xs underline">Ver suscripción #{{ $alert->details['customer_subscription_id'] }}</a>@endif
                <form method="POST" action="{{ route('superadmin.billing.fees.acknowledge',$alert) }}" class="mt-3">@csrf @method('PATCH')<button class="font-semibold underline">Marcar como revisado</button></form>
            </div>
        @endforeach
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Datos consultados directamente a Flow</h3>
            <p class="mt-2 text-sm text-gray-500">Ambiente: <strong>{{ $flowEnvironment }}</strong>. {{ $flowConfigured ? 'Conexión configurada.' : 'Pendiente configurar FLOW_ENABLED, FLOW_ENVIRONMENT, FLOW_API_KEY y FLOW_SECRET_KEY en el servidor.' }} Las credenciales no se introducen en este formulario.</p>
            <p class="mt-2 text-xs text-gray-500">La consulta es de solo lectura: no genera cobros ni activa suscripciones. Sandbox contiene pruebas; los datos reales requieren credenciales de producción. Los pagos se muestran a nivel de la cuenta Flow, sin asignarlos automáticamente a clínicas.</p>
            <form method="POST" action="{{ route('superadmin.billing.fees.flow-sync') }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
                <label class="text-sm">Fecha de pagos<input name="date" type="date" required value="{{ now('America/Santiago')->toDateString() }}" max="{{ now('America/Santiago')->toDateString() }}" class="mt-1 block rounded-lg border-gray-300"></label>
                <button @disabled(!$flowConfigured) class="rounded bg-blue-500 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-300">Consultar Flow</button>
            </form>
            <div class="mt-5 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead class="border-b"><tr><th class="p-3">Pago / orden</th><th class="p-3">Medio</th><th class="p-3">Pagado CLP</th><th class="p-3">Comisión informada Flow</th><th class="p-3">Descuento según saldo</th><th class="p-3">Saldo informado</th><th class="p-3">Abono previsto</th></tr></thead><tbody>
                @forelse($observations as $observation)<tr class="border-b"><td class="p-3">{{ $observation->paid_on->format('d/m/Y') }} · #{{ $observation->flow_order }}</td><td class="p-3">{{ $observation->media }}</td><td class="p-3">${{ number_format($observation->gross,0,',','.') }}</td><td class="p-3">${{ number_format($observation->reported_fee,0,',','.') }}</td><td class="p-3">${{ number_format($observation->gross-$observation->net,0,',','.') }}</td><td class="p-3">${{ number_format($observation->net,0,',','.') }}</td><td class="p-3">{{ $observation->transfer_on?->format('d/m/Y') ?? 'Pendiente' }}</td></tr>@empty<tr><td colspan="7" class="p-4 text-gray-500">No hay pagos consultados para esta cuenta y ambiente.</td></tr>@endforelse
            </tbody></table></div><div class="mt-4">{{ $observations->links() }}</div>
            <p class="mt-3 text-xs text-gray-500">Se conserva cada observación sin sobrescribir las anteriores. El saldo no confirma un depósito bancario. No se deduce una tasa de IVA a partir del campo fee ni se suma IVA nuevamente. Una diferencia genera un aviso de revisión; los cambios futuros de tarifa deben confirmarse con Flow. Configura FLOW_ALERT_EMAIL y el correo de Laravel para recibir estos avisos por email.</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold">Tarifa Flow para estimaciones</h3>
            <p class="mt-2 text-sm text-gray-500">Registra la tarifa acordada en tu cuenta Flow, incluido el medio de pago y plazo de abono. VITA no la obtiene automáticamente de Flow. Se aplica como un escenario de referencia para los planes.</p>
            <p class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-800">@if($current)Vigente: {{ $current->scenario }} · {{ number_format($current->rate_units / 10000,4,',','.') }} % + ${{ number_format($current->fixed_amount,0,',','.') }} CLP por pago, más {{ number_format($current->vat_units / 10000,4,',','.') }} % de IVA sobre la comisión.@elseNo hay una tarifa vigente. Los planes no mostrarán un neto estimado hasta configurarla.@endif</p>
        </div>
        <form method="POST" action="{{ route('superadmin.billing.fees.store') }}" class="rounded-xl bg-white p-6 shadow-sm">
            @csrf
            <h3 class="mb-5 text-lg font-semibold">Registrar nueva vigencia</h3>
            <div class="grid gap-5 md:grid-cols-2">
                <label class="text-sm font-medium">Escenario / tarifa contratada<input name="scenario" required maxlength="160" value="{{ old('scenario') }}" placeholder="Medio de pago y plazo de abono" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium">Vigente desde<input name="effective_from" type="date" required min="{{ now('America/Santiago')->toDateString() }}" value="{{ old('effective_from') }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium">Comisión porcentual (%)<input name="rate_percent" type="number" required min="0" max="100" step="0.0001" value="{{ old('rate_percent') }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium">Costo fijo por pago (CLP)<input name="fixed_amount" type="number" required min="0" max="1000000000000" step="1" value="{{ old('fixed_amount') }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium">IVA de la comisión (%)<input name="vat_percent" type="number" required min="0" max="100" step="0.0001" value="{{ old('vat_percent') }}" class="mt-1 w-full rounded-lg border-gray-300"></label>
                <label class="text-sm font-medium">Referencia de las condiciones<textarea name="note" required maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border-gray-300">{{ old('note') }}</textarea></label>
            </div>
            <p class="mt-5 text-xs text-gray-500">Cada tarifa se conserva sin sobrescribir. Su vigencia termina cuando comienza la siguiente. La nueva fecha debe ser posterior a la última registrada. No se cargan tarifas de ejemplo ni se modifica el precio de los planes.</p>
            <div class="mt-5 flex justify-end"><button class="rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700">Guardar tarifa</button></div>
        </form>
        <div class="overflow-x-auto rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold">Historial y próximas vigencias</h3>
            <table class="min-w-full text-left text-sm"><thead class="border-b"><tr><th class="p-3">Desde</th><th class="p-3">Escenario</th><th class="p-3">Comisión</th><th class="p-3">Fijo CLP</th><th class="p-3">IVA comisión</th><th class="p-3">Referencia</th></tr></thead><tbody>
            @forelse($rates as $rate)<tr class="border-b"><td class="p-3">{{ $rate->effective_from->format('d/m/Y') }}</td><td class="p-3">{{ $rate->scenario }}</td><td class="p-3">{{ $rate->rate_units / 10000 }} %</td><td class="p-3">${{ number_format($rate->fixed_amount,0,',','.') }}</td><td class="p-3">{{ $rate->vat_units / 10000 }} %</td><td class="p-3">{{ $rate->note }} · Usuario #{{ $rate->created_by }}</td></tr>@empty<tr><td colspan="6" class="p-4 text-gray-500">Sin tarifas registradas.</td></tr>@endforelse
            </tbody></table><div class="mt-4">{{ $rates->links() }}</div>
        </div>
    </div></div>
</x-app-layout>
