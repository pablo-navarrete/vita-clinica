(() => {
    const initialize = (draft, modal, options = {}) => {
    if (!draft || !modal) return;
    if (draft.dataset.signingInitialized) return draft.signingControl;
    draft.dataset.signingInitialized = '1';
    modal.hidden = true;
    const confirm = modal.querySelector('[data-signing-confirm]');
    const open = draft.querySelector('[data-signing-open]');
    const submit = confirm.querySelector('[data-signing-submit]');
    const close = confirm.querySelector('[data-signing-close]');
    const error = confirm.querySelector('[data-signing-error]');
    let operation = null;
    let busy = false;
    const headers = { Accept: 'application/json', 'X-CSRF-TOKEN': draft.querySelector('[name="_token"]').value };
    const message = (data, fallback) => Object.values(data.errors || {}).flat().join(' ') || data.message || fallback;
    const hide = () => { if (busy) return; modal.hidden = true; confirm.reset(); operation = null; open.focus(); };
    draft.addEventListener('submit', event => { event.preventDefault(); event.stopImmediatePropagation(); open.click(); });
    close.addEventListener('click', hide);
    modal.addEventListener('keydown', event => { if (event.key === 'Escape') { event.stopPropagation(); hide(); } });
    open.addEventListener('click', async () => {
        if (busy || !draft.reportValidity()) return;
        busy = true; open.disabled = true;
        try {
            const preparation = new FormData(draft); preparation.delete('_method');
            const response = await fetch(draft.dataset.previewUrl, { method: 'POST', headers, body: preparation });
            const data = await response.json();
            if (!response.ok) throw new Error(message(data, 'No se pudo preparar la firma.'));
            operation = data.operation_id;
            confirm.reset(); error.hidden = true;
            modal.querySelector('[data-signing-preview]').innerHTML = data.html;
            modal.hidden = false;
            confirm.querySelector('[name="acceptance"]').focus();
        } catch (exception) {
            if (window.showClinicalRecordToast) window.showClinicalRecordToast(exception.message);
            else if (window.Swal) window.Swal.fire({ toast: true, position: 'top-end', icon: 'error', titleText: exception.message, timer: 6000, showConfirmButton: false });
            else window.alert(exception.message);
        } finally { busy = false; open.disabled = false; }
    });
    confirm.addEventListener('submit', async event => {
        event.preventDefault(); event.stopImmediatePropagation();
        if (busy || !operation || !confirm.reportValidity()) return;
        busy = true; submit.disabled = true; close.disabled = true; error.hidden = true;
        const body = new FormData(confirm);
        body.set('operation_id', operation);
        if ((draft.dataset.signingMethod || 'PATCH') !== 'POST') body.set('_method', 'PATCH');
        confirm.querySelector('[name="password"]').value = '';
        try {
            const response = await fetch(draft.getAttribute('action'), { method: 'POST', headers, body });
            const data = await response.json();
            if (!response.ok) throw new Error(message(data, 'No se pudo guardar la firma. El documento anterior permanece intacto.'));
            if (options.onSigned) { await options.onSigned(data); return; }
            window.vitaHasUnsavedChanges = () => false;
            // Let the persistent layout handle the ordinary internal navigation.
            const link = document.createElement('a'); link.href = data.redirect; document.body.append(link); link.click(); link.remove();
        } catch (exception) { error.textContent = exception.message; error.hidden = false; }
        finally { body.delete('password'); busy = false; submit.disabled = false; close.disabled = false; }
    });
    return draft.signingControl = { isBusy: () => busy };
    };
    window.initializeEncounterSigning = initialize;
    initialize(document.querySelector('[data-signing-draft]'), document.getElementById('encounter-signature-modal'));
})();
