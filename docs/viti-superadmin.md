# Viti del superadmin VITA

El widget global conecta con POST /superadmin/assistant/ask. La autorización se comprueba en el servidor: dominio principal, guard web autenticado, usuario activo sin customer_id y rol global Super Administrador. Se valida el estado actualizado del usuario en cada pregunta. El cuerpo del mensaje no permite elegir otro usuario, clínica, rol ni contexto de autorización.

La primera consulta disponible es «¿Cuántos clientes nuevos tengo este mes?». Cuenta customers.created_at desde el inicio del mes hasta antes del inicio del siguiente, usando America/Santiago, igual que el calendario de la aplicación. Incluye activos e inactivos y responde con mes y año explícitos. Devuelve únicamente el agregado, sin nombres, correos ni datos clínicos. Preguntas de otros períodos reciben una explicación del alcance disponible, sin presentar el conteo actual como si perteneciera a otro período.

Los widgets de clínica, clínica pública y Mi Salud conservan endpoints separados. El atributo data-assistant-context refleja el ámbito decidido por el servidor y no otorga permisos. La conversación de superadmin usa una clave propia por usuario, distinta de la antigua demostración; Mi Salud no guarda conversación. Las respuestas no se almacenan en caché HTTP.

Se verificaron límite de mes, activos e inactivos, exclusión de meses vecinos, contexto manipulado, cuentas de clínica, cuenta inactiva, sesión exclusiva de Mi Salud, consulta sin sesión y validación de longitud. Las pruebas se ejecutan únicamente en vita_testing.
