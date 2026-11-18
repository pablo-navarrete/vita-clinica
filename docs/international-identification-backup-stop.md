# Aplicación local detenida durante la verificación del respaldo

Fecha: 05-11-2026. **No se aplicó la migración y no se configuraron claves.** La etapa 3 no se inició.

Actualización posterior autorizada: el comparador se ajustó con metadatos efectivos, el respaldo superó la verificación y la etapa 2 se aplicó. Ver `international-identification-stage2-applied.md`, incluida la actividad adicional detectada durante la comprobación final.

## Operación ejecutada

- Conexión confirmada: `127.0.0.1`, `vita_clinica`, Laragon, MySQL 8.0.30.
- Collation de base: `utf8mb4_0900_ai_ci`, sin cambios.
- Esquema previo coincidente con la referencia aprobada `vita_identification_test_20261105_232512_7911ee`.
- Solo una migración pendiente: `2026_11_05_000300_create_international_identifier_storage`.
- Respaldo completo generado con mysqldump: estructura, datos y opciones `--routines --events --triggers --hex-blob --single-transaction`. La base actual no tiene procedimientos, funciones, eventos ni vistas; sí tiene 53 triggers.
- SQL de 217.973 bytes guardado en `C:/laragon/backups/vita-identification-stage2/20261105_234550_c5d2ab/database.sql`.
- Configuración previa respaldada en el mismo directorio protegido, fuera de Git. No mostrar ni distribuir ese archivo: contiene secretos existentes.
- Permisos del directorio restringidos al usuario local y SYSTEM. Credenciales temporales del cliente MySQL eliminadas al terminar.
- Restauración terminada en `vita_identification_restore_20261105_234550_c7d076`, creada con la misma collation de base.

## Motivo de detención

La verificación automática comparaba literalmente las definiciones emitidas por SHOW CREATE TABLE. MySQL representó columnas de la restauración con `CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci`, mientras las originales muestran solamente `COLLATE utf8mb4_unicode_ci`.

La comparación textual rechazó el respaldo. En cumplimiento de la instrucción de detenerse ante cualquier error, no se modificó esa comprobación ni se repitió la aplicación.

## Diagnóstico posterior, exclusivamente de lectura

- Conteos y CHECKSUM TABLE coinciden en todas las tablas originales/restauradas.
- Coinciden los 53 triggers y sus metadatos comparados, excluyendo la fecha de creación.
- Coinciden todos los metadatos efectivos de columnas en information_schema: charset, collation, tipos, longitudes, defaults, nulabilidad y expresiones.
- Coinciden los metadatos de todos los índices.
- `.env` coincide byte a byte con la copia previa (comparación de huellas, sin mostrar valores).
- La migración no figura aplicada y las tres tablas nuevas todavía no existen en desarrollo.

La diferencia observada es de representación textual del charset explícito tras restaurar. No se alteró la collation habitual ni se ha identificado pérdida de datos.

## Pendiente antes de continuar

Revisar y autorizar el ajuste de la comparación de restauración para comprobar metadatos efectivos y aceptar únicamente la representación redundante ya identificada, manteniendo la comparación de datos, índices, FK, CHECK y triggers. No se debe ignorar de forma general cualquier diferencia DDL.

Después de esa revisión corresponde repetir la verificación y continuar, por separado, con claves persistentes y la única migración aprobada. El almacenamiento aún no está habilitado en la base habitual.

Archivo de operación preparado: `tools/apply-identifier-storage-local.php`, actualizado para utilizar la referencia con `utf8mb4_0900_ai_ci`, exigir el nombre exacto `vita_clinica` y respaldar/restaurar en aislamiento. Su comparación de restauración permanece estricta; no se intentó eludir el error.
