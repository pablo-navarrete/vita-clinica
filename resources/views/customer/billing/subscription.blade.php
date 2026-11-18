@extends('layouts.menu-customer')
@section('header')
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-xl font-semibold text-gray-800">Mi suscripción</h2><span class="rounded-full bg-gray-100 px-3 py-1 text-sm">{{ config('flow.environment') === 'sandbox' ? 'Pruebas · Sandbox' : 'Producción' }}</span></div>
@endsection
@section('content')
    <div class="subscription-forms mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        @if($errors->any())<div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @unless($subscription)<div class="rounded-xl bg-white p-6 shadow-sm">Todavía no tienes una suscripción asignada. Contacta al administrador de VITA para contratar tu plan.</div>@else
        <div class="rounded-xl bg-white p-6 shadow-sm">
            <div class="flex flex-wrap justify-between gap-3"><h3 class="text-lg font-semibold">{{ $quote['plan_name'] }}</h3><span class="rounded-full px-3 py-1 text-sm font-medium {{ $isPaidUp ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $isPaidUp ? 'Al día' : $state }}</span></div>
            @if($isPaidUp)
                <p class="mt-3 text-sm text-green-700">Pago confirmado. Tu suscripción está al día hasta el {{ $subscription->paid_through_on->copy()->subDay()->format('d/m/Y') }}.</p>
                @if($subscription->cancel_at_period_end || $subscription->cancelled_at)<p class="mt-2 text-sm text-gray-600">La baja está registrada; no se renovará el servicio.</p>@endif
            @endif
            <dl class="mt-5 grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-gray-500">Próximo período</dt><dd class="mt-1 font-semibold">${{ number_format($quote['amount'],0,',','.') }} CLP / {{ $quote['interval']==='month'?'mes':'año' }}</dd></div>
                <div><dt class="text-gray-500">Sedes contratadas</dt><dd class="mt-1">{{ $quote['branch_quantity'] }}</dd></div>
                <div><dt class="text-gray-500">Pagado hasta (fecha exclusiva)</dt><dd class="mt-1">{{ $subscription->paid_through_on?->format('d/m/Y') ?? 'Sin pagos confirmados' }}</dd></div>
                <div><dt class="text-gray-500">Vencimiento próximo período</dt><dd class="mt-1">{{ $subscription->next_renewal_on->format('d/m/Y') }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-gray-500">{{ $agreement && $agreement->collection_mode==='automatic' ? 'Renovación automática autorizada en Flow.' : 'Modalidad de pago manual: no se ordenan cargos automáticos desde VITA.' }} {{ $agreement && $agreement->state!=='active' ? 'La operación con Flow está pendiente de conciliación.' : '' }}</p>
            <p class="mt-2 text-xs text-gray-500">Una tarjeta registrada no acredita un pago. El período se amplía únicamente al confirmar la transacción mediante la API. Los datos clínicos se conservan.</p>
            @unless($ready)<p class="mt-4 rounded-lg bg-amber-50 p-3 text-sm text-amber-800">La contratación aún no está habilitada. Verifica la configuración de Flow y FLOW_PUBLIC_URL (URL HTTPS pública para recibir confirmaciones). En producción también se requiere la habilitación expresa de pagos.</p>@endunless
            <form method="POST" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'refresh']) }}" class="mt-4">@csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}"><button class="rounded border border-gray-300 px-4 py-2 text-sm hover:bg-gray-50">Actualizar estado y pagos</button></form>
        </div>
        @unless($subscription->cancel_at_period_end || $subscription->cancelled_at)
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <form method="POST" target="_blank" rel="noopener" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'pay']) }}" class="rounded-xl bg-white p-6 shadow-sm">
                @csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}"><h3 class="text-lg font-semibold">Pago manual</h3><p class="mt-2 mb-5 border-b border-gray-200 pb-5 text-sm text-gray-500">Paga un período cuando llegue su vencimiento. Flow se abrirá en otra pestaña. Si ya existe un importe en Flow, se utilizará ese mismo cobro.</p>
                <label class="my-4 flex items-start gap-2 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>Confirmo el pago de ${{ number_format($quote['amount'],0,',','.') }} CLP correspondiente al período desde {{ $quote['period_start'] }} hasta {{ $quote['period_end'] }} (término exclusivo).</span></label>
                <button @disabled(!$ready || ($agreement && ($agreement->state!=='active' || $agreement->collection_mode!=='manual'))) class="rounded px-4 py-2 font-semibold text-white disabled:opacity-50" style="background-color:{{ themeColor(600) }}">Pagar período con Flow</button>
            </form>
            <div class="rounded-xl bg-white p-6 shadow-sm"><h3 class="text-lg font-semibold">Tarjeta y renovación automática</h3>
                <p class="mt-2 text-sm text-gray-500">{{ $billingCustomer?->card_last_four ? $billingCustomer->card_brand.' · termina en '.$billingCustomer->card_last_four : 'No hay una tarjeta confirmada.' }}</p>
                <form method="POST" target="_blank" rel="noopener" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'card']) }}" class="mt-4 space-y-3">@csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                    <p class="text-xs text-gray-500">La tarjeta se introduce en Flow; VITA conserva únicamente su marca y últimos cuatro dígitos.</p>
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>Autorizo el registro o reemplazo de tarjeta para pagos recurrentes. Si ya tengo una suscripción Flow en pago manual, registrar la tarjeta reactivará sus cargos automáticos.</span></label>
                    <label class="block text-sm">Confirma tu contraseña<input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border-gray-300"></label>
                    <button @disabled(!$ready) class="rounded border border-gray-300 px-4 py-2 text-sm disabled:opacity-50">{{ $billingCustomer?->card_last_four ? 'Reemplazar tarjeta en Flow' : 'Registrar tarjeta en Flow' }}</button>
                </form>
                @unless($agreement)
                <form method="POST" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'automatic']) }}" class="mt-5 space-y-3 border-t pt-5">@csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                    <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>Autorizo a Flow a cobrar ${{ number_format($quote['amount'],0,',','.') }} CLP cada {{ $quote['interval']==='month'?'mes':'año' }}, desde {{ $quote['period_start'] }}, hasta que desactive los cargos o solicite la baja. Incluye {{ $quote['branch_quantity'] }} sedes.</span></label>
                    <label class="block text-sm">Confirma tu contraseña<input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-lg border-gray-300"></label>
                    <button @disabled(!$ready || !$billingCustomer?->card_last_four) class="rounded px-4 py-2 font-semibold text-white disabled:opacity-50" style="background-color:{{ themeColor(600) }}">Activar renovación automática</button>
                </form>@endunless
            </div>
        </div>
        @if($agreement && $agreement->collection_mode==='automatic')
        <form method="POST" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'manual']) }}" class="rounded-xl bg-white p-6 shadow-sm">@csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
            <h3 class="font-semibold">Seguir con pago manual</h3><p class="mt-2 text-sm text-gray-500">Se retirará la tarjeta registrada en Flow. Flow continuará generando los importes de tu plan, que podrás pagar manualmente. Esta acción no da de baja el servicio.</p>
            <label class="my-4 flex items-start gap-2 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>Quiero desactivar los cargos automáticos y continuar pagando manualmente.</span></label>
            <button class="rounded border border-gray-300 px-4 py-2 text-sm">Desactivar cargos automáticos</button>
        </form>@endif
        <details class="rounded-xl bg-white p-6 shadow-sm"><summary class="cursor-pointer font-semibold text-red-700">Dar de baja el servicio</summary>
            <form method="POST" action="{{ route('customers.subscription.action',['customer'=>request()->route('customer'),'action'=>'cancel']) }}" class="mt-4 space-y-4">@csrf <input type="hidden" name="revision" value="{{ $subscription->revision }}">
                <p class="text-sm text-gray-600">No continuaré contratando períodos nuevos. Se conserva el período ya pagado y toda la información clínica. No equivale a cambiar a pago manual.</p>
                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="acceptance" value="1" required class="mt-1 rounded border-gray-300"><span>Confirmo la baja del servicio al finalizar lo pagado.</span></label>
                <div class="sm:max-w-sm">
                    <label for="subscription-cancel-password" class="mb-2 block text-sm font-medium text-gray-700">Confirma tu contraseña</label>
                    <input id="subscription-cancel-password" type="password" name="password" required autocomplete="current-password" class="block w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <button class="rounded bg-red-600 px-4 py-2 font-semibold text-white hover:bg-red-700">Confirmar baja</button>
            </form>
        </details>
        @else<p class="rounded-xl bg-gray-100 p-5 text-sm">Baja registrada. Se conserva lo pagado hasta {{ $subscription->access_ends_on?->format('d/m/Y') }}. Para volver a contratar, contacta al administrador de VITA.</p>@endunless
        <div class="overflow-x-auto rounded-xl bg-white p-6 shadow-sm"><h3 class="mb-4 text-lg font-semibold">Pagos confirmados</h3>
            <table id="subscription-payments-table" class="display nowrap min-w-full overflow-hidden rounded-xl"><thead style="background-color: {{ themeColor(100) }}"><tr><th class="p-3">Fecha</th><th class="p-3">Período</th><th class="p-3">Importe CLP</th><th class="p-3">Medio</th></tr></thead><tbody>@foreach($payments as $payment)<tr class="border-b"><td class="p-3" data-order="{{ $payment->paid_on->format('Y-m-d') }}">{{ $payment->paid_on->format('d/m/Y') }}</td><td class="p-3">{{ $payment->period_start->format('d/m/Y') }} → {{ $payment->period_end->format('d/m/Y') }}</td><td class="p-3" data-order="{{ $payment->amount }}">${{ number_format($payment->amount,0,',','.') }}</td><td class="p-3">{{ $payment->method==='flow'?'Flow':'Registro manual verificado' }}</td></tr>@endforeach</tbody></table>
        </div>
        @endunless
    </div>
