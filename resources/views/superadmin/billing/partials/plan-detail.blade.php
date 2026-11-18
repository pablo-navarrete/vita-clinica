<section class="rounded-lg border border-gray-200 p-4">
    <div class="flex flex-wrap justify-between gap-3"><div><p class="font-semibold">{{ $plan->code }} · {{ $plan->active ? 'Activo' : 'Inactivo' }}</p><p class="mt-2 text-sm text-gray-600">{{ $plan->description }}</p></div>
        <a href="{{ route('superadmin.billing.plans.edit',$plan) }}" class="font-semibold text-blue-600">Editar plan</a>
    </div>
</section>
<h3 class="font-semibold">Condiciones del plan</h3>
@if($terms)
    @include('superadmin.billing.partials.commercial-summary')
@else
    <p class="rounded-lg bg-gray-50 p-4 text-sm text-gray-600">Completa los precios y condiciones desde Editar plan.</p>
@endif
<details class="rounded-lg border border-gray-200 p-4">
    <summary class="cursor-pointer font-semibold">Historial de cambios</summary>
    <p class="my-3 text-sm text-gray-500">Las condiciones anteriores se conservan automáticamente.</p>
    @foreach($versions->where('status','published') as $previous)
        <p class="border-t py-3 text-sm"><a class="font-semibold text-blue-600" href="{{ route('superadmin.billing.versions.show',$previous) }}">Condiciones del {{ $previous->published_at->timezone('America/Santiago')->format('d/m/Y H:i:s') }}</a></p>
    @endforeach
    @foreach($events as $event)
        <p class="border-t py-2 text-xs text-gray-500">{{ $event->created_at->timezone('America/Santiago')->format('d/m/Y H:i:s') }} · Usuario #{{ $event->actor_id }} · {{ ['plan_created'=>'Plan creado','plan_updated'=>'Plan editado','plan_deleted'=>'Plan retirado','draft_created'=>'Preparación de cambio','draft_updated'=>'Condiciones guardadas','version_published'=>'Condiciones confirmadas'][$event->action] ?? 'Cambio registrado' }}</p>
    @endforeach
    {{ $events->links() }}
</details>
