@extends('vita.directory.layout')
@section('title','Clínicas y centros médicos')
@section('description','Conoce clínicas y centros que utilizan VITA y visita sus páginas web.')
@section('canonical',route('vita.directory.index',request()->integer('page',1)>1?['page'=>request()->integer('page')]:[]))
@section('content')<section class="directory-hero"><div class="wrap"><h1>Encuentra tu próximo centro de atención</h1><p>Conoce a los clientes que forman parte de VITA. Visita su página para conocer sus servicios y opciones de atención.</p>
<form class="directory-search" action="{{ route('vita.directory.index') }}" method="GET" role="search">
<label for="directory-search" class="directory-visually-hidden">Nombre, ciudad, región o dirección</label><input id="directory-search" name="buscar" value="{{ $search }}" maxlength="100" placeholder="Nombre, ciudad, región o dirección">
<div class="directory-specialty-picker" data-specialty-picker>
    <label for="directory-specialty" class="directory-visually-hidden" data-specialty-label>Especialidad</label>
    <input id="directory-specialty-search" type="search" maxlength="100" placeholder="Selecciona o busca una especialidad" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="directory-specialty-options" hidden>
    <select id="directory-specialty" name="especialidad" aria-label="Especialidad">
        <option value="">Todas las especialidades</option>
        @if($selectedSpecialty && !$specialtyOptions->contains('id',$selectedSpecialty))<option value="{{ $selectedSpecialty }}" selected hidden>{{ $specialtyName }} (sin clínicas disponibles)</option>@endif
        @foreach($specialtyOptions as $option)<option value="{{ $option['id'] }}" @selected($selectedSpecialty === $option['id'])>{{ $option['name'] }}</option>@endforeach
    </select>
    <ul id="directory-specialty-options" role="listbox" aria-label="Especialidades disponibles" hidden></ul>
    <span class="directory-specialty-status directory-visually-hidden" role="status" aria-live="polite"></span>
</div>
@if($nearby)<input type="hidden" name="cercanas" value="1">@endif
<button class="directory-button"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i> Buscar</button>
<button type="button" class="directory-button directory-button-secondary" id="directory-nearby" data-url="{{ route('vita.directory.location') }}"><i class="fa-solid fa-location-crosshairs" aria-hidden="true"></i> Cerca de mí</button>
@if($search || request()->boolean('cercanas') || $selectedSpecialty)<a class="directory-button directory-button-clear" href="{{ route('vita.directory.index') }}"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Limpiar</a>@endif
</form>
@if($selectedSpecialty)<p class="directory-specialty-applied">Especialidad: <strong>{{ $specialtyName }}</strong> <a href="{{ route('vita.directory.index',request()->only(['buscar','cercanas'])) }}" aria-label="Quitar filtro de especialidad">Quitar especialidad <span aria-hidden="true">×</span></a></p>@endif
<p id="directory-location-status" role="status" aria-live="polite">@if($nearby)Ordenadas por distancia aproximada. Solo aparecen centros con coordenadas registradas; no representa distancia por carretera.@elseif($locationExpired)Para ordenar por cercanía, pulsa «Cerca de mí» y permite el acceso a tu ubicación.@endif</p>
<p class="directory-location-note">«Cerca de mí» solicita permiso de ubicación. Usaremos una posición aproximada durante 30 minutos, sin incluirla en la URL.</p>
</div></section>
<div class="wrap"><div class="directory-grid">@foreach($profiles as $profile)@if($card=$service->card($profile))@include('vita.directory.card')@endif @endforeach</div>@if($profiles->isEmpty())<p>No encontramos clínicas publicadas para esta búsqueda.</p>@endif
<nav class="directory-pages" aria-label="Páginas del directorio">@if($profiles->previousPageUrl())<a href="{{ $profiles->previousPageUrl() }}">Anterior</a>@endif<span aria-current="page">Página {{ $profiles->currentPage() }}</span>@if($profiles->nextPageUrl())<a href="{{ $profiles->nextPageUrl() }}">Siguiente</a>@endif</nav></div>@endsection
@section('scripts')<script src="{{ asset('js/customer-directory-location.js') }}"></script><script src="{{ asset('js/customer-directory-specialty.js') }}"></script>@endsection
