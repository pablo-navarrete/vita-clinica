<div class="space-y-4 text-sm text-gray-800">
    <div class="rounded-lg border p-4" style="background-color: {{ themeColor(50) }}; border-color: {{ themeColor(200) }}">
        <p class="font-semibold">{{ \App\Services\Signatures\EncounterAddendumSnapshotBuilder::TYPES[$snapshot['addendum_type']] }} · Secuencia documental {{ $snapshot['sequence'] }}</p>
        <p>Firma Electrónica Simple (VITA) · {{ $snapshot['professional']['name'] }} · {{ app(\App\Services\Signatures\SignerIdentitySnapshot::class)->label($snapshot['professional']) }}</p>
        <p>{{ $snapshot['branch']['name'] }} · {{ $snapshot['specialty']['name'] ?: 'Sin especialidad' }}</p>
    </div>
    <p><strong>Paciente:</strong> {{ $snapshot['patient']['name'] }} · {{ app(\App\Services\Identification\DocumentIdentityPresenter::class)->label($snapshot['patient']) }}</p>
    <p><strong>Atención:</strong> #{{ $snapshot['encounter_id'] }} · <strong>Documento original:</strong> #{{ $snapshot['original_document']['id'] }}</p>
    <p class="break-all text-xs text-gray-500">SHA-256 original: {{ $snapshot['original_document']['sha256'] }}</p>
    @if($snapshot['correction'])
        <div class="space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p><strong>Documento rectificado:</strong> #{{ $snapshot['correction']['document_id'] }} · Secuencia {{ $snapshot['correction']['document_version'] }}</p>
            <p><strong>Apartado:</strong> {{ \App\Services\Signatures\EncounterAddendumSnapshotBuilder::FIELDS[$snapshot['correction']['field']] }}</p>
            <div><strong>Texto anterior conservado:</strong><p class="whitespace-pre-wrap break-words">{{ $snapshot['correction']['previous_text'] ?? 'Sin registro' }}</p></div>
            <div><strong>Motivo:</strong><p class="whitespace-pre-wrap break-words">{{ $snapshot['correction']['reason'] }}</p></div>
        </div>
    @endif
    <div><strong>Contenido de la adenda:</strong><p class="whitespace-pre-wrap break-words">{{ $snapshot['content'] }}</p></div>
    <p class="text-xs text-gray-500">Último documento revisado: #{{ $snapshot['previous_document']['id'] }}. No se modifican ni reemplazan los documentos anteriores. Esta adenda no incorpora archivos nuevos.</p>
</div>
