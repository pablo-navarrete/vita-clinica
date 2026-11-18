# Especialidades desde un archivo PHP

## Catálogo único

`config/specialties.php`, clave `catalog`, contiene las 90 especialidades y disciplinas disponibles
como pares de ID numérico estable y nombre. No hay tablas operativas de catálogo maestro,
mantenedores, importadores, conciliaciones, observer ni tareas programadas para el catálogo.
Para ampliar la lista se añade un ID nuevo al archivo y se despliega normalmente. Nunca
renumerar ni reutilizar IDs; conservar los usados históricamente. Si se usa configuración
cacheada, reconstruirla al desplegar (`php artisan config:cache`).

## Compatibilidad clínica

`specialties` conserva las referencias locales necesarias para citas y atenciones. La migración
consolidada `2026_11_04_000019_create_specialties_table.php` crea directamente `catalog_id`
nullable e indexado, sin FK hacia un catálogo en DB, y `public_visible`. Ya no se requiere
un backfill ni una copia adicional del catálogo para migraciones. La base local fue reiniciada
con autorización explícita el 04/11/2026; no se conservaron datos de clientes anteriores.

No se modifican ni fusionan IDs locales duplicados. `specialty_user`, citas, atenciones y
snapshots firmados mantienen sus referencias. Las desconocidas existentes pueden conservarse
o retirarse de sus asignaciones actuales, pero no asignarse a otros profesionales. No se
sobrescriben nombres, textos clínicos ni evidencias firmadas.

`SpecialtyCatalog` lee opciones desde config. Al guardar una selección crea bajo demanda
una referencia local para la clínica si hace falta, conservando los IDs que ya tenga asignados
el profesional. Esto es persistencia de una asignación, no importación ni sincronización del
catálogo completo. Todos los IDs almacenados en pivotes y citas siguen siendo positivos y
locales. Los formularios usan valores negativos para distinguir IDs de catálogo de IDs locales
históricos; no son IDs clínicos y nunca se guardan en el pivot.

Las transacciones bloquean cliente y profesional para serializar la creación de referencias
y las asignaciones por sede. Las validaciones comprueban clínica, sede activa, pertenencia
activa y selección válida antes de retirar asignaciones. Los controladores conservan sus
permisos de administración por sede. Elegir una especialidad no otorga Doctor ni permisos
clínicos. Cambiar roles ya no borra especialidades: la oferta pública se recalcula según los
permisos efectivos, sin alterar las referencias históricas.

## Interfaz y publicación

Se mantienen Select2, etiquetas múltiples y modales existentes al crear/editar profesionales
y sus especialidades por sede, también desde el perfil con los permisos correspondientes.
Se retiraron rutas y enlaces de administración de especialidades de clínicas y SuperAdmin.

La oferta pública usa nombres de config y una categoría por clínica. Exige profesional activo
y no eliminado, sede activa y no eliminada, pertenencia activa, asignación tenant/sede coherente,
visibilidad pública y rol Doctor de esa sede o un rol con permiso `medical_records.manage`.
No exige horarios ni citas disponibles. La habilitación general de la web/reservas sigue siendo
la existente. Una especialidad sin clasificación segura no se expone públicamente.
Las reservas agrupan equivalencias y traducen la categoría elegida al ID local realmente
asignado al profesional en la sede elegida. No dependen del espejo global de roles.

Los gráficos históricos cuentan citas aun cuando la especialidad o el profesional estén
inactivos; agrupan por catálogo cuando está clasificado y mantienen las desconocidas por ID
local. No cambian las referencias históricas almacenadas. El directorio central continúa
mostrando clientes con el consentimiento existente; esta modificación no añade nuevos filtros
ni publica datos individuales de profesionales.

## Retirada del sistema anterior

Se eliminaron servicios/controladores/modelos/vistas/comandos exclusivos de importación y
normalización administrable, junto con sus pruebas sustituidas por las del catálogo PHP.
Tras el reinicio completo autorizado se retiraron también las migraciones y las siete tablas
del catálogo anterior, sus alias, correspondencias y auditorías. No se requieren cron,
importadores ni acciones administrativas periódicas para usar las especialidades.

## Verificación y reversión

`FileSpecialtyCatalogTest`: selección múltiple, creación/edición por HTTP, permisos por sede,
aislamiento tenant, conservación de asignaciones duplicadas y desconocidas, oferta operativa,
reservas con equivalencias, gráficos históricos y retirada de rutas/comandos.
`FileSpecialtyConcurrencyTest`: esquema MySQL temporal vacío y dos procesos creando una única referencia local compartida
por dos asignaciones. No se copian datos reales a esa base.
Regresiones: directorio, perfiles, FES, adendas y PDF.

La consolidación es una nueva línea base para instalaciones vacías. No aplicar estas migraciones
consolidadas sobre otra instalación poblada con el historial anterior. Ver `database-baseline.md`.
