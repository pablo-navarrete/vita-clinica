# Validación técnica de identificación internacional — etapa 2

Fecha: 05-11-2026. Motor real: MySQL 8.0.30, InnoDB. PHP 8.2.0, PHPUnit 10.5.63.

Validación posterior de paridad con desarrollo (`utf8mb4_0900_ai_ci`): ver `international-identification-collation-validation.md`. Ese informe identifica la referencia vigente y amplía los resultados a 122 pruebas y 2.107 assertions.

**Resultado: esquema técnicamente apto para revisión y aprobación en este entorno. No se aplicó en la base habitual ni en producción.** No hay integración en formularios, rutas, reservas, Viti ni documentos clínicos.

## Aislamiento y procedimiento

Se creó una base nueva para cada ensayo, con prefijo obligatorio `vita_identification_test_`. La ejecución final utilizó `vita_identification_test_20261105_222653_e2ad94`.

`tools/validate-identifier-storage.php`:

1. Rechaza configuración cacheada y motores distintos de MySQL.
2. Lee estructura, conteos, checksums y triggers de todas las tablas de la base habitual; no copia sus datos.
3. Crea una base aislada de nombre aleatorio y cambia explícitamente la conexión de migraciones.
4. Reconstruye allí las migraciones base y crea registros históricos **sintéticos** de cliente, usuario y paciente antes de aplicar la nueva migración.
5. Aplica la migración propuesta, verifica preservación de tablas anteriores, revierte con las tablas nuevas vacías y vuelve a aplicarla.
6. Ejecuta pruebas unitarias e integración en procesos con DB_DATABASE aislado, sin modificar `.env`.
7. Compara registros históricos sintéticos y la base habitual al terminar. Guarda un informe sin números documentales ni claves.

No se ejecuta `migrate:fresh`, no se borra ninguna base y las bases sintéticas de los ensayos se conservan. La validación requiere permiso del usuario MySQL para crear bases y consultar `performance_schema.data_lock_waits`. No debe exponerse como un endpoint web.

## Resultados finales medidos

- 121 pruebas aprobadas; 2.096 assertions; 0 fallos, 0 pruebas omitidas.
- 109 pruebas unitarias y 12 pruebas de integración MySQL.
- PHPUnit: 2,454 segundos y 36 MB; corresponde a la suite, no incluye el tiempo de reconstruir el esquema.
- Migración aplicada y reaplicada correctamente.
- Reversión vacía: recupera la estructura y contenido anteriores, excepto contadores AUTO_INCREMENT, que no deben rebobinarse.
- Reversión con datos, incluidos retirados: rechazada antes de borrar tablas.
- Preservación de estructura y datos anteriores durante la migración aislada: correcta.
- Históricos sintéticos previos: intactos tras migración y pruebas.
- Base habitual: mismas estructuras, conteos, checksums y triggers antes/después en todos los ensayos.

| Tabla nueva | PK | Índices únicos adicionales | FK | CHECK |
| --- | ---: | ---: | ---: | ---: |
| customer_identifiers | 1 | 4 | 4 | 5 |
| user_identifiers | 1 | 4 | 5 | 5 |
| patient_identifiers | 1 | 4 | 5 | 5 |

Los cuatro índices únicos son UUID, referencia histórica, principal generado y reserva documental. Las 14 FK usan DELETE RESTRICT; no hay CASCADE en los identificadores. Se verificaron 11 triggers de protección.

EXPLAIN de búsqueda exacta de pacientes: índice `ident_patient_lookup`, acceso `ref`, estimación del optimizador de una fila, `Using index condition`. Esta es una comprobación de uso de índice con datos de prueba pequeños, no una prueba de carga masiva.

## Casos comprobados

