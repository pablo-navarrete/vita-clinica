@extends('vita.directory.layout')
@section('title',$card['name'])
@section('description',\Illuminate\Support\Str::limit($card['description'],155))
@section('canonical',route('vita.directory.show',$card['slug']))
@section('content')
<script type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'Organization','name'=>$card['name'],'description'=>$card['description'],'url'=>$card['website']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) !!}</script>
<div class="wrap clinic-profile">
    <a class="clinic-back" href="{{ route('vita.directory.index', !empty($selectedSpecialty) ? ['especialidad'=>$selectedSpecialty] : []) }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Volver al directorio</a>
    <article class="clinic-presentation">
        @if($card['cover'])
            <img class="clinic-cover" src="{{ $card['cover'] }}" alt="Presentación de {{ $card['name'] }}" width="1160" height="360" fetchpriority="high" decoding="async">
        @else
            <div class="clinic-cover clinic-cover-default" aria-hidden="true"><span>VITA</span></div>
        @endif
        <div class="clinic-introduction">
            <div class="directory-identity">
                @if($card['brand_display'] !== 'name' && $card['logo'])
                    <img class="clinic-logo" src="{{ $card['logo'] }}" alt="Logo de {{ $card['name'] }}" width="88" height="88" decoding="async">
                @endif
                <div>
                    <p class="clinic-eyebrow">Conoce esta clínica</p>
                    <h1 @if($card['brand_display']==='logo' && $card['logo']) class="directory-visually-hidden" @endif>{{ $card['name'] }}</h1>
                    @if($card['commune'] || $card['city'] || $card['region'])
                        <p class="clinic-location"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ implode(' · ',array_unique(array_filter([$card['commune'],$card['city'],$card['region']]))) }}</p>
                    @endif
                </div>
            </div>
            @include('vita.directory.rating')
            <p class="directory-description clinic-description">{{ $card['description'] }}</p>
            @if(!empty($specialtyMatch))<p class="directory-specialty-match">Esta clínica ofrece {{ $specialtyMatch }}</p>@endif
            @if($card['address'])<p class="clinic-location">{{ $card['address'] }}</p>@endif
            <a class="directory-button" href="{{ $card['website'] }}">Visitar página web <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>
        </div>
    </article>

    @if($card['specialties'] || $card['branches'] || $card['contact'] || $card['socials'])
    <div class="clinic-sections {{ ($card['contact'] || $card['socials']) ? 'clinic-sections-with-contact' : '' }}">
        @if($card['specialties'] || $card['branches'])
        <div class="clinic-main-sections">
            @if($card['specialties'])
            <section class="clinic-section" aria-labelledby="clinic-specialties">
                <h2 id="clinic-specialties"><i class="fa-solid fa-stethoscope" aria-hidden="true"></i> Especialidades disponibles</h2>
                <p>Conoce las áreas de atención que ofrece esta clínica.</p>
                <ul class="clinic-specialty-list">@foreach($card['specialties'] as $specialty)<li>{{ $specialty }}</li>@endforeach</ul>
            </section>
            @endif
            @if($card['branches'])
            <section class="clinic-section" aria-labelledby="clinic-branches">
                <h2 id="clinic-branches"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> Nuestras sedes</h2>
                <div class="clinic-branch-list">
                    @foreach($card['branches'] as $branch)
                    <article class="clinic-branch">
                        <h3>{{ $branch['name'] }}</h3>
                        @if($branch['address'])<p>{{ $branch['address'] }}</p>@endif
                        @if($branch['commune'] || $branch['region'])<p>{{ implode(' · ',array_filter([$branch['commune'],$branch['region']])) }}</p>@endif
                        @if($branch['map'])<a class="clinic-text-link" href="{{ $branch['map'] }}" target="_blank" rel="noopener noreferrer">Ver mapa <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endif
                    </article>
                    @endforeach
                </div>
            </section>
            @endif
        </div>
        @endif
        @if($card['contact'] || $card['socials'])
        <aside class="clinic-section clinic-contact" aria-labelledby="clinic-contact">
            <h2 id="clinic-contact">Contacto</h2>
            @foreach($card['contact'] as $kind=>$contact)
                <a class="clinic-contact-item" href="{{ $contact['url'] }}"><i class="fa-solid {{ $kind==='phone' ? 'fa-phone' : 'fa-envelope' }}" aria-hidden="true"></i><span>{{ $contact['label'] }}</span></a>
            @endforeach
            @if($card['socials'])
                <p class="clinic-social-label">Redes oficiales</p>
                <div class="clinic-socials">@foreach($card['socials'] as $social)<a href="{{ $social['url'] }}" aria-label="{{ $social['label'] }}" target="_blank" rel="noopener noreferrer"><i class="{{ $social['icon'] }}" aria-hidden="true"></i></a>@endforeach</div>
            @endif
            <a class="directory-button" href="{{ $card['website'] }}">Visitar página web <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
        </aside>
        @endif
    </div>
    @endif
    @include('vita.directory.reviews')
</div>
@endsection
