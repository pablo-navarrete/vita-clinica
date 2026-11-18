# Identificación internacional de pacientes — etapa 4

## Alcance y estado de habilitación

La integración utiliza el núcleo y repositorio existentes: validación central, AES-256-GCM, HMAC versionado independiente, unicidad por clínica y conservación de reservas documentales retiradas. No migra automáticamente RUT históricos ni cambia sus valores.

**Las altas nuevas de pacientes y las asignaciones documentales nuevas permanecen bloqueadas en el backend.** No existe una variable de configuración que permita saltarse este bloqueo. Reservas públicas sigue creando pacientes directamente y no coordina las reservas HMAC. Viti conserva su escritor anterior mediante un adaptador privado, seleccionado exclusivamente por el nombre de ruta resuelto por Laravel. No se modificaron esos módulos para incorporar identificación internacional.

Se permite consultar, buscar y representar identificaciones existentes, y adoptar explícitamente el documento histórico del mismo paciente cuando la coincidencia es segura y no existen otras reservas o pacientes históricos equivalentes. No se fusionan personas. Una identificación reconocida no acredita identidad ni habilitación profesional. Las pruebas positivas de altas completas que simulan escritores coordinados usan un mock en la base aislada: **no demuestran que las altas estén habilitadas en desarrollo**.

Para desbloquear las altas se necesita una etapa autorizada que coordine todos los escritores de pacientes con el mismo protocolo transaccional y de unicidad. No basta con retirar el guard.

## Implementación

- `PatientIdentification`: validación, consulta privada, adopción histórica explícita, búsqueda exacta HMAC, conflictos históricos, snapshot y presentación enmascarada.
- `DocumentIdentityPresenter`: etiquetas y representación FHIR centralizadas; los valores históricos no clasificados no se presentan como documentos extranjeros verificados.
- `LegacyPatientWriterGuard`: cierre seguro frente a escritores antiguos no coordinados.
- `PatientIdentifierAccess`: contextos de acceso estrechos para identificación inicial con `patients.create` y lectura clínica autorizada. No otorga permisos generales de actualización al creador.
- `IdentifierRepository` / `IdentifierRepositoryFactory`: reutilización del almacenamiento cifrado, proyecciones enmascaradas y búsquedas exactas dentro de la clínica.
- `PatientController`, rutas y middleware: formularios, consulta privada sin caché, búsqueda documental mediante POST, transacción paciente/identificador/ficha/auditoría. La respuesta de DataTables elimina entrada y consultas de depuración incluso con `APP_DEBUG`.
- `Patient`, controladores de agenda, chat, dashboard, horarios e historial: presentación enmascarada; las búsquedas parciales siguen utilizando nombres.
- Componentes `identification-fields` y `identification-country`: país con búsqueda y teclado, Chile predeterminado y validación del núcleo.
- Vistas internas de pacientes, ficha, historial, notificaciones, agenda y PDF: representación centralizada. Los eventos históricos almacenados no se reescriben.
- `MedicalRecordFhirDocument`: identificación actual del recurso Patient desde el repositorio; documentos firmados mantienen sus snapshots históricos independientes.

## FES v4

Las nuevas atenciones y adendas usan `schema_version = 4`. El snapshot de la atención congela nombre e identificación del paciente, incluyendo referencia, país, tipo, namespace, número normalizado/presentación, versión y estado de validación. Un cambio entre vista previa y confirmación invalida la operación. No se guardan secretos ni HMAC en el snapshot.

Las adendas copian la identidad del paciente del documento original, también cuando es v1, v2 o v3. El verificador comprueba esa igualdad para adendas v4. Los lectores aceptan versiones 1, 2, 3 y 4; no recalculan ni reescriben versiones antiguas. PDF y FHIR conservan la identidad de cada documento firmado. Se mantienen contraseña, aceptación, permisos de médico/sede y bloqueos existentes.

## Migración y procedimiento local

Migración: `2026_11_06_000200_allow_absent_legacy_patient_rut`.

Solo permite NULL en `patients.rut`. Exige el esquema esperado y conserva `varchar(255)`, charset `utf8mb4`, collation `utf8mb4_unicode_ci` e índice único de clínica/RUT. La base conserva `utf8mb4_0900_ai_ci`. La reversión rechaza volver a NOT NULL si existe algún paciente sin RUT.

