# Cierre de identificación internacional, etapa 3

## Resultado

Clientes extranjeros y doctores con identificación internacional habilitados en los
flujos existentes, conservando las autorizaciones por clínica y sede. No se concede
el rol Doctor por validar un documento. La validación tampoco acredita habilitación profesional.
No se integraron pacientes, reservas, Viti ni portal global.

## Migración y respaldo

Aplicada únicamente en `vita_clinica`, `127.0.0.1`, Laragon MySQL 8.0.30:
`2026_11_06_000001_allow_absent_legacy_customer_rut`.
Conserva varchar(255), utf8mb4 y utf8mb4_unicode_ci de la columna; solo permite NULL.
Los valores históricos, las demás tablas, los triggers y `.env` se conservaron.
No se importaron identificadores: las tres tablas nuevas siguen sin registros en desarrollo.

Respaldo completo protegido fuera de Git:
`C:/laragon/backups/vita-identification-stage3/20261106_005951_c00a69/`.
Contiene `database.sql`, configuración protegida de recuperación e `application-report.json`.
Se restauró y verificó antes de aplicar la migración en desarrollo.
Base de restauración: `vita_identification_restore_stage3_20261106_005952_577800`.

El primer ensayo se detuvo por el cambio de CHECKSUM TABLE de customers tras modificar
la nulabilidad. La comparación de valores de las 83 tablas confirmó que no había cambios
de datos. Se añadió una comprobación binaria por valores para customers, con pruebas
que distinguen NULL, vacío, ceros iniciales, tipos, bytes y filas duplicadas. El resto
de controles estrictos permanece. Se repitió respaldo y restauración antes de aplicar.
La reversión a NOT NULL rechaza ejecutarse si existen clientes sin RUT.

## Firmas

- Lectura explícita de esquemas 1 (original) y 2 (adenda), sin reescritura.
- Nuevas atenciones y adendas usan esquema 3.
- La secuencia documental es independiente del formato del snapshot.
- Una adenda v3 puede referenciar documentos históricos v1/v2.
- Identidad congelada: usuario, nombre, país, tipo, namespace, número normalizado y
  presentación, referencia, versión de normalización y validación documental.
- No contiene claves HMAC, secretos, contraseñas ni tokens.
- Se conserva el cifrado existente de snapshots y evidencias.
- Los documentos antiguos se verifican contra sus bytes almacenados, sin reconstruirlos.
- Valores heredados sin identificador nuevo permanecen sin reclasificar, con país desconocido.
- Si existen identificadores nuevos pero ninguno principal activo, se rechaza la firma;
  la configuración visual sigue accesible y no se vuelve silenciosamente al RUT antiguo.

La firma y los cambios documentales usan primero el bloqueo transaccional de customer.
Cambiar la identidad invalida la vista previa pendiente; permite preparar una nueva.
No se bloquean cambios indefinidamente por tener atenciones abiertas.
Las modificaciones documentales conservan auditoría cifrada y las correcciones centrales
de nombre se registran en la clínica propietaria. Las tablas generales no devuelven RUT completos.

PDF utiliza la identidad histórica y etiquetas según documento. FHIR distingue las
identidades históricas del mismo usuario y conserva documentos originales/adendas separados.
La aceptación de la firma no crea por sí misma una identidad FHIR distinta.

## Archivos de este cierre

Nuevos:

- `app/Services/Signatures/SignerIdentitySnapshot.php`
- `app/Support/MySqlRowFingerprint.php`
- `tools/apply-customer-rut-nullable-local.php`
- `tests/Integration/Identification/InternationalSignatureTest.php`
- `tests/Support/international-signature-worker.php`
- `tests/Unit/Identification/MySqlRowFingerprintTest.php`
- Este informe.

Modificados:

- `app/Services/Identification/IdentificationForms.php`
- `app/Services/Identification/Storage/IdentifierRepository.php`
- `app/Services/Signatures/EncounterSnapshotBuilder.php`
- `app/Services/Signatures/EncounterAddendumSnapshotBuilder.php`
- `app/Services/Signatures/EncounterSigningService.php`
- `app/Services/Signatures/EncounterAddendumService.php`
- `app/Services/Signatures/EncounterSignatureVerifier.php`
- `app/Support/MedicalRecordFhirDocument.php`
- `app/Support/ClinicActivityLogger.php`
- Controladores `superadmin/CustomerController`, `customer/ProfessionalController`,
  `customer/ProfileController` y `customer/RolePermissionController`.
- Vistas `customer/user/partials/electronic-signature` y parciales de record-workspace:
  `signature-preview`, `addendum-preview`, `pdf-signature`.
- `tests/Integration/Identification/IdentificationFormsTest.php`
- `tests/Feature/CustomerSubscriptionTest.php`
- `tools/validate-identification-stage3.php`
- `docs/international-identification-stage3.md`

La migración ya preparada no necesitó cambios. No se modificaron los triggers de firmas,
el escritor de evidencias ni los estados de Encounter.

## Pruebas

Última base aislada: `vita_identification_test_stage3_20261106_010024_3209c7`.

- 266 pruebas PHP: 265 aprobadas y 1 omitida; 3.507 assertions; 31,491 s; 84 MB.
- La omitida requiere atenciones históricas finalizadas sin firma en el esquema limpio.
  Las firmas históricas v1/v2 sí se prueban mediante fixtures sintéticos independientes.
- Incluye clientes chilenos/extranjeros, suscripciones, Flow simulado, directorio,
  firmas, adendas, permisos, aislamiento, cifrado, HMAC y conservación de evidencias.
- Dos carreras nuevas usan procesos independientes y verifican espera real mediante
  performance_schema.data_lock_waits: editar antes de confirmar y editar después de firmar.
- Exportación PDF real y FHIR, identidades históricas separadas y documento retirado.
- 3 pruebas JavaScript aprobadas; sintaxis de los PHP modificados comprobada.
- El runner confirmó desarrollo y `.env` intactos durante las pruebas.
- Tras la migración se verificaron lectura de metadatos y claves en un proceso nuevo.

Informe: `storage/logs/identification-stage3-vita_identification_test_stage3_20261106_010024_3209c7.json`.

No se ejecutaron cobros reales ni una prueba real de Flow; se utilizaron dobles HTTP.
No se realizó inspección visual interactiva ni validación externa completa de FHIR.
No constituye certificación legal ni Firma Electrónica Avanzada.

La nueva decisión de operar exclusivamente en Chile queda pendiente de propuesta/aprobación:
no se añadieron restricciones territoriales ni se limitaron países emisores de documentos.
