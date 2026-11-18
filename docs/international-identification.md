# Identificación internacional — núcleo, etapa 1

## Alcance

Este núcleo no está integrado todavía con formularios, controladores, modelos,
reservas, Viti, pacientes ni firmas. No crea tablas, migraciones, procesos de
importación ni mantenedores. No lee ni escribe datos de clínicas o pacientes.
No reemplaza los campos `rut` existentes ni valida retroactivamente sus valores.

Las clases se encuentran en `App\Services\Identification` y pueden resolverse
mediante el contenedor de Laravel 10 o instanciarse sin aplicación ni base de datos.
Los tipos enumerados y propiedades readonly son compatibles con PHP 8.1+.

## Países

`config/countries.php` contiene 249 códigos asignados ISO 3166-1 alfa-2, incluidos
territorios, y nombres de presentación en español. Chile (`CL`) es el valor
predeterminado devuelto por CountryCatalog; el servicio de identificación exige
siempre un país explícito y no lo deduce de un número.

El catálogo se distribuye localmente. No requiere llamadas de red al usar VITA.
La definición puede inyectarse explícitamente al constructor para pruebas; por
defecto CountryCatalog lee el archivo versionado, sin requerir `app()` o conexión DB.
No es un catálogo editable por clínica o SuperAdmin.

Fuentes fijadas en la configuración:

- Códigos ISO: datos de pycountry/iso-codes, revisión
  `e974d00d5ead823a48d6944a6df1696e95e507e3`.
  https://github.com/pycountry/pycountry/blob/e974d00d5ead823a48d6944a6df1696e95e507e3/src/pycountry/databases/iso3166-1.json
- Nombres españoles: Unicode CLDR, revisión
  `d2f155b4b2fd98b0b2453e2e09ce76794b5749c2`.
  https://github.com/unicode-org/cldr-json/blob/d2f155b4b2fd98b0b2453e2e09ce76794b5749c2/cldr-json/cldr-localenames-full/main/es/territories.json
- Referencia del estándar: https://www.iso.org/iso-3166-country-codes.html
- Atribución de nombres: Unicode CLDR, Unicode License V3:
  https://www.unicode.org/license.txt

Se filtraron únicamente los códigos asignados: no se incorporaron regiones de CLDR,
alias, códigos reservados o de uso privado como EU, UK, XK o ZZ. No se inventó un
país para «desconocido». Las actualizaciones del catálogo serán cambios versionados
con revisión de los códigos históricos; no actualizaciones automáticas en ejecución.

`all()` devuelve un mapa de código/nombre ordenado por nombre.
`search()` acepta código o nombre, sin distinguir mayúsculas y tildes. Esta
normalización de búsqueda se aplica a nombres de países, nunca a documentos.
La búsqueda del servicio queda preparada para un futuro selector, sin crear UI.

## Tipos y titulares

DocumentTypeRegistry acepta:

- Persona natural (`natural_person`): `national_id`, `passport`, `tax_id`.
- Persona jurídica (`legal_entity`): únicamente `tax_id`.

Una persona natural puede tener identificación tributaria; no se considera
automáticamente persona jurídica. El tipo de titular se recibe explícitamente:
no se infiere por el rango numérico ni por el país.

Las categorías extranjeras representan qué categorías admite VITA con reglas
básicas, no una certificación de que cada país emita todos los tipos enumerados.
Las particularidades nacionales se incorporarán solo con fuentes verificadas.

En Chile, documento nacional y tributario comparten el namespace `CL:RUT` para no
duplicar un mismo registro dentro del conjunto de una persona. La etiqueta puede
ser RUT/RUN o RUT tributario, y ambos usan módulo 11. El pasaporte chileno utiliza
la regla básica y un namespace distinto; nunca se valida como RUT.

País emisor, nacionalidad y residencia son conceptos independientes. Este DTO solo
contiene el país emisor: no calcula ni asigna los otros dos.

## Validación y resultados

```php
use App\Services\Identification\IdentificationService;

$service = new IdentificationService();
$document = $service->identify('CL', 'national_id', '123456785');

$document->original;                    // 123456785
$document->number->normalized;          // 123456785
$document->number->display;             // 12.345.678-5
$document->number->level->value;        // checksum
$document->number->ruleVersion;         // cl-rut-v1
$document->holderIdentityVerified();    // false

$foreign = $service->identify('AR', 'passport', '00Ab-123/45');
$foreign->number->normalized;           // 00Ab-123/45
$foreign->number->level->value;         // basic

$tax = $service->identify('CL', 'tax_id', '12345678-5', 'natural_person');
```

IdentificationService devuelve un DocumentIdentifier inmutable con la definición,
valor original y ValidatedNumber. InvalidIdentification identifica el campo mediante
`field` y ofrece un mensaje en español que no incluye el número rechazado.
No es todavía una regla FormRequest; la futura adaptación traducirá estos errores
a las validaciones de Laravel sin duplicar sus algoritmos.

### RUT chileno, cl-rut-v1

- Formatos admitidos: compacto, cuerpo-guion-DV o puntos de miles y guion-DV.
- Cuerpo de uno a ocho dígitos ASCII, mayor que cero y sin ceros iniciales.
- DV numérico o K/k; módulo 11 con factores 2 a 7 desde la derecha.
- Normalizado sin puntos/guion y con K mayúscula; presentación con puntos y guion.
- Espacios ASCII exteriores admitidos; el original completo se conserva.
- Se rechazan agrupaciones incorrectas y caracteres inesperados: no se borran
  letras o signos para convertir silenciosamente una entrada inválida en válida.
