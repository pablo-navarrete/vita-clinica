<div id="attention-document-list" class="border-t border-gray-100 px-5 py-4">
    <h4 class="text-sm font-semibold text-gray-800">Documentos de esta atención</h4>
    <div class="mt-3 space-y-3">
        @forelse($documents->where('encounter_id', $currentEncounter->id) as $document)
            <div class="rounded-lg border border-gray-200 p-3">
                <p class="break-words text-sm font-medium text-gray-800">{{ $document->original_name }}</p>
                @if($document->description)<p class="mt-1 text-xs text-gray-500">{{ $document->description }}</p>@endif
                <div class="mt-2 flex flex-wrap gap-3 text-xs font-semibold">
                    <a href="{{ route('patients.documents.preview', ['customer' => request()->route('customer'), 'id' => $patient->id, 'document' => $document->id]) }}" target="_blank" rel="noopener noreferrer" style="color: {{ themeColor(700) }}">Ver documento</a>
                    <a href="{{ route('patients.documents.download', ['customer' => request()->route('customer'), 'id' => $patient->id, 'document' => $document->id]) }}" class="text-gray-600">Descargar</a>
                </div>
            </div>
        @empty
            <p class="text-xs text-gray-500">Sin documentos adjuntos en esta atención.</p>
        @endforelse
    </div>
</div>
