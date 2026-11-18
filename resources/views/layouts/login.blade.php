@php
    $clinic = app()->bound('customer') ? app('customer') : null;
    $clinicName = $clinic?->name ?? config('app.name', 'VITA');
    $loginLogo = app()->bound('logos_by_location') ? (app('logos_by_location')['login'] ?? null) : null;
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $clinicName }} · Iniciar sesión</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .vita-login { --accent: {{ themeColor(700) }}; --hover: {{ themeColor(800) }}; --soft: {{ themeColor(50) }}; --tint: {{ themeColor(100) }}; --line: {{ themeColor(200) }}; margin: 0; font-family: 'Figtree', sans-serif; color: #18232e; background: #fff; }
        .vita-login *, .vita-login *::before, .vita-login *::after { box-sizing: border-box; }
        .vita-login { min-height: 100vh; min-height: 100svh; display: flex; flex-direction: column; }
        .vita-login .login-topbar { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 16px clamp(24px, 4vw, 64px); background: #fff; border-bottom: 1px solid #edf0f2; }
        .vita-login .login-topbar .login-identity { margin: 0; min-width: 0; }
        .vita-login .login-topbar .login-identity img { height: 40px; max-width: 90px; }
        .vita-login .login-topbar .login-identity-name { font-size: 18px; }
        .vita-login .login-home { color: var(--accent); font-size: 14px; font-weight: 500; text-decoration: none; padding: 10px 14px; border-radius: 8px; }
        .vita-login .login-home:hover { background: var(--soft); }
        .vita-login .login-shell { display: grid; grid-template-columns: minmax(0, 55fr) minmax(0, 45fr); flex: 1; }
        .vita-login .login-welcome { position: relative; isolation: isolate; overflow: hidden; display: flex; flex-direction: column; justify-content: center; padding: clamp(40px, 6vw, 96px); background: linear-gradient(145deg, var(--soft) 0%, var(--tint) 65%, var(--line) 100%); }
        .vita-login .login-welcome::before { content: ''; position: absolute; z-index: -1; width: 620px; height: 620px; right: -340px; top: -330px; border: 1px solid var(--line); border-radius: 46% 54% 65% 35%; transform: rotate(-25deg); opacity: .45; box-shadow: 0 0 0 55px rgb(255 255 255 / .25), 0 0 0 115px rgb(255 255 255 / .18); }
        .vita-login .login-welcome::after { content: ''; position: absolute; z-index: -1; width: 600px; height: 340px; left: -240px; bottom: -250px; border: 1px solid var(--line); border-radius: 50%; transform: rotate(25deg); opacity: .4; box-shadow: 0 0 0 65px rgb(255 255 255 / .28); }
        .vita-login .welcome-copy { width: 100%; max-width: 540px; margin: 48px 0; animation: login-appear .45s ease-out both; }
        .vita-login .welcome-label { display: flex; align-items: center; gap: 10px; margin: 0 0 18px; color: var(--accent); font-size: 11px; line-height: 1.6; letter-spacing: .16em; font-weight: 700; }
        .vita-login .welcome-label::before { content: ''; width: 24px; height: 2px; flex-shrink: 0; background: currentColor; }
        .vita-login .welcome-title { margin: 0 0 20px; font-size: clamp(32px, 3.33vw, 56px); line-height: 1.12; letter-spacing: -.045em; font-weight: 600; text-wrap: balance; overflow-wrap: anywhere; }
        .vita-login .welcome-description { margin: 0; max-width: 420px; font-size: 16px; line-height: 1.8; color: #506172; }
        .vita-login .welcome-signature { position: absolute; bottom: 36px; left: clamp(40px, 6vw, 96px); display: flex; align-items: center; gap: 12px; color: #526371; font-size: 12px; }
        .vita-login .welcome-signature strong { font-size: 15px; letter-spacing: .18em; color: var(--accent); }
        .vita-login .signature-divider { width: 1px; height: 16px; background: var(--line); }
        .vita-login .login-access { display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 48px clamp(28px, 4vw, 72px); background: #fff; }
        .vita-login .login-form-content { width: 100%; max-width: 370px; animation: login-appear .45s ease-out both; }
        .vita-login .login-identity { display: flex; align-items: center; gap: 14px; margin-bottom: 46px; text-decoration: none; color: #18232e; }
        .vita-login .login-identity img { width: auto; max-width: 100px; height: 54px; object-fit: contain; }
        .vita-login .login-identity-name { font-size: 21px; font-weight: 600; letter-spacing: -.025em; line-height: 1.25; overflow-wrap: anywhere; }
        .vita-login .auth-form-title { margin: 0 0 8px; font-size: 30px; line-height: 1.2; font-weight: 600; letter-spacing: -.035em; }
        .vita-login .auth-form-description { margin: 0 0 30px; font-size: 14px; line-height: 1.6; color: #687581; }
        .vita-login .auth-field + .auth-field { margin-top: 22px; }
        .vita-login .auth-field label { display: block; margin-bottom: 8px; color: #35424e; font-weight: 500; font-size: 13px; }
        .vita-login .auth-field input { display: block; width: 100%; height: 48px; padding: 12px 14px; border: 1px solid #dce1e6; border-radius: 9px; background: #fff; color: #18232e; font: inherit; font-size: 14px; transition: border-color .15s ease, box-shadow .15s ease; }
        .vita-login .auth-field input::placeholder { color: #98a1aa; }
        .vita-login .auth-field input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--tint); --tw-ring-color: transparent; }
        .vita-login .auth-field input[aria-invalid="true"] { border-color: #b91c1c; }
        .vita-login .password-control { position: relative; }
        .vita-login .password-control input { padding-right: 50px; }
        .vita-login .password-toggle { position: absolute; inset: 2px 2px 2px auto; width: 44px; display: grid; place-items: center; background: transparent; border: 0; border-radius: 7px; color: #75818c; cursor: pointer; }
        .vita-login .password-toggle:hover { background: #f5f7f9; color: var(--accent); }
        .vita-login .password-toggle svg { width: 19px; height: 19px; }
        .vita-login .auth-recovery { display: block; width: fit-content; margin: 16px 0 26px auto; font-size: 12px; color: var(--accent); text-decoration: none; }
        .vita-login .auth-recovery:hover { text-decoration: underline; }
        .vita-login .auth-submit { display: flex; align-items: center; justify-content: center; gap: 12px; width: 100%; min-height: 48px; margin-top: 24px; padding: 12px 18px; border: 1px solid transparent; border-radius: 9px; background: var(--accent); color: #fff; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; transition: background-color .15s ease, box-shadow .15s ease; }
        .vita-login .auth-submit:hover { background: var(--hover); box-shadow: 0 4px 12px rgb(15 23 42 / .08); }
        .vita-login .auth-submit svg { width: 17px; height: 17px; }
        .vita-login a:focus-visible, .vita-login button:focus-visible { outline: 2px solid var(--accent); outline-offset: 4px; }
        .vita-login .login-security { display: flex; justify-content: center; align-items: center; gap: 7px; margin: 26px 0 0; font-size: 11px; color: #77828c; }
        .vita-login .login-security svg { width: 13px; height: 13px; flex-shrink: 0; }
        .vita-login [x-cloak] { display: none !important; }
        .vita-login .login-home { display: inline-flex; align-items: center; gap: 8px; line-height: 1.4; }
        .vita-login .login-home svg { width: 16px; height: 16px; flex-shrink: 0; }
        .vita-login .login-topbar nav { flex-shrink: 0; }
        .vita-login .login-identity-name { min-width: 0; }
        .vita-login .auth-field input:hover:not(:focus) { border-color: #b6c0ca; }
        .vita-login .password-toggle { transition: background-color .15s ease, color .15s ease; }
        @media (max-width: 480px) {
            .vita-login .login-topbar { gap: 12px; padding: 14px 16px; }
            .vita-login .login-topbar .login-identity { gap: 8px; }
            .vita-login .login-topbar .login-identity img { height: 32px; max-width: 56px; }
            .vita-login .login-topbar .login-identity-name { font-size: 14px; }
            .vita-login .login-home { font-size: 12px; padding: 8px 0 8px 6px; max-width: 130px; }
        }
        @keyframes login-appear { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }
        @media (min-width: 768px) and (max-width: 1023px) {
            .vita-login .login-shell { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
            .vita-login .login-welcome { padding: 40px 32px; }
            .vita-login .welcome-signature { left: 32px; }
            .vita-login .welcome-title { font-size: 34px; }
        }
        @media (max-width: 767px) {
            .vita-login .login-shell { display: flex; flex-direction: column; }
            .vita-login .login-access { order: 0; flex: 1; padding: 34px 24px 28px; }
            .vita-login .login-identity { margin-bottom: 30px; }
            .vita-login .login-identity img { height: 46px; }
            .vita-login .login-welcome { order: 1; padding: 24px; }
            .vita-login .login-welcome::before, .vita-login .login-welcome::after { display: none; }
            .vita-login .welcome-copy { margin: 0 auto; max-width: 370px; }
            .vita-login .welcome-label { margin-bottom: 10px; font-size: 10px; letter-spacing: .12em; }
            .vita-login .welcome-title { font-size: 22px; line-height: 1.2; margin-bottom: 10px; }
            .vita-login .welcome-description { font-size: 13px; line-height: 1.6; }
            .vita-login .welcome-signature { position: static; margin: 24px auto 0; width: 100%; max-width: 370px; font-size: 10px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .vita-login .welcome-copy, .vita-login .login-form-content { animation: none; }
            .vita-login input, .vita-login button { transition: none; }
        }
        .vita-login .login-access { background: var(--soft); }
        .vita-login .login-form-content { max-width: 450px; padding: 40px; border: 1px solid #e2e8f0; border-radius: 22px; background: #fff; box-shadow: 0 8px 28px #14384b08; }
        @media (max-width: 640px) {
            .vita-login .login-access { padding: 28px 16px 36px; }
            .vita-login .login-form-content { padding: 28px 24px; border-radius: 18px; }
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/vita-auth-background.css').'?v='.filemtime(public_path('css/vita-auth-background.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/vita-access-footer.css').'?v='.filemtime(public_path('css/vita-access-footer.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/figtree.css') }}">
    <link rel="stylesheet" href="{{ asset('css/vita-auth-header.css').'?v='.filemtime(public_path('css/vita-auth-header.css')) }}">
@include('components.phone-assets')
@include('components.favicon')
</head>
<body class="vita-login">
    <header class="login-topbar">
        <a href="{{ $publicSiteUrl ?? url('/login') }}" class="login-identity">
            <img src="{{ $loginLogo ? asset('storage/' . $loginLogo->url) : asset('storage/admin/logos/logo-vita-3.png') }}" alt="Logo {{ $clinicName }}">
            <span class="login-identity-name">{{ $clinicName }}</span>
        </a>
        @if ($publicSiteUrl)
            <nav aria-label="Navegación principal"><a href="{{ $publicSiteUrl }}" class="login-home"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg><span>Volver al sitio web</span></a></nav>
        @endif
    </header>
    <main class="login-shell vita-auth-background">
        @include('components.auth-background-curves')
        <section class="login-welcome" aria-labelledby="welcome-title">
            <div class="welcome-copy">
                <p class="welcome-label">{{ $motivation['label'] }}</p>
                <h1 id="welcome-title" class="welcome-title">{{ $motivation['title'] }}</h1>
                <p class="welcome-description">{{ $motivation['description'] }}</p>
            </div>
            <div class="welcome-signature"><strong>VITA</strong><span class="signature-divider" aria-hidden="true"></span><span>Tecnología al servicio de la salud</span></div>
        </section>
        <section class="login-access" aria-label="Acceso a la plataforma">
            <div class="login-form-content">
                {{ $slot }}
                <p class="login-security"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>Acceso seguro a tu espacio de trabajo</p>
            </div>
        </section>
    </main>
    @include('layouts.footer-access')
</body>
</html>
