<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Continuar reserva · VITA</title>@include('components.favicon')
</head>
<body><main><h1>Continúa tu reserva</h1><p>Conservaremos la atención seleccionada. Podrás revisarla antes de confirmar.</p>
<form id="portal-booking-exchange" method="POST">@csrf<button type="submit">Continuar con Mi Salud</button></form>
<a href="{{ route('customer.public-booking.portal.cancel',['customer'=>app('customer')->domain,'intent'=>$intent]) }}">Continuar con el formulario tradicional</a>
</main><script nonce="{{ $nonce }}">history.replaceState(null,'',location.pathname);document.getElementById('portal-booking-exchange').requestSubmit();</script></body></html>