- RUT con/sin puntos y guion reconoce el mismo documento y namespace tributario/personal chileno.
- Un paciente puede conservar varios documentos de países/tipos distintos; solo uno principal activo.
- El mismo documento de pacientes se admite en clínicas distintas; un actor ajeno es rechazado.
- No se impone unicidad global a clientes ni entre diferentes cuentas de usuario. Las pruebas de almacenamiento de esos ámbitos usan un autorizador de prueba; no sustituyen la política de autorización real, cubierta por los casos multitenant con el autorizador concreto.
- Documento retirado: deja de aparecer como activo, sigue siendo consultable con autorización y conserva la reserva.
- SQL rechaza UUID/reservas/principales incompatibles, actores FK inexistentes, un titular de otra clínica, cambio de tenant, metadatos inmutables y borrado físico.
- Registros históricos en conflicto pueden coexistir sin reclamar identidad; no pueden ser principales ni generar una reserva y su referencia de procedencia es única. Son fixtures SQL de restricciones: no se implementó un importador.
- Concurrencia: dos procesos PHP independientes, con conexiones MySQL propias, esperan una barrera. La prueba bloquea primero la clínica y consulta `performance_schema` hasta observar **dos escritores simultáneamente esperando el bloqueo real**. Al liberar el bloqueo, el resultado es exactamente una creación y un rechazo por duplicado, con una sola fila final.
- AES-GCM: aleatoriedad, integridad y rechazo de manipulación de contenido/metadatos.
- HMAC: igualdad normalizada dentro del ámbito y separación entre clínicas, países y titulares aplicables.
- Rotación: lectura y búsqueda de documentos v1 con el anillo v2 antes de rotar; mantenimiento de original y búsqueda después de rotar; rechazo de escritores con versión antigua.
- Fallo parcial: un segundo documento manipulado hace fallar una rotación después de preparar el primero; la transacción conserva el primer registro exactamente como estaba.
- Claves faltantes, malformadas, reutilización AES/HMAC y reutilización de APP_KEY fallan con mensajes sin secretos. No se probó con claves productivas ni se generaron claves reales.

## Problemas encontrados y cambios

1. Un test comparaba atributos de un modelo recién insertado con los atributos completos recuperados de MySQL. Se corrigió la lectura inicial; no había modificación de datos personales.
2. La comparación del esquema de prueba confundía AUTO_INCREMENT legítimamente avanzado con un cambio estructural. Se normaliza solo ese contador en la base sintética; la comparación de la base habitual sigue siendo estricta.
3. Faltaba rechazar explícitamente una clave de identificación igual a APP_KEY. Se añadió la comprobación en `IdentifierCrypto`, la referencia de rechazo en `config/identification.php` y un test para ambos propósitos. APP_KEY nunca se utiliza para cifrar o buscar identificadores.
4. La primera prueba concurrente solo lanzaba procesos a la vez. Se reforzó con barreras de preparación y comprobación de dos esperas simultáneas en MySQL.

No fue necesario modificar el SQL de la migración propuesta.

## Archivos de esta validación

- Nuevo: `tools/validate-identifier-storage.php`.
- Ampliados: `tests/Integration/Identification/IdentifierStorageTest.php`, `tests/Support/identifier-storage-worker.php`, `tests/Unit/Identification/IdentifierCryptoTest.php`.
- Protección adicional: `app/Services/Identification/Storage/IdentifierCrypto.php`, `config/identification.php`.
- Documentación: este informe y `docs/international-identification-storage.md`.
- Informe JSON final local: `storage/logs/identifier-validation-vita_identification_test_20261105_222653_e2ad94.json`.

## Pendientes y límites

- La aprobación definitiva y aplicación en la base habitual siguen pendientes; esta validación no las ejecuta.
- No se migraron documentos históricos reales ni se implementó una resolución administrativa. El diseño futuro preservará evidencia y añadirá eventos auditables de resolución; no liberará reservas mediante borrado ni actualización informal.
- Antes de un despliegue futuro, actualizar las expectativas del test general `FreshDatabaseSchemaTest`, que mantiene contadores anteriores fijos de migraciones/triggers. No se ejecutó la suite clínica completa ni se modificó ese test en esta validación acotada.
- Configurar claves reales fuera del repositorio, permisos de secretos y recuperación de respaldos; conservar claves anteriores mientras existan documentos o respaldos que dependan de ellas.
- El cambio de clave de escritura requiere mantenimiento coordinado por ámbito y detener escritores antiguos. No se implementa una rotación automática sin interrupción entre despliegues arbitrarios.
- La reversión con datos se realiza desactivando la integración y conservando las tablas. No existe un rollback destructivo permitido.
- Revalidar en la versión/configuración MySQL de producción. La prueba no certifica cumplimiento legal, rendimiento masivo ni seguridad del servidor productivo.
