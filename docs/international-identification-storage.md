# Identificación internacional: etapa 2

Estado: código y esquema preparados para revisión. La migración se ha ensayado exclusivamente en bases MySQL aisladas, según el informe `international-identification-validation.md`. No se ha aplicado en la base habitual ni escrito identificadores históricos reales. Los formularios, controladores y campos `rut` siguen funcionando como antes.

## Esquema propuesto

La migración `2026_11_05_000300_create_international_identifier_storage.php` crea tres tablas InnoDB en MySQL 8 (requiere CHECK efectivamente soportado, 8.0.16 o superior):

| Tabla | Titular y ámbito | Reserva documental |
| --- | --- | --- |
| customer_identifiers | customer_id | Por cliente; el mismo titular tributario puede tener múltiples clientes. |
| user_identifiers | user_id, customer_id nullable | Por usuario. No impone una nueva identidad única entre cuentas. |
| patient_identifiers | patient_id, customer_id obligatorio | Por clínica, incluso entre pacientes diferentes. |

Actualmente un usuario tiene un `customer_id`, puede pertenecer a varias sedes y los usuarios globales tienen clínica nula. No se ha introducido una pertenencia nueva entre clínicas ni unicidad tributaria entre cuentas existentes.

Campos comunes: UUID, ámbito, país emisor, tipo, namespace, clase de titular, versión de normalización, estado/nivel de validación, procedencia, referencia/clasificación histórica, carga cifrada, versiones de claves, HMAC de búsqueda y reserva, principal, retiro, actores, revisión y fechas. El original, normalizado, presentación y eventos de auditoría están dentro de la carga cifrada. No se crean columnas de número legible.

Restricciones propuestas:

- Índice único de reserva por ámbito, versión HMAC y claim_hash. Para pacientes el ámbito es toda la clínica; para usuarios y clientes es el titular individual.
- Un documento retirado conserva su reserva. No se reasigna automáticamente a otro paciente ni se elimina; una eventual corrección administrativa exige un diseño explícito posterior.
- Como máximo un principal activo por titular mediante columna generada e índice único; se permite no tener principal.
- Registros históricos `legacy_unclassified` o `legacy_conflict` no pueden ser principales ni reservar identidad. Exigen procedencia histórica y validación `legacy_unverified`; pueden coexistir sin fusionarse.
- Registros utilizables exigen metadatos completos, validación básica o checksum y HMAC de reserva igual al de búsqueda.
- Usuarios/pacientes solo admiten persona natural; clientes admiten natural o jurídica.
- Once triggers protegen identidad/procedencia inmutables, coherencia de clínica, revisiones, retiro y prohibición de borrado. Dos de ellos impiden mover de clínica usuarios/pacientes que ya tengan identificadores.
- Claves foráneas RESTRICT: tampoco se podrá borrar físicamente un titular o actor referenciado. Revisar esta política antes de activar escrituras.
- `down()` solo permite retirar tablas vacías. Con cualquier registro, incluso retirado o en conflicto, rechaza la reversión; se debe desactivar la integración y conservar los datos.

La migración es aditiva pero el DDL de MySQL no es transaccional: debe respaldarse antes de su aplicación. Se validó ejecución, restricciones, índices y triggers en MySQL 8.0.30 aislado; sigue pendiente su aprobación para la base habitual.

## Seguridad y acceso

`IdentifierCrypto` utiliza AES-256-GCM, nonce aleatorio y etiqueta de autenticación; autentica también titular, ámbito, metadatos, revisión, principal, retiro y HMAC. El HMAC-SHA256 usa claves independientes y un contexto que separa tenants y clases de titular. No hay búsquedas LIKE ni normalizados legibles nuevos.

Las claves se configuran en `config/identification.php`, sin fallback a APP_KEY. Cada clave es aleatoria, de 32 bytes, con prefijo `base64:`. Se rechaza reutilizar APP_KEY como AES o HMAC, además de reutilizar AES como HMAC. Versiones v1/v2 permiten lectura histórica y rotación. No se han generado claves ni modificado `.env`. Guardarlas mediante secretos del servidor y respaldar las versiones necesarias para descifrar documentos.

El repositorio exige actor, titular y clínica; vuelve a consultar pertenencia y permisos. SuperAdmin puede administrar identificadores de clientes/usuarios, pero este repositorio no le concede acceso a pacientes. Las operaciones de escritura bloquean la clínica (o usuario global), se ejecutan en transacción y comprueban revisiones. Es un bloqueo conservador que serializa escrituras de identificadores de una clínica, no operaciones clínicas actuales.

