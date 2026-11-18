# Recuperación del acceso — 15 de noviembre de 2026

## Causa confirmada

Durante la validación del cambio de teléfonos se ejecutó `ProfileTest`, que utiliza
`RefreshDatabase`. `phpunit.xml` no definía una base exclusiva de pruebas, por lo que
Laravel tomó `vita_clinica` desde `.env` y reinició sus tablas.

La primera operación destructiva está en `binlog.000295`, evento DROP TABLE en posición
4971, precedido por el evento de transacción en posición 4892. El reinicio ocurrió el
14 de noviembre de 2026 a las 14:48:53 según el horario local de mysqlbinlog.
Esto eliminó los registros de clínicas y usuarios; las rutas de subdominios dejaron
de encontrar su clínica. DNS y Apache estaban funcionando.

## Recuperación

Se preservó el estado afectado, el archivo de configuración y los binlogs en:

`C:/laragon/backups/vita-access-recovery/20261115_162710/`

El directorio tiene permisos restringidos al usuario de Windows y SYSTEM. Contiene
datos privados y material de recuperación; no debe publicarse ni incorporarse a Git.

Se verificó el SHA-256 del respaldo del 8 de noviembre:

`ef882c66e0bfa5b3ea450041fb2dffacf2f782bc6289f643bcc4ea7ed60fca50`

Se restauró en una base separada, `vita_recovered_20261115_162754`, y se reprodujeron:

1. `binlog.000294` desde posición 63265557, inicio de la migración posterior al respaldo.
2. `binlog.000295` hasta posición 4892, excluyendo el primer reinicio de las pruebas.

El replay se limitó y reescribió al esquema de recuperación. Se rechazaron destinos
ajenos y operaciones CREATE/DROP DATABASE antes de aplicarlo. La base afectada no fue
sobrescrita. `.env` apunta ahora a `vita_recovered_20261115_162754`; no volver a apuntarlo
a la base vacía `vita_clinica`.

Se recuperaron 1 clínica activa, 2 usuarios, 3 pacientes, 1 perfil del directorio y
1 cuenta Mi Salud. El punto recuperado no contiene citas, atenciones ni reseñas.
Se verificaron 215 relaciones de claves foráneas, sin filas huérfanas, y la lectura
de dos identificadores cifrados de pacientes con las claves existentes.

Se comprobaron respuestas HTTP 200 para `/login` en el dominio principal y para `/`
y `/login` en `clinica1.vita-clinica.test`. No se cambiaron contraseñas de usuarios.

## Prevención

`phpunit.xml` establece `DB_DATABASE=vita_testing`. `Tests/CreatesApplication` invoca
`TestingDatabaseGuard` antes de ejecutar los traits de pruebas. El guard rechaza la
base de la aplicación, la recuperada y cualquier nombre no autorizado; solo permite
esquemas MySQL expresamente identificados para pruebas o SQLite en memoria/archivo
dedicado. El bloqueo no depende de que `APP_ENV` tenga el valor correcto.

Las pruebas de autenticación y perfiles se ejecutan en `vita_testing`. Las bases de
desarrollo y recuperación no deben utilizarse en comandos de pruebas ni en sus
subprocesos. La prueba unitaria `TestingDatabaseGuardTest` cubre el rechazo de ambos
esquemas y la aceptación de destinos aislados.