`tools/apply-patient-rut-nullable-local.php` verifica entorno local no cacheado, conexión sin réplicas/URL alternativa, `vita_clinica`, servidor Laragon MySQL 8.0.30, esquema e índice y que solo esté pendiente esta migración. Con `--apply`:

1. Crea un respaldo SQL completo con rutinas, eventos y triggers, y configuración de recuperación protegida fuera de Git.
2. Restaura en otra base y compara metadatos efectivos y datos.
3. Ensaya solo la migración en la restauración.
4. Verifica los valores de pacientes con fingerprint de sus bytes, ya que CHECKSUM TABLE puede cambiar por la representación física de NULL.
5. Confirma que la base habitual no cambió durante la comprobación.
6. Aplica exclusivamente esa migración y compara con el resultado ensayado.

No realiza rollback automático ni borra bases de respaldo ante una diferencia. No inserta identificadores ni pacientes de prueba en la base habitual.

## Verificación

Se reutiliza `tools/validate-identification-stage3.php`, que crea una base MySQL aislada, ejecuta la suite y verifica que la base habitual y `.env` permanezcan intactos. Incluye almacenamiento, cifrado, rotación, unicidad, documentos retirados, aislamiento y carreras reales con procesos independientes.

`PatientIdentificationIntegrationTest` cubre el bloqueo real, adopción histórica explícita, ceros iniciales, búsqueda exacta sin exposición, permisos de sede, alta transaccional simulada, fallo parcial con rollback, reservas retiradas, compatibilidad del adaptador Viti y reversión protegida. `InternationalSignatureTest` cubre documentos v1/v2/v3, nuevas firmas/adendas v4 y carreras entre modificación de identidad y firma, comprobando espera real en `performance_schema.data_lock_waits`.

Las pruebas JavaScript del componente verifican país predeterminado, separación documental, respuestas fuera de orden, fallos de carga, formato/checksum de RUT y preservación de documentos extranjeros. No sustituyen una revisión visual ni una prueba manual con teclado en un navegador.

## Resultados reales

- Migración local aplicada después de restauración y ensayo satisfactorios. Respaldo recuperable: `C:/laragon/backups/vita-identification-stage4/20261106_035227_a178aa/database.sql`. El directorio protegido contiene además la configuración de recuperación y `application-report.json`; sus secretos no se publican en este informe.
- Copia restaurada y conservada: `vita_identification_restore_stage4_20261106_035227_98de3d`.
- Valores de pacientes verificados byte a byte, sin cambios; demás tablas, triggers y configuración intactos. Único cambio funcional de esquema: permitir NULL en `patients.rut`, más el registro de la migración.
- Suite final posterior a la aplicación: base aislada `vita_identification_test_stage3_20261106_035257_ec91be`, **291 pruebas, 3.689 aserciones, 0 fallos, 2 omitidas**, 41,502 segundos, 86 MB. El nombre conserva el prefijo del runner reutilizado de etapa 3.
- Las dos omisiones pertenecen a pruebas anteriores que necesitan una atención histórica ya finalizada y un SuperAdmin con dos clínicas preexistentes. Las nuevas pruebas sí construyen y verifican documentos históricos sintéticos v1/v2/v3 y adendas v4; no se presentan las omisiones como aprobadas.
- JavaScript: 8 resultados correctos, 0 fallos, para identificación, modal de firma y adendas.
- Sintaxis PHP verificada en servicios, controladores, FHIR y herramienta de aplicación afectados.
- El runner confirmó nuevamente que la base habitual y `.env` no cambiaron durante las pruebas posteriores. No hubo pruebas modificadoras en la base habitual.
- No se verificó manualmente el diseño visual en navegador. No se inicia la etapa 5.

## Archivos de esta etapa

### Ajuste posterior: buscador único del DataTable

Presentación posterior autorizada: en la tabla del módulo Pacientes se muestra el número completo, sin el prefijo «Documento histórico», únicamente cuando el repositorio autoriza la consulta completa de ese paciente. Un RUT histórico válido se formatea para la presentación sin cambiar el valor almacenado; los valores históricos no clasificables se conservan literalmente. Si el usuario no tiene acceso documental completo por sede, la tabla conserva el enmascaramiento. Agenda, chat y dashboard mantienen sus proyecciones enmascaradas. Esta excepción de presentación reemplaza el comportamiento anterior de enmascarar también todas las filas de Pacientes.

