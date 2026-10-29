
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'VITA') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            min-height: 100%;
        }

        body {
            font-family: 'Figtree', sans-serif;

            /* Fondo usando el theme */
            background: {{ themeColor(50) }};

            color: #1e293b;
            overflow-x: hidden;
        }

        /* =========================================================
           FONDO
        ========================================================= */

        .background {
            position: fixed;
            inset: 0;
            z-index: 0;

            pointer-events: none;
            background-color: #f5faff;
            background-image:
                linear-gradient(135deg, {{ themeColor(100) }} 0%, transparent 50%, {{ themeColor(200) }} 100%),
                url('{{ asset('images/login-background-botanical.png') }}');
            background-position: center;
            background-size: cover;
            background-repeat: no-repeat;
        }

        /* =========================================================
           CONTENIDO PRINCIPAL
        ========================================================= */

        .content {
            position: relative;
            z-index: 10;

            min-height: 100vh;

            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;

            padding: 40px 20px;
        }

        /* =========================================================
           LOGO
        ========================================================= */

        .logo-container {
            text-align: center;
            margin-bottom: 24px;

            animation: fadeDown 0.6s ease-out;
        }

        .logo-link {
            display: flex;
            flex-direction: column;
            align-items: center;

            text-decoration: none;
        }

        .logo-image {
            height: 90px;
            width: auto;
            max-width: 260px;

            object-fit: contain;

            margin-bottom: 12px;

            filter:
                drop-shadow(
                    0 5px 12px rgba(30, 80, 110, 0.10)
                );
        }

        .fallback-logo {
            width: 78px;
            height: 78px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 22px;

            background:
                linear-gradient(
                    135deg,
                    {{ themeColor(500) }},
                    {{ themeColor(700) }}
                );

            color: white;

            font-size: 2.2rem;

            box-shadow:
                0 12px 30px rgba(30, 80, 110, 0.15);
        }

        .logo-container h1 {
            margin: 0;

            color: {{ themeColor(700) }};

            font-size: 1.65rem;
            line-height: 1.2;
            font-weight: 700;

            letter-spacing: -0.02em;
        }

        .logo-subtitle {
            margin-top: 6px;

            color: #64748b;

            font-size: 0.82rem;
            font-weight: 500;

            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        /* =========================================================
           TARJETA DE LOGIN
        ========================================================= */

        .login-card {
            position: relative;

            width: 100%;
            max-width: 430px;

            padding: 32px;

            background: rgba(255, 255, 255, 0.96);

            border: 1px solid rgba(100, 160, 190, 0.13);

            border-radius: 22px;

            box-shadow:
                0 25px 60px rgba(30, 80, 110, 0.10),
                0 8px 25px rgba(30, 80, 110, 0.06);

            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);

            animation: fadeUp 0.7s ease-out;
        }

        /* Línea decorativa superior de la tarjeta */

        .login-card::before {
            content: "";

            position: absolute;

            top: 0;
            left: 12%;
            right: 12%;

            height: 3px;

            border-radius: 0 0 10px 10px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    {{ themeColor(500) }},
                    {{ themeColor(600) }},
                    transparent
                );

            opacity: 0.75;
        }

      

        /* =========================================================
           FOOTER
        ========================================================= */

        .login-footer {
            margin-top: 18px;

            color: #64748b;

            font-size: 0.75rem;

            text-align: center;
        }

        /* =========================================================
           ANIMACIONES
        ========================================================= */

        @keyframes fadeDown {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

  
        /* =========================================================
           MOBILE
        ========================================================= */

        @media (max-width: 640px) {

            .content {
                justify-content: flex-start;

                min-height: 100vh;

                padding-top: 45px;
                padding-bottom: 30px;
            }

            .logo-container {
                margin-bottom: 20px;
            }

            .logo-image {
                height: 75px;
            }

            .fallback-logo {
                width: 68px;
                height: 68px;

                font-size: 1.9rem;
            }

            .logo-container h1 {
                font-size: 1.4rem;
            }

            .logo-subtitle {
                font-size: 0.72rem;
            }

            .login-card {
                max-width: 100%;

                padding: 26px 22px;

                border-radius: 20px;
            }

          
        }

        /* =========================================================
           PANTALLAS PEQUEÑAS
        ========================================================= */

        @media (max-height: 700px) {

            .content {
                padding-top: 25px;
                padding-bottom: 25px;
            }

            .logo-container {
                margin-bottom: 15px;
            }

            .logo-image {
                height: 65px;
            }

            .login-card {
                padding: 24px;
            }
        }

        /* =========================================================
           ACCESIBILIDAD
        ========================================================= */

        @media (prefers-reduced-motion: reduce) {

            .logo-container,
            .login-card {
                animation: none;
            }
        }

        .content { padding: 48px 28px; }
        .auth-layout { display: grid; grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr); align-items: center; gap: clamp(40px, 7vw, 100px); width: 100%; max-width: 1080px; }
        .auth-brand { padding: 24px 8px; }
        .auth-brand-link { display: inline-flex; align-items: center; gap: 16px; text-decoration: none; margin-bottom: 42px; max-width: 100%; }
        .auth-logo { width: auto; max-width: 120px; height: 72px; object-fit: contain; }
        .auth-brand h1 { margin: 0; font-size: clamp(24px, 3vw, 34px); line-height: 1.2; font-weight: 800; letter-spacing: -0.035em; color: {{ themeColor(800) }}; overflow-wrap: anywhere; }
        .auth-eyebrow { display: block; font-size: 12px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: {{ themeColor(700) }}; }
        .auth-brand h2 { margin: 18px 0; font-size: clamp(34px, 4vw, 52px); line-height: 1.12; font-weight: 700; letter-spacing: -0.04em; color: #1e293b; }
        .auth-brand > p { margin: 0; max-width: 430px; font-size: 16px; line-height: 1.85; color: #475569; }
        .auth-brand-note { display: flex; align-items: center; gap: 10px; margin-top: 32px; font-size: 13px; color: {{ themeColor(700) }}; }
        .auth-brand-note i { font-size: 19px; }
        .auth-form-panel { min-width: 0; width: 100%; max-width: 450px; justify-self: end; }
        .auth-form-panel .login-card { max-width: none; padding: 40px; border: 1px solid rgba(255,255,255,.9); border-radius: 26px; background: rgba(255,255,255,.96); box-shadow: 0 24px 70px rgba(15,23,42,.09); }
        .auth-form-panel .login-card::before { display: none; }
        .auth-form-title { margin: 0 0 8px; font-size: 27px; font-weight: 700; letter-spacing: -0.035em; color: #1e293b; }
        .auth-form-description { margin: 0 0 30px; color: #64748b; font-size: 14px; line-height: 1.6; }
        .auth-login-form input[type="email"], .auth-login-form input[type="password"] { min-height: 48px; border-radius: 12px; border-color: #cbd5e1; background: #f8fafc; padding: 12px 14px; }
        .auth-login-form input:focus { border-color: {{ themeColor(500) }}; --tw-ring-color: {{ themeColor(500) }}; }
        .auth-login-form .auth-submit { width: 100%; justify-content: center; min-height: 48px; border-radius: 12px; margin-top: 24px; font-size: 14px; letter-spacing: 0; text-transform: none; }
        .auth-login-form .auth-recovery { display: block; text-align: right; font-size: 13px; margin-top: 16px; color: {{ themeColor(700) }}; }
        @media (max-width: 767px) {
            .content { padding: 28px 20px; justify-content: center; }
            .auth-layout { grid-template-columns: minmax(0, 1fr); gap: 24px; max-width: 450px; }
            .auth-brand { padding: 0; text-align: center; }
            .auth-brand-link { margin-bottom: 18px; gap: 12px; }
            .auth-logo { height: 56px; max-width: 95px; }
            .auth-brand h2 { font-size: 30px; margin: 12px 0; }
            .auth-brand h2 br { display: none; }
            .auth-brand > p { font-size: 14px; line-height: 1.6; }
            .auth-brand-note { display: none; }
            .auth-form-panel .login-card { padding: 28px 24px; border-radius: 22px; }
        }
        @if ($motivation)
        .auth-brand h2 { margin: 10px 0 12px; overflow-wrap: break-word; text-wrap: balance; }
        .auth-brand-note { margin-top: 20px; }
        @endif
    </style>
</head>

<body>
    <div class="background" aria-hidden="true"></div>





    <!-- =========================================================
         CONTENIDO
    ========================================================== -->

    <main class="content">
        <div class="auth-layout">
            <section class="auth-brand" aria-label="Bienvenida">
                <a href="/" class="auth-brand-link">
                    @if (isset(app('logos_by_location')['login']))
                        <img src="{{ asset('storage/' . app('logos_by_location')['login']->url) }}" class="auth-logo" alt="{{ app()->bound('customer') && app('customer') ? app('customer')->name : config('app.name') }}">
                    @else
                        <img src="{{ asset('storage/admin/logos/logo-vita-3.png') }}" class="auth-logo" alt="VITA">
                    @endif
                    <h1>{{ app()->bound('customer') && app('customer') ? app('customer')->name : config('app.name', 'VITA') }}</h1>
                </a>
                <span class="auth-eyebrow">{{ $motivation['label'] ?? 'TECNOLOGÍA AL SERVICIO DE LA SALUD' }}</span>
                <h2>@if ($motivation){{ $motivation['title'] }}@else Un espacio para<br>cuidar mejor.@endif</h2>
                <p>{{ $motivation['description'] ?? 'Simplifica tu gestión clínica y dedica más tiempo a lo que realmente importa: tus pacientes.' }}</p>
                <div class="auth-brand-note"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i><span>Tecnolog&iacute;a al servicio de la salud</span></div>
            </section>
            <section class="auth-form-panel" aria-label="Acceso a la plataforma">
                <div class="login-card">{{ $slot }}</div>
                <div class="login-footer"><i class="fa-solid fa-lock" aria-hidden="true"></i> Acceso seguro &middot; Plataforma de salud <strong>VITA</strong></div>
            </section>
        </div>
    </main>

</body>

</html>

