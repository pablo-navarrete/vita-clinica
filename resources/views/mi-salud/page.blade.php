@extends('mi-salud.layout')
@section('content')
@if(in_array($page,['login','register']))
<div class="login-shell vita-auth-background">
    @include('components.auth-background-curves')
    <section class="login-welcome" aria-labelledby="welcome-title">
        <div class="welcome-copy"><p class="welcome-label">VITA · MI SALUD</p>
            <h1 id="welcome-title" class="welcome-title">Tu salud, en tu propio espacio.</h1>
            <p class="welcome-description">{{ $page==='register'?'Crea tu cuenta gratuita para comenzar en Mi Salud.':'Ingresa a tu cuenta personal para administrar tu perfil en Mi Salud.' }} Puedes comenzar aunque todavía no te atiendas en una clínica de VITA.</p>
        </div>
        <div class="welcome-signature"><div class="welcome-brand">@include('mi-salud.brand')</div><span aria-hidden="true"></span>Tecnología al servicio de la salud</div>
    </section>
    <section class="login-access" aria-labelledby="login-title"><div class="login-form-content card">
        @if($page==='register')
            @include('mi-salud.register-form')
        @else
        <p class="login-caption">TU CUENTA PERSONAL</p><h2 id="login-title">Iniciar sesión</h2><p class="login-description">Bienvenido a Mi Salud.</p>
        <form method="POST" action="{{ route('mi-salud.login') }}">@csrf
            <div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" placeholder="tu@correo.cl" required maxlength="255"></div>
            <div class="field"><label for="password">Contraseña</label><div class="portal-password-control"><input id="password" name="password" type="password" autocomplete="current-password" placeholder="Ingresa tu contraseña" required maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password'])</div></div>
            <a class="recovery" href="{{ route('mi-salud.forgot') }}">¿Olvidaste tu contraseña?</a>
            <button class="button wide" type="submit">Ingresar <span aria-hidden="true">→</span></button>
        </form>
        <p class="auth-bottom">¿Aún no tienes cuenta? <a href="{{ route('mi-salud.register') }}">Registrarme</a></p>
        <p class="login-security"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3M12 14v3"/></svg>Tu acceso personal a Mi Salud</p>
        @endif
    </div></section>
