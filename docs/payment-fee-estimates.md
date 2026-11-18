> Esquema consolidado el 04/11/2026: las referencias a migraciones incrementales de este documento describen el desarrollo anterior. Para instalaciones vacías utiliza las migraciones actuales de creación por tabla; consulta [database-baseline.md](database-baseline.md).

# Estimaciones de comisiones

SuperAdmin → Gestión comercial → Comisiones de pago.

No hay tarifas precargadas. El administrador registra la tasa porcentual, costo fijo en CLP, IVA sobre la comisión, escenario contratado (medio de pago/plazo de abono), fecha y referencia de las condiciones. La configuración no consulta ni modifica Flow. Es un escenario de referencia, no una garantía universal para cualquier medio de pago.

Cada registro es una versión inmutable, con autor y fecha de registro. Su vigencia termina al comenzar la siguiente. La fecha de una nueva versión debe ser posterior a la última registrada y no anterior a hoy en America/Santiago. No se sobrescriben tarifas publicadas: para cambiarlas se registra una vigencia posterior. Se serializa la publicación mediante una fila técnica bloqueada en MySQL y un índice único de fecha. UPDATE/DELETE están bloqueados también por triggers.

Crear/editar planes muestra el escenario vigente y una simulación mensual/anual basada en precio base + sedes adicionales simuladas. No agrega esos valores al contrato ni a los pagos. La simulación no representa ganancia; no calcula impuestos propios de VITA ni costos operacionales. Las tarifas futuras no se usan hasta su vigencia.

Cálculo CLP: comisión = redondeo(monto × tasa / 100) + costo fijo; IVA = redondeo(comisión × IVA / 100); neto = monto − comisión − IVA. Redondeo a pesos con mitades hacia arriba. Porcentajes almacenados como enteros con cuatro decimales; cálculo entero en PHP y BigInt en JavaScript. El simulador admite montos hasta 1.000.000.000.000 CLP. Un resultado negativo se muestra sin ocultarlo.

La comisión efectiva y las diferencias de liquidación se incorporarán con la integración de Flow y conciliación; no se consideran confirmadas las estimaciones. La configuración actual no altera suscripciones, períodos pagados, precios de planes ni restricciones clínicas.

Migración aditiva: `2026_11_02_150000_create_payment_fee_rates.php`. Pruebas: `PaymentFeeTest` (vigencias, cálculo, IVA, costo fijo, cero, neto negativo, permisos, validación e inmutabilidad SQL).
