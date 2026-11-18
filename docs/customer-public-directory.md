> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Directorio público de clientes

## Presentación configurable

Migración `2026_11_04_110000_add_directory_brand_and_cover`: identidad logo y nombre (predeterminada), solo nombre o solo logo; origen de portada de VITA, banner propio o imagen subida. La portada se muestra separada de la identidad. Sin logo válido se mantiene el nombre visible; el nombre sigue presente en búsqueda, metadatos y accesibilidad en todas las modalidades.

Las portadas subidas se validan PNG/JPEG/WebP, máximo 4 MB y 4096×4096; se decodifican y recodifican a PNG, con ruta aleatoria bajo `directory/customer_ID`. No se aceptan rutas enviadas por el navegador. Una transacción fallida elimina solo el archivo recién preparado; las portadas anteriores no se sobrescriben ni eliminan, pues figuran en el consentimiento histórico. Los cambios de presentación se incluyen en la auditoría pública autorizada.

## Etapa 1: presentación, consentimiento y permisos

`customers` sigue siendo la única identidad de cliente. Perfil 1:1 en `customer_directory_profiles`, autorización desactivada por defecto. La clínica administra su presentación desde Mi cuenta → Directorio de VITA. Requiere administrador activo de la sede seleccionada, perteneciente al cliente. SuperAdmin no autoriza publicaciones por representación.

Se reutilizan logos y banners públicos activos propios. No se suben ni copian archivos en este módulo. Los recursos se verifican por customer_id y prefijo de almacenamiento. Extensiones y tipo de imagen real se verifican al presentarlos. Si faltan, fueron eliminados o dejan de estar activos, se usa portada CSS VITA e inicial del cliente. Ubicación, dirección, portada y logo son opcionales. La descripción es obligatoria; una descripción del footer se ofrece solo como sugerencia para revisión.

Cada guardado con publicación habilitada exige consentimiento explícito, identifica usuario, fecha, versión del texto y contenido autorizado. El historial `customer_directory_events` es inmutable por triggers MySQL. El control de revisión y bloqueo del cliente serializa primeras publicaciones concurrentes. El retiro no depende de nueva aceptación y no elimina evidencia.

Migraciones aditivas: 2026_11_04_100000_create_customer_directory_profiles y 2026_11_04_101000_protect_directory_consent_history.

## Etapa 2: páginas públicas y supervisión

Dominio principal: `/clinicas`, `/clinicas/{slug}`, `/directory-sitemap.xml`. Ruta tenant raíz nombrada `customer.website.home`, sin cambiar dirección ni controlador. El enlace al sitio se genera desde esta ruta y el subdominio configurado, nunca desde una URL introducida por el cliente. Slug de directorio estable; cambiar nombre no rompe enlaces.

Elegibilidad compartida: consentimiento directory-v1, perfil habilitado y no suspendido; customer activo, no eliminado, subdominio válido, inicio público activo y sin mantenimiento. Sin condiciones de suscripción, pago, dirección o portada. No se llama ensureHomeForCustomer ni se crean páginas desde lecturas del directorio. Una tarjeta por customer_id.

SuperAdmin → Directorio de clientes: suspensión/levantamiento con motivo, revisión y auditoría. No puede modificar consentimiento. El retiro/suspensión afecta listado, ficha y sitemap inmediatamente; no hay caché pública persistente que invalidar. Los buscadores pueden tardar en retirar copias.

DTO de salida explícito: nombre comercial, descripción, ubicación autorizada, imágenes verificadas y URL. No se serializan modelos completos, usuarios, pacientes, datos fiscales o pagos. Las fichas no muestran profesionales individuales. Las entidades clínicas y agendas no se consultan ni cambian.

Blade con HTML rastreable, enlaces de paginación, búsqueda GET por nombre, canonical, metadatos sociales y Organization JSON-LD escapado. Sin indexación en ambientes no productivos o búsqueda interna. Sitemap para publicaciones elegibles; se debe enviar a Search Console en producción y referenciar desde el robots.txt productivo correspondiente. No garantiza posicionamiento. No se añadieron mapas, APIs externas, dependencias ni filtros por ubicación; se guardan ciudad/región para incorporarlos después.

Pruebas: consentimiento, retiro, ausencia de ubicación/imágenes, aislamiento de recursos, revisiones obsoletas, permisos, privacidad pública, búsqueda, ficha, mantenimiento, suspensión y sitemap. Los tests utilizan transacciones con rollback; no generan clínicas de ejemplo persistentes.
