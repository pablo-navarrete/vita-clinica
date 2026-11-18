@php($addenda = app(\App\Services\Signatures\EncounterAddendumReader::class)->read($encounter))
<div class="border-t bg-white px-5 py-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h4 class="font-semibold text-gray-800">Adendas médicas firmadas</h4><p class="mt-1 text-xs text-gray-500">Documentos independientes. La atención original se conserva sin cambios.</p></div>
        @if(app(\App\Services\Signatures\EncounterAddendumPolicy::class)->allows(auth()->user(), $encounter))
            <a data-addendum-modal-url href="{{ route('patients.encounters.addenda.create', ['customer' => request()->route('customer'), 'id' => $patient->id, 'encounter' => $encounter->id]) }}" class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}"><i class="fa-solid fa-file-circle-plus" aria-hidden="true"></i>Agregar adenda</a>
        @endif
    </div>
    <div class="mt-4 space-y-4">
        @forelse($addenda as $entry)
            @php($snapshot = $entry['snapshot'])
            <article id="addendum-document-{{ $entry['signature']->document_version_id }}" class="rounded-lg border border-gray-200 p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <h5 class="font-semibold">{{ \App\Services\Signatures\EncounterAddendumSnapshotBuilder::TYPES[$snapshot['addendum_type']] }} · Secuencia {{ $snapshot['sequence'] }}</h5>
                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Firmada · FES VITA</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">{{ $entry['signature']->identity['name'] }} · {{ $entry['signature']->signed_at->timezone('America/Santiago')->format('d/m/Y H:i:s') }} · Documento #{{ $entry['signature']->document_version_id }}</p>
                @include('customer.patient.record-workspace.addendum-content', ['snapshot' => $snapshot])
                <p class="mt-3 break-all text-xs text-gray-500">SHA-256: {{ $entry['signature']->content_hash }}</p>
            </article>
        @empty <p class="text-sm text-gray-500">Esta atención aún no tiene adendas.</p> @endforelse
    </div>
</div>
