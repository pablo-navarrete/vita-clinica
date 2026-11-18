(() => {
    document.querySelectorAll('[data-profile-assignments]').forEach(root => {
    const branch = root.querySelector('[data-assignment-branch]');
    const fields = root.querySelector('[data-assignment-fields]');
    const message = root.querySelector('[data-assignment-message]');
    const saveButton = root.querySelector('[data-save-assignment]');
    let busy = false;
    let specialtyCatalog = [], selectedSpecialties = [], canAssign = false;
    const specialtySelect = root.querySelector('[data-specialty-select]');
    function renderSpecialties() {
        const container = root.querySelector('[data-assignment-specialties]');
        container.replaceChildren();
        selectedSpecialties.forEach(id => {
            const specialty = specialtyCatalog.find(item => Number(item.id) === id);
            if (!specialty) return;
            const badge = document.createElement('span');
            badge.className = 'inline-flex items-center gap-2 rounded-full bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700';
            const remove = document.createElement('button');
            remove.type = 'button'; remove.textContent = '×'; remove.disabled = !canAssign;
            remove.setAttribute('aria-label', 'Quitar ' + specialty.name);
            remove.addEventListener('click', () => { selectedSpecialties = selectedSpecialties.filter(value => value !== id); renderSpecialties(); });
            badge.append(document.createTextNode(specialty.name), remove); container.append(badge);
        });
        if (!selectedSpecialties.length) container.textContent = 'Aún no hay especialidades asignadas en esta sede.';
    }
    function selectSpecialty() {
        const id = Number(specialtySelect.value);
        if (canAssign && id && !selectedSpecialties.includes(id)) { selectedSpecialties.push(id); renderSpecialties(); }
        specialtySelect.value = '';
        if (window.jQuery?.fn.select2) window.jQuery(specialtySelect).trigger('change.select2');
    }
    if (specialtySelect && window.jQuery?.fn.select2) {
        window.jQuery(specialtySelect).select2({ width: '100%', dropdownParent: window.jQuery(root),
            language: { noResults: () => 'No se encontraron especialidades', searching: () => 'Buscando…' } })
            .on('change', selectSpecialty);
    } else specialtySelect?.addEventListener('change', selectSpecialty);
    async function request(url, options = {}) {
        const response = await fetch(url, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, cache: 'no-store' });
        const data = await response.json();
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudieron guardar los cambios.');
        return data;
    }
    async function load() {
        fields.disabled = true;
        saveButton.disabled = true;
        message.textContent = 'Cargando asignaciones…';
        const query = '?branch_id=' + encodeURIComponent(branch.value);
        const isRoles = root.dataset.kind === 'roles';
        const roles = isRoles ? await request(root.dataset.rolesUrl + query) : { roles: [] };
        const specialties = !isRoles ? await request(root.dataset.specialtiesUrl + query) : null;
        root.querySelectorAll('[data-assignment-role]').forEach(input => input.checked = roles.roles.some(role => String(role.id) === input.value));
        if (specialties) {
        specialtyCatalog = specialties.specialties;
        selectedSpecialties = specialties.assigned.map(Number);
        canAssign = specialties.can_assign;
        specialtySelect.replaceChildren(new Option('Selecciona una especialidad', ''));
        specialtySelect.disabled = !canAssign;
        specialties.specialties.forEach(specialty => {
            specialtySelect.append(new Option(specialty.name, specialty.id));
        });
        renderSpecialties();
        if (window.jQuery?.fn.select2) window.jQuery(specialtySelect).trigger('change.select2');
        root.querySelector('[data-save-assignment="specialties"]').disabled = !specialties.can_assign;
        root.querySelector('[data-specialty-help]').textContent = specialties.can_assign ? '' : 'Guarda primero el rol Doctor en esta sede para asignar especialidades.';
        }
        message.textContent = ''; fields.disabled = false;
        saveButton.disabled = root.dataset.kind === 'specialties' && !canAssign;
    }
    function fail(error) { message.textContent = error.message || 'No se pudieron cargar las asignaciones.'; }
    branch.addEventListener('change', () => load().catch(fail));
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-save-assignment]');
        if (!button || button.disabled || busy) return;
        busy = true; fields.disabled = true; branch.disabled = true;
        saveButton.disabled = true;
        try {
            const roles = button.dataset.saveAssignment === 'roles';
            const selected = roles ? Array.from(root.querySelectorAll('[data-assignment-role]:checked'), input => Number(input.value)) : selectedSpecialties;
            await request(roles ? root.dataset.rolesUrl : root.dataset.specialtiesUrl, { method: 'PATCH',
                body: JSON.stringify({ branch_id: Number(branch.value), [roles ? 'role_ids' : 'specialty_ids']: selected }) });
            await load();
            busy = false; close();
            message.textContent = 'Cambios guardados correctamente.';
            if (window.showClinicalRecordToast) window.showClinicalRecordToast(message.textContent, 'success');
            else window.Swal?.fire({ toast: true, position: 'top-end', icon: 'success', title: message.textContent,
                showConfirmButton: false, timer: 3000 });
        } catch (error) { fail(error); fields.disabled = false; saveButton.disabled = root.dataset.kind === 'specialties' && !canAssign; }
        finally { busy = false; branch.disabled = false; }
    });
    let opener;
    let previousOverflow;
    function close() {
        if (busy) return;
        root.hidden = true;
        document.body.style.overflow = previousOverflow;
        opener?.focus();
    }
    document.querySelectorAll('[data-open-assignment="' + root.dataset.kind + '"]').forEach(button => {
        button.addEventListener('click', () => {
            opener = button;
            previousOverflow = document.body.style.overflow;
            root.hidden = false;
            document.body.style.overflow = 'hidden';
            branch.focus();
            load().catch(fail);
        });
    });
    root.querySelectorAll('[data-assignment-close]').forEach(button => button.addEventListener('click', close));
    root.addEventListener('click', event => { if (event.target === root) close(); });
    document.addEventListener('keydown', event => {
        if (root.hidden) return;
        if (event.key === 'Escape') { event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const focusable = Array.from(root.querySelectorAll('button:not(:disabled), select:not(:disabled), input:not(:disabled)'))
                .filter(element => !element.closest('fieldset:disabled'));
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
        }
    });
    });
})();
