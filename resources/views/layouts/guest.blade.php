<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'VITA') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        body {
            margin: 0;
            overflow-x: hidden;
            font-family: 'Figtree', sans-serif;
            background-color: {{ themeColor(100) }};
        }

        /* Fondo principal */
        .background {
            position: fixed;
            inset: 0;
            z-index: 0;
            background-color: {{ themeColor(100) }};
        }

        /* Imagen superior con curva */
        .background::before {
            content: "";
            position: absolute;
            inset: 0;
            height: 42vh;

            background:
                linear-gradient(rgba(0, 0, 0, .35), rgba(0, 0, 0, .35)),
                url('{{ asset('img/medic2.png') }}') center/cover no-repeat;

            clip-path: polygon(0 0,
                    100% 0,
                    100% 88%,
                    50% 100%,
                    0 88%);
        }

        .content {
            position: relative;
            z-index: 10;

            min-height: 100vh;

            display: flex;
            flex-direction: column;
            align-items: center;

            padding-top: 70px;
            padding-left: 20px;
            padding-right: 20px;
        }

        .logo-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo-container h1 {
            color: white;
            font-size: 1.8rem;
            font-weight: 700;
            text-shadow: 0 2px 8px rgba(0, 0, 0, .35);
            margin-top: 10px;
        }

        .login-card {
            width: 100%;
            max-width: 430px;

            background: rgba(255, 255, 255, .92);

            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);

            border-radius: 24px;

            padding: 30px;

            box-shadow:
                0 15px 35px rgba(0, 0, 0, .12),
                0 5px 15px rgba(0, 0, 0, .08);
        }

        @media (max-width: 640px) {
            .background::before {
                height: 35vh;
            }

            .content {
                padding-top: 50px;
            }

            .logo-container h1 {
                font-size: 1.4rem;
            }

            .login-card {
                padding: 24px;
            }
        }
    </style>
</head>

<body>

    <div class="background"></div>

    <div class="content">

        <div class="logo-container">

            <a href="/" class="flex flex-col items-center text-center">

                @if (isset(app('logos_by_location')['login']))
                    <img src="{{ asset('storage/' . app('logos_by_location')['login']->url) }}" class="h-[100px] w-auto"
                        alt="Logo">
                @else
                    <i class="fa-solid fa-laptop-medical text-6xl text-white"></i>
                @endif

                <h1>
                    {{ app()->bound('customer') ? app('customer')->name : config('app.name') }}
                </h1>

            </a>

        </div>

        <div class="login-card">
            {{ $slot }}
        </div>

    </div>

</body>

</html>
