> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Flow: consulta de comisiones y avisos

Esta integración consulta la API real `GET /payment/getTransactions`, con HMAC SHA-256 y HTTPS. No crea pagos, no registra tarjetas ni contrata suscripciones. No sustituye todavía la integración de cobros recurrentes. No hay claves precargadas ni conexión activa por defecto.

## Activación sandbox

1. Crear una cuenta independiente en https://sandbox.flow.cl/ y obtener API Key y Secret Key en la cuenta Flow.
2. Configurar únicamente en el servidor:

```dotenv
FLOW_ENABLED=true
FLOW_ENVIRONMENT=sandbox
FLOW_API_KEY=clave_de_la_cuenta_sandbox
FLOW_SECRET_KEY=secreto_de_la_cuenta_sandbox
FLOW_ALERT_EMAIL=correo_financiero_del_administrador
```

3. Ejecutar `php artisan config:clear` (o reconstruir la caché de configuración al desplegar).
4. SuperAdmin → Comisiones de pago → Consultar Flow por fecha. Deben existir pagos de prueba en esa cuenta para obtener observaciones.
5. Para datos de producción usar `FLOW_ENVIRONMENT=production` y credenciales de producción. Los registros se aíslan por ambiente y huella de la API Key. Nunca presentar los datos de sandbox como pagos reales.

No introducir claves en chat, vistas o repositorio. Los errores no imprimen URLs firmadas ni respuestas de error del proveedor. Cualquier observabilidad HTTP externa debe redactar query strings con credenciales. No se almacena el cuerpo completo de Flow, correos de pagadores, tarjetas, claves, firmas de petición ni tokens.

## Datos y conciliación

La migración aditiva `2026_11_02_160000_create_flow_fee_monitoring.php` crea:

- `flow_payment_observations`: observaciones inmutables de pagos exitosos CLP: orden Flow, referencia comercial, fecha, medio, importe, `paymentData.fee`, `paymentData.balance` y abono previsto.
- `payment_fee_alerts`: avisos de revisión, configuración relacionada, evidencia numérica, envío y reconocimiento por SuperAdmin.

Se rechazan respuestas incompletas e importes no admitidos. Solo se consultan datos autenticados de la cuenta configurada. No se asignan automáticamente a una clínica por correo, referencia o importe y no se modifican `subscription_payments` ni períodos pagados. Esa vinculación requerirá órdenes y suscripciones Flow identificadas desde VITA en la etapa de contratación.

La comisión informada y el descuento calculado como importe menos saldo son columnas distintas. No se deduce una tasa de IVA desde `fee` ni se agrega IVA otra vez al dato del proveedor. El saldo no prueba que el banco haya recibido el depósito; falta la conciliación de liquidaciones. Cada cambio en los datos de una orden genera una observación nueva; repetir los mismos datos conserva una sola observación mediante huella única. Se conservan versiones anteriores.

## Alertas

Se compara el descuento observado con la estimación que estaba vigente **en la fecha del pago**. Diferencias superiores a un peso generan un aviso de revisión. La diferencia puede deberse a medio de pago, cuotas, costo fijo, cambios de tarifa/IVA, redondeos u otros ajustes: no demuestra por sí sola que cambió el porcentaje contratado.

Los avisos se agrupan por cuenta/ambiente, tarifa configurada, medio y banda del descuento efectivo (dos decimales porcentuales) para evitar un correo por cada pago. Una banda distinta permite otro aviso. Marcar un aviso revisado no actualiza precios ni tarifas. Actualizar en VITA una tarifa, costo fijo o IVA también crea un aviso independiente con la nueva vigencia; publicar no recalcula contratos.

La API revisada no ofrece un aviso de futura modificación de tarifa contractual ni un detector legal de cambios de IVA. Confirmar esas condiciones con Flow antes de actualizar la configuración. Este monitor detecta diferencias después de observar pagos; no promete aviso anticipado ni identifica automáticamente la causa. Los precios y márgenes requieren decisión del administrador.

## Automatización

- `php artisan flow:sync-fees --date=YYYY-MM-DD`: consulta todas las páginas del día (hasta 100 páginas de 100 pagos; si excede el límite informa fallo).
- `php artisan flow:sync-fees`: revisa ayer y hoy, según America/Santiago. Reconsultar fechas anteriores manualmente si hay ajustes tardíos.
- `php artisan flow:send-fee-alerts`: envía avisos no revisados ni enviados a `FLOW_ALERT_EMAIL`. Requiere correo Laravel configurado. Sin destinatario no se envían correos; los avisos siguen en el panel.

Ambos comandos quedan programados cada hora sin solapamiento. El servidor necesita el cron habitual `php artisan schedule:run` cada minuto. La consulta diaria usa bloqueo de caché; en múltiples servidores utilizar caché compartida con soporte de locks (por ejemplo Redis). Estos comandos jamás generan cargos. Para días grandes usar el comando, evitando límites de tiempo de peticiones web.

El correo se marca enviado después de aceptación del transporte. Si el proceso cae entre envío y confirmación podría repetirse un aviso; no se promete entrega exactamente una vez. Un error de red o de paginación deja la sincronización sin confirmar, conserva las observaciones ya verificadas y permite reintentar sin duplicarlas.

## Pruebas y límites

`FlowFeeMonitorTest` usa `Http::fake` y `Notification::fake`: firma HMAC, método GET, idempotencia, rechazo de datos faltantes/monedas, separación de cuentas y ambientes, paginación, avisos, correo y errores sin secretos. No son pruebas contra una cuenta Flow real.

Fuentes oficiales revisadas: https://developers.flow.cl/api (autenticación, `payment/getTransactions`, `paymentData.fee`, `paymentData.balance`), https://web.flow.cl/es-cl/tarifas .
