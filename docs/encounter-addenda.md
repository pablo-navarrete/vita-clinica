> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Adendas médicas firmadas — VITA

## Uso y alcance

Desde el historial de una atención finalizada y firmada, el firmante original puede seleccionar **Agregar adenda**. Debe estar activo, tener rol y membresía de médico en la sede original y tener esa sede seleccionada. Ningún permiso administrativo permite firmar por representación.

El formulario se abre en un modal dentro de la ficha. La vista previa y contraseña usan un segundo paso del mismo flujo FES; volver a editar conserva el texto. Tras confirmar se cierra el modal y se actualiza únicamente el historial, preservando otros campos sin guardar de la ficha. La ruta completa se conserva como alternativa para abrir en otra pestaña.

Tipos disponibles: observación adicional, complemento y rectificación. Cada adenda es de texto y tiene vista previa, aceptación específica y nueva confirmación de contraseña. Una rectificación exige documento de destino, apartado y motivo; la vista previa y el documento firmado conservan íntegramente el texto anterior. Puede corregirse un apartado clínico del original (motivo, evaluación o tratamiento) o el contenido de una adenda previa de la misma atención. El texto nuevo no sustituye al anterior.

La atención permanece en estado 2 y conserva contenido, hash, firma y fecha de finalización. No se reabre, no se actualiza su cita y no se reenvían notificaciones de finalización. Las observaciones históricas permanecen en su tabla y no reciben firmas retroactivas. Para atenciones históricas sin firma original se mantiene el comportamiento previo de observaciones; no se permiten adendas que finjan una firma original inexistente.

## Persistencia

Se reutilizan las tres tablas de FES:

- `encounter_document_versions`: el original sigue siendo `kind=original`, versión 1. Cada adenda es `kind=addendum`, con secuencia 2, 3, etc., `parent_version_id` apuntando al original y esquema canónico 2. `rectifies_version_id` referencia el documento corregido, cuando corresponde. La secuencia ordena documentos independientes: no reemplaza versiones previas.
- `encounter_signatures`: una firma por versión documental. Se reutilizan identidad cifrada, fecha UTC del servidor, método FES, autenticación por contraseña, aceptación versionada, hash y referencias a activos visuales opcionales.
- `encounter_signing_operations`: `purpose=original|addendum` y referencia al padre impiden reutilizar preparaciones entre rutas. Preparación de diez minutos, estado y recuperación de archivos permanecen compartidos.

El contenido cifrado de cada adenda incluye tipo, texto, identidad del autor, contexto histórico del paciente/sede/especialidad, documento y hash original, documento y hash anterior, secuencia y corrección completa cuando corresponde. Los originales existentes mantienen sus bytes, cifrado, esquema y hash: no se recalculan ni reescriben.

`SigningOperationManager` centraliza preparación, bloqueo, propósito, aceptación y comparación de hashes. `SignedDocumentWriter` centraliza versión/evidencia y confirmación de la operación. Ambos flujos reutilizan serializador, verificador de contraseña, límites, proveedor FES y almacenamiento cifrado. No existe un sistema paralelo de firmas.

## Concurrencia e integridad

El servicio bloquea la operación y la atención, vuelve a comprobar al firmante original, verifica la contraseña y reconstruye el contenido. La referencia al último documento firmado debe seguir siendo la misma: si otra adenda se confirmó, se exige nueva vista previa. La siguiente secuencia se asigna bajo el bloqueo común; la restricción única `(encounter_id, version)` y la unicidad de firma/operación son barreras adicionales.

El trigger `vita_version_insert` valida contexto, tipo, padre, secuencia y destino de rectificación. `vita_signature_insert` distingue original (atención en curso) de adenda (atención finalizada, padre original firmado y mismo firmante). Las restricciones de inmutabilidad, observaciones sin firma, adjuntos y borrados relacionados permanecen activas. No se agrega ninguna ruta de edición/eliminación de adendas.

El verificador comprueba el contenido, referencias al original/anterior, secuencia, autor y texto anterior de las rectificaciones. La verificación por colección reutiliza resultados para no descifrar repetidamente la misma cadena dentro de una lectura.

