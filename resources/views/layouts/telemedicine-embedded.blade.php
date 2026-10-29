<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Teleconsulta · VITA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        html, body { margin: 0; background: #f8fafc; }
        [x-cloak] { display: none !important; }
        main { padding: 8px !important; }
        main > section { margin-top: 8px !important; padding: 8px !important; box-shadow: none !important; }
        main > section:first-child { margin-top: 0 !important; }
        main > section:first-child h1, main > section:first-child .uppercase, main > section:first-child h1 + p { display: none; }
        main > section:first-child a { display: none; }
        main > section:first-child > div:first-child { gap: 0; }
        main > section:first-child > div:first-child > div:first-child { display: none; }
        #connectionStatus { padding: 3px 8px; font-size: 11px; }
        .embedded-video-stage { display: block; position: relative; padding: 0 !important; height: max(260px, calc(100dvh - 140px)); background: #0f172a; overflow: hidden; border-radius: 12px; }
        .embedded-video-stage > div:first-child { position: absolute; bottom: 8px; right: 8px; z-index: 5; width: 28%; height: 30%; aspect-ratio: auto; border: 1px solid #94a3b8; border-radius: 8px; }
        #remoteTracks { width: 100%; height: 100%; aspect-ratio: auto; padding: 0; }
        #remoteTracks > div { min-height: 0; }
        main > section:last-of-type { gap: 4px; }
        main > section:last-of-type button { font-size: 11px; padding: 6px 8px; border-radius: 8px; }
        @media(max-height:300px) { .embedded-video-stage { height: clamp(72px, calc(100dvh - 115px), 105px); } main > section { margin-top: 4px !important; } }
    </style>
    @stack('styles')
</head>
<body>@yield('content')@stack('scripts')</body>
</html>
