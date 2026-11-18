# Mi Salud — primera entrega

Cuenta personal gratuita independiente de User y Patient, sin pertenencia a
clínicas, creación de fichas ni vinculaciones automáticas. Las rutas viven en
el dominio principal configurado, bajo `/mi-salud`. En desarrollo:
`http://vita-clinica.test/mi-salud`.

## Funcionamiento

Registro con nombre, correo normalizado, contraseña de al menos 12 caracteres
con letras/números e identificación declarada. Se reutilizan CountryCatalog,
DocumentTypeRegistry e IdentificationService. Chile es el país emisor inicial;
los pasaportes y documentos extranjeros conservan sus caracteres significativos.
Documento válido y correo verificado no equivalen a identidad del titular
acreditada. No se consultan ni vinculan registros clínicos por coincidencia.

Se conserva la cuenta sin verificar hasta completar el enlace de correo.
Puede iniciar sesión, solicitar otro enlace y salir, pero el panel y perfil
requieren verificación. El panel inicial no contiene datos clínicos. El perfil
permite cambiar nombre, documento declarado y contraseña mediante contraseña
actual. El correo permanece de solo lectura en esta primera entrega.

Los documentos se almacenan con IdentifierCrypto (AES-256-GCM y claves HMAC
independientes, versionadas, existentes), con binding de cuenta y registro.
El índice HMAC tiene contexto exclusivo de Mi Salud y de la cuenta: no reserva
globalmente el documento declarado ni compara pacientes entre tenants. Un
cambio de documento retira el anterior, sin eliminarlo. Una columna generada
única garantiza un solo identificador actual por cuenta. Se conservan las
versiones antiguas de claves necesarias para leer los registros anteriores.

Verificación y recuperación usan tokens aleatorios de 256 bits, solo su hash
SHA-256 en MySQL, propósito separado, vencimiento de 30 minutos, consumo único
transaccional y revocación del enlace anterior al reenviar. GET no modifica
cuentas. El fragmento se borra de la URL antes de confirmar por POST. Si falla
la validación de la contraseña nueva, el token queda solo en la respuesta privada
no-store para reintentar; no se flashea a sesión ni se añade a la URL.

Los correos se envían después del commit, sin colas que persistan el token en
texto plano; se rechazan transportes de logs fuera de pruebas. Un fallo de envío
invalida el enlace y registra un evento seguro, sin imprimir el error original.
Las respuestas de registro duplicado y recuperación son neutrales.

## Autenticación y aislamiento

Guard `mi_salud`, proveedor `portal_accounts`. No se cambia el guard por defecto
`web`, ni se reutiliza su broker de contraseñas. La recuperación del portal se
implementa con los tokens de propósito restringido de esta entrega.

Grupo `mi_salud_web` independiente: cookies cifradas, sesión, CSRF y bindings,
sin IdentifyCustomer ni navegación parcial administrativa. PortalSession exige
dominio principal y configura cookie propia `vita_mi_salud_session`, host-only,
path `/mi-salud`; restaura la configuración al terminar la solicitud.
PortalAccess utiliza explícitamente el guard personal, cuenta activa y versión
de sesión. Cambiar/restablecer contraseña invalida sesiones personales previas.
Salir del portal no invoca logout del guard web. No se agregaron roles o permisos
de clínicas ni accesos a PDF, FHIR, FES, citas o telemedicina.

Las páginas del portal tienen no-store, no-referrer, noindex y CSP con recursos
locales, sin analítica, fuentes o scripts de terceros. Los eventos de auditoría
son append-only y contienen exclusivamente ID de cuenta, acción y fecha.

## Archivos

- Migración: `database/migrations/2026_11_06_000400_create_mi_salud_accounts.php`.
- Modelo: `app/Models/PortalAccount.php`.
- Servicio: `app/Services/MiSalud/PortalAccountService.php`.
- Controlador: `app/Http/Controllers/MiSalud/AccountController.php`.
- Middleware: `app/Http/Middleware/PortalSession.php`, `PortalAccess.php`.
- Correo: `app/Mail/PortalSecurityLink.php`.
- Rutas: `routes/mi-salud.php`; registro del grupo en RouteServiceProvider y Kernel.
- Vistas: `resources/views/mi-salud/{layout,page,identity,email}.blade.php`.
- Recursos locales: `public/css/mi-salud.css`, `public/js/mi-salud.js`.
- Configuración: guard/proveedor en `config/auth.php`; exclusión de security_token
  de flash en `app/Exceptions/Handler.php`.
