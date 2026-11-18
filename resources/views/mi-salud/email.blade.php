<!doctype html><html lang="es"><meta charset="utf-8"><body>
<h1>Mi Salud · VITA</h1><p>{{ $purpose==='verify'?'Confirma tu correo para completar tu cuenta personal.':'Solicitaste recuperar el acceso a tu cuenta personal.' }}</p>
<p><a href="{{ $securityUrl }}">{{ $purpose==='verify'?'Verificar mi correo':'Restablecer mi contraseña' }}</a></p>
<p>Este enlace vence en 30 minutos y solo puede usarse una vez. Si no hiciste esta solicitud, puedes ignorar este mensaje.</p>
<p>No compartas el enlace. Este correo no contiene información clínica.</p></body></html>
