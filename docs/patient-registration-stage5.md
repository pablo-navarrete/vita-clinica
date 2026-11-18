# Registro central de pacientes: regresión de etapa 5

## Reactivación de Viti interno (6 de noviembre de 2026)

Por autorización expresa se habilitó `channels.viti=true`, conservando
`internal=true`, `public_booking=false` y la bandera general habilitada.
Se comprobó la configuración efectiva en un proceso nuevo sobre el entorno
local `vita_clinica`: interno/Viti habilitados y público bloqueado con 503.
No había caché de configuración, por lo que no fue necesario limpiarla.

La revisión confirmó que Viti registra exclusivamente mediante
PatientRegistrationService, con actor autenticado, patients.create, contexto
de clínica/sede, confirmación explícita, validación central y reserva de
unicidad. Los documentos retirados o históricos ambiguos siguen bloqueados.
No requiere un enlace de verificación por correo ni HTTPS público para el alta
autenticada. No se cambiaron reservas públicas ni otras funcionalidades.

Se amplió la prueba conversacional hasta la confirmación de cita: un solo
paciente nuevo, un identificador y una cita, sin creación previa a sus respectivas
confirmaciones. La primera regresión completa encontró únicamente un parámetro
`customer` ausente en la ruta simulada del fixture al generar el enlace de agenda;
se corrigió el fixture sin cambiar el código funcional.

La repetición de los conjuntos afectados pasó íntegramente en
`vita_identification_test_stage3_20261106_174803_4b8bda`: **48 pruebas, 471
aserciones, cero fallos u omisiones; 19,737 segundos, 64 MB**. Incluye registro
etapa 5, integración de pacientes, conversación Viti y control arquitectónico
de escritores, además de las cuatro carreras reales MySQL. El ensayo completo
previo confirmó que la base habitual y `.env` permanecieron intactos. No se
crearon pacientes locales adicionales ni se aplicaron migraciones en esta
reactivación. Registro: `storage/logs/viti-registration-enable-targeted.txt`.

## Cierre y despliegue local (6 de noviembre de 2026)

La migración `2026_11_06_000300_create_patient_registration_attempts` quedó
aplicada en `vita_clinica`, lote 8. No quedan migraciones pendientes. Se confirmó
MySQL 8.0.30 de Laragon y collation utf8mb4_0900_ai_ci, sin cambiar propiedades
de tablas anteriores. El respaldo completo con datos, estructura, procedimientos,
eventos y triggers está en
`C:/laragon/backups/vita-patient-registration-stage5/20261106_173359_0ca1c6`.
El directorio tiene ACL restringida e incluye configuración de recuperación;
sus secretos no se copiaron al repositorio ni se imprimieron.

Se restauró en `vita_identification_restore_stage5_20261106_173359_115ed1`;
metadatos efectivos y datos coincidieron. La migración se ensayó sobre esa copia
antes de aplicarla localmente. El resultado local coincidió con el ensayo:
cuatro CHECK exigibles, cuatro FK con RESTRICT, índices y tres triggers nuevos.
Las tablas, filas y objetos anteriores quedaron intactos. Evidencia en
`docs/verification/patient-registration-stage5-deployment.json`.

La regresión posterior al despliegue se ejecutó en
`vita_identification_test_stage3_20261106_173629_459c2c`: **335 pruebas, 4.135
aserciones, cero fallos, dos omitidas; 60,430 segundos y 90 MB**. Las dos
omisiones son las descritas más abajo. Las huellas de la base habitual y de
`.env` se conservaron durante esa ejecución. La primera ejecución coincidió
temporalmente con la migración autorizada, por lo que su control de huella
detectó ese cambio esperado; se repitió íntegramente una vez terminada la
migración para obtener esta verificación estable.

Se retiró LegacyPatientWriterGuard tras comprobar que no tenía dependencias.
La protección efectiva reside en PatientRegistrationService, con habilitación
por canal: **internal=true, viti=false, public_booking=false**, además de la
bandera general habilitada. Las peticiones de los canales deshabilitados fallan
con 503; la prueba nueva comprueba que habilitar interno no abre el público.
No hay otro creador de pacientes en el código de aplicación.

Una corrección de compatibilidad evita depender de crypto.randomUUID en HTTP
local: usa getRandomValues para UUID v4 cuando la API nativa no está disponible.
Dos pruebas Node verifican ambas variantes. La auditoría Chrome del enlace
público volvió a pasar, pero no habilita el canal ni sustituye SMTP/HTTPS real.

Después de habilitar interno se realizó una comprobación operativa desde
terminal, invocando el controlador real con un usuario y sede existentes
autorizados, sin cambiar permisos. **No fue una inspección visual del navegador.**
Se conservaron dos pacientes sintéticos identificados como verificación de etapa
5: IDs **58 (CL)** y **59 (pasaporte AR)**, con contactos no reales. Ambos tienen
identificador cifrado recuperable, coincidencia HMAC, ficha y dos auditorías;
el segundo no tiene RUT ficticio. Se compararon byte a byte las huellas de todas
las filas preexistentes antes/después: ninguna cambió, incluidos documentos
firmados y sus hashes. Evidencia en
`docs/verification/patient-registration-stage5-local-check.json`.

No se modificaron FES ni Mi Salud. Los canales públicos/de correo y las altas
de Viti permanecen apagados por decisión del despliegue interno: falta comprobar
entrega real y recorrido HTTPS correspondiente. Los conflictos históricos siguen
requiriendo revisión y no se fusionan. No queda una migración pendiente de etapa
5. Las secciones siguientes conservan el informe de la regresión previa.

