# Opiniones de atención en la vitrina

Las tarjetas del directorio muestran el promedio y número de opiniones públicas.
La presentación de cada clínica incluye opiniones paginadas y sus respuestas.
No se crean puntuaciones ni comentarios ficticios.

## Publicación

Desde «Escribir una opinión», el paciente entra a Mi Salud con correo verificado.
Se exige una atención finalizada y una autorización explícita de reserva desde esa
misma cuenta: `portal_booking_authorizations` completada, enlazada por
`registration_operation` con un registro completado de `patient_registration_attempts`
y la cita de la atención. Se comprueban cuenta, clínica, paciente y cita.
Una coincidencia de documentos (`portal_patient_links`) no habilita una reseña.
Las reservas externas a Mi Salud todavía no habilitan este flujo.

El paciente elige la atención, si evalúa a la clínica o a su profesional, de 1 a 5
estrellas, un alias público y un comentario. El profesional se obtiene de la atención;
no se acepta un ID de profesional enviado por el navegador. La publicación requiere
consentimiento. El promedio de la tarjeta agrupa las opiniones de atención de la
clínica, incluidas las dirigidas a sus profesionales; no es un promedio independiente
por profesional.

Hay una opinión por cuenta y clínica, protegida por bloqueo de cuenta y unicidad SQL.
No hay edición ni republicación tras retirarla. Desde Mi Salud se puede retirar la
opinión incluso si la clínica dejó de estar publicada: desaparecen del público tanto
la reseña como sus respuestas y se excluye del promedio. El registro se conserva.

## Respuestas

«Opiniones de pacientes» aparece en el menú de administradores y profesionales.
El administrador responde en nombre de la clínica para su sede seleccionada; el
profesional responsable responde las opiniones de sus atenciones en esa misma sede.
Se verifican clínica, estado activo, pertenencia y rol de sede. No se permite
representación global ni que un profesional responda por otro. Una respuesta por
usuario y reseña, sin sobrescribir respuestas anteriores. Se requiere consentimiento
de publicación y se conserva el nombre/rol público al responder.

## Privacidad y alcance

Solo se presentan alias, estrellas, comentario, destinatario, fecha de publicación
y respuestas. No se publican email, documento, ID de paciente, fecha de atención,
motivo ni contenido clínico. Todos los textos se escapan en Blade. Los formularios
incluyen CSRF y límites de solicitudes. La autorización para reseñar no da acceso a
la ficha clínica. No hay transcripción, respuestas automáticas ni correo saliente.

La retirada voluntaria está implementada. Reportes, moderación editorial, edición
de reseñas y perfiles públicos independientes por profesional son etapas posteriores.

## Instalación y verificación

Migración aditiva: `2026_11_14_000100_create_directory_reviews.php`.
Aplicar antes de desplegar las consultas nuevas del directorio.

`DirectoryReviewTest` verifica elegibilidad, cuenta ajena, autenticación, consentimiento
y rango de estrellas mediante el controlador, duplicados, alias/escape de HTML,
promedios, retirada, respuestas autorizadas y regreso después del login.
`CustomerDirectoryTest` cubre regresión del directorio y sus filtros.
