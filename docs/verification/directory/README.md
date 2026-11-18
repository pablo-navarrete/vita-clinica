# Directorio público de VITA: verificación de etapa 4

## Alcance y resultado

Se verificaron el directorio central, su selector de especialidades y las fichas
`/clinicas/{slug}`. No se modificaron reservas, citas, documentos clínicos,
asignaciones ni estadísticas históricas. No se ejecutaron migraciones destructivas
ni se cargaron datos sintéticos en la base habitual. No hubo cambios de esquema.

Resultado funcional: **61 pruebas Laravel, 550 comprobaciones, cero fallos y cero
omisiones en la ejecución final**, más cuatro pruebas JavaScript aprobadas.
La revisión automatizada/visual en Chrome cubrió 12 combinaciones de página/ancho.
Esto no equivale a certificar conformidad WCAG ni capacidad productiva concurrente.

## Entorno, aislamiento y método

- Windows local, PHP 8.2.0 CLI y MySQL 8.0.30.
- Cada ejecución del benchmark crea un esquema MySQL nuevo con prefijo
  `vita_directory_audit_`, comprueba la conexión y aplica las migraciones existentes
  exclusivamente allí. El script no borra esquemas ni usa migrate:fresh.
- Resultado final: `vita_directory_audit_20261105_103853`.
- Regresiones: otro esquema independiente, `vita_directory_audit_20261105_103903`.
- Los esquemas sintéticos y capturas se conservaron para inspección/reproducción.
- Cada escenario tiene un calentamiento y siete muestras secuenciales medidas con
  hrtime. Se mide el kernel HTTP de Laravel completo dentro de un proceso PHP
  ya arrancado: middleware, SQL, renderizado y generación de respuesta.
- **No se mide la red HTTP/TLS, arranque de cada worker, descarga de imágenes,
  servicios externos ni concurrencia. No es una prueba de carga de producción.**
- Array session/cache, sin caché persistente de oferta. Las mediciones de cercanía
  usan una ubicación sintética consentida en sesión. No usan una ubicación real.
- Imágenes predeterminadas, contacto/redes deshabilitados en el benchmark masivo.
  Su autorización y revocación se verifican con fixtures funcionales independientes.
- La memoria informada es el máximo PHP asignado durante las muestras del proceso
  reutilizado, incluidas sus estructuras de framework/benchmark; no es RAM de MySQL
  ni una estimación de memoria por usuario concurrente. El incremento de arenas
  asignadas dentro de muchas solicitudes calientes fue cero y no significa consumo cero.

El generador varía de 1 a 3 sedes, de 2 a 10 profesionales y de 2 a 7 especialidades
por clínica, con dos asignaciones por profesional cuando corresponda. Incluye
clientes no publicables, profesionales/sedes inactivos y categorías ocultas.
A 5.000 clientes hay 29.995 profesionales y 10.001 sedes sintéticas.

## Resultados medidos finales

Tiempos en **milisegundos**, mediana de siete muestras. Cada celda representa una
respuesta real del kernel, no un tiempo estimado a partir de otro volumen.

| Clientes | Inicio | Especialidad | Texto/ubicación + especialidad | Cercanía + especialidad | Ficha |
|---:|---:|---:|---:|---:|---:|
| 100 | 47,84 | 79,20 | 84,48 | 79,33 | 15,87 |
| 500 | 135,61 | 156,57 | 149,85 | 164,80 | 14,93 |
| 1.000 | 156,02 | 210,34 | 186,54 | 208,58 | 13,63 |
| 5.000 | 618,11 | 777,72 | 772,79 | 982,49 | 13,67 |

P95 empírico por rango más próximo; con solo siete muestras corresponde al máximo
observado, por lo que no caracteriza estadísticamente las colas de producción.

| Clientes | P95 inicio | P95 especialidad | P95 combinada | P95 cercanía | P95 ficha |
|---:|---:|---:|---:|---:|---:|
| 100 | 54,26 | 99,31 | 86,98 | 85,27 | 16,84 |
| 500 | 174,09 | 162,63 | 153,22 | 242,90 | 18,73 |
| 1.000 | 160,00 | 247,77 | 209,46 | 230,52 | 14,34 |
| 5.000 | 629,86 | 825,90 | 913,96 | 1.067,24 | 17,55 |

| Clientes | Consultas por solicitud | Pico PHP máximo | Página 2 mediana | Última página mediana | Tarjetas última página |
|---:|---:|---:|---:|---:|---:|
| 100 | 7 | 38 MiB | 79,80 ms | 81,07 ms | 9 |
| 500 | 7 | 40 MiB | 123,38 ms | 126,89 ms | 1 |
| 1.000 | 7 | 42 MiB | 212,61 ms | 239,23 ms | 3 |
| 5.000 | 7 | 46 MiB | 953,07 ms | 964,35 ms | 11 |

La página normal contiene como máximo 12 tarjetas. El último número de página se
calculó contando las clínicas elegibles con la especialidad seleccionada.
El total de clientes sintéticos no es el total de clientes publicables.
Los siete SQL incluyen configuración de VITA, opciones agregadas, conteo,
página y relaciones de presentación. La ficha tiene siete en este fixture, con
contacto y redes deshabilitados. Habilitarlos agrega consultas de conjunto constantes,
no una consulta por profesional, sede o tarjeta.