---

Validación del 6 de noviembre de 2026. **Altas deshabilitadas** mediante
`config/patient_registration.php`. No se aplicaron migraciones a `vita_clinica`.
No se avanzó al portal Mi Salud.

## Resultado medido

`tools/validate-identification-stage3.php` creó y migró exclusivamente
`vita_identification_test_stage3_20261106_162544_dc85ab`, en MySQL local.
Resultado: 334 pruebas, 4.130 aserciones, cero fallos, dos omitidas;
48,582 segundos y 90 MB de memoria máxima informada por PHPUnit.
Las huellas de datos, estructura y triggers de la base habitual, y la huella
de `.env`, coincidieron antes y después.

Omisiones: una prueba de EncounterSigningTest requiere una atención histórica
finalizada preexistente; SuperAdminBranchEditTest requiere SuperAdmin y dos
clínicas preexistentes. No se presentan como aprobadas. La suite incluye además
fixtures sintéticos para compatibilidad documental y aislamiento.

## Verificaciones críticas

- Registro interno y Viti utilizan PatientRegistrationService. La única creación
  de Patient en `app` está dentro del servicio. PatientWriterArchitectureTest
  revisa patrones de escritura por modelos, alias, relaciones y SQL habituales;
  es una barrera de regresión, no una prohibición universal de SQL futuro.
- Creación de paciente, identificador, ficha y auditoría transaccional; prueba de
  fallo parcial y reintento idempotente. Identificación cifrada y búsqueda HMAC,
  documentos retirados reservados y coincidencias históricas enviadas a revisión.
- Cuatro carreras con procesos PHP independientes, observando esperas reales de
  bloqueo en performance_schema: interno/Viti, interno/público, público/público
  y consumo simultáneo del mismo token. Un solo paciente/identificador y una
  sola cita cuando corresponde. Dos solicitudes por el último horario vuelven
  a consultar disponibilidad bajo bloqueo antes de crear paciente y cita.
- Solicitar un enlace no crea paciente ni retiene horario. Verificar contacto
  no equivale a verificar identidad documental. Coincidencia histórica o un
  correo nuevo no autoriza reutilizar una ficha. Las respuestas públicas son
  neutrales y no devuelven datos clínicos ni identificadores de pacientes.
- Token aleatorio de 256 bits: persistencia SHA-256, vencimiento a 30 minutos,
  límite de intentos y consumo único. El GET no confirma nada; se requiere POST.
  El correo se envía después del commit; no se usa el transportador de logs.
- La pantalla de verificación es independiente, sin analítica ni recursos
  externos, CSP restrictiva, no-referrer y no-store. Borra el fragmento mediante
  history.replaceState y transmite el token únicamente en el cuerpo del POST.
- `node tools/audit-registration-browser.cjs` verificó la respuesta HTML real con
  un token sintético y Chrome local: fragmento eliminado, un solo POST, ningún
  token en URL/cabeceras y ninguna solicitud externa o de analítica. No se
  contactaron pacientes reales. No se certifica configuración de terceros:
  antes de producción deben excluirse cuerpos/tokens de APM, proxies, tracing
  y seguimiento de enlaces del proveedor de correo.
- La limpieza solo elimina intentos públicos vencidos y no vinculados después
  de la retención configurada. Conserva recibos confirmados y registros clínicos.

## Archivos principales

Servicios nuevos en `app/Services/Patients`: PatientRegistrationService,
PatientIdentityResolver y PublicPatientRegistration; parser compartido
`app/Services/Identification/PatientDocumentMention.php`.
Integración en PatientController, PublicBookingController, VitaAssistantController,
VitiBookingConversation, VitiAppointmentCancellation e IdentifierRepository.
Controles auxiliares en AppointmentController, PatientIdentification,
PatientAuditLogger y Handler. Rutas en routes/web.php; formularios de pacientes
y reservas, página independiente de verificación, Mailable y plantilla de correo.
Configuración patient_registration y comando CleanupPatientRegistrations con
su programación. No se modificó lógica FES para esta etapa.

Pruebas principales: PatientRegistrationStage5Test, PatientWriterArchitectureTest,
PatientIdentificationIntegrationTest, VitiBookingConversationTest y
FileSpecialtyCatalogTest. Se corrigió un fixture de especialidad que usaba un
RUT ficticio, manteniendo la validación documental real.

## Pendiente antes de habilitar

1. Aplicar únicamente `2026_11_06_000300_create_patient_registration_attempts.php`
   después del respaldo recuperable, restauración verificada y ensayo exigidos.
   Crea tabla, índices, CHECK y triggers; todavía no existe en la base habitual.
2. Probar entrega SMTP y recorrido HTTPS reales en sandbox; las pruebas usan
   correo y notificaciones simulados. Revisar rate limiting compartido en
   despliegues con varios procesos/servidores y redacción de observabilidad.
3. Definir el procedimiento administrativo para intentos en revisión. No hay
   una interfaz nueva que resuelva conflictos ni se fusionan pacientes.
4. Conservar versiones antiguas de claves necesarias para recibos cifrados;
   una rotación de esos recibos inmutables requiere procedimiento auditado.
5. Solo después, aprobar el cambio de la bandera de habilitación.

LegacyPatientWriterGuard permanece en el repositorio, sin llamadas activas en
`app`. La regresión respalda retirarlo como código obsoleto; **retirarlo no es
habilitar las altas**. Se conserva hasta cerrar el despliegue controlado anterior.
