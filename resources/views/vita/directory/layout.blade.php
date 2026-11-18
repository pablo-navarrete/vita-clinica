<!DOCTYPE html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ trim($__env->yieldContent('title')) }} · {{ $site['brand'] }}</title><meta name="description" content="{{ trim($__env->yieldContent('description')) }}">
<link rel="canonical" href="{{ trim($__env->yieldContent('canonical')) }}"><meta property="og:title" content="{{ trim($__env->yieldContent('title')) }}"><meta property="og:description" content="{{ trim($__env->yieldContent('description')) }}"><meta property="og:type" content="website"><meta property="og:url" content="{{ trim($__env->yieldContent('canonical')) }}">
<meta property="og:locale" content="es_CL">
@if(!empty($card['cover']) || !empty($card['logo']))<meta property="og:image" content="{{ $card['cover'] ?: $card['logo'] }}"><meta property="og:image:alt" content="{{ $card['name'] }}">@endif
<meta name="csrf-token" content="{{ csrf_token() }}">
@if(!app()->environment('production') || request()->filled('buscar') || request()->boolean('cercanas') || request()->filled('especialidad'))<meta name="robots" content="noindex,follow">@endif
<link rel="preconnect" href="https://fonts.bunny.net"><link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="{{ asset('css/customer-directory.css') }}?v={{ filemtime(public_path('css/customer-directory.css')) }}">
<link rel="stylesheet" href="{{ asset('css/vita-patient-access.css').'?v='.filemtime(public_path('css/vita-patient-access.css')) }}">
@include('components.phone-assets')
@include('components.favicon')
</head><body><a class="directory-skip-link" href="#directory-main">Saltar al contenido</a>@php($publicMenus=collect(\App\Models\VitaWebsiteSetting::menus($site))->filter(fn($menu)=>$menu['status']))@include('vita.topbar')
<header class="directory-header"><div class="wrap directory-nav"><a href="{{ route('vita.directory.index') }}" class="directory-brand">@if(in_array($site['header_brand_display'],['logo','both']) && isset($logosByLocation['header']))<img src="{{ asset('storage/'.$logosByLocation['header']->url) }}" alt="Logo {{ $site['brand'] }}" style="height:44px;max-width:120px;object-fit:contain;vertical-align:middle">@endif @if($site['header_brand_display']!=='logo' || !isset($logosByLocation['header'])){{ $site['brand'] }}@endif</a><nav aria-label="Navegaci&#243;n principal"><a href="{{ route('vita.directory.index') }}">Inicio</a>@include('vita.specialty-menu')<a href="{{ route('vita.website') }}">Trabaja con nosotros</a>@include('vita.patient-access')</nav></div></header>
<main id="directory-main" tabindex="-1">@yield('content')</main>@include('vita.footer')@yield('scripts')</body></html>
