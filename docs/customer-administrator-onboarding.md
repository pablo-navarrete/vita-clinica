# Administrador general del cliente

SuperAdmin → Clientes permite crear y editar el administrador general del cliente:
nombre, RUT, correo, teléfono y contraseña confirmada. No hay selección de sede.
Si no hay administrador general, Editar ofrece «Agregar administrador».
Si hay varios administradores, se puede seleccionar cuál editar.
La contraseña vacía al editar conserva el hash actual.

Crear el cliente, su sede principal, administrador y suscripción opcional constituye
una transacción. Agregar un administrador desde Editar también es atómico y bloquea
el cliente para evitar altas duplicadas. Debe existir al menos una sede activa.

El usuario pertenece exclusivamente al customer_id, recibe el rol Administrador
y pertenencia activa en todas sus sedes activas. No recibe Doctor ni SuperAdmin.
La marca is_customer_administrator se asigna exclusivamente desde este flujo de
SuperAdmin; no admite asignación masiva. Editar explícitamente un administrador
desde Clientes confirma también este alcance general.

BranchCapacityService asigna estos administradores a las sedes que posteriormente
se creen o reactiven. Pueden administrar todas las sedes desde cualquier sede activa.
La migración no convierte automáticamente usuarios anteriores: agrega una marca
desactivada por defecto. Los administradores de sede y profesionales conservan
sus roles específicos. Los administradores de sede no aparecen en este formulario.

Las contraseñas se almacenan como hash, no se devuelven al navegador ni se guardan
en old input. El login busca el correo dentro del tenant correspondiente.

Migración aditiva: 2026_11_05_000100_add_customer_administrator_flag_to_users.
No se eliminan usuarios ni datos clínicos.
