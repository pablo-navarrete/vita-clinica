> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Suscripciones locales de clientes

Acceso: SuperAdmin → Suscripciones. Una suscripción principal por `customer_id`, compartida por todas sus sedes.

Esta etapa administra contratos y registra pagos manuales verificados. No ejecuta cobros, no integra Flow, no envía avisos comerciales, no emite documentos SII ni impone restricciones al acceso clínico. No modifica `customers.payment_status`, `paid_at` ni `expires_at`. Esos campos anteriores no se convierten automáticamente en pagos.

## Fechas y condiciones

- El alta selecciona un precio publicado vigente de un plan activo. Los importes y días de prueba/gracia provienen de la base de datos.
- El día de inicio establece el vencimiento. Si se habilita prueba, el primer vencimiento corresponde al final de la prueba.
- Los períodos son `[inicio, fin)`: la fecha de fin es el comienzo del siguiente período.
- Se conserva el día original al ajustar meses cortos: 31 de enero → 28 de febrero → 31 de marzo. En contratos anuales se conserva también el mes y se contempla el 29 de febrero.
- No se admiten anticipos en el MVP. Cada registro manual corresponde al próximo período ya iniciado y exige importe exacto, referencia única y detalle de verificación. No admite pagos parciales ni prorrateos.
- Cambiar el catálogo no reescribe contratos existentes. SuperAdmin puede programar explícitamente otro precio vigente o cantidad de sedes para el siguiente período. El cambio se confirma al registrar ese pago y conserva el historial anterior. No se cambia todavía la periodicidad mensual/anual de contratos existentes.
- La cantidad contratada incluye la sede principal; no crea ni elimina sedes reales.
- La cancelación conserva los pagos y el período vigente; solo detiene futuras renovaciones locales. No equivale a cancelar una suscripción en Flow.

## Persistencia y garantías

Migración aditiva `2026_11_02_140000_create_customer_subscriptions.php`:

- `customer_subscriptions`: condiciones actuales, calendario y revisión para detectar formularios desactualizados.
- `subscription_payments`: pagos manuales con desglose histórico y referencia a la versión inmutable del plan.
- `subscription_changes`: cambio pendiente, aplicado o cancelado; índice único para un solo pendiente por contrato.
- `subscription_events`: trazabilidad de las acciones.

Los servicios requieren SuperAdmin global. Cada escritura bloquea la suscripción en MySQL y comprueba su revisión. El alta bloquea al cliente. Índices únicos protegen la suscripción por cliente y los pagos por período y referencia. Los pagos y eventos no admiten actualizaciones ni borrados mediante Eloquent o SQL. Las transacciones incluyen la actualización del período y la auditoría; un fallo revierte todo. No hay rutas para borrar pagos ni suscripciones.

No debe ejecutarse un rollback destructivo: la migración requiere una migración posterior revisada si se necesita cambiar esta estructura.

## Pendiente

### Cupos de sedes activas

Crear y reactivar sedes desde Clientes valida ahora `branch_quantity` de la suscripción actual. Se cuentan todas las sedes activas no eliminadas, incluida la principal; desactivar libera cupo. Un cambio pendiente todavía no amplía cupos. Las sedes existentes no se desactivan por reducir el contrato; si ya exceden el límite, se bloquean nuevas creaciones/reactivaciones hasta regularizarlo. Los clientes sin suscripción conservan el comportamiento anterior. Esta validación no bloquea acceso a datos clínicos por mora o cancelación. `BranchCapacityService` serializa altas y activaciones mediante bloqueo del cliente y la suscripción dentro de una transacción MySQL.

Integración sandbox de Flow, contratación y autorización de tarjetas por el cliente, cobros recurrentes ejecutados por Flow, webhooks verificados e idempotentes, conciliación, fallos/reintentos, reembolsos, notificaciones por correo, políticas comerciales de cambios de precio y migración de clientes existentes. También quedan pendientes promociones, excepciones, consumo real, cambios de periodicidad/prorrateos y una política explícita de restricciones comerciales. El módulo actual no afirma que un pago manual haya sido confirmado por una pasarela.

## Pruebas

`CustomerSubscriptionTest`: pantallas y DataTables, importes, un contrato por cliente, pagos repetidos, períodos cortos y bisiestos, prueba/gracia, cambios diferidos, cancelación, aislamiento de SuperAdmin, inmutabilidad SQL y rollback ante fallo de auditoría. Fixtures transaccionales sin borrar datos existentes.

`CustomerSubscriptionConcurrencyTest`: dos procesos PHP con conexiones MySQL independientes intentan la misma renovación; solo uno debe confirmar. Crea una base temporal copiando exclusivamente estructuras, y la elimina al finalizar. Requiere permisos de creación de bases para el usuario de pruebas.
