@if($snapshot['correction'])
    <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
        <p><strong>Rectifica documento #{{ $snapshot['correction']['document_id'] }}</strong> · {{ \App\Services\Signatures\EncounterAddendumSnapshotBuilder::FIELDS[$snapshot['correction']['field']] }}</p>
        <p><strong>Texto anterior conservado:</strong></p><p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $snapshot['correction']['previous_text'] ?? 'Sin registro' }}</p>
        <p><strong>Motivo:</strong></p><p style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $snapshot['correction']['reason'] }}</p>
    </div>
@endif
<p class="mt-3 text-sm" style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $snapshot['content'] }}</p>