Muestras individuales, tiempo SQL y memoria: [performance.json](performance.json).

## Consultas y optimización

Se capturó EXPLAIN de los SELECT ejecutados, incluidos opciones, conteo, paginación,
cercanía y ficha. Evidencia: [especialidad](explain-specialty.json) y
[cercanía](explain-nearby.json). No se añadieron índices a ciegas.

Las relaciones utilizan los índices existentes: PRIMARY, índice único de perfiles
por cliente, clinic_branches_customer_id_status_index,
clinic_branch_user_clinic_branch_id_user_id_unique, branch_user_role_unique,
specialty_user_branch_unique, specialties_customer_id_foreign,
website_pages_customer_id_slug_unique y website_maintenances_customer_id_unique.

Los filtros y el límite de página permanecen en SQL. Los EXISTS/IN no multiplican
tarjetas por profesional. Las categorías se obtienen en una consulta de conjunto;
no se ejecuta offered() para cada tarjeta. Se mantiene el eager loading de las
relaciones de presentación después de paginar y la carga diferida de portadas.

Problema encontrado: la consulta operativa verificaba dos veces la misma pertenencia
y sede mediante joins anidados. Se eliminaron las verificaciones duplicadas del
camino offeredQuery, manteniendo todas las condiciones de actividad, tenant,
asignación y rol/capacidad. La comprobación de rol queda en EXISTS independiente.
El método de validación utilizado por reservas permanece intacto.

Medianas a 5.000 clientes, antes/después, mediciones locales orientativas:

| Escenario | Antes | Después |
|---|---:|---:|
| Inicio | 706,58 ms | 618,11 ms |
| Especialidad | 1.117,16 ms | 777,72 ms |
| Combinada | 893,35 ms | 772,79 ms |
| Cercanía | 1.182,57 ms | 982,49 ms |
| Ficha | 15,20 ms | 13,67 ms |

No son experimentos de hardware dedicado: hay variabilidad local y no todas las
mediciones pequeñas mejoran. Se probó y descartó una alternativa UNION por cada
categoría porque su coste de planificación empeoraba los tiempos; no quedó en código.

EXPLAIN muestra tablas temporales para categorías y filesort para distancia,
con aproximadamente 4.904 perfiles estimados a 5.000 clientes. El cálculo de distancia
y el LIKE de búsqueda parcial requieren examinar candidatos; no desaparecen por
tener un número constante de consultas. **El directorio todavía necesita una prueba
concurrente y objetivos de latencia antes de afirmar capacidad productiva.**

## Seguridad, privacidad y consistencia

Se verificaron consentimiento, retiro, suspensión, mantenimiento, baja lógica e
inactividad de clínicas; fichas no publicables devuelven 404. Se comprobaron imágenes
de otro tenant, sedes ajenas/inactivas/eliminadas, revisiones obsoletas, rollback
ante fallo de auditoría, contacto/redes revocados y ausencia de contactos privados.

Se probaron GET manipulados, ID de especialidad inválido/array/expresión SQL,
búsqueda con comillas/comodines, URLs javascript, credenciales en enlaces y redes
desconocidas. Las URLs de clínica se generan desde la ruta pública existente.
No se realiza ninguna descarga backend de enlaces sociales arbitrarios.

Vulnerabilidad corregida: los campos SEO se insertaban con @yield sin escape dentro
de title/metadatos. Se utiliza escape de Blade para nombre, descripción y URLs,
con prueba de payloads que intentan cerrar title o atributos. El JSON-LD conserva
JSON_HEX_TAG y las protecciones existentes contra cierre de script.

El catálogo se comprueba sin duplicados tras normalización. Se probaron múltiples
asignaciones equivalentes, desactivación del profesional/sede/pertenencia y ocultación
de especialidad, sin caché persistente. Las estadísticas históricas y referencias
de citas se preservan mediante las pruebas existentes de catálogo.

## Accesibilidad y responsive

Chrome real, contenido sintético renderizado en servidor, anchos 390, 768, 1024 y
1440 px: inicio, filtro seleccionado y ficha (12 combinaciones). Evidencia:
[browser.json](browser.json), capturas en storage/app/directory-audit/final.
Se revisaron capturas de móvil, tablet y escritorio; sin scroll horizontal,
enlaces anidados, H1 duplicado ni filtros sin nombre accesible en los casos evaluados.
Se consultó el árbol de accesibilidad del navegador.

Se comprobó selección con flechas y Enter y anuncio de búsqueda sin coincidencias.
Corrección: Flecha arriba al abrir ahora comienza en la última opción. El selector
conserva el select nativo sin JavaScript y mantiene aria-expanded/activedescendant.
Se añadió enlace para saltar al contenido, foco visible y objetivos de interacción
de al menos 44 px en los controles ajustados.

