# Mis atenciones

El paciente entra desde Mi Salud > Mis atenciones. Cada centro habilitado tiene su propia ficha, antecedentes y cronología de atenciones finalizadas con firma. El detalle muestra el contenido firmado, sus adendas y los documentos incluidos en la firma. La consulta es de solo lectura.

La clínica habilita el acceso desde la ficha clínica del paciente > Acceso Mi Salud. Se requiere un administrador central con permisos de administración de sucursales y gestión de fichas, la dirección exacta de una cuenta con correo verificado, comprobación de identidad y confirmación de contraseña. La coincidencia documental crea una candidatura, nunca un permiso clínico automático. La autorización se puede revocar desde la misma pantalla.

Cada solicitud comprueba cuenta activa, identidad documental vigente, vínculo y autorización. Los centros no habilitados no se revelan. Los antecedentes y documentos se filtran por paciente y clínica; los archivos se sirven desde las copias firmadas verificadas. La consulta no establece contexto de administración de una clínica en la sesión del paciente. Los permisos y accesos quedan auditados sin copiar el contenido clínico al registro de auditoría.

## Validación e instalación local

La suite PortalClinicalHistoryTest y las regresiones EncounterSigningTest, EncounterAddendumTest y EncounterPdfSignatureTest completaron 29 pruebas y 431 aserciones, con una prueba condicional omitida. Se ejecutaron únicamente contra vita_testing mediante tools/validate-portal-clinical-access.php y TestingDatabaseGuard.

La migración aditiva 2026_11_16_000100 se aplicó con tools/apply-portal-clinical-access-local.php. Se verificaron restauración completa, instalación y reversión en una base independiente antes de instalarla en la aplicación. Las tablas y triggers preexistentes permanecieron intactos, salvo el registro esperado en migrations.

Respaldo protegido y reporte: C:/laragon/backups/vita-portal-clinical-access/20261116_032533_4846a3/. La copia de ensayo contiene información real: no debe usarse para pruebas automatizadas. El comparador normaliza únicamente la declaración redundante CHARACTER SET utf8mb4 cuando conserva la misma colación; mantiene las comprobaciones de filas, checksums, contadores y triggers.

Las clínicas deben habilitar los permisos existentes individualmente; la instalación no autoriza cuentas ni publica fichas previas. No se incorporan automáticamente atenciones externas a VITA.

## Panel y navegación

El panel principal incluye el gráfico anual de doce meses y rankings de centros y profesionales. Los selectores de año y mes actualizan automáticamente el contenido; el mes filtra totales y rankings y se destaca en el gráfico anual. Solo se incluyen registros habilitados, con firma verificada. La prueba local sin correo verificado muestra ceros.

Mi Salud conserva el menú y Viti al navegar entre panel, perfil y fichas mediante mi-salud-navigation.js. Reutiliza las páginas autorizadas del servidor, actualiza el contenido y reinicializa sus controles. Mantiene historial del navegador, navegación normal sin JavaScript y descargas nativas. El guardado de perfil incluye CSRF y no repite automáticamente escrituras ante errores de red. No se modificó la política de seguridad que impide enmarcar Mi Salud.

Validación posterior: PortalClinicalHistoryTest, 9 pruebas y 87 aserciones. tools/audit-mi-salud-navigation.cjs prueba con datos sintéticos en Chrome la persistencia del menú, navegación, historial, controles y guardado de perfil, y actualización automática de año y mes, sin acceder a cuentas ni bases de datos de la aplicación.

Los tres paneles (VITA, clínicas y Mi Salud) cargan la misma distribución local de Chart.js 4.5.1 desde public/js/vendor/chart.umd.min.js, tomada de la dependencia instalada y con su licencia MIT adjunta. Mi Salud usa las opciones de barras del dashboard de clínicas: diseño responsive, leyenda inferior, eje desde cero, valores enteros y paleta índigo. Los rankings usan barras horizontales. mi-salud-charts.js destruye las instancias antes de sustituir contenido y las crea de nuevo después, incluidos los cambios parciales de filtros. Conserva tabla accesible y listas con los valores.

Validación actual: 9 pruebas, 93 aserciones; auditoría sintética en navegador verifica también la versión local de Chart.js, renderizado, datos y liberación de instancias entre vistas y filtros.
