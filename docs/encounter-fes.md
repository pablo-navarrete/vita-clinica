> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Firma Electrónica Simple de atenciones — VITA

## Alcance

Cada atención nueva se crea En curso (1). Su único cierre como Finalizada (2) pasa por el servicio FES. Cancelada continúa siendo 3. No se generan firmas retroactivas ni se requiere una imagen visual. Esta implementación registra aceptación e identidad mediante contraseña; no es FEA ni se declara certificada.

Desde la ficha, **Firmar y finalizar atención** prepara una vista previa de diez minutos. El médico revisa motivo, evaluación, tratamiento, paciente, identidad profesional, especialidad, sede, modalidad, fechas, cita y listado de adjuntos con sus hashes. Se firma la atención individual: los antecedentes generales editables de la ficha no forman parte de esta versión.

El modal requiere consentimiento explícito y contraseña. Se verifica el hash del usuario autenticado exacto, sin buscar por correo. Hay límites de intentos por usuario/clínica y por IP. La contraseña no se guarda ni se incluye en auditoría; sigue excluida del flash de sesión de Laravel. Mantener HTTPS, un caché compartido para límites en despliegues con varios servidores, y no configurar observabilidad para capturar cuerpos de solicitudes con contraseñas.

## Persistencia y cifrado

- `encounter_signing_operations`: preparación vinculada al médico, clínica, atención y contenido cifrado, con vencimiento y estado. Es un diario de recuperación, no una firma.
- `encounter_document_versions`: contenido canónico cifrado, SHA-256 de los bytes originales, esquema, versión, tipo y referencia a versión padre. Los futuros tipos adenda/rectificación se incorporarán sin sobrescribir la versión original.
- `encounter_signatures`: referencia exacta a versión, identidad histórica cifrada, aceptación y su texto/versionado, método, mecanismo de autenticación, hora UTC del servidor, hash y versiones de activos visuales opcionales.

Canonicalización v1: UTF-8 JSON sin escapar Unicode ni barras; claves de objetos ordenadas recursivamente, listas en orden explícito (adjuntos por ID). Se recortan extremos de evaluación/tratamiento antes de presentar la vista previa; se conserva el texto interior. El hash es del contenido canónico, no del ciphertext ni del PDF agregado. Los tiempos de autenticación y firma pertenecen a la evidencia y se generan al confirmar.

El cifrado utiliza Laravel `Crypt` y `APP_KEY`. Respaldar la clave de forma separada y protegida: perderla impide recuperar versiones y adjuntos. Una rotación de claves requiere un procedimiento específico de conservación; no cambiar APP_KEY sin ese procedimiento. Las restricciones de base de datos impiden ediciones/borrados ordinarios, pero no sustituyen respaldos ni protección frente a administradores de infraestructura con privilegios para retirar esos controles.

## Archivos: preparación, confirmación y recuperación

El disco privado `signed_encounters` apunta a `storage/app/private/signed-encounters`. No tiene URL pública ni enlace de storage. La operación registrada existe antes de escribir archivos.

1. La transacción bloquea la operación y la atención; vuelve a comprobar médico/sede, estado, contraseña y contenido. Los escritores de adjuntos y cancelación también bloquean la atención.
2. Se preparan copias cifradas bajo `{customer_id}/{operation_uuid}/`. El origen se lee por bloques de 1 MB, calculando el SHA-256; se cifra cada bloque con posición y ruta autenticadas. Solo se usa un temporal de ciphertext. El manifiesto conserva hash, tamaño, número de bloques y versión de formato. Cada archivo se relee y verifica antes de confirmar.
3. En la misma transacción se guardan versión y evidencia, se finaliza la atención y se actualiza su cita. La operación queda `committed`. Los mensajes de finalización se envían después del commit.
4. Ante error no se borran archivos inmediatamente: un error de conexión podría ocultar un commit exitoso. `signatures:cleanup` bloquea operaciones expiradas y consulta nuevamente firmas antes de limpiar. Una firma confirmada siempre impide la eliminación, aunque el estado del diario fuera inconsistente.
5. Un proceso caído deja archivos asociados a una operación conocida y recuperable. Un reintento válido puede repetir la preparación mientras no exista firma; las versiones confirmadas nunca se sobrescriben.

El comando se programa cada hora. En producción debe estar activo el scheduler de Laravel:

```sh
php artisan schedule:run
```

Los adjuntos de atenciones firmadas se descargan desde la copia cifrada y verificada, incluso si falta el archivo de trabajo original. No eliminar manualmente directorios de operaciones confirmadas. Respaldar conjuntamente base de datos, disco firmado y claves; en varios servidores usar almacenamiento privado compartido o garantizar afinidad/acceso común antes de escalar.

## Controles de escritura