Contraste calculado con luminancia WCAG: [contrast.json](contrast.json).
El texto del hero tenía 4,37:1 sobre el extremo claro azul del degradado; se corrigió
a 5,37:1. Texto normal sobre blanco: 4,76:1; botón: 6,70:1; etiquetas: 8,01:1;
foco: 6,16:1 frente al fondo del hero.

**Limitaciones:** no se utilizó NVDA, JAWS ni VoiceOver; árbol AX y ARIA no sustituyen
una prueba con lector de pantalla. No hubo auditoría completa con axe ni certificación
WCAG 2.2 AA. Solo Chrome de escritorio con viewport emulado, no Safari/iOS ni Android
físico. Las vistas estáticas de auditoría usan CSS/JS locales y no descargan fuentes,
iconos o imágenes externas: esas dependencias necesitan comprobación en staging.

## SEO

Verificados title/description por clínica, canonical sin especialidad, Open Graph,
noindex de búsquedas parametrizadas, JSON-LD seguro, sitemap y exclusión de perfiles
no publicables. Se añadió og:locale y og:image cuando hay portada/logo público válido.
No se genera una nueva URL canónica para la coincidencia de búsqueda.
La aceptación/indexación por Google y Search Console no puede verificarse en local.

## Regresiones y reproducción

Ejecución final: [regression.xml](regression.xml), 61 pruebas / 550 comprobaciones,
cero fallos, cero omitidas. Incluye CustomerDirectoryTest, FileSpecialtyCatalogTest,
ProfileAssignmentsTest, MySchedulesModuleTest, CustomerAdministratorOnboardingTest,
SuperAdminBranchEditTest, TenantPasswordRecoveryTest y VitiBookingConversationTest.
Este último ejercita una reserva confirmada, validación de disponibilidad y cancelación.
No se ejecutó la totalidad de la suite ajena al alcance del directorio.

Las tres pruebas que inicialmente requerían fixtures se ejecutaron al preparar
roles, permisos, espejo de roles, dos clientes y paciente sintéticos en el esquema
aislado. Sus resultados iniciales omitidos no se contabilizan como aprobados.

JavaScript: node --test tests/Browser/directory-specialty.test.cjs, cuatro aprobadas.
Compilación de Blade correcta; caché de vistas limpiada al finalizar.

Herramientas reproducibles:

1. `php tools/audit-directory.php --label=final`: crea un esquema aislado nuevo,
   aplica migraciones, genera los cuatro conjuntos y guarda métricas/EXPLAIN/HTML.
2. `php tools/audit-directory.php --label=regression --schema-only`: crea otro
   esquema vacío, sin cargar miles de clientes.
3. `php tools/seed-directory-regression.php`: prepara solo fixtures sintéticos en
   el esquema auditado identificado por el archivo de regresión.
4. Ejecutar los tests con DB_DATABASE igual al esquema de regresión; no en la base habitual.
5. `node tools/audit-directory-browser.cjs`: requiere Chrome local y permiso para
   lanzarlo. Usa las respuestas sintéticas guardadas y produce capturas/métricas.
6. `node tools/directory-contrast.cjs`: recalcula los pares de contraste documentados.

Los scripts de preparación no emiten claves ni cambian .env. No envían notificaciones,
usan direcciones .test y no llaman a Flow ni proveedores clínicos.

## Archivos cambiados en esta etapa

- app/Services/SpecialtyCatalog.php: simplificación operativa equivalente.
- resources/views/vita/directory/layout.blade.php: escape SEO, Open Graph y salto al contenido.
- public/js/customer-directory-specialty.js: corrección de navegación con Flecha arriba.
- public/css/customer-directory.css: foco, objetivos táctiles y contraste.
- tests/Feature/CustomerDirectoryTest.php: escape, bajas y parámetros manipulados.
- tests/Browser/directory-specialty.test.cjs: regresión de teclado.
- tools/audit-directory.php, tools/audit-directory-browser.cjs,
  tools/directory-contrast.cjs y tools/seed-directory-regression.php.
- Este informe y los resultados adjuntos. No hay migraciones nuevas.

## Antes de publicar

1. Repetir el benchmark por HTTP/TLS en staging con la configuración real de PHP,
   OPcache, MySQL, disco, imágenes y datos representativos, y sin modo debug.
2. Acordar SLO y medir concurrencia, throughput, P95/P99, saturación de workers,
   CPU y RAM de MySQL. A 5.000 clientes la cercanía local ronda un segundo; no
   prometer respuesta instantánea ni capacidad simultánea basada solo en este ensayo.
3. Si el objetivo de latencia lo exige, evaluar índices/reescrituras con EXPLAIN
   ANALYZE sobre el servidor real; geofiltro espacial para cercanía y búsqueda de
   texto indexada requieren definir semántica antes de cambiar resultados.
4. Completar lector de pantalla, Safari/iOS, Android y revisión WCAG manual.
5. Verificar HTTPS, host central/subdominios, proxy de confianza, assets públicos,
   robots de producción, sitemap y Search Console. No hacer indexable staging.
6. Mantener monitorización de consultas lentas y errores, copias de seguridad y
   despliegue reversible. Conservar las retiradas de consentimiento sin cachés obsoletas.
