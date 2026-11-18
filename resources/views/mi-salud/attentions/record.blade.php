@extends('mi-salud.layout')
@section('content')
<a class="history-back" href="{{ route('mi-salud.attentions.index') }}">← Mis atenciones</a>
<section class="page-heading"><span class="eyebrow">MI FICHA EN ESTE CENTRO</span><h2>{{ $center->customer->name }}</h2><p>Paciente: {{ $center->patient->name }} · Consulta en modo lectura</p></section>
<nav class="history-tabs" aria-label="Secciones de mi ficha"><a href="#antecedentes">Ficha y antecedentes</a><a href="#historial">Atenciones firmadas</a></nav>
<section class="card history-background" id="antecedentes"><h2>Ficha y antecedentes</h2><p class="help">Información registrada por este centro. Si necesitas corregir algún dato, comunícate con él.</p>
    <div class="history-background-grid">
        <div><h3>Alergias</h3><ul>@forelse($record?->allergies ?? [] as $item)<li>{{ $item->allergen }}@if($item->reaction) · {{ $item->reaction }}@endif</li>@empty<li>Sin información registrada.</li>@endforelse</ul></div>
        <div><h3>Enfermedades crónicas</h3><ul>@forelse($record?->chronicDiseases ?? [] as $item)<li>{{ $item->name }}</li>@empty<li>Sin información registrada.</li>@endforelse</ul></div>
        <div><h3>Medicamentos habituales</h3><ul>@forelse($record?->regularMedications ?? [] as $item)<li>{{ $item->name }}</li>@empty<li>Sin información registrada.</li>@endforelse</ul></div>
        <div><h3>Cirugías previas</h3><ul>@forelse($record?->previousSurgeries ?? [] as $item)<li>{{ $item->name }}</li>@empty<li>Sin información registrada.</li>@endforelse</ul></div>
    </div>
</section>
<section id="historial" class="history-timeline"><h2>Atenciones firmadas</h2><p class="help">El contenido finalizado por el profesional. Las atenciones en curso y canceladas no aparecen aquí.</p>
@forelse($encounters as $encounter)
    @php($snapshot=$snapshots[$encounter->id])
    <article class="card history-attention"><div class="history-attention-heading"><div><time datetime="{{ $snapshot['started_at'] }}">{{ \Carbon\Carbon::parse($snapshot['started_at'])->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</time><h3>{{ $snapshot['professional']['name'] ?? 'Profesional' }}</h3><p>{{ $snapshot['specialty']['name'] ?? 'Atención clínica' }} · {{ ($snapshot['modality']??'')==='telemedicine' ? 'Online' : 'Presencial' }}@if($snapshot['branch']['name']??null) · {{ $snapshot['branch']['name'] }}@endif</p></div><span class="status-pill">Firmada</span></div><p class="history-text">{{ $snapshot['chief_complaint'] }}</p><a class="button small" href="{{ route('mi-salud.attentions.encounter',['grant'=>$center->id,'encounter'=>$encounter->id]) }}">Ver atención y documentos</a></article>
@empty<section class="card"><p>Aún no hay atenciones firmadas disponibles en este centro.</p></section>@endforelse
@if($encounters->hasPages())<nav class="history-pagination" aria-label="Páginas de atenciones">@if($encounters->previousPageUrl())<a href="{{ $encounters->previousPageUrl() }}#historial">← Anteriores</a>@endif<span>{{ $encounters->currentPage() }} / {{ $encounters->lastPage() }}</span>@if($encounters->nextPageUrl())<a href="{{ $encounters->nextPageUrl() }}#historial">Siguientes →</a>@endif</nav>@endif
</section>
@endsection
