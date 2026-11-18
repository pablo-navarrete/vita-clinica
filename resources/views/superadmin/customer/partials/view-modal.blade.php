<div x-cloak x-show="viewOpen" @click.self="closeView()" @keydown.escape.window="if (viewOpen) closeView()"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" role="dialog" aria-modal="true" aria-labelledby="clinic-detail-title">
    <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-xl">
        <div class="flex shrink-0 items-start justify-between gap-4 border-b bg-blue-50 px-6 py-5">
            <div class="flex min-w-0 items-center gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-600"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
                <div class="min-w-0"><p class="mb-1 text-xs font-semibold uppercase tracking-wider text-blue-600">Información del cliente</p><h2 id="clinic-detail-title" class="break-words text-xl font-semibold text-gray-800" x-text="viewData.name || 'Cliente'"></h2></div>
            </div>
            <button type="button" @click="closeView()" class="rounded-lg p-2 text-gray-400 hover:bg-white hover:text-gray-700" aria-label="Cerrar información del cliente"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
        </div>
        <div class="min-h-0 space-y-5 overflow-y-auto p-6">
            <section class="rounded-lg border border-gray-200 p-5" aria-labelledby="clinic-identity-heading">
                <h3 id="clinic-identity-heading" class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-800"><i class="fa-regular fa-id-card text-blue-500" aria-hidden="true"></i>Datos del cliente</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500">Razón social</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.legal_name || 'Sin registrar'"></dd></div>
                    <div><dt class="text-xs text-gray-500">Identificación tributaria</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.identification_display || viewData.rut || 'Sin registrar'"></dd></div>
                    <div><dt class="text-xs text-gray-500">Sostenedor</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.holder || 'Sin registrar'"></dd></div>
                    <div><dt class="text-xs text-gray-500">Identificador</dt><dd class="mt-1 text-sm font-medium text-gray-800" x-text="viewData.id ? '#' + viewData.id : '—'"></dd></div>
                </dl>
            </section>
            <section class="rounded-lg border border-gray-200 p-5" aria-labelledby="clinic-contact-heading">
                <h3 id="clinic-contact-heading" class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-800"><i class="fa-regular fa-address-book text-blue-500" aria-hidden="true"></i>Contacto</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500">Correo electrónico</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.email || 'Sin registrar'"></dd></div>
                    <div><dt class="text-xs text-gray-500">Teléfono</dt><dd data-phone-display class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.phone || 'Sin registrar'"></dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs text-gray-500">Dirección</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.address || 'Sin registrar'"></dd></div>
                </dl>
            </section>
            <section class="rounded-lg border border-gray-200 p-5" aria-labelledby="clinic-access-heading">
                <h3 id="clinic-access-heading" class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-800"><i class="fa-solid fa-globe text-blue-500" aria-hidden="true"></i>Acceso y estado</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    <div><dt class="text-xs text-gray-500">Dominio</dt><dd class="mt-1 break-words text-sm font-medium text-gray-800" x-text="viewData.domain ? viewData.domain + '.' + appDomain : 'Sin registrar'"></dd></div>
                    <div><dt class="mb-2 text-xs text-gray-500">Estado del cliente</dt><dd><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold" :class="viewData.status == 1 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-700'"><i class="fa-solid fa-circle text-[6px]" aria-hidden="true"></i><span x-text="viewData.status == 1 ? 'Activo' : 'Inactivo'"></span></span></dd></div>
                </dl>
            </section>
            <section class="rounded-lg border border-gray-200 p-5" aria-labelledby="clinic-payment-heading">
                <h3 id="clinic-payment-heading" class="mb-4 flex items-center gap-2 text-sm font-semibold text-gray-800"><i class="fa-regular fa-credit-card text-blue-500" aria-hidden="true"></i>Pagos y vigencia</h3>
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div><dt class="mb-2 text-xs text-gray-500">Estado de pago</dt><dd><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="viewData.payment_status == 1 ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-600'" x-text="viewData.payment_status == 1 ? 'Pagado' : 'Pendiente'"></span></dd></div>
                    <div><dt class="text-xs text-gray-500">Último pago</dt><dd class="mt-1 text-sm font-medium text-gray-800" x-text="viewData.paid_at || 'Sin registrar'"></dd></div>
                    <div><dt class="text-xs text-gray-500">Vencimiento</dt><dd class="mt-1 text-sm font-medium text-gray-800" x-text="viewData.expires_at || 'Sin registrar'"></dd></div>
                </dl>
            </section>
        </div>
        <div class="flex shrink-0 justify-end border-t bg-gray-50 px-6 py-4"><button type="button" @click="closeView()" class="rounded-lg bg-gray-600 px-5 py-2 text-sm font-semibold text-white hover:bg-gray-700">Cerrar</button></div>
    </div>
</div>