- No se consultan registros oficiales, no se verifica existencia, titularidad ni
  naturaleza jurídica y no se aplica una lista de números supuestamente ficticios.
- La política de ceros iniciales en RUT es explícita: se rechazan, no se eliminan.
  Los documentos extranjeros sí los conservan.

### Documentos básicos, basic-document-v1

- Se exige una cadena, nunca un número JSON/PHP; no se convierten a enteros.
- De 1 a 100 caracteres Unicode después de quitar solo espacios ASCII exteriores.
  Antes de procesar se limita la entrada original a 128 caracteres.
- Letras, números, marcas Unicode y separadores internos: espacios, punto, guion,
  barra, apóstrofo recto o tipográfico. Inicio alfanumérico; final alfanumérico o
  marca combinada. No se admite una cadena formada solo por separadores.
- Se conservan ceros iniciales, mayúsculas/minúsculas, acentos, puntuación y espacios
  interiores, incluso dobles; no se translitera ni se normaliza Unicode NFC/NFKC.
- Se rechazan UTF-8 inválido, controles, saltos de línea/párrafo, formato bidi,
  caracteres invisibles y símbolos fuera de la política. No se admiten líneas MRZ
  completas ni imágenes: se espera el número del documento.
- Resultado `basic`, no «checksum correcto» ni «documento auténtico».

Estas reglas no pretenden comprobar el formato oficial de más de 100 países.
Un nuevo validador nacional debe conservar su propia versión y no reinterpretar
silenciosamente números ya almacenados con otra regla.

Las clases de reglas concretas son estrategias internas; los futuros escritores
deben entrar por IdentificationService para aplicar también el límite original,
UTF-8 y controles comunes. No se deben guardar ni registrar DTO completos en logs.
La validación no sustituye parámetros SQL enlazados, escape HTML o autorización.

## Varios documentos, principal y retirados

DocumentSet y DocumentRecord modelan el ciclo de vida solo en memoria:

```php
use App\Services\Identification\DocumentSet;

$set = (new DocumentSet())
    ->add('document-1', $document, primary: true)
    ->add('document-2', $foreign);
$changed = $set->withPrimary('document-2');
$retired = $changed->retire('document-2', new DateTimeImmutable());
```

- Las operaciones devuelven otro conjunto sin modificar el anterior.
- Cero o un documento principal, siempre activo; no se selecciona otro en silencio.
- Retirar conserva el contenido y fecha de retiro. No permite sobrescribir una
  referencia ni reintroducir un documento idéntico ya retirado bajo otra referencia.
- No combina personas naturales con personas jurídicas.
- La igualdad considera país, namespace, tipo de titular y número normalizado.
  Así distingue países y preserva diferencias significativas como `00123`/`123`.
- Este conjunto no comprueba el propietario real ni customer_id, no fusiona usuarios
  con pacientes y no garantiza unicidad en base de datos. Son responsabilidades
  explícitamente pendientes de la etapa de persistencia y sus permisos.

Los DTO describen validación documental, no autenticación o prueba de identidad.
holderIdentityVerified() siempre devuelve false y no recibe un flag desde la entrada.
Una futura evidencia de identidad deberá modelarse por separado con su procedimiento
y trazabilidad. Tampoco se otorgan roles ni permisos por tener un documento válido.

## Pruebas y límites de etapa

Pruebas puras PHPUnit en tests/Unit/Identification, sin Tests\TestCase de Laravel,
RefreshDatabase, modelos, aplicación o conexión de base de datos. Cubren todo el
catálogo, búsqueda, tipos por titular, RUT y sus tres DV posibles, entradas extranjeras,
Unicode, límites, controles, duplicados y el ciclo de vida inmutable.

Comando:

```text
php vendor/phpunit/phpunit/phpunit tests/Unit/Identification
```

No se ejecutan migraciones ni pruebas clínicas porque este núcleo todavía no está
integrado. No se garantiza unicidad persistente ni aislamiento multiusuario mediante
un conjunto en memoria; las pruebas de concurrencia/tenant vendrán con la etapa 2.

Resultado de la ejecución final: **98 pruebas, 1.988 comprobaciones, cero fallos**,
PHPUnit 10.5.63 / PHP 8.2.0, 0,186 s y 10 MiB. Incluye resolución del servicio
mediante Illuminate Container sin arrancar Laravel. Laravel Pint --test aprobó los
20 archivos PHP nuevos. No se consultó ni modificó ninguna base de datos.

## Decisiones previas a etapa 2

1. Política de unicidad tributaria entre clientes, incluidos varios clientes de una
   misma persona/entidad y usuarios globales con contexto customer_id nullable.
2. Esquema cifrado, claves HMAC de búsqueda, índices únicos y estrategia de rotación.
3. Clasificación de registros antiguos: no asumir Chile a partir del número ni
   rechazar operaciones clínicas por identificadores históricos no clasificados.
4. Flujo autorizado para sustituir/reactivar documentos y conservar su auditoría.
5. Tratamiento de documentos desconocidos o números que requieran separadores fuera
   de la política básica; ampliar mediante reglas versionadas antes de integrar.
6. Protección de búsqueda pública contra enumeración y futura vinculación de cuentas
   globales, sin usar el documento como credencial ni mezclar información entre clínicas.
7. Formularios nuevos usarán CL por defecto, pero los editores históricos no deben
   completar país/tipo automáticamente. No cambiar snapshots ni hashes firmados.
