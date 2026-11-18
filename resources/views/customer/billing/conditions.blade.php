@extends('layouts.menu-customer')
@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">Condiciones y contrato</h2>
@endsection
@section('content')
<div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-2 text-lg font-semibold text-gray-800">Condiciones de contratación de VITA</h3>
        <p class="text-sm text-gray-600">Aquí puedes consultar las condiciones comerciales registradas para {{ app('customer')->name }}. Consultar esta página no constituye aceptación del contrato ni autorización de cargos.</p>
    </section>
    @if($generalPolicy)
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">{{ $generalPolicy->title }}</h3>
        <div class="whitespace-pre-wrap break-words text-sm leading-relaxed text-gray-700">{{ $generalPolicy->body }}</div>
        <p class="mt-4 text-xs text-gray-500">Condiciones generales publicadas · Versión #{{ $generalPolicy->id }}. No constituye constancia de aceptación ni modifica las condiciones históricas del período contratado.</p>
    </section>
    @endif
    @if($subscription && $terms)
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-5 text-lg font-semibold text-gray-800">Resumen del plan asignado</h3>
        <dl class="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
            @foreach(['Plan'=>$quote['plan_name'], 'Periodicidad'=>$quote['interval']==='month'?'Mensual':'Anual', 'Importe por período'=>'$'.number_format($quote['amount'],0,',','.').' CLP', 'Sedes contratadas'=>$subscription->branch_quantity, 'Modalidad'=>$classification['payment_mode_label'], 'Situación contractual'=>$classification['contract_label']] as $label=>$value)
            <div><dt class="text-gray-500">{{ $label }}</dt><dd class="mt-1 font-medium text-gray-800">{{ $value }}</dd></div>
            @endforeach
        </dl>
        <p class="mt-5 text-xs text-gray-500">Versión comercial #{{ $version->id }}. Se muestra la versión asignada a la suscripción, no un precio público posterior ni un cambio pendiente.</p>
    </section>
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">Contrato y condiciones del plan</h3>
        <div class="whitespace-pre-wrap break-words text-sm leading-relaxed text-gray-700">{{ $terms['terms'] ?? 'No hay texto contractual registrado para esta versión.' }}</div>
    </section>
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">Límites y funcionalidades registrados</h3>
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            <div><dt class="text-gray-500">Sedes incluidas en el precio base</dt><dd>{{ $terms['included_branches'] }}</dd></div>
            <div><dt class="text-gray-500">Prueba del plan</dt><dd>{{ $terms['trial_days'] }} días (su aplicación depende de la contratación)</dd></div>
            <div><dt class="text-gray-500">Período de gracia del plan</dt><dd>{{ $terms['grace_days'] }} días</dd></div>
            @foreach($terms['limits'] ?? [] as $limit)<div><dt class="text-gray-500">{{ \App\Services\Billing\BillingCapabilities::LIMITS[$limit['code']] ?? 'Límite contratado' }}</dt><dd>{{ $limit['value'] === null ? 'Sin límite' : $limit['value'] }} · {{ ($limit['scope'] ?? '') === 'branch' ? 'Por sede' : 'Por clínica' }}</dd></div>@endforeach
        </dl>
        <ul class="mt-4 list-inside list-disc space-y-1 text-sm text-gray-700">@forelse($terms['features'] ?? [] as $feature)<li>{{ \App\Services\Billing\BillingCapabilities::FEATURES[$feature] ?? 'Funcionalidad contratada' }}</li>@empty<li>No hay funcionalidades detalladas en esta versión.</li>@endforelse</ul>
    </section>
    @else
    <section class="rounded-lg bg-white p-6 shadow-sm"><p class="text-sm text-gray-600">Todavía no hay una suscripción asignada a esta clínica. Contacta al administrador de VITA para registrar el plan y sus condiciones.</p></section>
    @endif
    <section class="rounded-lg bg-white p-6 shadow-sm">
        <h3 class="mb-4 text-lg font-semibold text-gray-800">Pagos y conservación de información</h3>
        <ul class="list-inside list-disc space-y-3 text-sm leading-relaxed text-gray-600">
            <li>Los cargos recurrentes requieren una autorización expresa independiente. Registrar o consultar un plan no autoriza cobros.</li>
            <li>Los períodos pagados se actualizan después de verificar el pago. Un intento pendiente no equivale a un pago confirmado.</li>
            <li>El historial de pagos conserva las condiciones comerciales del período correspondiente.</li>
            <li>La baja conserva el período ya pagado y no elimina fichas clínicas, atenciones, firmas ni documentos.</li>
        </ul>
        <p class="mt-5 rounded-lg bg-gray-50 p-3 text-sm text-gray-600">El vencimiento fijo mensual y los recargos por atraso todavía no están implementados. Esta página no los incorpora como condiciones aceptadas.</p>
        <a href="{{ route('customers.subscription.index',['customer'=>request()->route('customer')]) }}" class="mt-5 inline-block rounded-md px-5 py-2 text-sm font-semibold text-white hover:brightness-90" style="background-color:{{ themeColor(600) }}">Ver mi suscripción</a>
    </section>
</div>
@endsection
