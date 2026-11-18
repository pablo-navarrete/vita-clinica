> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Modalidades y consulta comercial de suscripciones

Etapa informativa, sin nuevos cobros ni restricciones de acceso.

- `customer_subscriptions.payment_mode`: preferencia explícita `automatic` o `manual`. Nuevas altas por `SubscriptionService` usan automática si se omite. Esto no crea ni autoriza un acuerdo Flow.
- `contract_status`: nuevas altas pendientes de aceptación; registros anteriores `legacy_transition`. No se inventan consentimientos ni pagos. La cancelación contractual se consulta desde las fechas y banderas existentes.
- Un acuerdo Flow abierto tiene prioridad para mostrar la modalidad efectiva. Consultarlo no modifica el proveedor ni el registro local. Un acuerdo en preparación/revisión no se presenta como autorización operativa confirmada.
- Estado financiero derivado de fechas reales de inicio, prueba, período pagado y gracia. No equivale al estado contractual.
- Acceso comercial informado como sin restricción durante la transición. No reemplaza los permisos, estado administrativo, ni las medidas de seguridad existentes.

SuperAdmin consulta modalidad, contrato, finanzas, acceso, período pagado y próximo vencimiento en la tabla y detalle. La creación permite elegir la modalidad prevista. No se permite editar una modalidad existente mediante este formulario: una migración de cobro necesita conciliación y autorización en una etapa posterior.

La migración 2026_11_03_110000 es aditiva, con modalidad nula para registros anteriores; conserva períodos, auditoría, pagos, usuarios y datos clínicos. No crea suscripciones ficticias para clientes sin contrato: su asignación y revisión queda para el proceso posterior de transición.

Pendiente: aceptación contractual verificable, cortesías con vigencia y auditoría, transferencias pendientes de aprobación, recordatorios, cambios seguros de modalidad y política comercial de acceso. Se reutilizan las órdenes y acuerdos Flow actuales, sin otro motor de cargos.
