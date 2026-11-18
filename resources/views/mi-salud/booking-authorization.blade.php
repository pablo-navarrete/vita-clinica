<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Continuar reserva · Mi Salud</title><link rel="stylesheet" href="{{ asset('css/style.css') }}">@include('components.favicon')
</head>
<body><main class="container py-5"><section class="card mx-auto" style="max-width:560px"><div class="card-body p-4">
<h1 class="h3">Continúa tu reserva en {{ $clinic->name }}</h1>
<p>Utilizaremos tus datos personales para esta reserva. Esta autorización no comparte fichas clínicas ni acredita tu identidad profesional.</p>
<form method="POST" action="{{ route('mi-salud.booking.approve',['intent'=>$intent]) }}">@csrf<input type="hidden" name="authorize" value="1"><button class="btn btn-primary" type="submit">Autorizar y continuar</button></form>
<a class="btn btn-link mt-3" href="{{ $cancel }}">Continuar sin Mi Salud</a>
</div></section></main></body></html>
