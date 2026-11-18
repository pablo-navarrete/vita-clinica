# Mi Salud: agendamiento público — etapa 4

La integración utiliza el formulario y el controlador públicos existentes. No habilita el canal `public_booking`, no altera FES y no concede acceso a fichas clínicas.

## Flujo

1. «Continuar con Mi Salud» guarda únicamente la selección de atención en una solicitud cifrada de 15 minutos, ligada al cliente y a la sesión de la web de esa clínica.
2. El dominio central autentica con `mi_salud` y conserva el identificador de la solicitud durante el inicio de sesión. Se exige correo realmente verificado: la vista previa local no lo sustituye.
3. El titular autoriza usar sus datos para esa reserva. No necesita activar la opción de compartir sus datos básicos con personal de clínicas.
4. Se emite un código de 256 bits con vencimiento de cinco minutos. El código permanece cifrado en el backend y su hash se reserva para un solo canje. No aparece en URLs, HTML ni formularios del navegador.
5. El retorno lleva solamente el identificador de la solicitud. Una página sin recursos externos ejecuta un POST con CSRF. El backend verifica cliente, sesión, expiración, estado e identidad antes de canjear el código, que después se elimina del payload.
6. El mismo formulario oculta los campos personales ya disponibles, conserva la atención seleccionada y solicita los datos obligatorios faltantes. Los datos disponibles se vuelven a imponer desde el servidor, independientemente de cambios en el navegador.
7. La confirmación vuelve a validar sede, profesional, especialidad, horarios, ausencias y ocupación. Utiliza `PatientRegistrationService`, la transacción existente y el mismo estado de cita y notificaciones.

La cookie `vita_mi_salud_session` mantiene dominio nulo y ruta `/mi-salud`. No se comparte con subdominios. Los destinos proceden de la configuración del cliente; no se acepta una URL de retorno libre.

## Identificación y reutilización

- Paciente nuevo: identificación cifrada/HMAC, ficha local, auditoría y vínculo candidato mediante los servicios existentes. No se importan datos clínicos.
- Paciente existente: puede reutilizarse con evidencia de una reserva Mi Salud anterior confirmada para ese mismo documento y cliente, o con el recibo de contacto verificado que ya admitía el servicio público.
- Una coincidencia documental candidata no confirma titularidad. Documentos retirados, duplicados, cuentas ambiguas y registros históricos sin evidencia suficiente no generan otro Patient ni permiten reservar a nombre de un tercero. El usuario puede continuar con la verificación tradicional; cuando tampoco exista evidencia confiable, se requiere resolución autorizada con la clínica. No se presenta la coincidencia como identidad acreditada.
- Nunca se confía en `patient_id` o `portal_account_id` proporcionados por el navegador.

Una reserva confirmada conserva solo la evidencia documental cifrada necesaria para reutilizaciones posteriores. Las solicitudes abandonadas vencidas se depuran desde el comando de limpieza existente; no se eliminan reservas confirmadas ni datos clínicos.

## Concurrencia

Se mantiene un orden de bloqueos cuenta → clínica → autorización → intento → profesional. La consulta de ocupación utiliza una lectura bloqueante actual para no reutilizar una vista anterior bajo MySQL REPEATABLE READ. La prueba con dos procesos y dos conexiones MySQL comprueba contención real y exige exactamente una confirmación para el último horario.

## Archivos de implementación

- `database/migrations/2026_11_08_000300_create_portal_booking_authorizations.php`: única tabla nueva, claves foráneas restrictivas, índices y reversión protegida si existe evidencia.
- `app/Services/MiSalud/PortalBookingService.php`: solicitudes, autorización, canje, validación de identidad, evidencia y depuración.
- `app/Http/Controllers/MiSalud/BookingAuthorizationController.php` y `resources/views/mi-salud/booking-authorization.blade.php`: autorización del titular.
- `app/Http/Controllers/MiSalud/AccountController.php` y `app/Http/Middleware/PortalSession.php`: retorno tras autenticación y nombre efectivo de cookie independiente, incluso con stores previamente resueltos.
- `app/Http/Controllers/customer/PublicBookingController.php`: entrada/retorno y confirmación reutilizando el motor público existente.
- `app/Services/Patients/PublicPatientRegistration.php` y `PatientRegistrationService.php`: contacto verificado desde autorización del titular y reutilización con evidencia.
- `resources/views/customer/web/booking.blade.php`, `portal-booking-return.blade.php` y `public/js/portal-booking-form.js`: experiencia pública y datos faltantes.
- `routes/web.php`, `routes/mi-salud.php` y `app/Exceptions/Handler.php`: rutas limitadas y protección de campos sensibles.
- `tests/Feature/PortalPublicBookingTest.php`, `tests/Support/portal-booking-worker.php` y `tests/Browser/portal-booking-form.test.cjs`: pruebas de integración, concurrencia y UI.
- `tools/audit-portal-booking-browser.cjs`: comprobación sintética en Chrome, sin sesiones reales.
- `tools/apply-portal-booking-local.php`: respaldo, restauración verificada y ensayo reversible antes de aplicar exclusivamente la migración local.

## Verificación y despliegue

Las pruebas modificadoras se ejecutan exclusivamente en una base `vita_identification_test_*`. El informe de despliegue local registra el respaldo recuperable, la base restaurada y la comparación de tablas y triggers existentes. Los reportes de Chrome y capturas sintéticas se generan en `storage/framework/testing/portal-booking-preview/`.

Para habilitar producción siguen siendo necesarias la comprobación de HTTPS, correo real y la configuración operacional del canal público. Esta etapa conserva `internal=true`, `viti=true`, `public_booking=false`.

### Resultados ejecutados en desarrollo

- Regresión final en MySQL aislado: **76 pruebas, 1.483 aserciones, sin fallos ni omisiones**. Incluye `PortalPublicBookingTest`, `PatientRegistrationStage5Test`, `AgendaPatientCreationTest`, `PortalProfileSharingTest`, `MiSaludAccountTest` y `PortalPatientLinkTest`.
- JavaScript: **20 pruebas aprobadas**, incluyendo los formularios compartidos, el modal de Mi Salud y la continuidad de la agenda interna.
- Chrome con HTML sintético renderizado por Blade: **390, 768, 1024 y 1440 px**, sin desbordamientos; selección restaurada, campos disponibles ocultos y aviso de horario retirado. La disponibilidad se simula en la prueba visual; los controles de reserva reales se ejercitan por separado contra MySQL aislado.
- Dos procesos MySQL reales compitieron por el mismo horario: exactamente una reserva confirmada. La regresión detectó y permitió corregir una lectura antigua de ocupación bajo REPEATABLE READ.
- Migración aplicada en `vita_clinica`: `2026_11_08_000300_create_portal_booking_authorizations`. Únicamente se agregó `portal_booking_authorizations`, sin triggers nuevos ni cambios en las tablas y triggers anteriores.
- Respaldo: `C:/laragon/backups/vita-portal-booking/20261108_043316_64f9e7/database.sql`. Restaurado y comparado satisfactoriamente en `vita_identification_restore_booking_20261108_043317_c5cf14`; también se ensayó la reversión sobre la tabla vacía.
- Verificación local posterior: tres claves foráneas `RESTRICT`, índice único de operación y los índices previstos. La tabla nueva contiene **cero filas**: las pruebas no insertaron pacientes ni autorizaciones sintéticas en la base habitual.
- Correo real y HTTPS no se presentan como verificados en esta entrega. El canal público permanece deshabilitado.