Los adjuntos originales permanecen en sus copias cifradas históricas y se verifican al preparar/confirmar. Esta entrega no incorpora carga de nuevos archivos clínicos en adendas. Los activos visuales opcionales actuales se copian de forma independiente para cada adenda; no se vuelven a copiar todos los adjuntos de la atención. Un fallo deja la operación preparada para recuperación; `signatures:cleanup` únicamente limpia operaciones expiradas sin firma confirmada, protegiendo archivos del original y de adendas ya confirmadas.

## Historial y exportaciones

El historial muestra original y adendas por separado, con tipo, secuencia, autor, fecha y estado FES. El original se obtiene exclusivamente de su versión `kind=original`, no de la firma más reciente.

PDF/A incorpora cada adenda como un bloque documental independiente, con referencia, rectificación y hash. FHIR R4 incorpora `DocumentReference` por original/adenda, con el JSON canónico en `content.attachment`, autor/fecha y relaciones `appends` al original y al documento rectificado cuando corresponde. No altera `ClinicalImpression.summary` para introducir el texto de una rectificación. El conjunto de la ficha no se presenta como firmado globalmente; no se genera una firma criptográfica FHIR o PDF.

## Migración y despliegue

Migración aditiva, posterior a las migraciones FES existentes:

```sh
php artisan migrate
```

Agrega una referencia nullable a versiones, propósito/padre a operaciones y actualiza los triggers. Se requiere privilegio MySQL `TRIGGER`. Desplegar código y migración coordinadamente, con tráfico suspendido durante el cambio de triggers: MySQL no garantiza una transacción global del DDL. El rollback automático se rechaza para evitar eliminar referencias o dejar firmas confirmadas sin sus protecciones; cualquier reversión requiere una migración posterior auditada. No se ejecutan actualizaciones de contenido clínico ni conversiones de datos históricos.

Conservar scheduler, almacenamiento privado compartido si existen varios servidores y respaldos de MySQL/discos/APP_KEY como describe `docs/encounter-fes.md`. Esta FES no se declara certificada ni equivalente a FEA.

## Pruebas

- `EncounterAddendumTest`: tres tipos, corrección de original/adenda, motivos/apartados inválidos, propósito de operaciones, doble envío, preparaciones obsoletas, aceptación/contraseña/límites/expiración, aislamiento de clínica/sede/autor, superadministrador, protección SQL, conservación exacta del original, archivos temporales/confirmados, historial, PDF real y documentos FHIR separados.
- `EncounterSigningConcurrencyTest`: procesos PHP y conexiones MySQL independientes, incluyendo dos preparaciones de adenda distintas y doble envío de una misma operación. Solo una confirma; el original permanece idéntico. La base temporal copia únicamente estructura y se elimina al terminar.
- Regresiones FES originales, perfil visual y reglas de creación. Fixtures compartidas en `tests/Support/SigningFixtures.php`, revertidas al terminar las pruebas transaccionales.
- Simulaciones JavaScript: modal reutilizable PATCH/POST, errores/cancelación, selección de apartado según documento y seguimiento de cambios sin guardar. No sustituyen revisión manual en navegadores y móviles reales.

## Archivos principales

Nuevos: `EncounterAddendumController`, `EncounterAddendumPolicy`, `EncounterAddendumService`, `EncounterAddendumSnapshotBuilder`, `EncounterAddendumReader`, `SigningOperationManager`, `SignedDocumentWriter`, `SignatureVisualAssets`, migración 140000, vista `customer/patient/addendum`, parciales de vista previa/contenido/historial y `public/js/encounter-addendum.js`.

Actualizados: relaciones `Encounter`/`EncounterDocumentVersion`, servicio FES original, verificador, almacenamiento, modal/JavaScript compartidos, rutas, carga de historial, PDF/FHIR y pruebas de regresión/concurrencia.

Pendiente: nuevos adjuntos propios de adendas, proveedores FEA, otros firmantes o suplencias (requieren nuevas reglas), validación jurídica y firmas criptográficas en exportaciones.
