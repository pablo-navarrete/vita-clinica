# Aplicación local de identificación: comprobación previa detenida

Fecha: 05-11-2026. No se aplicó ninguna migración en la base habitual.

Actualización: la diferencia descrita en este diagnóstico se resolvió posteriormente reproduciendo la collation habitual en una nueva base aislada. Consultar `international-identification-collation-validation.md`; la base habitual sigue sin migrarse.

Se verificó conexión MySQL local, APP_ENV local, versión 8.0.30, directorio de datos dentro de Laragon y esquema seleccionado `vita_clinica`. `.env` está excluido de Git y no está versionado. No hay configuración Laravel cacheada.

La comparación de solo lectura con `vita_identification_test_20261105_222653_e2ad94` detectó:

| Elemento | Desarrollo | Entorno validado |
| --- | --- | --- |
| Charset de base | utf8mb4 | utf8mb4 |
| Collation predeterminada | utf8mb4_0900_ai_ci | utf8mb4_unicode_ci |

Las definiciones de las tablas coinciden, ignorando únicamente los valores variables AUTO_INCREMENT. Los cuerpos, eventos y demás propiedades comparadas de los 53 triggers existentes coinciden; difiere su metadato `Database Collation`.

Esta diferencia activa la instrucción explícita de detenerse antes de continuar. No se modificó la collation de desarrollo ni se omitió la comprobación. Tampoco se generaron claves, escribió `.env`, creó un respaldo/restauración ni ejecutó el paso `migrate`.

El almacenamiento no se considera aplicado ni listo todavía en la base habitual. Para continuar con seguridad, se recomienda repetir la validación aislada usando la collation real `utf8mb4_0900_ai_ci`, manteniendo intacta la base de desarrollo. Solo después corresponde preparar y restaurar un respaldo protegido, configurar claves persistentes independientes y aplicar la única migración aprobada.

Se creó `tools/apply-identifier-storage-local.php` como herramienta de operación controlada; únicamente se ejecutó `--inspect`. Su rama de aplicación permanece sin ejecutar y requiere completar la revisión de la diferencia antes de utilizarse. La herramienta no importa datos históricos ni integra la etapa 3.