- Pruebas: `tests/Feature/MiSaludAccountTest.php`.
- Herramientas: `tools/validate-mi-salud.php`, `apply-mi-salud-local.php`,
  `render-mi-salud-preview.php`, `audit-mi-salud-browser.cjs`.

## Migración local y respaldo

Aplicada únicamente `2026_11_06_000400_create_mi_salud_accounts`, creando:
portal_accounts, portal_account_identifiers, portal_auth_tokens y
portal_audit_events. Tres FK RESTRICT, dos CHECK y dos triggers de inmutabilidad
de auditoría, además de los índices y la restricción de documento actual.
La reversión se niega si hay cuentas o auditoría; no se borran datos para forzarla.

Antes de aplicarla se verificaron host local, vita_clinica, MySQL 8.0.30 Laragon
y collation utf8mb4_0900_ai_ci. Respaldo completo con estructura, datos,
procedimientos, eventos y triggers:
`C:/laragon/backups/vita-mi-salud-stage1/20261106_181402_2d820a`.
ACL restringida, configuración de recuperación fuera del repositorio.
Restaurado y comparado mediante metadatos efectivos y datos en
`vita_identification_restore_mi_salud_20261106_181402_6cbcf7`.
La migración se ensayó en esa copia. El resultado local coincidió con el ensayo;
tablas, datos y objetos anteriores permanecieron intactos, al igual que `.env`.
Evidencia: `docs/verification/mi-salud-stage1-deployment.json`.

## Verificación

Las pruebas comprueban cuentas chilenas/extranjeras, ceros iniciales, validación,
documentos declarados repetidos sin bloqueo global, correo duplicado neutral,
misma dirección en User y PortalAccount con credenciales independientes,
cifrado, ausencia de escrituras clínicas, verificación/recuperación, expiración,
reenvío, replay, cambio de perfil, revocación de sesiones, CSRF, cookies,
dominios, throttling, auditoría inmutable, fallo atómico y configuración de claves
inválida. Solo se ejecutan con autorización y nombre de base MySQL aislada.

Se corrigió la colisión de contadores entre rutas de autenticación usando un
prefijo de rate limiting distinto por acción. La regresión amplia previa al
despliegue pasó: 351 pruebas, 4.317 aserciones, dos omisiones históricas conocidas,
52,146 s y 90 MB. Las 17 pruebas específicas posteriores al ajuste de reintento
de contraseña pasaron con 187 aserciones. Los resultados posteriores al despliegue
están en `storage/logs/mi-salud-postdeployment-regression.txt`.

Resultado final posterior al despliegue: **352 pruebas, 4.328 aserciones, cero
fallos, dos omitidas; 51,702 s y 90 MB**, en
`vita_identification_test_mi_salud_20261106_181517_cbb0d5`. Las huellas de la base
habitual y `.env` permanecieron intactas. Las omisiones corresponden a fixtures
preexistentes de EncounterSigningTest (atención histórica finalizada) y
SuperAdminBranchEditTest (SuperAdmin y dos clínicas); no se presentan como
pruebas aprobadas. No se verificó concurrencia específica de tokens del portal
con procesos paralelos; su consumo único se probó secuencialmente y se protege
con bloqueos de cuenta/token dentro de transacciones.

Chrome verificó 16 combinaciones (registro, ingreso, panel y perfil a 390, 768,
1024 y 1440 px): sin overflow horizontal y con etiquetas en controles. Se
inspeccionaron capturas de móvil y escritorio. El enlace sintético elimina el
fragmento, hace un único POST y no envía tokens en URLs/cabeceras ni carga recursos
externos. Son vistas reales renderizadas con datos sintéticos, no sesiones reales
de personas. Artefactos en `storage/framework/testing/mi-salud-preview`.

HTTP local: registro 200; panel sin sesión 302. No se crearon cuentas sintéticas
en la base habitual. No se alteró PatientRegistrationService ni sus canales:
internal y viti habilitados; public_booking deshabilitado.

## Pendiente

- Entrega real SMTP y recorrido de enlaces con HTTPS de producción: no se
  presentan como probados. El transporte local configurado es SMTP; las pruebas
  automatizadas usan Mail::fake. Configurar cookies Secure y revisar entrega,
  redacción de observabilidad y seguimiento de enlaces antes del despliegue público.
- El cambio de correo, MFA, representantes y gestión de sesiones por dispositivo
  no forman parte de esta entrega.
- No hay vinculaciones, consultas clínicas, reservas ni telemedicina desde el
  portal. El buscador público existente sigue en la página principal.
