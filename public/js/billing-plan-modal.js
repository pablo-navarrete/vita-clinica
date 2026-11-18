(() => {
    if (window.VitaPlanModal) return;
    let modal, content, title, message, opener, previousOverflow, request, sequence = 0, saving = false, dirty = false, currentUrl;
    let rows = [];
    function syncConditions(form) {
        const value = name => form.querySelector('input[type="checkbox"][name="' + name + '"]')?.checked;
        const set = (name, disabled, required) => {
            const field=form.querySelector('[name="'+name+'"]');
            if(field) { field.disabled=disabled; field.required=required; }
        };
        for(const period of ['month','year']) for(const key of ['amount','additional_branch_amount']) {
            const enabled=value('definition[prices]['+period+'][enabled]');
            set('definition[prices]['+period+']['+key+']',!enabled,Boolean(enabled));
        }
        for(const code of ['users','professionals','branches']) {
            const unlimited=value('definition[limits]['+code+'][unlimited]');
            set('definition[limits]['+code+'][value]',Boolean(unlimited),!unlimited);
        }
        for(const metric of ['ai_tokens','storage_bytes']) {
            const prefix='definition[quotas]['+metric+']';
            const enabled=value(prefix+'[enabled]'), unlimited=value(prefix+'[unlimited]');
            for(const field of form.querySelectorAll('[name^="'+prefix+'["]')) {
                if(field.name===prefix+'[enabled]') continue;
                field.disabled=!enabled || (field.name===prefix+'[quantity]' && unlimited);
                field.required=Boolean(enabled) && ['[quantity]','[reset_period]','[overage_policy]'].some(key=>field.name===prefix+key) && !(field.name===prefix+'[quantity]' && unlimited);
            }
        }
    }
    function fillDefinition(form, data) {
        function fill(value,name) {
            if(value && typeof value==='object' && !Array.isArray(value)) { for(const [key,item] of Object.entries(value)) fill(item,name+'['+key+']'); return; }
            const fields=Array.from(form.querySelectorAll('[name]')).filter(field=>field.name===name || field.name===name+'[]');
            for(const field of fields) {
                if(field.type==='hidden') continue;
                if(field.type==='checkbox') field.checked=Array.isArray(value) ? value.includes(field.value) : Boolean(Number(value));
                else field.value=value ?? '';
            }
        }
        fill(data || {},'definition'); syncConditions(form);
    }
    function layout(isForm) {
        const footer = modal.querySelector('[data-plan-view-footer]');
        footer.hidden = isForm; footer.style.display = isForm ? 'none' : 'flex';
        modal.querySelector('[data-plan-modal-panel]').style.maxWidth = isForm ? '42rem' : '48rem';
        content.className = isForm ? '' : 'space-y-5 px-6 py-4';
    }
    function localForm(row) {
        content.replaceChildren(document.getElementById('billing-plan-form-template').content.cloneNode(true));
        const form = content.querySelector('[data-plan-form]');
        if (row) {
            form.action = row.delete_url;
            const method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; method.value = 'PUT'; form.append(method);
            for (const key of ['name','code','description','position']) form.elements.namedItem(key).value = row[key] ?? '';
            form.elements.namedItem('active').value = row.active ? '1' : '0';
            form.elements.namedItem('code').readOnly = true;
        }
        if(form.querySelector('[data-plan-conditions]')) {
            form.elements.namedItem('revision').value = row?.commercial?.revision ?? 0;
            fillDefinition(form,row?.commercial?.definition || {});
        }
        layout(true); title.textContent = row ? 'Editar plan' : 'Crear plan';
        form.elements.namedItem('name').focus();
    }
    function localSummary(row) {
        content.replaceChildren(document.getElementById('billing-plan-summary-template').content.cloneNode(true));
        for (const [key, value] of Object.entries({ name:row.name, code:row.code, status:row.active ? 'Disponible en catálogo' : 'Contratación inactiva',
            description:row.description || '', versions:'Las condiciones se gestionan desde Editar plan.' })) {
            content.querySelector('[data-summary-' + key + ']').textContent = value;
        }
        layout(false); title.textContent = 'Ver plan: ' + row.name;
    }
    function initialize() {
        if (modal) return;
        modal = document.getElementById('billing-plan-modal');
        content = modal.querySelector('[data-plan-modal-content]'); title = modal.querySelector('h2'); message = modal.querySelector('[data-plan-errors]');
        modal.addEventListener('input', event => { if (event.target.closest('[data-plan-form]')) dirty = true; });
        modal.addEventListener('change', event => { const form=event.target.closest('[data-plan-form]'); if(form) { dirty=true; syncConditions(form); } });
        modal.addEventListener('click', event => { if (event.target === modal || event.target.closest('[data-close-plan]')) close(); });
        modal.addEventListener('submit', async event => {
            const form = event.target.closest('[data-plan-form]'); if (!form) return;
            event.preventDefault(); event.stopPropagation(); if (saving) return;
            saving = true; message.hidden = true;
            const submit = form.querySelector('button[type="submit"], button:not([type])'); if (submit) submit.disabled = true;
            try {
                const response = await fetch(form.getAttribute('action'), { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo guardar el plan.');
                dirty = false; saving = false; await close();
                window.dispatchEvent(new Event('billing:plan-saved'));
                window.Swal?.fire({ toast:true, position:'top-end', icon:'success', title:data.message, showConfirmButton:false, timer:3000 });
            } catch (error) { message.textContent = error.message; message.hidden = false; }
            finally { saving = false; if (submit) submit.disabled = false; }
        });
    }
    async function discard() {
        if (!dirty) return true;
        const result = await window.Swal.fire({ title:'¿Descartar cambios?', text:'Los cambios del formulario no se han guardado.', icon:'warning', showCancelButton:true,
            confirmButtonText:'Descartar', cancelButtonText:'Seguir editando' });
        return result.isConfirmed;
    }
    async function close() {
        if (saving || !(await discard())) return;
        request?.abort(); ++sequence;
        modal.hidden = true; modal.style.display = 'none'; dirty = false;
        document.body.style.overflow = previousOverflow;
        opener?.focus();
    }
    async function open(url, trigger, row) {
        if (saving || !(await discard())) return;
        initialize();
        if (modal.hidden) { previousOverflow = document.body.style.overflow; opener = trigger || document.activeElement; }
        dirty = false; modal.hidden = false; modal.style.display = 'flex'; document.body.style.overflow = 'hidden';
        message.hidden = true;
        modal.querySelector('[data-close-plan]').focus();
        request?.abort(); request = new AbortController(); const ticket = ++sequence;
        const target = new URL(url, location.href);
        row = row || rows.find(item => [item.show_url,item.edit_url].some(value => new URL(value,location.href).pathname === target.pathname));
        currentUrl = target;
        if (target.pathname.endsWith('/plans/create')) { localForm(null); return; }
        if (row && target.pathname.endsWith('/edit')) { localForm(row); return; }
        if (row && !target.searchParams.has('page')) localSummary(row);
        try {
            const response = await fetch(url, { signal:request.signal, headers:{Accept:'application/json'}, cache:'no-store' });
            const data = await response.json(); if (!response.ok || !data.html) throw new Error(data.message || 'No se pudo abrir el plan.');
            if (ticket !== sequence) return;
            currentUrl = new URL(url, location.href); title.textContent = data.title; content.innerHTML = data.html;
            const isForm = Boolean(content.querySelector('[data-plan-form]'));
            layout(isForm);
            if(isForm) syncConditions(content.querySelector('[data-plan-form]'));
            content.querySelector('input:not([type="hidden"]), select, textarea')?.focus();
        } catch (error) {
            if (error.name === 'AbortError' || ticket !== sequence) return;
            message.textContent = error.message; message.hidden = false;
        }
    }
    document.addEventListener('click', event => {
        const cancel = event.target.closest('[data-plan-modal-close]');
        if (cancel && modal?.contains(cancel)) { event.preventDefault(); event.stopPropagation(); close(); return; }
        const link = event.target.closest('a[href]');
        if (!link || event.button !== 0 || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return;
        const url = new URL(link.href, location.href);
        const pagination = modal?.contains(link) && currentUrl && url.pathname === currentUrl.pathname;
        if (!link.hasAttribute('data-plan-modal-link') && !pagination) return;
        event.preventDefault(); event.stopPropagation(); open(url.href, link);
    }, true);
    document.addEventListener('keydown', event => {
        if (!modal || modal.hidden || document.querySelector('.swal2-container')) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const items = Array.from(modal.querySelectorAll('a[href],button:not(:disabled),input:not([type="hidden"]):not(:disabled),select:not(:disabled),textarea:not(:disabled)'));
            const first = items[0], last = items[items.length-1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    window.VitaPlanModal = {open, setRows: value => { rows = value; }};
})();
