<x-app-layout>
    <x-slot name="header"><div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-gray-800">Suscripción · {{ $subscription->customer->name }}</h2><a href="{{ route('superadmin.billing.subscriptions.index') }}" class="text-sm text-gray-600">Volver a suscripciones</a></div></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @include('superadmin.billing.partials.errors')
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold">Situación comercial</h3>
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                @foreach(['payment_mode_label'=>'Modalidad de pago','contract_label'=>'Estado contractual','financial_label'=>'Estado financiero','access_label'=>'Acceso comercial','authorization_label'=>'Acuerdo de cobro'] as $key=>$label)
                    <div><dt class="text-gray-500">{{ $label }}</dt><dd class="mt-1 font-medium">{{ $classification[$key] }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-gray-500">Clasificación informativa: no cambia permisos ni acceso clínico. Una modalidad automática prevista no acredita consentimiento. Los clientes anteriores sin modalidad explícita quedan pendientes de clasificación; los acuerdos Flow existentes se muestran sin modificarlos.</p>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><h3 class="text-lg font-semibold">{{ $quote['plan_name'] }}</h3><span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ $state }}</span></div>
            <dl class="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-gray-500">Importe por período</dt><dd class="mt-1 font-semibold">${{ number_format($quote['amount'], 0, ',', '.') }} CLP · {{ $quote['interval'] === 'month' ? 'Mensual' : 'Anual' }}</dd></div>
                <div><dt class="text-gray-500">Sedes contratadas</dt><dd class="mt-1 font-semibold">{{ $subscription->branch_quantity }} ({{ $quote['included_branches'] }} incluidas)</dd></div>
                <div><dt class="text-gray-500">Inicio</dt><dd class="mt-1">{{ $subscription->starts_on->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Próximo período / vencimiento</dt><dd class="mt-1">{{ $subscription->cancel_at_period_end ? 'Sin renovación' : $subscription->next_renewal_on->format('d/m/Y') }}</dd></div>
                <div><dt class="text-gray-500">Período pagado hasta</dt><dd class="mt-1">{{ $subscription->paid_through_on?->format('d/m/Y') ?? 'Sin pagos registrados' }}</dd></div>
                <div><dt class="text-gray-500">Fin de prueba</dt><dd class="mt-1">{{ $subscription->trial_ends_on?->format('d/m/Y') ?? 'No aplica' }}</dd></div>
                <div><dt class="text-gray-500">Día de renovación</dt><dd class="mt-1">{{ $subscription->anchor_day }}{{ $quote['interval'] === 'year' ? ' del mes '.$subscription->anchor_month : ' de cada mes' }}</dd></div>
                <div><dt class="text-gray-500">Gracia contratada</dt><dd class="mt-1">{{ $subscription->price->version->grace_days }} días</dd></div>
            </dl>
            <p class="mt-5 text-xs text-gray-500">Las fechas de término son exclusivas: el nuevo período comienza ese día. En meses cortos se usa el último día disponible, conservando el día original para las renovaciones siguientes.</p>
            <p class="mt-3 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">Administración local. No se ejecutan cargos, no se emiten documentos tributarios ni se restringe el acceso clínico. Los cambios en el catálogo no alteran automáticamente este contrato.</p>
        </div>

        @if($flowOrders->isNotEmpty())
            <div class="rounded-xl bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-lg font-semibold">Intentos de pago Flow</h3>
                <div class="overflow-x-auto rounded-lg"><table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left"><tr><th class="p-3">Fecha</th><th class="p-3">Referencia</th><th class="p-3">Importe</th><th class="p-3">Estado</th></tr></thead>
                    <tbody>@foreach($flowOrders as $order)<tr class="border-t"><td class="p-3">{{ $order->created_at->format('d/m/Y H:i') }}</td><td class="p-3">{{ $order->id }}</td><td class="p-3">${{ number_format($order->quote['amount'] ?? 0,0,',','.') }} CLP</td><td class="p-3">{{ ['prepared'=>'Preparado','sending'=>'En verificación','pending'=>'Pendiente','paid'=>'Aprobado','failed'=>'Fallido: requiere revisar resultado','review'=>'En revisión'][$order->state] ?? 'En revisión' }}</td></tr>@endforeach</tbody>
                </table></div>
                {{ $flowOrders->withQueryString()->links() }}
                <p class="mt-3 text-xs text-gray-500">Los pagos recurrentes confirmados figuran en el historial de pagos. Esta consulta no contacta a Flow ni acredita pagos pendientes.</p>
            </div>
        @endif
        @if($subscription->pendingChange)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-6">
                <h3 class="font-semibold">Cambio programado</h3>
                <p class="mt-2 text-sm">{{ $upcoming['plan_name'] }} · {{ $upcoming['branch_quantity'] }} sedes · ${{ number_format($upcoming['amount'], 0, ',', '.') }} CLP desde {{ \Illuminate\Support\Carbon::parse($upcoming['period_start'])->format('d/m/Y') }}, al registrar el pago del nuevo período.</p>
                <p class="mt-2 text-sm text-gray-600">{{ $subscription->pendingChange->reason }}</p>
                <form action="{{ route('superadmin.billing.subscriptions.changes.cancel', $subscription) }}" method="POST" class="mt-3">@csrf @method('DELETE')<input type="hidden" name="revision" value="{{ $subscription->revision }}"><button class="text-sm font-semibold text-red-700">Cancelar cambio pendiente</button></form>
            </div>
        @endif

        @unless($subscription->cancel_at_period_end || $subscription->cancelled_at)
            <form action="{{ route('superadmin.billing.subscriptions.payments.store', $subscription) }}" method="POST" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                <h3 class="text-lg font-semibold">Registrar pago manual</h3>
                <p class="mb-5 mt-2 text-sm text-gray-500">Registra únicamente un pago recibido y verificado. Período: {{ $upcoming['period_start'] }} a {{ $upcoming['period_end'] }} (término exclusivo). No se admiten anticipos en esta etapa.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    <div><label for="payment-date" class="mb-1 block text-sm font-medium">Fecha real de pago</label><input id="payment-date" type="date" name="paid_on" value="{{ old('paid_on', now('America/Santiago')->toDateString()) }}" max="{{ now('America/Santiago')->toDateString() }}" required class="w-full rounded-lg border-gray-300"></div>
                    <div><label for="payment-amount" class="mb-1 block text-sm font-medium">Importe exacto (CLP)</label><input id="payment-amount" type="number" name="amount" value="{{ $upcoming['amount'] }}" readonly class="w-full rounded-lg border-gray-300 bg-gray-100 text-gray-500"></div>
                    <div><label for="payment-reference" class="mb-1 block text-sm font-medium">Referencia única del pago</label><input id="payment-reference" name="reference" value="{{ old('reference') }}" maxlength="120" required class="w-full rounded-lg border-gray-300"></div>
                    <div><label for="payment-note" class="mb-1 block text-sm font-medium">Detalle de verificación</label><textarea id="payment-note" name="note" rows="2" maxlength="2000" required class="w-full rounded-lg border-gray-300">{{ old('note') }}</textarea></div>
                </div>
                <div class="mt-5 flex justify-end"><button class="rounded px-4 py-2 font-semibold text-white" style="background-color: {{ themeColor(600) }}">Registrar pago recibido</button></div>
            </form>
            @unless($subscription->pendingChange)
            <form action="{{ route('superadmin.billing.subscriptions.changes.store', $subscription) }}" method="POST" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                <h3 class="text-lg font-semibold">Programar cambio de plan o sedes</h3>
                <p class="mb-5 mt-2 text-sm text-gray-500">Se conservará el período actual. El cambio se aplicará al registrar el próximo pago. Por ahora debes mantener la periodicidad {{ $quote['interval'] === 'month' ? 'mensual' : 'anual' }}.</p>
                <div class="grid gap-5 md:grid-cols-2">
                    @include('superadmin.billing.subscriptions.price-fields', ['options' => array_values(array_filter($options, fn($option) => $option['interval'] === $quote['interval']))])
                    <div class="md:col-span-2"><label for="change-reason" class="mb-1 block text-sm font-medium">Motivo del cambio</label><textarea id="change-reason" name="reason" rows="2" maxlength="2000" required class="w-full rounded-lg border-gray-300">{{ old('reason') }}</textarea></div>
                </div>
                <div class="mt-5 flex justify-end"><button class="rounded px-4 py-2 font-semibold text-white" style="background-color: {{ themeColor(600) }}">Programar para el próximo período</button></div>
            </form>
            @endunless
            <details class="rounded-xl bg-white p-6 shadow-sm"><summary class="cursor-pointer font-semibold text-red-700">Cancelar renovación</summary>
                <form action="{{ route('superadmin.billing.subscriptions.cancel', $subscription) }}" method="POST" class="mt-4 space-y-4">
                    @csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                    <p class="text-sm text-gray-600">Se conserva el período pagado y todo su historial. Esta acción no elimina información ni cambia el acceso clínico.</p>
                    <label class="block text-sm font-medium">Motivo<textarea name="reason" required maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border-gray-300"></textarea></label>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="confirmation" value="1" required class="rounded border-gray-300"> Confirmo la cancelación de futuras renovaciones.</label>
                    <button class="rounded bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700">Confirmar cancelación</button>
                </form>
            </details>
        @else
            <p class="rounded-xl bg-gray-100 p-5 text-sm text-gray-700">Cancelación registrada. Fin del período conservado: {{ $subscription->access_ends_on?->format('d/m/Y') }}. El acceso clínico no se modifica en esta etapa.</p>
        @endunless

        <div class="overflow-x-auto rounded-xl bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-lg font-semibold">Historial de pagos</h3>
            <table id="subscription-payments-table" class="display nowrap min-w-full overflow-hidden rounded-xl"><thead style="background-color: {{ themeColor(100) }}"><tr><th class="p-3">Fecha</th><th class="p-3">Referencia</th><th class="p-3">Período</th><th class="p-3">Importe</th><th class="p-3">Condiciones históricas</th></tr></thead><tbody>
                @foreach($payments as $payment)<tr class="border-b"><td class="p-3" data-order="{{ $payment->paid_on->format('Y-m-d') }}">{{ $payment->paid_on->format('d/m/Y') }}</td><td class="p-3">{{ $payment->reference }}</td><td class="p-3">{{ $payment->period_start->format('d/m/Y') }} → {{ $payment->period_end->format('d/m/Y') }}</td><td class="p-3" data-order="{{ $payment->amount }}">${{ number_format($payment->amount, 0, ',', '.') }} CLP</td><td class="p-3">{{ $payment->breakdown['plan_name'] }} · {{ $payment->breakdown['branch_quantity'] }} sedes</td></tr>@endforeach
            </tbody></table>
        </div>
        <div class="rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Trazabilidad</h3>
            @php($labels = ['created'=>'Suscripción creada','manual_payment_recorded'=>'Pago manual registrado','change_scheduled'=>'Cambio programado','change_cancelled'=>'Cambio cancelado','cancellation_scheduled'=>'Cancelación programada'])
            <p class="mb-3 text-sm text-gray-500">Registro de quién creó o modificó la suscripción y cuándo. No representa pagos realizados.</p>
            @foreach($events as $event)<div class="border-b py-3 text-sm"><p class="font-medium">{{ $labels[$event->action] ?? 'Gestión de suscripción' }}</p><p class="mt-1 text-xs text-gray-500">{{ $event->created_at->timezone('America/Santiago')->format('d/m/Y H:i') }} · {{ $event->actor?->name ?? 'Usuario #'.$event->actor_id }}</p>
                @if(!empty($event->details['note']))<p class="mt-2">Observaciones: {{ $event->details['note'] }}</p>@endif
                @if(!empty($event->details['reason']))<p class="mt-2">Motivo: {{ $event->details['reason'] }}</p>@endif
            </div>@endforeach
            <div class="mt-4">{{ $events->links() }}</div>
        </div>
    </div></div>
    @push('scripts')<script src="{{ asset('js/billing-payment-history.js') }}?v={{ filemtime(public_path('js/billing-payment-history.js')) }}"></script>@endpush
    @push('styles')<style>
        #subscription-payments-table_wrapper .dataTables_paginate .paginate_button { min-width:2.25rem; margin:0 .15rem; padding:.4rem .65rem; border:1px solid transparent !important; border-radius:.5rem; color:#374151 !important; }
        #subscription-payments-table_wrapper .dataTables_paginate .paginate_button:hover { border-color:#d1d5db !important; background:#f3f4f6 !important; color:#111827 !important; }
        #subscription-payments-table_wrapper .dataTables_paginate .paginate_button.current { border-color:{{ themeColor(600) }} !important; background:{{ themeColor(600) }} !important; color:#fff !important; }
        #subscription-payments-table_wrapper .dataTables_paginate .paginate_button.disabled { color:#9ca3af !important; }
    </style>@endpush
</x-app-layout>
