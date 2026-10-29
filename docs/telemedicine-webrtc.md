# Telemedicina con WebRTC

La atención conserva su sala y enlace firmado de paciente. El navegador usa RTCPeerConnection para video/audio de dos participantes. Laravel autoriza el acceso y transmite SDP/ICE por canales privados de Reverb; no transporta el video. No requiere LiveKit.

## Desarrollo

- Configurar Reverb y las variables REVERB_* y VITE_REVERB_* existentes.
- Ejecutar `php artisan reverb:start`; mantener el proceso activo.
- Servir la aplicación por HTTPS (localhost también permite getUserMedia). El subdominio *.test necesita HTTPS.
- Abrir la atención como médico y el enlace firmado del paciente en otro navegador; aceptar cámara, micrófono y consentimiento.
- Sin STUN/TURN puede funcionar en una misma red, pero no garantiza conectividad por Internet.

## Producción

- Laravel por HTTPS y Reverb por WSS, con los dominios reales en allowed_origins de config/reverb.php.
- Procesos Reverb supervisados y balanceo compatible con WebSocket; Redis compartido para caché/bloqueos y escalado de Reverb. No usar caché file entre varios servidores.
- Configurar servidor STUN/TURN, por ejemplo coturn. Configuración de entorno de Laravel:

```dotenv
WEBRTC_STUN_URLS=stun:turn.example.com:3478
WEBRTC_TURN_URLS=turn:turn.example.com:3478?transport=udp,turns:turn.example.com:5349?transport=tcp
WEBRTC_TURN_SECRET=secreto-compartido-con-coturn
```

El secreto debe coincidir con static-auth-secret de coturn usando use-auth-secret. Laravel entrega credenciales temporales HMAC SHA1 de seis horas tras autorizar al participante. El secreto nunca se envía al navegador. Configurar certificados, realm, IP pública, puertos TURN y rango de puertos de relay en coturn y el firewall. Las credenciales expiran: al terminar su ventana de atención el navegador sale de la llamada.

Después de actualizar entorno: `php artisan config:cache`. Si cambia VITE_REVERB_*, reconstruir con `npm run build`. Reiniciar Reverb al cambiar su configuración.

## Acceso y operación

- Un dispositivo médico y un dispositivo paciente por sala, con arrendamientos de 30 segundos en caché y latidos cada 10 segundos.
- El acceso del paciente y cada mensaje requieren URL firmada vigente y consentimiento; el médico requiere autorización de clínica/sede/profesional.
- SDP/ICE se emiten solamente en el canal privado de esa sala. No se guardan en la base de datos.
- Salir detiene cámara/micrófono; finalizar avisa a ambos y cierra la sesión, conservando la atención clínica.
- Implementa negociación perfecta (paciente polite), candidatos pendientes y reinicio ICE ante fallo de red.
- El enlace firmado actúa como credencial de paciente; debe compartirse únicamente con él.

## Validación antes de atender pacientes

Probar dos dispositivos en redes distintas, WiFi/datos móviles, permisos denegados, reconexión, cierre de pestaña, segunda pestaña, consentimiento rechazado y finalización. Probar relay TURN forzado en un entorno de prueba y carga de señalización/Reverb/TURN antes de habilitar miles de usuarios. Medir ancho de banda de TURN: las llamadas que no logren conexión directa pasan por ese servidor. La implementación no equivale a una capacidad certificada de miles de conexiones.

Referencias: https://developer.mozilla.org/en-US/docs/Web/API/WebRTC_API/Perfect_negotiation y https://webrtc.org/getting-started/peer-connections .

## Sala persistente al navegar

El layout menu-customer presenta un contenedor principal con un iframe para los módulos y otro para la sala. Los enlaces y formularios de los módulos siguen cargando sus documentos completos y scripts; la sala permanece en un documento independiente. No se traslada ni se vuelve a insertar el iframe del video.

- Los mensajes entre documentos validan origen y ventana emisora.
- Volver a la misma atención muestra la sala existente sin reasignar su URL.
- Una segunda atención no reemplaza la sala abierta. Se debe cerrar la anterior.
- El cuadro se puede arrastrar, minimizar y ampliar. Volver a la atención cambia únicamente el documento del módulo.
- Cambiar de sede se bloquea durante una llamada activa porque cambia el contexto de autorización. Cerrar sesión o recargar toda la pestaña desconecta la llamada y solicita confirmación del navegador cuando corresponde.
- No se garantizan llamadas persistentes entre pestañas distintas ni al cerrar el navegador.
- El servidor web debe permitir iframes del mismo origen (por ejemplo, X-Frame-Options SAMEORIGIN, nunca DENY para estas vistas) y camera/microphone para el mismo origen en Permissions-Policy.

Verificación: `node --test tests/Browser/vita-shell.test.cjs` comprueba con documentos simulados que no se reasigna el iframe del video al navegar ni al volver a la misma sala. No sustituye la prueba real de WebRTC entre dos navegadores.

### Navegación del contenido central

El documento del menú y la barra superior permanece cargado. vita-module-navigation.js prepara el siguiente documento de contenido en un iframe oculto, manteniendo el contenido actual visible. Al terminar de cargar, cambia solamente ese contenido central. Cada módulo conserva sus propios scripts y dependencias aislados, sin duplicar listeners en el menú. Los parámetros internos _vita_frame y _vita_content se eliminan de la URL visible. Los formularios y redirecciones internas mantienen el contexto de contenido mediante PersistVitaContentNavigation; los enlaces firmados y externos no se modifican. Se comprueban cambios clínicos sin guardar antes de salir de una atención.

Verificar también `node --test tests/Browser/vita-module-navigation.test.cjs` y `php artisan test --filter=VitaContentNavigationTest`. Las pruebas simulan el reemplazo del contenido, no una videollamada real.

### Acciones de cada módulo

Usar `window.VitaNavigation.refresh()` después de una acción AJAX que necesite recargar la pantalla, y `window.VitaNavigation.navigate(url)` para redirigir dentro del panel. `refresh()` conserva URL, filtros y fragmento del documento central, prepara el reemplazo sin dejarlo en blanco y no agrega un paso al historial. Para consultar la ruta del módulo desde el menú persistente, usar `window.VitaNavigation.currentUrl()`.

Los formularios nativos ya se dirigen al documento de contenido; las acciones que actualizan DataTables o elementos mediante AJAX conservan su comportamiento. El cambio de sede renueva intencionalmente el menú y el contexto de permisos; está bloqueado durante una llamada activa. El cierre de sesión sale del contenedor completo. Las vistas públicas del sitio no usan este flujo del panel.
