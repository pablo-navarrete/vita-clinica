# Ficha pública del directorio — etapa 2

La ruta existente `/clinicas/{slug}` conserva la consulta de elegibilidad compartida
con el directorio. La ficha devuelve 404 al retirarse la autorización, suspenderse,
eliminarse o desactivarse la clínica, o cuando su web deja de estar disponible.
La respuesta evita almacenar información autorizada en cachés compartidas.

`CustomerDirectoryService::detail()` prepara únicamente datos públicos:

- Identidad, portada y descripción existentes, respetando la preferencia de marca.
- Especialidades desde `SpecialtyCatalog::offered()`, normalizadas y sin duplicados.
- Sedes activas, no eliminadas y expresamente habilitadas para el directorio.
- Teléfono/correo de WebsiteTopbar habilitada, solo con autorización del perfil.
- Redes de WebsiteSocialSetting, solo con autorización y URLs HTTP/HTTPS válidas,
  sin credenciales y con redes reconocidas. No se realizan solicitudes a esas URLs.

No utiliza contactos administrativos, datos de pacientes ni profesionales individuales.
Los enlaces de mapa se construyen con direcciones autorizadas no vacías; las URLs
del sitio se siguen generando desde la ruta pública tenant existente.
Las secciones vacías se omiten. No exige publicar sedes ni dirección física.

Solo la ficha consulta las secciones adicionales: el listado y el sitemap no cargan
sedes/contacto/especialidades para cada tarjeta. Las consultas de detalle son por
conjunto, no por profesional ni por sede. Las portadas de tarjetas usan carga diferida;
la portada principal tiene dimensiones explícitas y prioridad alta.

La portada, logo y nombre de las tarjetas abren la ficha. El botón del sitio mantiene
su destino directo sin enlaces HTML anidados. Se conservan canonical, Open Graph,
datos estructurados y renderizado Blade. No hay migraciones en esta etapa.

Pruebas: CustomerDirectoryTest y FileSpecialtyCatalogTest; revisión en Chrome sin
interfaz a 390, 768 y 1440 píxeles sin desbordamiento horizontal. Las capturas locales
se generaron con contenido ficticio y sin alterar datos de producción.

## Etapa 3 — búsqueda por especialidad

El parámetro público `especialidad` contiene un ID positivo de config/specialties.php,
validado contra sus claves. No son los IDs locales de la tabla specialties.
El selector permite búsqueda sin distinguir tildes ni mayúsculas; conserva un select
nativo como alternativa sin JavaScript. Soporta teclado, ratón y selección táctil.
Solo ofrece categorías con alguna clínica elegible y una asignación operativa válida.
Una selección que dejó de tener oferta conserva su contexto, pero devuelve cero
resultados y no se ofrece como una nueva opción del desplegable.

SpecialtyCatalog::offeredQuery() centraliza las reglas que usa también offered().
Los filtros del directorio usan una subconsulta por customer_id y EXISTS para las
asignaciones; no unen profesionales a las tarjetas ni duplican las clínicas.
specialtyOptions() obtiene IDs distintos mediante una consulta de conjunto limitada
a clientes publicables. No hay caché persistente ni consultas offered() por tarjeta.
Las imágenes/identidades se cargan con eager loading solo para la página paginada.

Se combina con buscar y cercanas; los enlaces de paginación preservan los filtros.
Quitar especialidad conserva nombre/ubicación y reinicia la página. Los enlaces de
las tarjetas a la ficha preservan el ID, pero el enlace a la web de la clínica no cambia.
La ficha revalida la oferta al cargar antes de destacar «Esta clínica ofrece…».
Canonical y Open Graph usan la URL de ficha sin parámetros; las búsquedas con
especialidad reciben noindex,follow. El sitemap conserva las URL originales.

Pruebas: CustomerDirectoryTest, FileSpecialtyCatalogTest, SuperAdminLayoutTest y
tests/Browser/directory-specialty.test.cjs. Se compara el número de consultas al
pasar de una clínica a una página completa, además de comprobar paginación y cercanía.

Pendiente para etapa 4: pruebas de carga a gran escala con datos representativos,
planes EXPLAIN y revisión visual/accesible del filtro en navegadores y dispositivos.
