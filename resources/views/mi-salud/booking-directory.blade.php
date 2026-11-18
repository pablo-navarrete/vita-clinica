@extends('mi-salud.layout')
@section('content')
<section class="page-heading"><h2>Encuentra dónde atenderte</h2><p>Busca clínicas y profesionales con agenda online. Elige un centro para consultar profesionales, fechas y horas disponibles.</p></section>
<form class="card portal-booking-search" method="GET" action="{{ route('mi-salud.booking.index') }}">
    <div class="field"><label for="booking-search">Centro o ubicación</label><input id="booking-search" name="buscar" type="search" value="{{ $search }}" maxlength="100" placeholder="Nombre, comuna, ciudad o región"></div>
    <div class="field"><label for="booking-specialty">Especialidad</label><select id="booking-specialty" name="especialidad" data-search-select data-search-placeholder="Buscar especialidad" data-options-label="Especialidades" data-empty-message="No encontramos especialidades con ese nombre."><option value="">Todas las especialidades</option>@foreach($specialtyOptions as $option)<option value="{{ $option['id'] }}" @selected($selectedSpecialty===$option['id'])>{{ $option['name'] }}</option>@endforeach</select></div>
    <button type="submit" class="button portal-booking-submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/></svg>Buscar</button>
    <button type="button" class="button portal-booking-nearby" data-portal-location data-url="{{ route('mi-salud.booking.location') }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>Cerca de mí</button>
    @if($nearby)<input type="hidden" name="cercanas" value="1">@endif
    @if($search || $selectedSpecialty || request()->boolean('cercanas'))<a class="button portal-booking-clear" href="{{ route('mi-salud.booking.index') }}"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m4 4 8 8M12 4l-8 8"/></svg>Limpiar</a>@endif
</form>
<p id="portal-booking-location-status" class="help" role="status" aria-live="polite">@if($nearby)Ordenados por distancia aproximada. Solo aparecen centros con ubicación registrada.@elseif($locationExpired)La ubicación expiró. Pulsa “Cerca de mí” para ordenar por cercanía.@endif</p>
<p class="help">Tu navegador solicitará permiso. Usaremos una ubicación aproximada durante 30 minutos, sin incluirla en la URL.</p>
<p class="help">{{ $profiles->total() }} {{ $profiles->total()===1?'centro con agenda online':'centros con agenda online' }}. La agenda del centro se abre en otra pestaña para conservar tu panel.</p>
<section class="portal-booking-grid" aria-label="Centros para agendar">
@foreach($profiles as $profile)
    @if($card=$directory->card($profile))
    <article class="card portal-booking-card">
        @if($card['cover'])<img class="portal-booking-cover" src="{{ $card['cover'] }}" alt="Presentación de {{ $card['name'] }}" loading="lazy" width="600" height="240">@endif
        <div class="portal-booking-identity">@if($card['logo'])<img src="{{ $card['logo'] }}" alt="" width="48" height="48" loading="lazy">@endif<h2>{{ $card['name'] }}</h2></div>
        <p>{{ \Illuminate\Support\Str::limit($card['description'],170) }}</p>
        @if($card['city'] || $card['region'] || $card['commune'])<p class="portal-booking-location"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>{{ implode(' · ',array_unique(array_filter([$card['commune'],$card['city'],$card['region']]))) }}</p>@endif
        @if($card['review_count'])<p class="help">{{ number_format((float)$card['rating'],1,',','.') }} / 5 · {{ $card['review_count'] }} opiniones</p>@endif
        @if($card['distance_km']!==null)<p class="help">A {{ number_format((float)$card['distance_km'],1,',','.') }} km aproximadamente</p>@endif
        <div class="portal-booking-actions"><a class="button small" href="{{ route('customer.public-booking.index',['customer'=>$profile->customer->domain]) }}" target="_blank" rel="noopener noreferrer" aria-label="Agendar en {{ $card['name'] }} (otra pestaña)">Agendar atención</a><a href="{{ route('vita.directory.show',['slug'=>$card['slug']]) }}" target="_blank" rel="noopener noreferrer">Ver presentación</a></div>
    </article>
    @endif
@endforeach
</section>
@if($profiles->isEmpty())<section class="empty-card"><h2>No encontramos centros para esta búsqueda</h2><p>Prueba otra ubicación o especialidad. Aquí aparecen centros publicados con agenda online habilitada.</p></section>@endif
@if($profiles->hasPages())<nav class="history-pagination" aria-label="Páginas de centros">@if($profiles->previousPageUrl())<a href="{{ $profiles->previousPageUrl() }}">← Anterior</a>@endif<span>{{ $profiles->currentPage() }} / {{ $profiles->lastPage() }}</span>@if($profiles->nextPageUrl())<a href="{{ $profiles->nextPageUrl() }}">Siguiente →</a>@endif</nav>@endif
@endsection
