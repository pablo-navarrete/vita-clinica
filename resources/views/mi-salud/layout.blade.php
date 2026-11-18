@php($workspace = in_array($page,['panel','profile','attentions','booking']) && isset($account))
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer">
    <title>Mi Salud · VITA</title>
    <link rel="stylesheet" href="{{ asset('css/figtree.css') }}">
    <link rel="stylesheet" href="{{ asset('css/mi-salud.css').'?v='.filemtime(public_path('css/mi-salud.css')) }}">
    @if($workspace)<link rel="stylesheet" href="{{ asset('css/mi-salud-attentions.css').'?v='.filemtime(public_path('css/mi-salud-attentions.css')) }}">@endif
    @if($workspace)<link rel="stylesheet" href="{{ asset('css/mi-salud-booking.css').'?v='.filemtime(public_path('css/mi-salud-booking.css')) }}">@endif
    @if(in_array($page,['login','register']))<link rel="stylesheet" href="{{ asset('css/mi-salud-login.css').'?v='.filemtime(public_path('css/mi-salud-login.css')) }}">@endif
    @if(in_array($page,['login','register']))<link rel="stylesheet" href="{{ asset('css/vita-auth-background.css').'?v='.filemtime(public_path('css/vita-auth-background.css')) }}">@endif
    <script src="{{ asset('js/mi-salud.js').'?v='.filemtime(public_path('js/mi-salud.js')) }}" defer></script>
    <link rel="stylesheet" href="{{ asset('css/vita-access-footer.css').'?v='.filemtime(public_path('css/vita-access-footer.css')) }}">
    @if($workspace)<link rel="stylesheet" href="{{ asset('css/mi-salud-workspace.css').'?v='.filemtime(public_path('css/mi-salud-workspace.css')) }}">@endif
    @if($workspace)<link rel="stylesheet" href="{{ asset('css/vita-sidebar.css').'?v='.filemtime(public_path('css/vita-sidebar.css')) }}">@endif
    @if($workspace)<link rel="stylesheet" href="{{ asset('css/vita-workspace-layout.css').'?v='.filemtime(public_path('css/vita-workspace-layout.css')) }}">@endif
    <link rel="stylesheet" href="{{ asset('css/vita-workspace-theme.css').'?v='.filemtime(public_path('css/vita-workspace-theme.css')) }}">
    @if(in_array($page,['login','register']))<link rel="stylesheet" href="{{ asset('css/vita-auth-header.css').'?v='.filemtime(public_path('css/vita-auth-header.css')) }}">@endif
@include('components.phone-assets')
    <link rel="stylesheet" href="{{ asset('css/clinic-field-focus.css').'?v='.filemtime(public_path('css/clinic-field-focus.css')) }}">
@include('components.favicon')
</head>
<body @class(['vita-patient-portal','portal-login'=>in_array($page,['login','register']),'portal-workspace'=>$workspace])>
<a class="skip" href="#contenido">Ir al contenido</a>
@if($workspace)
@component('layouts.clinic-workspace')
    @slot('sidebar') @include('mi-salud.sidebar') @endslot
    @slot('navigation') @include('mi-salud.navigation') @endslot
@else
<header class="portal-header"><a class="brand" href="{{ route('mi-salud.home') }}">@include('mi-salud.brand') <span class="brand-caption">Mi Salud</span></a>
    <nav aria-label="Navegación personal">
    @if(in_array($page,['login','register']))
        <a href="{{ route('vita.directory.index') }}">← Volver al sitio web</a>
    @elseif($account ?? null)
        <a href="{{ route('mi-salud.panel') }}">Mi espacio</a><a href="{{ route('mi-salud.profile') }}">Mi perfil</a>
        <form method="POST" action="{{ route('mi-salud.logout') }}">@csrf<button class="text-button" type="submit">Salir</button></form>
    @else
        <a href="{{ route('mi-salud.login') }}">Ingresar</a><a class="button small" href="{{ route('mi-salud.register') }}">Crear cuenta</a>
    @endif
    </nav>
</header>
@endif
@if($workspace)<div id="portal-content-loading" hidden role="status" aria-live="polite"><div class="portal-content-preloader"><span class="portal-content-spinner" aria-hidden="true"></span><span class="portal-visually-hidden">Cargando contenido…</span></div></div>@endif
<main id="contenido" tabindex="-1">
    @if($workspace)
        <header class="portal-page-header"><h1>{{ ['panel'=>'Panel de Mi Salud','profile'=>'Mi perfil','attentions'=>'Mis atenciones','booking'=>'Agendar atención'][$page] }}</h1></header>
        <div class="portal-page-content">
    @endif
    @if(session('status'))<div class="notice" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="notice error" role="alert"><strong>Revisa los siguientes datos</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
    @if($workspace)</div>@endif
</main>
@if($workspace)
<x-workspace-footer />
@endcomponent
@else
@include('layouts.footer-access',['description'=>'Mi Salud es tu espacio personal en VITA.'])
@endif
@if($workspace)
@include('components.vita-assistant',['portalMode'=>true])
<script src="{{ asset('js/mi-salud-viti.js').'?v='.filemtime(public_path('js/mi-salud-viti.js')) }}" defer></script>
<script src="{{ asset('js/vendor/chart.umd.min.js').'?v='.filemtime(public_path('js/vendor/chart.umd.min.js')) }}" defer></script>
<script src="{{ asset('js/mi-salud-charts.js').'?v='.filemtime(public_path('js/mi-salud-charts.js')) }}" defer></script>
<script src="{{ asset('js/mi-salud-navigation.js').'?v='.filemtime(public_path('js/mi-salud-navigation.js')) }}" defer></script>
@endif
</body></html>