Corrección de búsqueda histórica: la comprobación de módulo 11 no debe ocultar registros antiguos inconsistentes. Para pacientes sin identificadores clasificados, el buscador compara exactamente el RUT histórico ignorando puntos, guion, espacios y mayúsculas, aunque el valor no supere el checksum. No inventa dígitos verificadores ni crea identificadores o cambia su validación. Los documentos cifrados continúan buscándose mediante HMAC; no se usa LIKE sobre ellos.

Presentación en ficha clínica: `PatientIdentification::clinicalNumber()` utiliza la autorización de lectura clínica existente para mostrar el documento completo en el encabezado, datos personales y encabezados del editor de adendas. No modifica snapshots ni firmas. Agenda y chat no adquieren acceso completo. Verificación específica: 19 pruebas, 138 aserciones, 0 fallos, en la base aislada `vita_identification_test_stage3_20261106_041352_089ad2`, incluyendo renderizado de la ficha con RUT/pasaporte y denegación con permiso exclusivo de agenda.

Se retiraron el formulario documental adicional y el desplegable de contexto. La interfaz muestra solamente el buscador normal de DataTables, con el placeholder «Nombre o RUT». Consulta nombres parciales y RUT chilenos válidos equivalentes mediante el servicio central y HMAC. No se infiere el país de un documento extranjero ni se utiliza LIKE sobre documentos cifrados. La API conserva la búsqueda documental explícita ya implementada; no se muestra otro control de búsqueda en esta pantalla.

Archivos del ajuste: `resources/views/customer/patient/index.blade.php`, `PatientController.php`, `PatientIdentification.php` y `PatientIdentificationIntegrationTest.php`. No requiere migración ni cambia los flujos de escritura o firma.

Verificación del ajuste: **294 pruebas, 3.736 aserciones, 0 fallos, 2 omisiones previas**, en `vita_identification_test_stage3_20261106_040141_b0398b`; 38,346 segundos y 86 MB. Incluye búsquedas por nombre, formatos equivalentes de RUT, documento extranjero exacto, contexto incorrecto, aislamiento, conteos del DataTable y validación de parámetros. Base habitual y `.env` intactos. No se realizó una revisión visual manual del desplegable.

Nuevos:

- `app/Services/Identification/DocumentIdentityPresenter.php`
- `app/Services/Identification/PatientIdentification.php`
- `app/Services/Identification/LegacyPatientWriterGuard.php`
- `app/Services/Identification/Storage/PatientIdentifierAccess.php`
- `database/migrations/2026_11_06_000200_allow_absent_legacy_patient_rut.php`
- `resources/views/components/identification-country.blade.php`
- `tests/Integration/Identification/PatientIdentificationIntegrationTest.php`
- `tests/Unit/Identification/DocumentIdentityPresenterTest.php`
- `tools/apply-patient-rut-nullable-local.php`
- Este informe y `docs/verification/international-identification-stage4.json`.

Adaptados:

- `app/Services/Identification/Storage/{IdentifierRepository,IdentifierRepositoryFactory}.php`
- `app/Http/Controllers/customer/{PatientController,AppointmentController,ChatController,DashboardController,ProfessionalScheduleController,MyEncounterController}.php`
- `app/Models/Patient.php`, `app/Exceptions/Handler.php`, `app/Http/Middleware/CheckRolePermission.php`, `routes/web.php`
- `app/Services/Signatures/{EncounterSnapshotBuilder,EncounterAddendumSnapshotBuilder,EncounterSignatureVerifier}.php`
- `app/Support/MedicalRecordFhirDocument.php`
- `resources/views/components/identification-fields.blade.php`
- Vistas de `customer/patient` y `customer/patient/record-workspace` de formulario, ficha, actividad, agenda, PDF y vistas previas de firma/adenda.
- Vistas de `customer/{Appointment,chat,dashboard,encounters,professional,notifications}` y `layouts/navigation-customer.blade.php` para etiquetas enmascaradas.
- `tests/Integration/Identification/InternationalSignatureTest.php` y `tests/Support/international-signature-worker.php`.

No se modificaron los controladores de reservas públicas ni Viti, sus tablas, las suscripciones, los registros históricos ni los documentos firmados existentes.
