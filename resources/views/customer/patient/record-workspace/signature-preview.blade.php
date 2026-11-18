<div class="space-y-4 text-sm text-gray-800">
    <div class="rounded-lg border p-4" style="background-color: {{ themeColor(50) }}; border-color: {{ themeColor(200) }}">
        <p class="font-semibold">Firma Electrónica Simple (VITA)</p>
        <p>{{ $snapshot['professional']['name'] }} · {{ app(\App\Services\Signatures\SignerIdentitySnapshot::class)->label($snapshot['professional']) }}</p>
        <p>{{ $snapshot['specialty']['name'] ?: 'Sin especialidad' }} · {{ $snapshot['branch']['name'] }}</p>
    </div>
    <p><strong>Paciente:</strong> {{ $snapshot['patient']['name'] }} · {{ app(\App\Services\Identification\DocumentIdentityPresenter::class)->label($snapshot['patient']) }}</p>
    <p><strong>Atención:</strong> #{{ $snapshot['encounter_id'] }} · {{ $snapshot['modality'] === 'telemedicine' ? 'Telemedicina' : 'Presencial' }}</p>
    <p><strong>Inicio:</strong> {{ \Illuminate\Support\Carbon::parse($snapshot['started_at'])->timezone('America/Santiago')->format('d/m/Y H:i:s') }} · <strong>Cita:</strong> {{ $snapshot['appointment_id'] ?? 'Sin cita' }}</p>
    @foreach (['chief_complaint' => 'Motivo', 'clinical_assessment' => 'Evaluación clínica / diagnóstico', 'treatment_plan' => 'Plan / tratamiento'] as $field => $label)
        <div><h4 class="font-semibold">{{ $label }}</h4><p class="whitespace-pre-wrap break-words">{{ $snapshot[$field] ?: 'Sin registro' }}</p></div>
    @endforeach
    <div><h4 class="font-semibold">Adjuntos incluidos</h4>
        @forelse ($snapshot['attachments'] as $document)
            <div class="mt-2 rounded border p-3"><p>{{ $document['name'] }} · {{ $document['bytes'] }} bytes</p><p>{{ $document['description'] }}</p><p class="break-all text-xs text-gray-500">SHA-256: {{ $document['sha256'] }}</p></div>
        @empty <p>Sin adjuntos.</p> @endforelse
    </div>
    <p class="text-xs text-gray-500">{{ count($snapshot['visual_assets']) ? 'Se conservarán las versiones actuales de tu firma visual y/o timbre como representación opcional.' : 'Firmarás sin imagen visual; la evidencia electrónica no depende de ella.' }}</p>
    <p class="text-xs text-gray-500">Se firma esta atención individual. Los antecedentes generales de la ficha no forman parte de esta versión. No corresponde a una Firma Electrónica Avanzada.</p>
</div>
