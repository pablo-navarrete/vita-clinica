# Notificaciones de citas por correo y WhatsApp

Vita envía al paciente avisos cuando se crea, confirma, modifica o cancela una cita, cuando comienza/finaliza la atención y cuando se comparte el acceso a telemedicina. La clínica elige correo, WhatsApp o ambos en **Notificaciones a pacientes**. El correo sale desde el servicio central de Vita; el correo de la clínica se usa como dirección de respuesta.

## Configuración central de Vita

Completa estas variables en el entorno central, no en el `.env` de cada clínica:

- `WHATSAPP_CLOUD_TOKEN`: token de acceso de WhatsApp Business Cloud API.
- `WHATSAPP_PHONE_NUMBER_ID`: identificador del número remitente aprobado.
- `WHATSAPP_GRAPH_VERSION`: versión activa de Graph API configurada para la cuenta.
- `WHATSAPP_TEMPLATE_LANGUAGE`: idioma aprobado para las plantillas, por defecto `es`.
- Variables `WHATSAPP_TEMPLATE_*`: nombre exacto de cada plantilla aprobada en Meta.

Las plantillas deben existir y estar aprobadas en la cuenta central de WhatsApp Business. Sus parámetros de cuerpo deben seguir este orden:

| Evento | Variable de plantilla | Parámetros del cuerpo |
| --- | --- | --- |
| Solicitud de cita | `WHATSAPP_TEMPLATE_APPOINTMENT_CONFIRMATION` | paciente, clínica, fecha y hora, profesional, enlace de confirmación, WhatsApp de contacto de la clínica |
| Cambio de cita | `WHATSAPP_TEMPLATE_APPOINTMENT_UPDATE` | paciente, clínica, estado, fecha y hora, profesional, WhatsApp de contacto |
| Cancelación | `WHATSAPP_TEMPLATE_APPOINTMENT_CANCELLED` | paciente, clínica, estado, fecha y hora, profesional, WhatsApp de contacto |
| Confirmación del paciente | `WHATSAPP_TEMPLATE_APPOINTMENT_CONFIRMED` | paciente, clínica, estado, fecha y hora, profesional, WhatsApp de contacto |
| Inicio de atención | `WHATSAPP_TEMPLATE_APPOINTMENT_IN_PROGRESS` | paciente, clínica, estado, fecha y hora, profesional, WhatsApp de contacto |
| Atención finalizada | `WHATSAPP_TEMPLATE_APPOINTMENT_ATTENDED` | paciente, clínica, estado, fecha y hora, profesional, WhatsApp de contacto |
| Invitación de telemedicina | `WHATSAPP_TEMPLATE_TELEMEDICINE_INVITATION` | paciente, clínica, fecha y hora, enlace firmado de acceso, WhatsApp de contacto |

El paciente debe tener teléfono con código de país y haber aceptado recibir mensajes de WhatsApp. Si falta el consentimiento, la configuración central, el teléfono o la plantilla aprobada, Vita registra el motivo y omite ese envío.

Después de agregar las variables al entorno, limpia la configuración cacheada de Laravel durante el despliegue (`php artisan config:cache`). Aplica también las migraciones nuevas para guardar el consentimiento de WhatsApp y evitar reenviar la misma invitación de telemedicina.
