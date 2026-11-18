<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Verificar solicitud · VITA</title>@include('components.favicon')
</head>
<body style="font-family:system-ui;max-width:560px;margin:8vh auto;padding:24px"><h1>Verifica tu solicitud</h1>
<p>Al continuar verificaremos este correo y comprobaremos nuevamente la disponibilidad. El enlace no da acceso a fichas clínicas.</p>
<form method="POST">@csrf<input type="hidden" id="registration-token" name="registration_token"><button type="submit" style="padding:12px 20px;cursor:pointer">Verificar correo y continuar</button></form>
<script nonce="{{ $nonce }}">const bearer=location.hash.slice(1);history.replaceState(null,'',location.pathname);document.getElementById('registration-token').value=/^[a-f0-9]{64}$/.test(bearer)?bearer:'';</script>
</body></html>
