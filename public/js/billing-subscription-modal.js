(() => {
    const modal = document.getElementById('subscription-create-modal');
    if (!modal) return;
    const form = modal.querySelector('form');
    const errors = modal.querySelector('[data-subscription-errors]');
    let opener, busy = false, previousOverflow;
    function close() {
        if (busy) return;
        window.jQuery?.('#subscription-customer, #subscription-price').select2?.('close');
        modal.hidden = true; modal.style.display = 'none';
        document.body.style.overflow = previousOverflow;
        opener?.focus();
    }
    document.querySelector('[data-open-subscription]')?.addEventListener('click', event => {
        opener = event.currentTarget; previousOverflow = document.body.style.overflow;
        errors.hidden = true; modal.hidden = false; modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        modal.querySelector('[data-close-subscription]').focus();
    });
    modal.querySelectorAll('[data-close-subscription]').forEach(button => button.addEventListener('click', close));
    modal.addEventListener('click', event => { if (event.target === modal) close(); });
    document.addEventListener('keydown', event => {
        if (modal.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key !== 'Tab') return;
        const focusable = [...modal.querySelectorAll('button, a[href], input:not([type="hidden"]), select, textarea, [tabindex="0"]')]
            .filter(element => !element.disabled && element.getClientRects().length);
        const first = focusable[0], last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    });
    form.addEventListener('submit', async event => {
        event.preventDefault(); event.stopPropagation();
        if (busy) return;
        busy = true; errors.hidden = true;
        const submit = form.querySelector('button:not([type="button"])');
        if (submit) submit.disabled = true;
        form.setAttribute('aria-busy', 'true');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 30000);
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form),
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: controller.signal });
            const result = await response.json();
            if (!response.ok) {
                const messages = Object.values(result.errors || {}).flat();
                throw new Error(messages.length ? messages.join('\n') : result.message || 'No se pudo crear la suscripción.');
            }
            const customer = document.getElementById('subscription-customer');
            customer.querySelector(`option[value="${Number(result.customer_id)}"]`)?.remove();
            form.reset(); window.jQuery?.(customer).trigger('change');
            document.getElementById('subscription-price')?.dispatchEvent(new Event('change'));
            window.jQuery?.('#billing-subscriptions-table').DataTable().ajax.reload(null, false);
            busy = false; close();
            window.Swal?.fire({ toast: true, position: 'top-end', icon: 'success', title: result.message, showConfirmButton: false, timer: 3000 });
        } catch (error) {
            errors.textContent = error.name === 'AbortError' ? 'La solicitud tardó demasiado. Revisa el listado antes de intentar nuevamente.' : error.message;
            errors.classList.add('whitespace-pre-line'); errors.hidden = false; errors.scrollIntoView({ block: 'nearest' });
        } finally {
            clearTimeout(timeout); busy = false;
            if (submit) submit.disabled = false;
            form.removeAttribute('aria-busy');
        }
    });
})();
