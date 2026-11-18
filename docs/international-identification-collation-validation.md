# Validación aislada con la configuración real de desarrollo

Fecha: 05-11-2026. Resultado: **apto técnicamente para solicitar la aplicación controlada en desarrollo**. No se aplicó en `vita_clinica`, no se modificó `.env` y no se inició la etapa 3.

## Entorno reproducido

Base nueva: `vita_identification_test_20261105_232512_7911ee`.

| Configuración | Desarrollo y base aislada |
| --- | --- |
| MySQL | 8.0.30, mismo servidor local |
| Charset de base | utf8mb4 |
| Collation predeterminada de base | utf8mb4_0900_ai_ci |
| Charset cliente/conexión/resultados | utf8mb4 |
| Collation de conexión Laravel | utf8mb4_unicode_ci |
| Motor | InnoDB |
| Aislamiento | REPEATABLE-READ |
| Zona horaria de sesión | SYSTEM |
| lower_case_table_names | 1 |
| foreign_key_checks / unique_checks | 1 / 1 |
| explicit_defaults_for_timestamp | 1 |

SQL_MODE coincide exactamente: `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.

La collation de conexión es distinta de la predeterminada de base **en ambos entornos**, como ya está configurado VITA. No se cambió ninguna de ellas. Las definiciones efectivas de las tablas también coinciden; no se forzó a las tablas una collation diferente.

## Comparación de esquema

Se reconstruyeron las migraciones base en aislamiento. Antes de aplicar identificación se comparó:

- Definición completa de todas las tablas, incluyendo índices, FK y CHECK, ignorando únicamente contadores AUTO_INCREMENT variables.
- Los 53 triggers existentes: cuerpo, tabla, evento, momento, SQL_MODE, definidor, charset, collation de conexión y `Database Collation`. Solo se excluyó su fecha de creación, que necesariamente cambia al reconstruirlos.

Ambas comparaciones coincidieron. La diferencia que había detenido la aplicación anterior quedó resuelta **en el entorno de pruebas**, manteniendo intacta la collation habitual.

Se aplicó exclusivamente en la base aislada `2026_11_05_000300_create_international_identifier_storage.php`. Se verificaron:

- Tres tablas nuevas, tres PK y doce índices únicos adicionales.
- 14 FK con DELETE RESTRICT; ninguna eliminación en cascada de identificadores.
- 15 CHECK y 11 triggers nuevos.
- Los 53 triggers anteriores conservan sus metadatos.
- Consulta adicional de solo lectura: los 11 triggers nuevos tienen `Database Collation = utf8mb4_0900_ai_ci`.
- `EXPLAIN`: búsqueda exacta usa `ident_patient_lookup`, acceso `ref`, `Using index condition`. La estimación del optimizador es una fila; no es una medición de carga masiva.
- `scope_key`, país y hashes de búsqueda/reserva usan `ascii_bin` en las tres tablas.

## Pruebas reales

**122 pruebas aprobadas, 2.107 assertions, sin fallos ni omisiones**: 109 unitarias y 13 de integración con MySQL real. PHPUnit tardó 2,454 segundos y utilizó 38 MB; no incluye reconstruir las migraciones.

Se repitieron unicidad por clínica, RUT equivalentes, documentos retirados, múltiples documentos/principal, aislamiento, conflictos históricos sintéticos, inmutabilidad, FK, prohibición de borrado, cifrado, HMAC, claves inválidas, rotación y rollback de fallos parciales.

La concurrencia utiliza dos procesos PHP independientes y comprueba en `performance_schema.data_lock_waits` que ambos estén esperando simultáneamente el bloqueo de la clínica. Al liberarlo, se obtiene exactamente una creación, un duplicado rechazado y una fila reservada.

La prueba adicional de collation almacena por separado `001Ab-é`, `001ab-é`, `001Ab-e` y `1Ab-é`. Cada búsqueda devuelve únicamente su documento, los cuatro HMAC son distintos y los valores normalizados conservan mayúsculas, tildes y ceros. Las equivalencias de RUT chileno siguen compartiendo la reserva esperada. La collation general no introduce equivalencias adicionales en búsquedas/reservas HMAC.

Reversión vacía y reaplicación: correctas. Reversión con registros retirados: rechazada sin pérdida de historial. Las tablas anteriores y los históricos sintéticos conservan su contenido.

## Protección de desarrollo

La herramienta compara antes y después todas las tablas habituales mediante DDL, conteos, checksums y triggers. Resultado: `habitual_schema_and_rows_unchanged = true`. También compara la huella de `.env`: `env_unchanged = true`. No se copiaron registros habituales a la base de pruebas ni se configuraron claves reales; las pruebas usan material sintético.

No se detectaron nuevas diferencias inesperadas. La base aislada se conserva para revisión.

## Archivos y evidencia

- Actualizado: `tools/validate-identifier-storage.php`, para reproducir `utf8mb4_0900_ai_ci`, comparar configuración, tablas y metadatos antes de la migración, y verificar que `.env` permanece intacto.
- Actualizado: `tests/Integration/Identification/IdentifierStorageTest.php`, con el caso de sensibilidad de documentos extranjeros.
- Sin cambios: migración propuesta, servicios de identificación, configuración de conexión y archivos de claves.
- Informe JSON: `storage/logs/identifier-validation-vita_identification_test_20261105_232512_7911ee.json`.

## Siguiente operación, pendiente de autorización

El esquema está listo para la aplicación controlada en desarrollo. En esa operación debe utilizarse esta nueva base como referencia, no la anterior con `utf8mb4_unicode_ci`. Todavía corresponde comprobar nuevamente el estado de desarrollo, crear y restaurar un respaldo protegido, configurar claves independientes persistentes fuera de Git y ejecutar únicamente la migración aprobada. Nada de eso se ejecutó en esta validación. No se autoriza ni inicia automáticamente la etapa 3.
