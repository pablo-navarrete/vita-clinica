        <form action="{{ route('superadmin.billing.subscriptions.store') }}" method="POST" class="{{ ($inModal ?? false) ? 'flex min-h-0 flex-1 flex-col bg-white' : 'rounded-xl bg-white shadow-sm' }}">
            @csrf
            <div class="min-h-0 overflow-y-auto p-6">
            @if($inModal ?? false)<div data-subscription-errors role="alert" hidden class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"></div>@endif
            @include('superadmin.billing.partials.errors')
            <p class="mb-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-800">Registra las condiciones y fechas del cliente. No se cobrará a ninguna tarjeta ni se alterará el acceso a VITA. Los pagos existentes no se importan automáticamente.</p>
            <div class="grid gap-5 md:grid-cols-2">
                <div class="subscription-customer-picker subscription-search-picker min-w-0">
                    <label for="subscription-customer" class="mb-1 block text-sm font-medium">Cliente</label>
                    <select id="subscription-customer" name="customer_id" required class="w-full rounded-lg border-gray-300"><option value="">Selecciona un cliente sin suscripción</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id',request('customer_id')) == $customer->id)>{{ $customer->name }}</option>@endforeach</select>
                </div>
                @include('superadmin.billing.subscriptions.price-fields', ['deferEstimate'=>true])
                <div><label class="mb-1 block text-sm font-medium" for="subscription-payment-mode">Modalidad de pago prevista</label><select id="subscription-payment-mode" name="payment_mode" class="w-full rounded-lg border-gray-300"><option value="automatic" @selected(old('payment_mode','automatic') === 'automatic')>Automática con Flow</option><option value="manual" @selected(old('payment_mode') === 'manual')>Manual</option></select><p class="mt-1 text-xs text-gray-500">Registra una preferencia. No autoriza cargos ni crea un acuerdo en Flow. La autorización del cliente se registra por separado.</p></div>
                <div><label for="subscription-start" class="mb-1 block text-sm font-medium">Inicio del primer período</label><input id="subscription-start" type="date" name="starts_on" value="{{ old('starts_on') }}" required class="w-full rounded-lg border-gray-300"><p class="mt-1 text-xs text-gray-500">Establece el día de renovación. Si habilitas la prueba, el primer pago vence al terminarla.</p></div>
                <label class="flex items-center gap-2 md:col-span-2"><input type="hidden" name="use_trial" value="0"><input type="checkbox" name="use_trial" value="1" @checked(old('use_trial')) class="rounded border-gray-300"> Aplicar los días de prueba del plan</label>
                @include('superadmin.billing.subscriptions.estimate')
                <div class="md:col-span-2"><label for="subscription-note" class="mb-1 block text-sm font-medium">Observaciones de la suscripción <span class="font-normal text-gray-500">(opcional)</span></label><textarea id="subscription-note" name="note" maxlength="2000" rows="3" class="w-full rounded-lg border-gray-300">{{ old('note') }}</textarea></div>
            </div>
            </div>
            <div class="flex shrink-0 justify-end gap-3 border-t bg-white px-6 py-4">@if($inModal ?? false)<button type="button" data-close-subscription class="rounded bg-gray-100 px-4 py-2 text-gray-700">Cancelar</button>@else<a href="{{ route('superadmin.billing.subscriptions.index') }}" class="rounded bg-gray-100 px-4 py-2 text-gray-700">Cancelar</a>@endif<button class="rounded px-4 py-2 font-semibold text-white" style="background-color: {{ themeColor(600) }}">Crear suscripción</button></div>
        </form>