@endsection
@push('scripts')<script src="{{ asset('js/billing-payment-history.js') }}?v={{ filemtime(public_path('js/billing-payment-history.js')) }}"></script>@endpush
@push('styles')
<style>
    .subscription-forms > .bg-white,
    .subscription-forms > .grid > .bg-white { border-radius:.5rem; }
    .subscription-forms h3 { color:#1f2937; }
    .subscription-forms label.block { font-weight:500; color:#374151; }
    .subscription-forms input[type="password"] { margin-top:.375rem; border-color:#d1d5db; border-radius:.375rem; box-shadow:0 1px 2px rgb(0 0 0 / .05); }
    .subscription-forms input[type="password"]:focus { border-color:{{ themeColor(600) }}; --tw-ring-color:{{ themeColor(600) }}; }
    .subscription-forms input[type="checkbox"] { color:{{ themeColor(600) }}; flex-shrink:0; }
    .subscription-forms input[type="checkbox"]:focus { --tw-ring-color:{{ themeColor(600) }}; }
    .subscription-forms form > button { display:block; margin-left:auto; margin-top:1.25rem; padding:.5rem 1.25rem; border-radius:.375rem; font-weight:600; transition:background-color .15s,filter .15s; }
    .subscription-forms form > button:hover:not(:disabled) { filter:brightness(.95); }
    .subscription-forms form > button:focus-visible { outline:2px solid {{ themeColor(600) }}; outline-offset:3px; }
    .subscription-forms form > button:disabled { cursor:not-allowed; opacity:.5; }
    .subscription-forms form > label:has(input[type="checkbox"]) { padding:.75rem; border:1px solid #e5e7eb; border-radius:.5rem; background:#f9fafb; line-height:1.5; }
    .subscription-forms form.space-y-3,
    .subscription-forms form.space-y-4 { padding-top:1.25rem; }
    #subscription-payments-table_wrapper .dataTables_paginate .paginate_button { min-width:2.25rem; margin:0 .15rem; padding:.4rem .65rem; border:1px solid transparent !important; border-radius:.5rem; color:#374151 !important; }
    #subscription-payments-table_wrapper .dataTables_paginate .paginate_button:hover { border-color:#d1d5db !important; background:#f3f4f6 !important; color:#111827 !important; }
    #subscription-payments-table_wrapper .dataTables_paginate .paginate_button.current { border-color:{{ themeColor(600) }} !important; background:{{ themeColor(600) }} !important; color:#fff !important; }
    #subscription-payments-table_wrapper .dataTables_paginate .paginate_button.disabled { color:#9ca3af !important; }
</style>
@endpush
