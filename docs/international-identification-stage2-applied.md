# Etapa 2 aplicada en desarrollo local

Fecha de ejecución: 05-11-2026, 23:53 UTC. Base: `vita_clinica`, host `127.0.0.1`, Laragon, MySQL 8.0.30. Collation conservada: `utf8mb4_0900_ai_ci`.

## Comparador y respaldo

Se incorporó `App\Support\MySqlMetadataComparator` al procedimiento local. La comparación conserva:

- Metadatos efectivos de columnas: orden, tipos y longitudes, nulabilidad, defaults, charset, collation, comentarios, atributos adicionales y expresiones generadas.
- Índices, unicidad, orden de columnas, expresiones, prefijos y visibilidad.
- Restricciones, columnas y destinos de claves foráneas, reglas UPDATE/DELETE y CHECK con su estado de aplicación.
- Motor, formato y opciones de tabla; definición DDL para detectar diferencias adicionales.
- Triggers, datos, conteos y checksums.

Se acepta exclusivamente el `CHARACTER SET utf8mb4` redundante en el prefijo de una columna con tipo, charset y collation efectivos verificados. No se eliminan textos similares dentro de defaults, CHECK ni literales. Los contadores AUTO_INCREMENT se siguen excluyendo únicamente en las opciones de tabla, como en la comparación previa con las bases sintéticas.

Cuatro pruebas nuevas cubren esa equivalencia, columnas de texto nullable y rechazo de cambios efectivos de tipos, nulabilidad, defaults, charset/collation, índices, FK, CHECK, motor y literales. El respaldo anterior se verificó nuevamente con éxito antes de continuar.

Respaldo actualizado y restaurado con éxito antes de configurar claves o ejecutar DDL:

- Directorio protegido: `C:/laragon/backups/vita-identification-stage2/20261105_235339_644e7c`.
- SQL completo: `database.sql`, generado con `--single-transaction --routines --events --triggers --hex-blob`.
- Restauración aislada: `vita_identification_restore_20261105_235339_36436d`, con `utf8mb4_0900_ai_ci`.
- Estructuras efectivas, datos y 53 triggers coincidentes con el original antes de la migración. No hay procedimientos, funciones, eventos ni vistas en el origen; el dump incluye las opciones para respaldarlos.
- `application-report.json` conserva la huella SHA-256 del SQL y el resultado de la aplicación.

El directorio de respaldo y `.env` tienen permisos restringidos al usuario local y SYSTEM. El archivo temporal de credenciales MySQL se eliminó. No publicar ni incorporar a Git los respaldos de configuración.

## Claves

Se generaron dos claves aleatorias independientes de 32 bytes para AES-256-GCM y HMAC-SHA256, con versión v1. El núcleo rechaza reutilización entre propósitos o de APP_KEY. No se modificó APP_KEY ni se imprimieron valores secretos.

Se persisten mediante las variables `IDENTIFICATION_ENCRYPTION_VERSION`, `IDENTIFICATION_ENCRYPTION_KEY_V1`, `IDENTIFICATION_LOOKUP_VERSION` e `IDENTIFICATION_LOOKUP_KEY_V1` en `.env`, excluido de Git y no versionado. Se conservan copias protegidas `.env.before` y `.env.after` en el directorio externo de respaldo.

Varios procesos PHP nuevos cargaron la configuración y validaron cifrado/descifrado autenticado y HMAC. No se reinició Laragon, pero las claves están en almacenamiento persistente, no dependen de la sesión de terminal y vuelven a leerse al iniciar PHP. Para AWS se mantiene la misma interfaz de variables de entorno: inyectar los secretos externamente con esos nombres, conservar las versiones necesarias y no incluir `.env` en el repositorio o la imagen. No se ha realizado un despliegue AWS.

## Migración ejecutada

Únicamente `2026_11_05_000300_create_international_identifier_storage`.

- Crea `customer_identifiers`, `user_identifiers` y `patient_identifiers`.
- 3 PK, 12 índices únicos adicionales, 14 FK con RESTRICT, 15 CHECK y 11 triggers nuevos.
- Esquema instalado coincidente con la referencia aprobada `vita_identification_test_20261105_232512_7911ee`.
- Registro de migraciones: solo se añadió la migración aprobada.
- Inmediatamente después de la aplicación, los datos y relaciones anteriores coincidían con el respaldo.
- Las tres tablas nuevas permanecen vacías; no se importaron RUT históricos.

## Pruebas posteriores

Suite de identificación ejecutada **en la base aislada**, no insertando fixtures en desarrollo:

- 126 pruebas aprobadas, 2.122 assertions, 0 fallos y 0 omitidas.
- 113 unitarias y 13 de integración MySQL real.
- 4,790 segundos, 38 MB en esa ejecución de PHPUnit.
- Incluye equivalencia de metadatos, unicidad, principales, retiros, aislamiento, conflictos sintéticos, restricciones SQL, concurrencia real, cifrado, HMAC, rotación y fallos parciales.

En desarrollo se realizaron comprobaciones de estructura/datos de solo lectura y pruebas criptográficas en procesos nuevos sin guardar documentos.

## Diferencia detectada en la comprobación final

La comparación estricta final con el respaldo se detuvo por nuevas filas creadas después de este, a las 20:55:02 locales:

- Un paciente.
- Una ficha clínica.
- Dos entradas de auditoría de paciente.
- Dos entradas de actividad (creación de paciente y ficha).

No se borró, corrigió ni revirtió esa actividad. No se puede atribuir su origen solo con la comparación de tablas; el paciente añadido no coincide con los nombres de los fixtures de esta suite.

Un diagnóstico posterior de solo lectura comparó cada fila histórica contra el respaldo mediante huellas en memoria, sin exponer datos personales. **Todas las filas preexistentes se conservan exactamente, sin cambios ni eliminaciones**. `customers` y `users` coinciden íntegramente. `patients` conserva las filas previas y contiene la nueva alta. La única adición esperada al registro de migraciones es la aprobada.

Las verificaciones posteriores no representan una base completamente idéntica al respaldo: existe actividad clínica adicional. El guard de comparación estricta se mantiene y no fue relajado para ignorarla. Evidencia protegida: `postcheck-diagnostic.json` y `final-history-check.json` en el directorio de respaldo.

## Estado y pendientes

El almacenamiento de etapa 2 está instalado, con claves configuradas y tablas vacías. La suite pasó y se verificó la conservación de todos los registros anteriores. Para obtener una comparación global estable posterior, repetir la verificación con la aplicación sin actividad concurrente y una referencia actualizada revisada; no restaurar el respaldo sobre nuevas altas.

No se modificaron formularios, controladores, reservas, Viti, fichas, firmas ni exportaciones. No se migraron datos históricos ni se inició la etapa 3. No se realizaron correcciones de datos a raíz del diagnóstico final.

Archivos de código de esta continuación: nuevo `app/Support/MySqlMetadataComparator.php`, nuevo `tests/Unit/Identification/MySqlMetadataComparatorTest.php` y actualizado `tools/apply-identifier-storage-local.php`. `.env` se modificó únicamente para provisionar las claves aprobadas; sus valores no están en la documentación ni en Git.