La rotación es una primitiva explícita sin comando automático: detener escritores antiguos, conservar claves anteriores, rotar todo el ámbito transaccionalmente y habilitar únicamente escritores nuevos. No mezclar versiones de escritura. Para pacientes se rota toda la clínica; un administrador limitado a una sede no puede autorizar por sí solo todos los pacientes. Los índices versionados por sí solos no evitan duplicados entre distintas claves; el bloqueo, comprobación de versiones y esta disciplina operativa son necesarios.

La auditoría cifrada conserva los eventos de creación, principal, retiro y rotación; no es un registro externo a prueba de un administrador de base de datos. La validación de un documento no verifica la identidad de su titular. Las excepciones públicas no incluyen números. El acceso explícito `read()` sí entrega el documento al llamador autorizado: nunca registrar su respuesta en logs.

## Diagnóstico histórico

`php artisan identification:diagnose` ejecuta lecturas consistentes en una transacción MySQL READ ONLY. No necesita las tablas nuevas ni claves persistentes, no crea archivos y no aplica cambios. Agrupa candidatos mediante HMAC efímero y devuelve únicamente conteos. Es idempotente para el mismo conjunto de datos.

Reconoce vacíos, valores inseguros, no clasificables, candidatos RUT con checksum válido/inválido, formatos equivalentes y duplicados candidatos. Un aspecto de RUT o checksum válido **no confirma país ni tipo documental**. Duplicados de clientes/usuarios son informativos; los candidatos válidos repetidos dentro de una clínica se señalan como conflictos potenciales de pacientes.

Resultado observado en la base local el 05-11-2026:

| Fuente | Filas | Candidato RUT checksum válido | Candidato RUT inválido | Vacío | Grupos duplicados |
| --- | ---: | ---: | ---: | ---: | ---: |
| customers | 1 | 1 | 0 | 0 | 0 |
| users | 2 | 0 | 1 | 1 | 0 |
| patients | 0 | 0 | 0 | 0 | 0 |

No se corrige ni se clasifica definitivamente el documento del usuario por ese resultado. No se ha construido ni ejecutado un importador histórico: antes de ello deben revisarse país, tipo, conflictos y reglas de resolución. El informe público de conteos no expone referencias individuales; una futura revisión de filas requiere una herramienta autorizada y protegida.

## Verificación y pendientes

Ejecutado: suite unitaria `tests/Unit/Identification`, 108 pruebas y 2.042 assertions, incluida la etapa 1. Cubre módulo 11, documentos extranjeros, ceros, normalización, cifrado aleatorio, manipulación, HMAC por ámbito, separación/rotación de claves y diagnóstico sin exposición de documentos.

Ejecutado posteriormente en MySQL 8.0.30 aislado: `tests/Integration/Identification/IdentifierStorageTest.php` y worker paralelo. Cubre almacenamiento, principales, retiro, reserva de pacientes, aislamiento, rotación, protección del titular, reversión no destructiva y dos procesos concurrentes MySQL. Está fuera de las suites predeterminadas, exige autorización explícita mediante variable y una base `vita_identification_test_*` previamente migrada. Nunca aplica migraciones por sí mismo. No habilitarlo contra la base habitual. Las claves deterministas en tests/Support son exclusivamente de prueba. Consultar el informe de validación para resultados finales.

Pendiente antes de activar: aprobación definitiva del esquema; revisión de expectativas del test general de esquema que contabiliza migraciones y triggers; revisión operativa de claves y recuperación de respaldos. La reserva tras retiro ya fue aceptada y las pruebas técnicas de esquema, concurrencia, fallos parciales y conflictos históricos sintéticos se ejecutaron en aislamiento.

## Resolución administrativa futura

Un retiro no libera identidad. Si un documento fue asignado al titular incorrecto, no se resolverá borrando filas ni eludiendo triggers. Una etapa posterior deberá introducir eventos de resolución inmutables que enlacen el registro incorrecto, el titular correcto, actor autorizado, motivo, revisión y evidencia; separar explícitamente la reserva vigente de la evidencia histórica mediante una migración aditiva revisada. La transferencia de una reserva necesitará bloquear todo el ámbito y aplicar comprobaciones de conflicto y auditoría en una sola transacción. Hasta implementar y aprobar ese procedimiento, la reasignación queda bloqueada. No existe ahora ninguna interfaz o comando para efectuarla.

No hay rutas nuevas, modelos Eloquent serializables ni integración en flujos actuales. Se utilizan objetos de ámbito y repositorio explícito para evitar exposición accidental de cargas o hashes. No se modifican documentos clínicos, firmas, reservas, exportaciones ni referencias existentes.
