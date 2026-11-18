> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Mi suscripción de la clínica

Módulo de cliente: **Mi cuenta → Mi suscripción**. Una suscripción principal por clínica, compartida por sus sedes. No reemplaza la configuración de la cuenta bancaria receptora en Flow.

## Modalidades

- Pago manual inicial: la clínica confirma el importe y solicita una orden `payment/create`, que se paga en Flow. Se reutiliza una orden pendiente para ese período. Un rechazo o anulación solo permite otro intento después de verificar el estado terminal en la API.
- Tarjeta: el administrador confirma su contraseña y consentimiento antes de abrir `customer/register`. Flow recoge la tarjeta, y VITA verifica el retorno con `customer/getRegisterStatus`. VITA conserva marca/últimos cuatro dígitos; los tokens y URLs transitorias quedan cifrados. No guarda PAN completo ni CVV.
- Renovación automática: requiere tarjeta verificada y una autorización expresa del importe, frecuencia, sedes y fecha. Crea un plan Flow específico con el importe contratado y una suscripción Flow. Flow ejecuta los cargos; el scheduler de VITA **no** usa `customer/collect`, `charge` ni reintenta cargos.
- Desactivar cargos automáticos: elimina la tarjeta con `customer/unRegister` y confirma que Flow ya no la tenga registrada. Conserva la suscripción remota para que Flow genere importes pagables manualmente y sus avisos. El botón de pago usa `invoice.paymentLink`, no crea una segunda orden. Registrar una tarjeta nuevamente en ese contrato puede reactivar los cargos; el consentimiento de registro lo advierte expresamente.
- Baja del servicio: confirma la contraseña y aceptación. Detiene la suscripción remota de inmediato para no generar nuevos cargos, pero conserva independientemente en VITA el período ya pagado hasta su término. No elimina información clínica ni activa bloqueos de acceso clínico. Pagos en tránsito o posteriores a la baja pueden requerir conciliación. No implementa reembolso automático.

## Reglas del MVP

El período que ya comenzó es pagable; no se agregaron anticipos ni prorrateos al calendario existente. Para activar renovación automática no debe haber períodos atrasados, órdenes manuales abiertas ni cambios de plan pendientes. Un precio gratuito no genera una orden de cobro: requiere el flujo administrativo correspondiente.

Mientras hay una orden Flow abierta o contrato Flow vigente, las operaciones locales de SuperAdmin no pueden registrar otro pago, cancelar solo localmente ni programar un cambio de condiciones incompatible. Los cambios comerciales de contratos ya integrados requieren una etapa específica de sincronización con Flow. No se modifican precios de suscripciones existentes automáticamente.

Volver a contratar después de una baja queda sujeto a gestión administrativa; no hay todavía interfaz de recontratación ni migración automática de saldos o cuentas Flow. Si cambian las claves, cuenta receptora o ambiente, los acuerdos existentes requieren conciliación/migración, no se recrean silenciosamente.

## Configuración y callbacks

Claves sandbox previamente configuradas, más:

```dotenv
FLOW_PUBLIC_URL=https://direccion-publica-del-servidor-o-tunel
FLOW_PRODUCTION_PAYMENTS=false
```

`FLOW_PUBLIC_URL` debe apuntar a la raíz de esta instalación Laravel, ser HTTPS y permitir que Flow acceda a `/api/billing/flow/*`. El dominio local `.test` no es accesible desde Flow. No se configura un túnel automáticamente ni se exponen datos del servidor sin una URL acordada. Los navegadores retornan al subdominio de la clínica obtenido de la base de datos.

Endpoints públicos sin sesión/CSRF (solo estos callbacks API):

- POST `/api/billing/flow/payment`: consulta el token en Flow y confirma pagos manuales.
- POST `/api/billing/flow/payment-return`: misma verificación, luego retorno a la clínica.
- POST `/api/billing/flow/card-return`: verifica el token asociado a un registro existente y el cliente Flow correcto.
- POST `/api/billing/flow/recurring/{agreement}`: consulta el pago en Flow, obtiene la suscripción e invoice y comprueba su asociación antes de acreditar.