`finish()` delega en `EncounterSigningService`; ya no actualiza estado directamente. El servicio usa transacciones, bloqueos y unicidad de versión original/operación. Los triggers MySQL impiden finalizar sin evidencia, modificar una atención ya firmada, editar o borrar evidencia/versiones, modificar adjuntos firmados o agregar observaciones sin firma. Claves foráneas restrictivas conservan referencias ante borrados físicos relacionados.

Las cuentas administrativas no pueden firmar por representación. Se exige médico responsable activo, mismo tenant, rol/membresía en la sede de la atención y esa sede seleccionada. Las atenciones antiguas sin sede o responsable resoluble requieren revisión administrativa de sus datos; no se inventa identidad ni sede al firmar.

Las observaciones antiguas permanecen. En atenciones firmadas, el formulario y la ruta de observaciones sin firma quedan bloqueados: la información posterior se agrega mediante adendas firmadas, descritas en `docs/encounter-addenda.md`. PDF/FHIR mantienen el formato agregado, pero usan las versiones conservadas y separan cada documento firmado. No se agrega firma al PDF completo ni una firma criptográfica FHIR.

## Migraciones

Ejecutar en orden, después de respaldar y verificar privilegio `TRIGGER` de MySQL:

```sh
php artisan migrate
```

Son aditivas; no reescriben datos clínicos históricos. Instalar aplicación y protecciones como un despliegue coordinado. MySQL no hace transaccional todo el DDL: comprobar las tres migraciones completadas antes de abrir tráfico. No retirar triggers ni ejecutar rollback de las tablas una vez existan firmas. La tercera protege también borrados de citas/especialidades: las cascadas de claves foráneas en MySQL no ejecutan triggers de tablas hijas.

## Pruebas y límites

- `EncounterSigningTest`: aceptación/contraseña/límites, versión cifrada, firma sin imagen, conservación visual, cita, reintentos, contenido obsoleto, adjuntos alterados, descarga histórica, aislamiento, protecciones SQL, fallos de evidencia/almacenamiento, limpieza y corrupción.
- `EncounterSigningConcurrencyTest`: procesos PHP y conexiones MySQL independientes compiten con dos preparaciones distintas, cancelación y carga de adjuntos. Usa una base temporal con estructura copiada sin datos y triggers propios; se elimina al terminar. Las claves foráneas se prueban en las pruebas transaccionales normales, porque `CREATE TABLE LIKE` no las copia.
- `EncounterCreationRulesTest`: regresión de creación/continuación; sus cierres de preparación usan cancelación, sin saltarse la exigencia de firma.
- `tests/Browser/encounter-signing.test.cjs`: simulación del modal, preparación POST, cancelación, errores, limpieza de contraseña y navegación interna. No sustituye pruebas manuales en navegadores/dispositivos reales.

Las adendas/rectificaciones firmadas de texto están disponibles mediante la etapa documentada en `docs/encounter-addenda.md`. Permanecen pendientes proveedores FEA, validación jurídica de cumplimiento, exportaciones con firmas criptográficas y eventual anclaje externo de evidencia.

## Archivos de esta etapa

- Controladores: `customer/EncounterController.php`, `EncounterSigningController.php` (nuevo), `PatientDocumentController.php`, `AppointmentController.php`, `MedicalRecordController.php`, `MyEncounterController.php`.
- Modelos: `Encounter.php` y nuevos `EncounterDocumentVersion.php`, `EncounterSignature.php`, `EncounterSigningOperation.php`.
- Contratos/servicios: `Contracts/EncounterSignatureProvider.php`; en `Services/Signatures/`, `CanonicalContentSerializer`, `EncounterSigningPolicy`, `SigningIdentityVerifier`, `EncounterSnapshotBuilder`, `SignedAttachmentStore`, `EncounterSigningService`, `EncounterSignatureVerifier`, `SignedEncounterProjection` y ampliación de `VitaSimpleSignatureMethod`.
- Operación: `Console/Commands/CleanEncounterSigningOperations.php`, `Console/Kernel.php`, `config/filesystems.php`, `routes/web.php` y las tres migraciones indicadas arriba.
- Interfaz: `record-workspace/attention.blade.php`, `history.blade.php`, `scripts.blade.php`, nuevos `signature-modal.blade.php` y `signature-preview.blade.php`; `public/js/encounter-signing.js`.
- Exportaciones mínimas: `customer/patient/medical-record-export-pdfa.blade.php`, `Support/MedicalRecordFhirDocument.php` y proyección desde `MedicalRecordController`.
- Pruebas: `Feature/EncounterSigningTest.php`, `EncounterSigningConcurrencyTest.php`, ajuste de `EncounterCreationRulesTest.php`, `Support/encounter-signing-worker.php`, `Browser/encounter-signing.test.cjs`.
