# Etapa 3 — clientes, usuarios y perfiles

Actualización: los dos bloqueos fueron resueltos posteriormente. Ver
[informe del cierre](international-identification-stage3-closure.md), que contiene
la migración aplicada, pruebas y limitaciones vigentes. El texto siguiente conserva
el diagnóstico y resultado de la entrega inicial.

Estado: integración de código implementada y probada en MySQL aislado. **No se aplicaron migraciones en `vita_clinica`, no se modificó `.env` ni se importaron documentos históricos.** Dos límites de compatibilidad se describen más abajo; no se consideran habilitados los flujos bloqueados.

## Actividad clínica previa

Se revisaron el diagnóstico anterior, los fixtures y las guardas de aislamiento de las pruebas de identificación. El paciente incorporado durante la comprobación anterior no coincidía con sus fixtures y las pruebas usan esquemas con prefijo `vita_identification_test_*`. Esto no permite establecer de manera concluyente el origen de aquella petición: no se dispone de una correlación de solicitud que permita atribuirla. No se afirma que la haya creado el usuario ni se modifica o elimina ningún registro.

Por esa incertidumbre se continuó únicamente con código y bases aisladas. El arnés compara todas las tablas habituales mediante estructura, conteos y checksums, además de los triggers y la huella de `.env`. En la ejecución final ambos indicadores fueron `true`: `habitual_database_unchanged` y `env_unchanged`.

El primer arranque del arnés encontró una referencia de Schema previamente resuelta antes de cambiar de conexión y falló por una tabla ya existente. La comparación confirmó que no hubo cambios en desarrollo. Se corrigió el arnés para seleccionar la base de pruebas **en el entorno de un proceso nuevo, antes de arrancar Laravel**, evitando reutilizar conexiones de esquema. No se intentó reparar ni restaurar datos de desarrollo.

## Funcionalidad

- Clientes: país emisor, persona natural/jurídica, tipo tributario y número; creación y edición desde SuperAdmin. Varias clínicas pueden compartir identificador tributario.
- Administrador inicial y administradores existentes del cliente: identificación personal mediante el mismo núcleo y repositorio.
- Profesionales/administrativos: formulario internacional en crear/editar y consulta autorizada desde el modal de detalle.
- Perfiles de clínica y SuperAdmin: consulta del principal y actualización explícita según la autorización existente. En clínica, el usuario sin rol administrativo en su sede solo puede consultar su documento; la protección también existe en el backend.
- Nuevos formularios: Chile predeterminado. Históricos sin clasificación: país/titular no inferidos; los controles empiezan sin esa clasificación y requieren una actualización explícita.
- Países del `CountryCatalog` existente, búsqueda por nombre/código sin distinguir tildes y selector HTML nativo utilizable mediante teclado. Labels, foco visible y cuadrícula responsive; estilos encapsulados y clases `form-control`/`form-select`, reutilizando Alpine y el tema. No se instaló otro framework ni un mantenedor.
- Cambiar un documento nunca marca la identidad como verificada. El país emisor no sustituye nacionalidad ni residencia.

Los controles de país/tipo/número usan un componente compartido. Las consultas privadas obtienen solo la proyección autorizada de identificación, con Cache-Control `no-store`; no agregan documentos descifrados a todos los registros del DataTable. Se protege la selección asíncrona de administradores para que una respuesta anterior no sobrescriba la selección más reciente.

## Persistencia y concurrencia

`IdentificationForms` adapta las peticiones al `IdentificationService`, que reutiliza `DocumentTypeRegistry`, y escribe a través de `IdentifierRepositoryFactory`/`IdentifierRepository`.

- Números originales/normalizados continúan cifrados con AES-GCM; búsquedas de equivalencias por HMAC exacto. No hay consultas LIKE sobre valores cifrados.
- Una transacción incluye cambios de cliente/usuario y su identificación. Se bloquea la raíz de la clínica (o usuario global), igual que el repositorio.
- La referencia de principal esperada evita sobrescrituras desde formularios desactualizados; si cambia, se rechaza y se revierte toda la operación.
- Cambiar el principal conserva los documentos previos y su auditoría cifrada. Reingresar un documento activo del mismo titular lo reutiliza, sin duplicarlo. Un retirado no puede recrearse eludiendo su reserva.
- No se introduce unicidad tributaria global entre clientes ni una nueva unicidad entre cuentas de usuario. Se conservan los ámbitos aprobados en etapa 2.
- La autorización propia de administradores de sede se alinea con el perfil existente; para otros usuarios se sigue exigiendo la administración de profesionales y el contexto válido de clínica/sede. No se crean nuevos roles ni permisos.
- Las asignaciones del rol Doctor se serializan con los cambios de identificación y comprueban su compatibilidad en las dos rutas existentes, evitando un atajo por administración de roles.

Los números internacionales no se recortan mediante el middleware genérico: se excluyen del trim y pasan por las reglas centrales. También se excluyen del flash de sesión al fallar validaciones. No se imprimen documentos ni claves en mensajes de error.

## Compatibilidad y bloqueos

### customers.rut

La columna histórica es NOT NULL. Para permitir clientes nuevos sin RUT chileno real se preparó:

`2026_11_06_000001_allow_absent_legacy_customer_rut.php`

Solo admite NULL en esa columna conservando tipo, charset y collation; no cambia valores existentes. Verifica el esquema esperado antes del ALTER. Su reversión se rechaza si existen clientes sin RUT, para no perder su identificación internacional.

