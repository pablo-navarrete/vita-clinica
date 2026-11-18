@extends('mi-salud.layout')
@section('content')
<a class="history-back" href="{{ route('mi-salud.attentions.record',$center->id) }}#historial">← Ficha de {{ $center->customer->name }}</a>
<section class="page-heading"><span class="eyebrow">ATENCIÓN FIRMADA</span><h2>{{ $snapshot['professional']['name'] ?? 'Mi atención' }}</h2><p>{{ \Carbon\Carbon::parse($snapshot['started_at'])->timezone(config('app.timezone'))->format('d/m/Y · H:i') }} · {{ $snapshot['specialty']['name'] ?? 'Atención clínica' }} · {{ ($snapshot['modality']??'')==='telemedicine' ? 'Online' : 'Presencial' }}</p></section>
<article class="card history-document">
    <p class="help">{{ $center->customer->name }}@if($snapshot['branch']['name']??null) · {{ $snapshot['branch']['name'] }}@endif</p>
    @foreach(['chief_complaint'=>'Motivo de consulta','clinical_assessment'=>'Evaluación clínica / diagnóstico','treatment_plan'=>'Plan e indicaciones'] as $field=>$label)<section><h2>{{ $label }}</h2><p class="history-text">{{ $snapshot[$field] ?? 'Sin información registrada.' }}</p></section>@endforeach
    <p class="history-signature">Finalizada con firma electrónica simple el {{ $attention->signature->signed_at->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}.</p>
</article>
<section class="card history-document"><h2>Documentos de esta atención</h2><ul class="history-documents">@forelse($snapshot['attachments']??[] as $file)<li><div><strong>{{ $file['name'] }}</strong>@if($file['description']??null)<p class="help">{{ $file['description'] }}</p>@endif</div><a class="button small" href="{{ route('mi-salud.attentions.document',['grant'=>$center->id,'encounter'=>$attention->id,'document'=>$file['id']]) }}">Descargar</a></li>@empty<li>No hay documentos adjuntos en esta atención.</li>@endforelse</ul></section>
@foreach($addenda as $addendum)
    <article class="card history-document"><span class="status-pill">{{ ($addendum['snapshot']['addendum_type']??'')==='rectification' ? 'Rectificación firmada' : 'Adenda firmada' }}</span><h2>Información posterior a la atención</h2><p class="help">{{ $addendum['snapshot']['professional']['name'] ?? 'Profesional' }} · {{ $addendum['signature']->signed_at->timezone(config('app.timezone'))->format('d/m/Y · H:i') }}</p><p class="history-text">{{ $addendum['snapshot']['content'] }}</p></article>
@endforeach
@endsection