El cuerpo del webhook no se considera evidencia del pago: se consulta la API autenticada con HMAC SHA-256. Los importes de producción requieren `FLOW_ENVIRONMENT=production`, claves propias y habilitación expresa `FLOW_PRODUCTION_PAYMENTS=true`. Un despliegue Laravel `production` no permite acreditar pagos de sandbox. No se han ejecutado cargos reales como parte de la implementación.

Los flujos que abren Flow usan otra pestaña para conservar la navegación y videollamada de VITA. Al volver, se puede actualizar el estado; el retorno del navegador por sí solo no acredita pagos.

## Persistencia y seguridad

Migraciones aditivas:

- `2026_11_02_170000_create_clinic_billing_portal.php`: clientes Flow por clínica/cuenta/ambiente, registros de tarjeta, acuerdos autorizados, órdenes y recibos confirmados.
- `2026_11_02_171000_protect_checkout_attempts_and_consents.php`: intentos de pago, exclusión de órdenes/acuerdos abiertos y triggers que protegen condiciones y consentimientos históricos.

Se conservan los estados y pagos anteriores. Los recibos son inmutables también en SQL; condiciones, autor, consentimiento y cotización de las órdenes/acuerdos no se pueden sobrescribir ni eliminar. Los servicios bloquean la suscripción en MySQL, usan revisión para formularios desactualizados y locks de caché para serializar acciones. Se recomienda caché compartida con locks (Redis) para múltiples servidores.

Solo un administrador activo de la sede actual perteneciente a la clínica puede operar el portal. Ser administrador en otra sede no basta. El SuperAdmin global no autoriza tarjetas ni cargos en representación de la clínica; conserva su panel administrativo de consulta.

La acreditación comprueba cuenta y ambiente, identidad de orden o suscripción/invoice, moneda e importe exactos, calendario autorizado y versión comercial. El pago, recibo, evento de auditoría y actualización del período se confirman en una sola transacción. Repetir una notificación devuelve el resultado ya registrado sin ampliar otra vez el período. Un error revierte la actualización.

En renovaciones, VITA conserva su fecha de fin exclusiva y acepta que Flow represente el último día inclusivo. Cualquier otra diferencia de calendario queda para revisión, sin extender el período silenciosamente.

Si se pierde la respuesta de una creación, no se repite automáticamente el POST. Una orden se recupera mediante `payment/getStatusByCommerceId`; una suscripción puede recuperarse consultando las suscripciones del cliente y cotejando el plan específico. Un cliente Flow o plan sin identidad confirmada puede necesitar intervención administrativa. Una creación incompleta no equivale a un pago ni autoriza recrear cargos.

## Conciliación

`php artisan flow:reconcile-subscriptions` consulta cada cinco minutos con el scheduler las órdenes pendientes y contratos abiertos. Solo hace GET a Flow, verifica pagos y registra avisos de revisión en Comisiones de pago ante discrepancias o errores. El correo de esos avisos utiliza el mecanismo ya configurado de `FLOW_ALERT_EMAIL`.

No reemplaza una conciliación contable de liquidaciones, contracargos o reembolsos. Los errores de pago no amplían el período; la página muestra el estado derivado de vencimiento/gracia. Los avisos comerciales completos a clínicas, SII y restricciones de acceso por falta de pago siguen fuera de esta etapa.

## Validación

`ClinicBillingPortalTest`: permisos de sede, consentimiento y contraseña, órdenes pendientes, recuperación tras timeout, datos bancarios minimizados, firma de callbacks vía consulta autenticada, importe/moneda/cliente/calendario, reintentos, contratos automáticos y paso a pago manual, no duplicar cobros y rollback.

`CustomerSubscriptionConcurrencyTest`: además de pagos manuales y cupos, dos procesos con conexiones MySQL independientes confirman el mismo recibo Flow y solo se aplica un período.

Las llamadas del proveedor en las pruebas usan `Http::fake`; no demuestran una inscripción bancaria real. La prueba de extremo a extremo en sandbox queda pendiente de la URL pública y de la interacción del titular en Flow.
