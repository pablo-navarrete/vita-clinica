<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sitio en mantenimiento | {{ app('customer')->name }}</title>
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    @include('customer.web.theme-colors')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body style="min-height:100vh; display:grid; place-items:center; margin:0; padding:24px; background:{{ themeColor(50) }}; font-family:Arial,sans-serif;">
    <main style="width:min(100%,680px); padding:56px 32px; border-radius:20px; background:#fff; text-align:center; box-shadow:0 18px 55px #00000012;">
        <div style="display:grid; place-items:center; width:84px; height:84px; margin:0 auto 24px; border-radius:50%; background:{{ themeColor(100) }}; color:{{ themeColor(700) }}; font-size:34px;">
            <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
        </div>
        <p style="margin:0 0 10px; color:{{ themeColor(700) }}; font-size:13px; font-weight:700; letter-spacing:.12em; text-transform:uppercase;">{{ app('customer')->name }}</p>
        <h1 style="margin:0 0 16px; color:#1f2937; font-size:clamp(28px,5vw,40px);">Estamos realizando mejoras</h1>
        <p style="margin:0; color:#6b7280; font-size:17px; line-height:1.7;">{{ $maintenance->message ?: 'Nuestro sitio está temporalmente en mantenimiento. Vuelve a visitarnos pronto.' }}</p>
    </main>
</body>
</html>