**Solo se aplicó en las bases aisladas. En desarrollo, crear un cliente extranjero sin RUT chileno permanece bloqueado con un error explícito hasta aplicar esta migración.** No se guardan números extranjeros, ceros ni marcadores falsos en `customers.rut`.

Para usuarios no se necesita un cambio de esquema: `users.rut` ya admite NULL.

Un RUT chileno nuevo o actualizado explícitamente se valida con módulo 11 y se refleja en el campo legado con formato chileno. Una identificación extranjera se guarda exclusivamente cifrada; un valor legado anterior se conserva sin reinterpretarlo. Las ediciones de contacto que no actualizan identificación no reescriben `rut` ni migran el documento.

La búsqueda general de las tablas continúa usando sus campos existentes; no se añadió un buscador global de documentos descifrados. La búsqueda exacta de equivalencias para persistencia y consulta del repositorio sigue usando HMAC.

### Identidad del médico en FES

`EncounterSnapshotBuilder` y `EncounterAddendumSnapshotBuilder` todavía incluyen `professional.rut` desde `users.rut`; la vista de firma visual también usa ese dato. No se modificaron esos archivos ni las exportaciones.

**Se bloquea crear/asignar el rol Doctor con un nuevo documento principal extranjero o pasaporte, y cambiar a ese tipo la identificación de quien ya tiene el rol Doctor.** Se permite el flujo chileno y la identificación internacional de administrativos y otros usuarios sin ese rol. Los históricos sin principal nuevo permanecen sin reinterpretación.

Habilitar ese flujo médico extranjero requiere un diseño posterior para la representación internacional de la identidad en documentos nuevos, preservando los snapshots firmados existentes. No se implementa de manera implícita en esta etapa ni se conceden permisos médicos por seleccionar un documento.

## Archivos

Nuevos:

- `app/Services/Identification/IdentificationForms.php`.
- `resources/views/components/identification-fields.blade.php`.
- Migración de nulabilidad descrita arriba.
- `tests/Integration/Identification/IdentificationFormsTest.php`.
- `tests/Browser/identification-fields.test.cjs`.
- `tools/validate-identification-stage3.php`.
- Este informe.

Modificados:

- `app/Services/Identification/Storage/IdentifierRepository.php`: lectura autorizada del principal con proyección limitada.
- `app/Services/Identification/Storage/VitaIdentifierAuthorizer.php`: coherencia con la edición propia de administradores de sede.
- Controladores `superadmin/CustomerController`, `superadmin/ProfileController`, `customer/ProfessionalController`, `customer/ProfileController` y `customer/RolePermissionController`.
- `routes/web.php`: GET privados de identificación en los grupos de autorización existentes.
- `app/Exceptions/Handler.php` y `app/Http/Middleware/TrimStrings.php`: tratamiento exclusivo de los nuevos campos.
- Vistas de clientes, detalle de cliente, profesionales y perfiles de clínica/SuperAdmin.
- `tests/Feature/CustomerAdministratorOnboardingTest.php`: fixtures con RUT válido y conservación del documento del administrador seleccionado.

Sin modificaciones en el catálogo de países, reglas centrales de normalización, modelos de pacientes, reservas, Viti, documentos firmados, PDF/FHIR o portal global.

## Pruebas ejecutadas

Base final: `vita_identification_test_stage3_20261106_002512_be6399`, reconstruida con migraciones en proceso aislado.

- 185 pruebas PHP ejecutadas: **184 aprobadas y 1 omitida**, 2.776 assertions, sin fallos. Tiempo PHPUnit 27,633 s; memoria 82 MB.
- La omitida es `EncounterSigningTest::test_existing_unsigned_history_remains_unchanged_and_is_not_signed_retroactively`: el clon limpio no contiene atenciones históricas finalizadas sin firma. No se presenta como aprobada ni se copió información clínica real para ejecutarla.
- Incluye 17 pruebas nuevas de formularios, núcleo y almacenamiento previos, concurrencia MySQL real de identificadores y regresiones de administradores, perfiles, firma visual, firma de atenciones y adendas.
- 3 pruebas JavaScript aprobadas: valores predeterminados/búsqueda, aislamiento de respuestas asíncronas y fallo de carga privada.
- Sintaxis PHP y formato de los archivos revisados.
- No se realizó una prueba visual interactiva en navegador ni una certificación de accesibilidad. Los controles usan semántica nativa, etiquetas y foco visible; queda pendiente comprobar su interacción visual en el entorno del usuario.

Informe de aislamiento: `storage/logs/identification-stage3-vita_identification_test_stage3_20261106_002512_be6399.json`.

No se ejecutó la suite completa de la aplicación. El test general de esquema con contadores fijos ya señalado en etapa 2 deberá revisarse junto con la aprobación de nuevas migraciones; no se modificó para ocultar diferencias.

## Pendiente antes de considerar todos los flujos habilitados

1. Revisar/aprobar la aplicación de la migración compatible en la base habitual, con respaldo controlado. Esta ejecución deliberadamente no lo hace por la incertidumbre del origen de actividad previa.
2. Decidir y autorizar la adaptación futura de la identidad internacional en FES antes de habilitar documentos extranjeros para Doctor.
3. Comprobar visualmente los formularios y la navegación por teclado.

No se avanzó a pacientes, reservas públicas, Viti, exportaciones ni portal global.
