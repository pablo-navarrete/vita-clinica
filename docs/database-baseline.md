# Línea base de base de datos — 04/11/2026

Se realizó un reinicio completo de la base local `vita_clinica`, autorizado expresamente por
el propietario aceptando la pérdida de todos sus datos. Esta consolidación no es una
migración incremental para instalaciones pobladas con el historial anterior.

## Estructura

- 80 migraciones de creación, una por tabla, incorporan directamente sus columnas finales,
  valores predeterminados, índices y claves foráneas. No quedan migraciones add/repair/backfill.
- `2026_11_04_000100_install_integrity_triggers.php` instala las 53 protecciones MySQL de firmas,
  adendas, auditoría, condiciones comerciales y pagos. También preserva las restricciones de
  concurrencia mediante índices y columnas generadas en las tablas correspondientes.
- Se mantiene el nombre de migración de personal access tokens de Sanctum para que su
  migración incluida en el paquete no intente crear la misma tabla por segunda vez.
- Se eliminaron las siete tablas obsoletas del catálogo de especialidades: categorías,
  eventos, registro de serialización, alias, correspondencias, eventos de resolución y versiones.
  Las opciones disponibles proceden únicamente de `config/specialties.php`.
- Las migraciones solo inicializan funcionalidades reconocidas (`billing_features`) y el
  registro de serialización `payment_fee_settings`. No crean planes, precios ni clientes ficticios.

Antes de borrar la base se reconstruyó el esquema en una base temporal vacía y se compararon
las columnas, índices, claves foráneas y cuerpos de triggers con el esquema de origen, excluyendo
las tablas descartadas. No se exportaron datos de pacientes ni registros de clientes.

## Instalación vacía

```
php artisan migrate --seed
```

Para reiniciar deliberadamente una instalación local vacía o descartable:

```
php artisan migrate:fresh --seed
```

La segunda orden elimina todos los datos de la conexión configurada. Los seeders existentes
`RoleSeeder` y `SuperAdminSeeder` crean los roles iniciales y el SuperAdmin de desarrollo
`admin@vita-clinica.cl`. No se restauran clínicas, pacientes, citas, documentos ni pagos.
La contraseña sigue siendo la definida en `SuperAdminSeeder`; se debe cambiar al iniciar sesión.

Los archivos físicos de `storage` y los datos externos de Flow no forman parte de un reinicio
SQL y no fueron eliminados ni modificados por esta operación.

## Resultados de validación

- Reconstrucción temporal: 80 tablas e índices/claves foráneas equivalentes; 53 triggers equivalentes.
- Suite completa: 153 pruebas aprobadas, 21 omitidas por depender de clientes/usuarios previos,
  1915 aserciones y ningún fallo. Incluye concurrencia real MySQL en firmas, planes,
  suscripciones y asignaciones de especialidades.
- Se actualizaron pruebas que aún referían migraciones retiradas, el antiguo perfil de Breeze
  sin rol y la antigua página comercial en `/`; ahora prueban el comportamiento vigente.
- Reconstrucción local final con seeders después de la suite, para no conservar fixtures de pruebas.

La suite contiene pruebas con `RefreshDatabase`: ejecutarla contra una base de pruebas separada
cuando existan datos que deban conservarse. Tras la validación de este reinicio se ejecutó
una reconstrucción final con seeders para dejar una base limpia y utilizable.
