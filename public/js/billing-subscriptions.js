(() => {
    const price = document.getElementById('subscription-price');
    const branches = document.getElementById('subscription-branches');
    const estimate = document.getElementById('subscription-estimate');
    function updateEstimate() {
        const option = price?.selectedOptions[0];
        if (!estimate) return;
        const amount = estimate.querySelector('[data-estimate-total]');
        const detail = estimate.querySelector('[data-estimate-detail]');
        if (!amount || !detail) return;
        if (!option?.value) {
            amount.textContent = 'Elige el plan de tu cliente';
            detail.textContent = 'Verás el importe por período y las sedes incluidas antes de crear la suscripción.';
            return;
        }
        const quantity = Number(branches.value);
        const included = Number(option.dataset.included);
        const total = Number(option.dataset.base) + Math.max(0, quantity - included) * Number(option.dataset.additional);
        const valid = Number.isSafeInteger(total) && Number.isSafeInteger(quantity) && quantity > 0;
        amount.textContent = valid ? `$${total.toLocaleString('es-CL')} CLP por ${option.dataset.interval === 'year' ? 'año' : 'mes'}` : 'Revisa la cantidad de sedes';
        detail.textContent = valid
            ? `${included} sede(s) incluida(s) · ${Math.max(0, quantity - included)} adicional(es) a $${Number(option.dataset.additional).toLocaleString('es-CL')} CLP cada una.`
            : 'Ingresa un número entero de sedes para calcular el importe.';
    }
    if (window.jQuery) window.jQuery(price).on('change', updateEstimate);
    else price?.addEventListener('change', updateEstimate);
    branches?.addEventListener('input', updateEstimate);
    updateEstimate();
    const element = document.getElementById('billing-subscriptions-table');
    if (!element || !window.jQuery?.fn.DataTable) return;
    const $ = window.jQuery;
    const text = $.fn.dataTable.render.text();
    $(element).DataTable({
        processing: true, serverSide: true, responsive: false, ajax: element.dataset.url,
        dom: 'lf<"billing-subscriptions-scroll"t>ip', searchDelay: 300, order: [[0, 'desc']], pageLength: 10,
        lengthMenu: [10, 25, 50, 100], pagingType: 'full_numbers',
        language: { processing: 'Procesando…', lengthMenu: 'Mostrar _MENU_ registros', search: 'Buscar:',
            zeroRecords: 'No se encontraron suscripciones', emptyTable: 'No hay suscripciones registradas',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)', loadingRecords: 'Cargando…',
            paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' } },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'customer_name', orderable: false, render: text },
            { data: 'plan_name', orderable: false, render: text },
            { data: 'billing_interval', orderable: false, searchable: false, render: text },
            { data: 'state_label', orderable: false, searchable: false, render: text },
            { data: 'payment_mode_label', orderable: false, searchable: false, render: text },
            { data: 'contract_label', orderable: false, searchable: false, render: text },
            { data: 'financial_label', orderable: false, searchable: false, render: text },
            { data: 'access_label', orderable: false, searchable: false, render: text },
            { data: 'paid_until', orderable: false, searchable: false, render: text },
            { data: 'next_date', name: 'next_renewal_on', searchable: false, render: text },
            { data: 'show_url', orderable: false, searchable: false, render: (url, type) => {
                if (type !== 'display') return url;
                const link = document.createElement('a');
                link.href = url; link.className = 'font-semibold text-blue-600 hover:underline';
                link.textContent = 'Ver suscripción'; return link.outerHTML;
            } }
        ]
    });
})();
