# Teléfonos internacionales

VITA valida los teléfonos con los metadatos de libphonenumber, en navegador y PHP.
No hace llamadas externas, consultas de geolocalización, comprobación de titularidad
ni envía SMS. Las banderas, traducciones y metadatos se sirven desde los assets locales.

## Comportamiento

- Selector con bandera y búsqueda de países en español; Chile como país inicial.
- El prefijo aparece separado junto a la bandera. El paciente selecciona el país
  y escribe únicamente el número nacional, sin repetir el prefijo. Por ejemplo,
  Chile + `987654321` produce `+56987654321` al guardar.
- El campo visible permite solo dígitos y no agrega espacios ni guiones. Las letras
  y el signo `+` aislado se bloquean con una indicación de ingresar el número nacional.
  Si se pega un número completo con prefijo, se separa del número y se muestra un aviso.
  Un prefijo repetido sin `+` se quita solo cuando el número internacional resultante
  es válido y la interpretación nacional con prefijo duplicado es inválida.
- Al escribir un prefijo internacional `+56`, `+54`, etc., se actualiza el país.
  Los códigos compartidos, como `+1`, requieren más dígitos para distinguir el territorio.
- Se aceptan móviles y teléfonos fijos válidos para su plan de numeración.
- Se rechazan códigos inexistentes, números cortos/incompletos, letras y extensiones.
- Los valores válidos se normalizan a E.164 (`+` y dígitos). Las configuraciones de
  enlaces WhatsApp que ya conservaban solo dígitos mantienen ese contrato al guardarse.
- `00` se interpreta como prefijo internacional. Números nacionales chilenos de nueve
  dígitos conservan compatibilidad y tienen prioridad sobre posibles prefijos extranjeros.
  Para números extranjeros debe utilizarse el selector o el prefijo internacional.
- Una validación positiva indica un patrón válido; no prueba que la línea exista,
  pertenezca al paciente o esté inscrita en WhatsApp.

## Cobertura del servidor

`PhoneAwareValidator`, registrado en `AppServiceProvider`, agrega `international_phone`
a cada atributo telefónico declarado en las reglas de Laravel: `phone`, `*_phone`
y `reply_whatsapp`, también en arreglos anidados. Conserva reglas de obligatoriedad,
campos opcionales, errores JSON y bolsas de errores existentes. No valida identificadores
de proveedores como `phone_number_id`.

El middleware `NormalizePhoneNumbers` normaliza las solicitudes de escritura para los
controladores que leen propiedades de Request. El propio validador también normaliza
los datos, cubriendo servicios internos y registro conversacional de pacientes.
Los valores inválidos se conservan para que la regla entregue un error en su campo.

La cobertura comprende pacientes y agenda, reservas públicas, profesionales, clínicas,
administradores y sedes, perfiles, Mi Salud, contactos de la web, WhatsApp y Viti.
No se modifican en lote teléfonos históricos. Un número antiguo inválido debe corregirse
cuando se envía nuevamente mediante un formulario con validación.

## Interfaz

`components/phone-assets.blade.php` carga la entrada Vite `vita-phone.js` en los layouts
administrativos, Mi Salud, reservas y sitios públicos. `intl-tel-input` muestra banderas
locales y selecciona el país desde el número. Funciona con campos agregados dinámicamente,
Alpine, jQuery `.val()`, autocompletado y modales de edición. Viti usa el mismo control
cuando solicita el teléfono del paciente.

El control visible no tiene nombre de envío y muestra el número nacional. El input
original conserva su nombre, ID y vínculo Alpine, queda oculto y recibe el número
internacional completo mediante eventos `input` nativos. Así, tanto FormData como
los formularios JSON envían el mismo valor y los metadatos CustomEvent del selector
no se convierten en objetos dentro de `x-model`. Las etiquetas y validaciones del
navegador apuntan al control visible.

Los enlaces `tel:`, enlaces WhatsApp y etiquetas `data-phone-display` muestran la bandera
de los números válidos sin modificar el número ni el destino. No se añade una bandera
a datos históricos que no se puedan interpretar de forma válida.

## Verificación

- `tests/Unit/PhoneNumbersTest.php`: formatos, países, códigos compartidos, fijos,
  números inválidos y normalización.
- `tests/Feature/PhoneValidationTest.php`: integración con Laravel, campos opcionales,
  requeridos y anidados, errores y normalización del Request.
- `node tools/audit-phone-browser.cjs`: Chrome real a 390 y 1440 px, detección de países,
  edición programática, Alpine, escritura, validación y dropdown dentro de la pantalla.
- Construir con `npm run build` tras cambios de interfaz o actualización de metadatos.

Dependencias: `giggsey/libphonenumber-for-php` y `intl-tel-input`; sus versiones quedan
registradas en los respectivos archivos lock. Mantener ambas actualizadas para incorporar
cambios en los planes de numeración.
