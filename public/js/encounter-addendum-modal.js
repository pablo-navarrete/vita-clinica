(() => {
    let host = null;
    let editor = null;
    let signing = null;
    let opener = null;
    let loading = false;
    const remove = () => {
        editor?.dispose(); host?.remove(); host = null; editor = null; signing = null;
        opener?.focus(); opener = null;
    };
    const notify = message => {
        if (window.showClinicalRecordToast) window.showClinicalRecordToast(message);
        else if (window.Swal) window.Swal.fire({ toast: true, position: 'top-end', icon: 'error', titleText: message, timer: 6000, showConfirmButton: false });
    };
    const close = async () => {
        if (!host || loading || signing?.isBusy()) return;
        if (editor?.isDirty()) {
            const discard = window.Swal
                ? (await window.Swal.fire({ icon: 'question', title: '¿Descartar esta adenda?', text: 'El texto sin firmar no se guardará.', showCancelButton: true, confirmButtonText: 'Descartar', cancelButtonText: 'Seguir editando' })).isConfirmed
                : window.confirm('¿Descartar el texto de la adenda sin firmar?');
            if (!discard) return;
        }
        remove();
    };
    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-addendum-modal-url]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault(); event.stopImmediatePropagation();
        if (host || loading) return;
        loading = true; opener = link;
        link.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(link.href, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.html) throw new Error(data.message || 'No se pudo abrir la adenda.');
            host = document.createElement('div'); host.id = 'encounter-addendum-host';
            host.innerHTML = data.html; document.body.append(host);
            const form = host.querySelector('[data-addendum-form]');
            editor = window.initializeEncounterAddendum(form);
            signing = window.initializeEncounterSigning(form, host.querySelector('#encounter-addendum-signature-modal'), {
                onSigned: data => {
                    remove();
                    window.dispatchEvent(new CustomEvent('clinical-addendum-signed', { detail: { url: data.redirect } }));
                },
            });
            host.addEventListener('click', click => {
                if (click.target.closest('[data-addendum-close]')) { click.preventDefault(); close(); }
            });
            form.querySelector('[name="type"]').focus();
        } catch (exception) {
            editor?.dispose(); host?.remove(); host = null; editor = null; signing = null;
            notify(exception.message || 'No se pudo abrir la adenda.');
        } finally { loading = false; link.removeAttribute('aria-busy'); }
    }, true);
    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape' || !host || !host.querySelector('#encounter-addendum-signature-modal')?.hidden) return;
        event.preventDefault(); close();
    });
})();
