@extends('mi-salud.layout')
@section('content')
<section class="page-heading"><span class="eyebrow">MI HISTORIA DE ATENCIÓN</span><h2>Mis atenciones</h2><p>Tus centros, fichas y atenciones firmadas, organizados en un solo lugar.</p></section>
@if($centers->isEmpty())
    @if(!$account->email_verified_at)<div class="notice" role="status">Vista de prueba local. Puedes explorar esta sección; las fichas reales requieren verificar tu correo y que la clínica habilite el acceso.</div>@endif
    <section class="empty-card"><div class="empty-symbol" aria-hidden="true">♡</div><h2>Aún no tienes fichas habilitadas</h2><p>Solicita al centro donde te atendiste que habilite el acceso a tu cuenta de Mi Salud, después de comprobar tu identidad. Necesitará el correo verificado de tu cuenta.</p><a class="button" href="{{ route('mi-salud.profile') }}">Ver mi perfil</a></section>
@else
    <div class="history-summary"><span><strong>{{ $centers->count() }}</strong> {{ $centers->count()===1 ? 'centro vinculado' : 'centros vinculados' }}</span><span><strong>{{ $centers->sum('attention_count') }}</strong> atenciones firmadas</span></div>
    <section class="history-centers" aria-label="Centros con acceso habilitado">
        @foreach($centers as $center)
        <article class="card history-center"><span class="status-pill">Acceso habilitado</span><h2>{{ $center->customer->name }}</h2><p>Ficha de {{ $center->patient->name }}</p><p><strong>{{ $center->attention_count }}</strong> {{ $center->attention_count===1 ? 'atención firmada' : 'atenciones firmadas' }}</p>
            @if($center->last_attention_at)<p class="help">Última atención: {{ \Carbon\Carbon::parse($center->last_attention_at)->timezone(config('app.timezone'))->format('d/m/Y') }}</p>@else<p class="help">Todavía no hay atenciones firmadas para mostrar.</p>@endif
            <a class="button small" href="{{ route('mi-salud.attentions.record',$center->id) }}">Ver ficha y atenciones</a>
        </article>
        @endforeach
    </section>
@endif
<p class="history-help">Aquí aparecen los registros de centros que utilizan VITA y habilitaron tu acceso. Cada centro conserva su propia ficha. Si falta un centro o una atención, consulta directamente con él.</p>
@endsection
