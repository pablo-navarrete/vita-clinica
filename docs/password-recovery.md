# Recuperación de contraseña

El enlace «Olvidé mi contraseña» del login solicita el correo de acceso. Laravel genera un token de un solo uso, válido por 60 minutos, y envía una notificación en español. La solicitud y el restablecimiento se limitan a la clínica identificada por el subdominio; en el dominio principal se limitan a cuentas globales. La identidad de los tokens incluye la clínica para evitar colisiones entre correos iguales de diferentes tenants.

Los enlaces emitidos antes del cambio de identidad de tokens dejan de ser válidos: se debe solicitar uno nuevo.

## Correo saliente de la plataforma

Se utiliza `config/mail.php`, independiente del correo configurado por cada clínica. Configurar en el servidor:

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.tu-proveedor.cl
MAIL_PORT=587
MAIL_USERNAME=usuario-del-servicio
MAIL_PASSWORD=credencial-del-servicio
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=acceso@tu-dominio.cl
MAIL_FROM_NAME="VITA"
```

Los valores de puerto y cifrado deben coincidir con el proveedor. Guardar las credenciales únicamente en `.env` o en el gestor de secretos del servidor. Tras cambiar la configuración, ejecutar `php artisan config:cache`.

En desarrollo, `MAIL_HOST=mailpit` captura el mensaje localmente: no entrega correo real. Para la prueba manual, abrir Mailpit, solicitar el enlace desde el subdominio de la clínica y abrir el mensaje recibido. Tras establecer una contraseña nueva, comprobar que el enlace ya no sirve. La entrega a una bandeja externa debe comprobarse después de configurar un proveedor real.

La respuesta al solicitar el enlace es genérica para correos existentes o inexistentes. Laravel limita las solicitudes de token para una misma cuenta mediante el intervalo del broker (60 segundos).

Prueba de integración: `php artisan test --filter=TenantPasswordRecoveryTest`. Usa notificaciones simuladas y revierte las escrituras de la prueba.
