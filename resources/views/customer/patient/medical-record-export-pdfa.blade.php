@php
    $allergies = $record?->allergies ?? collect();
    $chronicDiseases = $record?->chronicDiseases ?? collect();
    $regularMedications = $record?->regularMedications ?? collect();
    $previousSurgeries = $record?->previousSurgeries ?? collect();
@endphp
<style>
    body { font-family: dejavusans, sans-serif; font-size: 10pt; color: #1f2937; }
    h1 { color: #123b63; font-size: 19pt; margin: 0 0 4pt; }
    h2 { color: #123b63; font-size: 12pt; border-bottom: 1px solid #cbd5e1; padding-bottom: 4pt; margin: 16pt 0 7pt; }
    p { margin: 3pt 0; }
    .clinic { color: #475569; font-size: 9pt; }
    .subtitle { color: #475569; margin-bottom: 14pt; }
    .demographics { width: 100%; border-collapse: collapse; }
    .demographics td { width: 50%; border: 1px solid #dbe2ea; padding: 6pt; vertical-align: top; }
    .label { color: #64748b; font-size: 8pt; }
    .value { font-weight: bold; }
    .item { border-left: 2px solid #94a3b8; padding: 2pt 0 5pt 8pt; margin: 4pt 0; }
    .muted { color: #64748b; }
</style>

<div class="clinic">{{ $clinic->legal_name ?: $clinic->name }}</div>
<h1>Ficha clínica</h1>
<p class="subtitle">Documento PDF/A-1b · Generado el {{ $generatedAt->format('d/m/Y H:i') }}</p>

<h2>Identificación del paciente</h2>
<table class="demographics">
    <tr>
        <td><div class="label">Nombre completo</div><div class="value">{{ $patient->name }}</div></td>
        <td><div class="label">RUT</div><div class="value">{{ $patient->rut }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Fecha de nacimiento</div><div>{{ $patient->birth_date?->format('d/m/Y') ?? 'No registrada' }}</div></td>
        <td><div class="label">Sexo</div><div>{{ $patient->gender ?: 'No informado' }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Teléfono</div><div>{{ $patient->phone ?: 'No registrado' }}</div></td>
        <td><div class="label">Correo</div><div>{{ $patient->email ?: 'No registrado' }}</div></td>
    </tr>
    <tr>
        <td><div class="label">Dirección</div><div>{{ $patient->address ?: 'No registrada' }}</div></td>
        <td><div class="label">Previsión</div><div>{{ $patient->insurance?->name ?: 'No registrada' }}</div></td>
    </tr>
</table>

<p><strong>Sede de ingreso del paciente:</strong> {{ $patient->clinicBranch?->name ?? 'Sin sede registrada' }}</p>
<h2>Alergias e intolerancias</h2>
@forelse ($allergies as $allergy)
    <div class="item"><strong>{{ $allergy->allergen }}</strong>@if($allergy->reaction) — Reacción: {{ $allergy->reaction }}@endif</div>
@empty
    <p class="muted">Sin registros.</p>
@endforelse

<h2>Enfermedades crónicas</h2>
@forelse ($chronicDiseases as $disease)
    <div class="item">{{ $disease->name }}</div>
@empty
    <p class="muted">Sin registros.</p>
@endforelse

<h2>Medicamentos habituales</h2>
@forelse ($regularMedications as $medication)
    <div class="item">{{ $medication->name }}</div>
@empty
    <p class="muted">Sin registros.</p>
@endforelse

<h2>Cirugías previas registradas</h2>
@forelse ($previousSurgeries as $surgery)
    <div class="item">{{ $surgery->name }}</div>
@empty
    <p class="muted">Sin registros.</p>
@endforelse

<h2>Atenciones clínicas</h2>
@forelse ($patient->encounters as $encounter)
    <div class="item">
        <strong>{{ $encounter->started_at?->format('d/m/Y H:i') ?? 'Fecha no registrada' }}</strong>
        <span class="muted"> · {{ $encounter->professional?->name ?? 'Profesional no registrado' }}</span>
        @if($encounter->specialty)<span class="muted"> · {{ $encounter->specialty->name }}</span>@endif
        <p><strong>Sede de atención:</strong> {{ $encounter->clinicBranch?->name ?? $encounter->appointment?->clinicBranch?->name ?? 'Sin sede registrada' }}</p>
        @if($encounter->chief_complaint)<p><strong>Motivo:</strong> {{ $encounter->chief_complaint }}</p>@endif
        @if($encounter->clinical_assessment)<p><strong>Evaluación clínica:</strong> {{ $encounter->clinical_assessment }}</p>@endif
        @if($encounter->treatment_plan)<p><strong>Plan:</strong> {{ $encounter->treatment_plan }}</p>@endif
        @if($encounter->observations->isNotEmpty())
            <p><strong>Observaciones adicionales:</strong></p>
            @foreach ($encounter->observations as $observation)
                <div class="item">
                    <p>{!! nl2br(e($observation->observation)) !!}</p>
                    <p class="muted">
                        {{ $observation->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Fecha no registrada' }}
                        · {{ $observation->author?->name ?? 'Usuario eliminado' }}
                        @if($observation->clinicBranch) · {{ $observation->clinicBranch->name }}@endif
                    </p>
                </div>
            @endforeach
        @endif
    </div>
@empty
    <p class="muted">Sin atenciones registradas.</p>
@endforelse

<h2>Documentos registrados</h2>
@forelse ($patient->documents as $document)
    <div class="item">
        <strong>{{ $document->original_name }}</strong>
        <p>Sede: {{ $document->clinicBranch?->name ?? 'Sin sede registrada' }}</p>
        <p class="muted">{{ $document->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') }} · {{ $document->uploader?->name ?? 'Usuario eliminado' }}</p>
        @if($document->description)<p>{{ $document->description }}</p>@endif
    </div>
@empty
    <p class="muted">Sin documentos registrados.</p>
@endforelse

<h2>Movimientos de la ficha por sede</h2>
@forelse ($patient->auditLogs as $auditLog)
    <div class="item">
        <strong>{{ $auditLog->section }}</strong> · {{ $auditLog->action }}
        <p>Sede: {{ $auditLog->clinicBranch?->name ?? 'Sin sede registrada' }}</p>
        <p class="muted">{{ $auditLog->created_at?->timezone('America/Santiago')->format('d/m/Y H:i') }} · {{ $auditLog->user?->name ?? 'Usuario eliminado' }}</p>
    </div>
@empty
    <p class="muted">Sin movimientos registrados.</p>
@endforelse

<p class="muted" style="margin-top:18pt">Información clínica confidencial. Emitida por {{ $clinic->legal_name ?: $clinic->name }}.</p>
