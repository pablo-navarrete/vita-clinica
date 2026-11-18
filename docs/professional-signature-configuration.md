> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Configuración personal de firma visual — etapa 1

El médico accede desde **Mi perfil → Firma electrónica**. La configuración pertenece a `(customer_id, user_id)`, sin sede: puede reutilizarse en las sedes de la misma clínica. Una cuenta en otra clínica tiene otro usuario y otra configuración. Se exige cuenta activa y rol Doctor con membresía activa en al menos una sede activa de esa clínica. Un administrador que además es médico solamente administra su propia configuración; el superadministrador global no tiene acceso a estas imágenes.

## Datos y almacenamiento

- `professional_signature_profiles`: método y referencias actuales de firma y timbre, únicas por clínica/usuario.
- `professional_signature_assets`: versiones independientes con UUID, propietario, clínica, tipo, fecha, dimensiones, tamaño y SHA-256 del PNG normalizado. No se guardan contraseñas, certificados, datos clínicos ni nombres originales de archivos.
- Cada reemplazo genera un archivo y registro nuevos. Retirar una imagen quita la referencia actual; no borra versiones. El modelo rechaza edición/eliminación de versiones y las claves foráneas restringen borrados físicos de sus propietarios. Esto implica que un borrado físico futuro de una cuenta/clínica con activos requerirá una política explícita de conservación. No se ejecuta ninguna limpieza automática de históricos.
- Los cambios de configuración se registran en `clinic_activity_logs`, con el actor y las referencias anteriores/nuevas. Este historial y el SHA-256 son controles internos; no constituyen evidencia suficiente de firma de una atención ni un registro inviolable ante un administrador de base de datos.

Disco dedicado `professional_signatures`: `storage/app/private/professional-signatures/{customer_id}/{user_id}/{uuid}.png`. **No crear enlaces públicos para este directorio.** Incluirlo junto con MySQL en respaldos privados, con accesos y cifrado de infraestructura apropiados. Nunca se usa `Storage::url()` para estos activos.

Las vistas previas pasan por una ruta autenticada que verifica clínica, médico propietario y referencia actual. Se sirven como PNG, con `nosniff` y `Cache-Control: private, no-store`. Conocer un UUID no otorga acceso; históricos retirados no están disponibles por esta ruta.

Solo se reciben PNG/JPEG de hasta 2 MB y dimensiones de 32 × 16 a 2400 × 1600. Laravel valida archivo/MIME/dimensiones y el servicio verifica el contenido, decodifica con GD y genera un PNG nuevo: descarta metadatos y contenido añadido. SVG y otros formatos no se aceptan. Se requiere PHP GD y fileinfo. El dibujo usa Pointer Events y se envía como archivo PNG por el mismo flujo de validación; no se almacena base64 en la base de datos.

## Integración

`ProfessionalSignatureService` concentra autorización, escritura transaccional, historial y almacenamiento. Serializa cambios con un bloqueo del usuario y usa una restricción única para evitar perfiles duplicados. Los fallos eliminan únicamente archivos nuevos de la operación fallida.

`ElectronicSignatureMethod`, `SignatureMethodRegistry` y `VitaSimpleSignatureMethod` describen los métodos de configuración. Hoy solo se admite `fes_vita`. No implementan operaciones de firma ni proveedores FEA ficticios.

Migración aditiva:

```sh
php artisan migrate
```

No requiere compilar assets ni cambiar variables de autenticación. El canvas utiliza APIs modernas del navegador; subir archivos sigue siendo posible sin JavaScript. Para validar interacción real, probar mouse y táctil, guardar, reemplazar y retirar ambos activos desde Mi perfil, también navegando dentro del layout persistente.

## Verificación automatizada

`ProfessionalSignatureTest` crea clínicas, médicos y sedes temporales dentro de una transacción, que revierte al terminar, y usa almacenamiento simulado. Comprueba perfil/renderizado, PNG/JPEG, normalización, SHA-256, conservación y retiro de versiones, auditoría, validaciones, acceso entre usuarios/clínicas, rechazo de administradores, y reutilización entre sedes. No requiere borrar ni regenerar la base de datos.

## Etapa posterior

Falta implementar firma individual de atenciones: nueva verificación de identidad del profesional exacto, aceptación explícita, versión inmutable del contenido clínico, evidencia vinculada al hash y al método, cierre transaccional, permisos del responsable, prohibición de modificaciones silenciosas y rectificaciones auditables. Referenciar versiones específicas de activos desde cada evidencia futura. Evaluar cumplimiento y conservación antes de atribuir validez legal. Los métodos `finish()`, estados, PDF, FHIR y datos clínicos permanecen fuera de esta etapa.
