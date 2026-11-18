(() => {
    const initialize = form => {
    if (!form) return;
    if (form.dataset.addendumInitialized) return form.addendumControl;
    form.dataset.addendumInitialized = '1';
    const type = form.querySelector('[name="type"]');
    const group = form.querySelector('[data-rectification-fields]');
    const target = form.querySelector('[name="rectifies_version_id"]');
    const field = form.querySelector('[name="target_field"]');
    const reason = form.querySelector('[name="reason"]');
    const sync = () => {
        const rectification = type.value === 'rectification';
        group.hidden = !rectification;
        for (const control of [target, field, reason]) { control.disabled = !rectification; control.required = rectification; }
        const original = target.selectedOptions[0]?.dataset.kind === 'original';
        for (const option of field.options) {
            const allowed = original ? option.value !== 'content' : option.value === 'content';
            option.hidden = !allowed; option.disabled = !allowed;
        }
        if (field.selectedOptions[0]?.disabled) field.value = [...field.options].find(option => !option.disabled)?.value || '';
    };
    type.addEventListener('change', sync); target.addEventListener('change', sync); sync();
    let dirty = false;
    form.addEventListener('input', () => { dirty = true; });
    const previousGuard = window.vitaHasUnsavedChanges;
    const guard = () => dirty || Boolean(previousGuard?.());
    window.vitaHasUnsavedChanges = guard;
    const beforeUnload = event => {
        if (!window.vitaHasUnsavedChanges()) return;
        event.preventDefault(); event.returnValue = '';
    };
    window.addEventListener('beforeunload', beforeUnload);
    return form.addendumControl = {
        isDirty: () => dirty,
        dispose: () => {
            window.removeEventListener('beforeunload', beforeUnload);
            if (window.vitaHasUnsavedChanges === guard) window.vitaHasUnsavedChanges = previousGuard;
        },
    };
    };
    window.initializeEncounterAddendum = initialize;
    initialize(document.querySelector('[data-addendum-form]'));
})();
