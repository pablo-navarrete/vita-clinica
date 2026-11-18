/* Reuses the public booking form; contains no patient/account identifiers or authorization codes. */
(() => {
    'use strict';
    const form = document.getElementById('public-booking-form');
    if (!form) return;
    const country = form.elements.namedItem('identification_country');
    const type = form.elements.namedItem('identification_type');
    const types = window.vitaBookingDocumentTypes || {};
    const updateTypes = () => {
        const previous = type.value;
        type.replaceChildren(...(types[country.value] || []).map(item => new Option(item.label, item.value)));
        if (Array.from(type.options).some(option => option.value === previous)) type.value = previous;
        if (window.jQuery) window.jQuery(type).trigger('change.select2');
    };
    if (window.jQuery) window.jQuery(country).on('change.portalBooking', updateTypes);
    else country.addEventListener('change', updateTypes);
    (window.vitaPortalBookingFields || []).forEach(name => {
        const field = form.elements.namedItem(name);
        if (!field || !field.value) return;
        const group = field.closest('.form-group');
        if (group) group.hidden = true;
        field.readOnly = true;
    });
    if ((window.vitaPortalBookingFields || []).length) {
        const summary = document.createElement('p');
        summary.setAttribute('role', 'status');
        summary.className = 'alert alert-light mt-3';
        const updateSummary = () => {
            const label = name => {
                const field = form.elements.namedItem(name);
                return field?.selectedOptions?.[0]?.textContent || field?.value || 'Por seleccionar';
            };
            summary.textContent = `Revisa tu reserva: ${label('specialty_id')} · ${label('professional_id')} · ${label('date')} · ${label('time')}. La disponibilidad se comprobará al confirmar.`;
        };
        const submit = form.querySelector?.('.booking-submit');
        if (submit) form.insertBefore(summary, submit.parentElement);
        else form.append(summary);
        form.addEventListener('change', updateSummary);
        form.addEventListener('booking-selection-updated', updateSummary);
        if (window.jQuery) window.jQuery(form).on('change.portalSummary', updateSummary);
        updateSummary();
    }
})();
