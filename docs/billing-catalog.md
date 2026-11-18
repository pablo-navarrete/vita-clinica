> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Catálogo comercial de VITA — etapa 1

## Edición simplificada

La interfaz principal ahora ofrece Crear, Ver, Editar y Eliminar plan. Crear/Editar incluye presentación y condiciones comerciales en el mismo formulario; guardar confirma automáticamente las condiciones en una operación atómica. El versionado se conserva internamente, sin botones para crear o copiar borradores. El detalle presenta condiciones actuales y un historial desplegable.

`PlanEditingService` combina los servicios existentes y comprueba la revisión publicada antes de guardar, evitando sobrescribir cambios concurrentes de otro administrador. Las preparaciones internas reutilizan un solo borrador. Los borradores históricos que ya existieran no se eliminan, pero no aparecen como opciones de trabajo en la pantalla principal. Los endpoints anteriores se conservan por compatibilidad y la protección SQL de versiones publicadas sigue vigente.

La regla comercial solicitada es aplicar futuros cambios en la siguiente renovación elegible de cada clínica con aviso previo, preservando su período pagado. Todavía no se ejecuta: faltan las asignaciones de suscripción por clínica, períodos verificados, programación de cambios, excepciones, correos y sincronización Flow. No se simulan avisos enviados ni cambios de precio externo. La presente etapa solo simplifica el catálogo, sin cambiar acceso ni datos de clientes.

## Alcance y acceso

SuperAdmin → Gestión comercial → Planes (`/billing/plans`, dominio principal).
Se reutiliza `x-app-layout`, la navegación persistente, los formularios y los toast existentes.
Solo usuarios sin tenant y con el rol global Super Administrador pueden acceder; los servicios también verifican esta autorización. Se admite status=1 o null (cuentas globales históricas); status=0 se rechaza sin modificar la cuenta.

El catálogo no cobra, no sincroniza Flow, no crea suscripciones, no mide consumo y no restringe módulos clínicos.
No se modifican los campos manuales de pago de `customers`. No hay planes ni precios de ejemplo en producción.

## Datos

Migración aditiva: `2026_11_02_120000_create_billing_catalog.php`.

- `billing_plans`: identidad, presentación, orden y disponibilidad.
- `billing_plan_versions`: borradores/publicaciones numeradas, condiciones, sedes incluidas, prueba/gracia, snapshot y SHA-256.
- `billing_plan_prices`: precios finales CLP, mensual/anual independientes y adicional por sede del mismo período.
- `billing_plan_limits`: usuarios/profesionales activos únicos por clínica o sede; sedes por clínica. Nulo significa ilimitado y cero es una cantidad explícita. Un registro ausente es incompleto, nunca ilimitado implícitamente.
- `billing_features`: registro técnico de capacidades existentes; su contenido inicial no constituye ofertas comerciales.
- `billing_plan_features`: capacidades incluidas en cada versión.
- `billing_plan_quotas`: tokens de entrada/salida de IA y bytes de almacenamiento ocupado. Unidad determinada por la métrica; valores, período y política comercial en la BD. Cuotas opcionales, sin aplicación durante esta etapa.
- `billing_catalog_events`: auditoría append-only con actor y detalles antes/después o definición.

`BillingCapabilities` contiene claves técnicas y etiquetas reconocidas, NO precios, cuotas, días o límites comerciales. Incorporar una capacidad nueva requiere una implementación técnica revisada y una migración de su registro, no agregar un nombre desde el panel.

## Flujo

1. Crear un plan con código estable y presentación.
2. Crear un borrador vacío o copiar cualquier versión del mismo plan.
3. Completar condiciones: al menos una periodicidad habilitada, todos los límites, sedes, prueba, gracia y texto comercial.
4. Guardar y revisar. Un borrador vacío puede existir, pero guardar su definición requiere coherencia y publicar exige validarla nuevamente.
5. Aceptar la confirmación y publicar.
6. Los siguientes cambios comerciales se hacen copiando a un nuevo borrador.

La disponibilidad se edita en el plan. Retirarlo no elimina versiones. La presentación pública puede cambiar, pero su nombre/descripción también quedan congelados dentro del snapshot publicado. No se cambia ni se migra ningún contrato automáticamente.

## Integridad

PlanVersionService bloquea plan y versión en transacciones. Guardar condiciones reemplaza sus relaciones solo mientras es borrador. La publicación serializa una estructura estable, calcula SHA-256 y registra auditoría en la misma transacción. La lectura publicada usa el snapshot y verifica su hash.

Los modelos rechazan cambios de versiones publicadas. Los triggers MySQL bloquean UPDATE/DELETE de versiones publicadas y INSERT/UPDATE/DELETE de sus precios, límites, funcionalidades y cuotas; los escritores hijos bloquean la versión padre para serializarse con la publicación. No se permite mover condiciones entre versiones. FKs restrict evitan cascadas que eludan los triggers. Auditoría protegida contra UPDATE/DELETE.

La cuenta SQL de la aplicación no debe disponer en producción de permisos para TRUNCATE, ALTER, DROP ni eliminar triggers: un administrador de BD siempre puede cambiar el esquema. El hash no es una firma electrónica.

La migración no ofrece rollback automático destructivo: una reversión exige una migración posterior revisada para preservar historial.

## Extensión posterior

Promociones y excepciones por clínica serán documentos versionados independientes. Un contrato resolverá versión pública + ajustes autorizados + descuento y conservará el desglose aceptado. No se introducen descuentos ni precios por clínica como columnas improvisadas en `customers`.

Suscripciones, permisos efectivos, consumo, reservas, contratos, facturación tributaria y Flow quedan para etapas posteriores. Las políticas de cuota configuradas son contractuales y todavía no tienen efectos. Las exportaciones clínicas autorizadas deberán conservar su excepción de recuperación en cualquier restricción futura.

La sincronización deberá verificar que el total efectivo y la representación en Flow coinciden; una publicación local por sí sola no autoriza cobros ni garantiza sincronización.

## Verificación

`php artisan test --filter='BillingCatalogTest|BillingCatalogConcurrencyTest'`

Pruebas funcionales con datos transaccionales y rollback, sin RefreshDatabase. Cubren autorización, HTTP/vistas, precios independientes, límites nulos/cero, catálogo reconocido, validación, copia/publicación, SQL directo, fallo de auditoría, conservación de clínicas y desactivación.

La prueba concurrente crea una BD MySQL temporal de nombre generado, copia solamente estructura (ningún dato real), instala triggers del catálogo y ejecuta dos procesos independientes: doble publicación y publicación frente a cambio de precio. Comprueba una publicación y coincidencia entre snapshot y filas. Solo elimina esa BD temporal.

Para ejecutar estas pruebas se requiere la migración aplicada y una conexión MySQL; la prueba concurrente necesita permisos para crear/eliminar su BD temporal.
