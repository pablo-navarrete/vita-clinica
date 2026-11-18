<article class="directory-card">
@php($detailUrl = route('vita.directory.show', ['slug'=>$card['slug']] + (!empty($selectedSpecialty) ? ['especialidad'=>$selectedSpecialty] : [])))
<a class="directory-cover-link" href="{{ $detailUrl }}" aria-label="Ver presentación de {{ $card['name'] }}">
@if($card['cover'])<img class="directory-cover" src="{{ $card['cover'] }}" alt="Presentación de {{ $card['name'] }}" width="600" height="340" loading="lazy" decoding="async">@else<div class="directory-fallback" aria-hidden="true">VITA</div>@endif
</a>
<div class="directory-copy">
<div class="directory-identity">
@if($card['brand_display']!=='name' && $card['logo'])<a class="directory-identity-logo" href="{{ $detailUrl }}" aria-label="{{ $card['name'] }}"><img class="directory-brand-image" src="{{ $card['logo'] }}" alt="Logo de {{ $card['name'] }}" width="60" height="60" loading="lazy"></a>@endif
<h2 @if($card['brand_display']==='logo' && $card['logo']) class="directory-visually-hidden" @endif><a href="{{ $detailUrl }}">{{ $card['name'] }}</a></h2>
</div><p>{{ \Illuminate\Support\Str::limit($card['description'],170) }}</p>
@include('vita.directory.rating')
@if(!empty($specialtyName))<p class="directory-specialty-match">Ofrece {{ $specialtyName }}</p>@endif
@if($card['city'] || $card['region'])<p class="directory-card-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>{{ implode(' · ',array_filter([$card['city'],$card['region']])) }}</span></p>@endif
@if($card['distance_km'] !== null)<p class="directory-distance"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> A {{ number_format((float)$card['distance_km'],1,',','.') }} km aproximadamente</p>@endif
<div class="directory-actions"><a class="directory-button" href="{{ $card['website'] }}">Visitar sitio web</a><a class="directory-button directory-button-gray" href="{{ $detailUrl }}">Ver presentación</a></div></div></article>
