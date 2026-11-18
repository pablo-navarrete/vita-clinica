<p class="login-caption">TU CUENTA PERSONAL</p><h2 id="login-title">Crea tu cuenta</h2><p class="login-description">No necesitas pertenecer a ninguna clínica.</p>
<form method="POST" action="{{ route('mi-salud.register') }}" class="form-grid">@csrf
<div class="field full"><label for="name">Nombre completo</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="255"></div>
<div class="field full"><label for="email">Correo electrónico</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required maxlength="255"></div>
@include('mi-salud.identity')
<div class="field"><label for="password">Contraseña</label><div class="portal-password-control"><input id="password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password'])</div><p class="help">Al menos 12 caracteres, con letras y números.</p></div>
<div class="field"><label for="password-confirmation">Repite tu contraseña</label><div class="portal-password-control"><input id="password-confirmation" name="password_confirmation" type="password" autocomplete="new-password" required maxlength="1024">@include('mi-salud.password-toggle',['controlId'=>'password-confirmation'])</div></div>
<button class="button full" type="submit">Crear mi cuenta gratuita</button></form>
<p class="auth-bottom">¿Ya tienes cuenta? <a href="{{ route('mi-salud.login') }}">Ingresar</a></p>
