
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

            /*
             * El fondo utiliza directamente el color
             * definido por el theme.
             */
            background: {{ themeColor(50) }};
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
    </style>
</head>

<body>





    <!-- =========================================================
         CONTENIDO
    ========================================================== -->

    <main class="content">

        <!-- =====================================================
             LOGO
        ====================================================== -->

        <div class="logo-container">

            <a href="/" class="logo-link">

                @if (isset(app('logos_by_location')['login']))

                    <img
                        src="{{ asset('storage/' . app('logos_by_location')['login']->url) }}"
                        class="logo-image"
                        alt="{{ app()->bound('customer') ? app('customer')->name : config('app.name') }}"
                    >

                @else

                    <div class="fallback-logo">
                        <i class="fa-solid fa-laptop-medical"></i>
                    </div>

                @endif

                <h1>
                    {{ app()->bound('customer') ? app('customer')->name : config('app.name') }}
                </h1>

                {{-- 
                <div class="logo-subtitle">
                    Plataforma de atención en salud
                </div>
                --}}

            </a>

        </div>


        <!-- =====================================================
             LOGIN
        ====================================================== -->

        <div class="login-card">

            {{ $slot }}

        </div>


        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="login-footer">
            Acceso seguro · Plataforma de salud <strong>VITA</strong>
        </div>

    </main>

</body>

</html>