</div>
@elseif($page==='panel')
@if(isset($myReviews) && $myReviews->isNotEmpty())
<section class="card"><h2>Mis opiniones públicas</h2>@foreach($myReviews as $myReview)<div><p>{{ $myReview->name }} · {{ $myReview->rating }} / 5 estrellas</p><form method="POST" action="{{ route('mi-salud.reviews.withdraw',$myReview->slug) }}">@csrf @method('DELETE')<button type="submit" class="button">Retirar mi opinión</button></form></div>@endforeach</section>
@endif
<section class="welcome"><div><span class="eyebrow">TU ESPACIO PERSONAL</span><h2>Hola, {{ $account->name }}</h2><p>Bienvenido a Mi Salud. Un lugar para cuidar de ti.</p></div><span class="status-pill">{{ $account->email_verified_at ? 'Correo verificado' : 'Vista local de prueba' }}</span></section>
@include('mi-salud.attentions.summary')
@include('mi-salud.attentions.latest')
<section class="feature-grid" aria-label="Tu privacidad"><article class="card"><h2>Tu cuenta, independiente</h2><p>Puedes tener Mi Salud aunque todavía no te atiendas en una clínica de VITA.</p></article><article class="card"><h2>Tú tienes el control</h2><p>Ingresar tu documento no conecta ni comparte automáticamente fichas de otras clínicas.</p></article></section>
@elseif($page==='profile')
<section class="page-heading"><span class="eyebrow">MI CUENTA</span><h2>Mi perfil</h2><p>Administra tus datos personales de Mi Salud.</p></section>
<section class="profile-grid"><aside class="card"><h2>{{ $account->name }}</h2><p class="break">{{ $account->email }}</p><span class="status-pill">{{ $account->email_verified_at ? 'Correo verificado' : 'Vista local de prueba' }}</span><hr><h3>Documento declarado</h3><p>{{ $identification['display']??'Sin documento' }}</p><p class="help">{{ $identification['country']??'' }} · {{ $identification['type']??'' }}</p><p class="help">Identidad del titular no verificada. Estos datos no modifican registros clínicos de ninguna clínica.</p></aside>
<form class="card form-grid" method="POST" action="{{ route('mi-salud.profile') }}">@csrf @method('PUT')
<div class="field full"><label for="name">Nombre completo</label><input id="name" name="name" value="{{ old('name',$account->name) }}" maxlength="255" autocomplete="name" required></div>
<div class="field full"><label for="email">Correo de acceso</label><input id="email" type="email" value="{{ $account->email }}" disabled><p class="help">El correo de acceso se conserva en esta primera versión.</p></div>
@include('mi-salud.identity')
@include('mi-salud.profile-sharing')
<div class="field"><label for="password">Nueva contraseña (opcional)</label><div class="portal-password-control"><input id="password" name="password" type="password" autocomplete="new-password" minlength="12" maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password'])</div><p class="help">Al menos 12 caracteres, con letras y números.</p></div>
<div class="field"><label for="password-confirmation">Repetir nueva contraseña</label><div class="portal-password-control"><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password-confirmation'])</div></div>
<div class="field full"><label for="current-password">Confirma tu contraseña actual</label><div class="portal-password-control"><input id="current-password" name="current_password" type="password" autocomplete="current-password" required maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'current-password'])</div></div>
<div class="form-actions full"><a href="{{ route('mi-salud.panel') }}">Cancelar</a><button class="button" type="submit">Guardar cambios</button></div>
</form></section>
@else
<section class="auth-grid"><div class="auth-intro"><span class="eyebrow">VITA · MI SALUD</span><h1>Un espacio <br>para cuidar de ti.</h1><p>Tu cuenta personal, gratuita e independiente de las clínicas. Empieza con tus datos y administra tu perfil de forma segura.</p><div class="intro-note">Tu información clínica permanece separada en cada clínica. Registrarte no vincula fichas automáticamente.</div></div>
<div class="card auth-card">
@if($page==='forgot')
<h2>Recupera tu acceso</h2><p>Te enviaremos las instrucciones si existe una cuenta activa para ese correo.</p>
<form method="POST" action="{{ route('mi-salud.forgot') }}">@csrf<div class="field"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" autocomplete="email" required maxlength="255"></div><button class="button wide" type="submit">Solicitar enlace</button></form>
@elseif($page==='verify')
<h2>Verifica tu correo</h2><p>Abre el enlace que enviamos a tu correo para terminar de preparar tu cuenta. No se mostrará información clínica.</p><form method="POST" action="{{ route('mi-salud.verify.resend') }}">@csrf<button class="button wide" type="submit">Enviar otro enlace</button></form>
@if(\App\Http\Middleware\PortalAccess::localPreviewAllowed())
<div class="notice"><p>Est?s en desarrollo local. Puedes explorar tu panel sin enviar ni verificar correos.</p><form method="POST" action="{{ route('mi-salud.local-preview') }}">@csrf<button class="button wide" type="submit">Entrar al panel en local</button></form></div>
@endif
@elseif(in_array($page,['verify-token','reset']))
<h2>{{ $page==='reset'?'Elige una nueva contraseña':'Confirma tu correo' }}</h2><p>El enlace vence en 30 minutos y solo puede utilizarse una vez.</p>
<form method="POST" data-security-form>@csrf<input type="hidden" name="security_token" id="security-token" value="{{ $securityToken??'' }}">
@if($page==='reset')<div class="field"><label for="password">Nueva contraseña</label><div class="portal-password-control"><input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password'])</div><p class="help">Al menos 12 caracteres, con letras y números.</p></div><div class="field"><label for="password-confirmation">Repite tu contraseña</label><div class="portal-password-control"><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password-confirmation'])</div></div>@endif
<noscript><p>Activa JavaScript para procesar este enlace de forma segura.</p></noscript>
<button class="button wide" type="submit">{{ $page==='reset'?'Guardar nueva contraseña':'Verificar correo' }}</button></form>
<p><a href="{{ route('mi-salud.forgot') }}">Recuperar acceso</a> · <a href="{{ route('mi-salud.login') }}">Ingresar</a></p>
@endif
</div></section>
@endif
@endsection
