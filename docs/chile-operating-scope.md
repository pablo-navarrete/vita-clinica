# Ámbito operativo de VITA: Chile

## Resultado

VITA admite únicamente `CL` como país de operación de una clínica. El país emisor
del documento tributario sigue siendo independiente: se conservan los documentos
internacionales y no se deducen nacionalidad ni habilitación profesional.
Las sedes obtienen el país de su clínica; no tienen configuración propia.

En SuperAdmin → Clientes → Crear/Editar se muestra Chile como campo deshabilitado,
separado del documento tributario. Las clínicas nuevas requieren aceptación explícita
de la declaración `chile-operation-v1`. Su texto se obtiene del servidor.
La aceptación registra versión, texto, hash, usuario autenticado, fecha UTC y valor aceptado.
Su revisión comienza en `pending` y puede pasar a `reviewed` o `needs_information`.
Los cambios de revisión se agregan al historial, nunca sobrescriben eventos anteriores.
Guardar otra vez el mismo estado y nota no duplica eventos ni cambia la aceptación original.

La declaración y el estado de revisión no acreditan cumplimiento normativo, identidad
verificada ni habilitación profesional. Tampoco activan restricciones comerciales.
El responsable registrado en estos formularios es el SuperAdmin que realiza la acción;
no se atribuye su aceptación a otro usuario o a un representante no autenticado.

## Clínicas existentes

El usuario confirmó expresamente: «Sí, todas operan en Chile» para la base local.
Se utilizó esa confirmación exclusivamente para asignar el ámbito CL al agregar la columna.
No se generaron declaraciones ni revisiones retroactivas. Las clínicas mantienen acceso,
estado, suscripciones y datos anteriores, aunque carezcan de aceptación histórica.

## Migración y aplicación controlada

Aplicada únicamente en `vita_clinica`, conexión local 127.0.0.1, Laragon MySQL 8.0.30:

`2026_11_06_000100_add_customer_chile_operation`.

Agrega:

- `customers.operating_country_code`: CHAR(2), NOT NULL, DEFAULT CL, ASCII binario.
- CHECK que permite solo CL, incluso frente a escrituras SQL directas.
- `customer_operating_declarations`, con unicidad por clínica y versión.
- `customer_operating_reviews`, con FK compuesta que impide asociar la revisión
  de una clínica con la declaración de otra.
- Tres CHECK, cinco FK RESTRICT y cuatro triggers nuevos de inmutabilidad.

La nueva columna usa su propio charset ASCII para códigos ISO. No se cambiaron
charset, collation ni propiedades de columnas existentes. `customers.rut` sigue nullable.
El rollback se rechaza si existen evidencias, para evitar perder trazabilidad.

Respaldo completo protegido, fuera del repositorio:

`C:/laragon/backups/vita-chile-operation/20261106_025446_01c94b/`.

Contiene SQL de estructura, datos, triggers, rutinas y eventos, y configuración de
recuperación protegida. No se mostraron secretos ni se modificó `.env`.
Se restauró y verificó en `vita_territory_restore_20261106_025446_f0de08`.
La migración se ensayó allí antes de aplicarla en desarrollo.
Los valores de todas las columnas antiguas de customers se compararon por bytes;
las restantes tablas y triggers existentes se conservaron. El esquema y los datos
finales coinciden con el resultado ensayado en la restauración.
Informe protegido: `application-report.json` en el directorio de respaldo.

La comprobación posterior de solo lectura confirmó cero clientes con otro país,
herencia CL en las sedes y cero aceptaciones/revisiones históricas inventadas.
Metadatos: `storage/logs/chile-operation-local-verification.json`.

## Archivos

Nuevos:

- `database/migrations/2026_11_06_000100_add_customer_chile_operation.php`
- `app/Services/CustomerOperatingScope.php`
- `app/Models/CustomerOperatingDeclaration.php`
- `app/Models/CustomerOperatingReview.php`
- `resources/views/superadmin/customer/partials/operating-scope.blade.php`
- `tests/Integration/Identification/CustomerOperatingScopeTest.php`
- `tools/apply-chile-operation-local.php`
- Este informe.

Modificados:

- `app/Models/Customer.php`
- `app/Models/ClinicBranch.php`
- `app/Http/Controllers/superadmin/CustomerController.php`
- `resources/views/superadmin/customer/index.blade.php`
- Fixtures de `CustomerAdministratorOnboardingTest`, `CustomerSubscriptionTest`
  e `IdentificationFormsTest`: aceptación explícita en las nuevas contrataciones.
- `tools/validate-identification-stage3.php`: incorpora pruebas territoriales y de sedes.

No se modificaron servicios Flow, reglas comerciales, monedas, impuestos, FES v3,
documentos firmados, reservas, Viti ni identificadores de pacientes.

## Pruebas

Ejecución final aislada: `vita_identification_test_stage3_20261106_025618_e8e373`.

- 274 pruebas PHP: 272 aprobadas, 2 omitidas; 3.582 assertions, 32,131 s, 86 MB.
- Omisiones: un test requiere atenciones históricas finalizadas sin firma y otro
  requiere fixtures previos de dos clínicas. No se presentan como aprobados.
  Las firmas históricas y el aislamiento de sedes se prueban además con fixtures sintéticos.
- Creación con RUT chileno y con documento tributario extranjero, ambos operando en CL.
- Aceptación obligatoria, solicitudes manipuladas, permisos, revisión administrativa,
  idempotencia, rechazo de alteración/eliminación de evidencias y aislamiento por FK.
- Herencia por sedes, rechazo de países independientes y edición cruzada entre clínicas.
- Regresión de identificadores, FES/adendas, PDF/FHIR, suscripciones, Flow simulado y directorio.
- Tres pruebas JavaScript de identificación aprobadas y sintaxis PHP comprobada.
- El runner verificó que las pruebas no modificaron desarrollo ni `.env`.

## Limitaciones

La operación real en Chile requiere declaración y revisión humana; no se verifica
automáticamente por IP, documento, ubicación física ni registro profesional.
No se hicieron cobros reales ni una inspección visual interactiva en navegador.
La expansión territorial futura exige cambios explícitos de política y esquema;
no queda activada por mantener un catálogo internacional de países.
No se avanzó a pacientes, reservas públicas, Viti ni portal global.
