# Notificaciones y chat en tiempo real

La campana muestra notificaciones del sistema guardadas en `notifications`. El icono de mensajes muestra conversaciones y mensajes del chat; mantiene su contador independiente.

El layout persistente escucha `clinic-user.{customerId}.{userId}`. La autorización exige el usuario destinatario y el tenant actual. El evento `user.updated` contiene únicamente el área (`system` o `chat`) y, para chat, el identificador de conversación. No transmite textos clínicos ni contadores calculados para otros usuarios.

- Al guardar una notificación por el canal `database`, se avisa al destinatario después del commit.
- Las lecturas de notificaciones actualizan las otras pestañas del usuario.
- Crear conversaciones, enviar mensajes, cambiar reacciones o integrantes y registrar lecturas avisa a los usuarios afectados.
- Los eventos existentes por conversación continúan entregando mensajes y reacciones.
- Al suscribirse, reconectar o volver a la pestaña se recupera el estado desde los endpoints autenticados. Sin suscripción personal activa hay consultas de respaldo.
- La campana y la página de notificaciones se actualizan únicamente con avisos del sistema. El chat se sincroniza con avisos de mensajes.

## Ejecución

Mantener `php artisan reverb:start` en ejecución. Los avisos usan `ShouldBroadcastNow` y se emiten después del commit; estos avisos no requieren un worker de cola. Una falla de broadcasting no revierte una operación ya guardada.

En producción, configurar el host público de Reverb, HTTPS/WSS y los orígenes permitidos para los subdominios reales. Los valores VITE de Reverb se incorporan al compilar los assets. No usar `127.0.0.1` como host del navegador de usuarios remotos.

## Comprobación manual

Abrir dos sesiones de usuarios de una clínica. Crear una conversación nueva y enviar un mensaje: debe cambiar el contador de mensajes, sin modificar la campana. Crear o modificar una cita que notifique al profesional: debe cambiar la campana del destinatario. Abrir otra pestaña del destinatario y comprobar que las lecturas se sincronizan. Desconectar y reconectar la red para verificar la recuperación del estado. Repetir con otra clínica para comprobar el aislamiento.
