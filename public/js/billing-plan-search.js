(() => {
    const tableElement = document.getElementById('billing-plans-table');
    if (!tableElement || !window.jQuery?.fn.DataTable) return;
    const $ = window.jQuery;
    const text = $.fn.dataTable.render.text();
    const table = $(tableElement).DataTable({
        processing: true, serverSide: true, responsive: false,
        ajax: tableElement.dataset.url,
        dom: 'lfrtip', search: { search: tableElement.dataset.initialSearch || '' }, searchDelay: 300, order: [[0, 'desc']],
        pageLength: 10, lengthMenu: [10, 25, 50, 100], pagingType: 'full_numbers',
        language: {
            processing: 'Procesando…', lengthMenu: 'Mostrar _MENU_ registros', search: 'Buscar:',
            zeroRecords: 'No se encontraron planes', emptyTable: 'No hay planes registrados',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)', loadingRecords: 'Cargando…',
            paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
            aria: { sortAscending: ': ordenar ascendente', sortDescending: ': ordenar descendente' }
        },
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name', render: text },
            { data: 'code', name: 'code', render: text },
            { data: 'active', name: 'active', render: (value, type) => type === 'display'
                ? '<button type="button" class="billing-plan-status px-2 py-1 rounded text-xs text-white ' + (Number(value) === 1 ? 'bg-green-500' : 'bg-red-500') + '" aria-label="Cambiar estado del plan">' + (Number(value) === 1 ? 'Activo' : 'Inactivo') + '</button>' : value },
            { data: 'updated_at', name: 'updated_at', searchable: false, render: value => value ? new Date(value).toLocaleString('es-CL') : '—' },
            { data: null, orderable: false, searchable: false, render: (_value, type, row) => {
                if (type !== 'display') return '';
                return '<button type="button" class="billing-actions-btn rounded px-2 py-1 hover:bg-gray-100" aria-label="Acciones del plan" aria-haspopup="menu" aria-expanded="false" aria-controls="billing-plan-actions"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></button>';
            } }
        ]
    });
    window.addEventListener('billing:plan-saved', () => table.ajax.reload(null, false));
    $(tableElement).on('click', '.billing-plan-status', async function () {
        const row = table.row(this.closest('tr')).data();
        if (!row || this.disabled) return;
        this.disabled = true;
        try {
            const response = await fetch(row.status_url, {
                method: 'PATCH',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ active: Number(row.active) !== 1 }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'No se pudo actualizar el estado.');
            table.ajax.reload(null, false);
            window.Swal?.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
        } catch (error) {
            window.Swal?.fire({ icon: 'error', title: 'No se pudo actualizar el estado', text: error.message });
        } finally { this.disabled = false; }
    });
    $(tableElement).on('xhr.dt', (_event, _settings, json) => window.VitaPlanModal?.setRows(json?.data || []));
    const menu = document.createElement('div');
    menu.id = 'billing-plan-actions'; menu.hidden = true;
    menu.setAttribute('role', 'menu'); menu.setAttribute('aria-label', 'Acciones del plan');
    menu.className = 'fixed w-52 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg';
    menu.style.zIndex = '9999';
    menu.innerHTML = '<a role="menuitem" data-action="view" class="block px-4 py-2 text-sm hover:bg-gray-100"><i class="fa-solid fa-eye mr-2" aria-hidden="true"></i>Ver plan</a>' +
        '<a role="menuitem" data-action="edit" class="block px-4 py-2 text-sm hover:bg-gray-100"><i class="fa-solid fa-pen-to-square mr-2" aria-hidden="true"></i>Editar plan</a>' +
        '<button type="button" role="menuitem" data-action="delete" class="w-full border-t border-gray-100 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50"><i class="fa-solid fa-trash-can mr-2" aria-hidden="true"></i>Eliminar plan</button>';
    document.body.append(menu);
    let selected, trigger, deleting = false;
    function closeMenu(focus = false) { menu.hidden = true; trigger?.setAttribute('aria-expanded', 'false'); if (focus) trigger?.focus(); }
    $(tableElement).on('click', '.billing-actions-btn', function (event) {
        event.preventDefault(); event.stopPropagation();
        if (trigger === this && !menu.hidden) return closeMenu();
        closeMenu(); selected = table.row(this.closest('tr')).data(); if (!selected) return;
        trigger = this; menu.querySelector('[data-action="view"]').href = selected.show_url;
        menu.querySelector('[data-action="edit"]').href = selected.edit_url;
        menu.hidden = false; trigger.setAttribute('aria-expanded', 'true');
        const rect = trigger.getBoundingClientRect();
        menu.style.left = Math.max(8, Math.min(rect.right - menu.offsetWidth, innerWidth - menu.offsetWidth - 8)) + 'px';
        menu.style.top = Math.max(8, Math.min(rect.bottom + 5, innerHeight - menu.offsetHeight - 8)) + 'px';
        if (event.detail === 0) menu.querySelector('a').focus();
    });
    document.addEventListener('click', event => { if (!menu.contains(event.target)) closeMenu(); });
    document.addEventListener('scroll', () => closeMenu(), true);
    window.addEventListener('resize', () => closeMenu());
    $(tableElement).on('preDraw.dt', () => closeMenu());
    menu.addEventListener('keydown', event => {
        const items = Array.from(menu.querySelectorAll('[role="menuitem"]'));
        if (['ArrowDown','ArrowUp','Home','End'].includes(event.key)) {
            event.preventDefault(); const i = items.indexOf(document.activeElement);
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? items.length - 1 : (i + (event.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[next].focus();
        }
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape' && !menu.hidden) { event.preventDefault(); closeMenu(true); } });
    menu.addEventListener('click', async event => {
        const action = event.target.closest('[data-action]'); if (!action) return;
        const row = selected; closeMenu();
        if (action.dataset.action === 'view' && !event.ctrlKey && !event.metaKey && !event.shiftKey && !event.altKey) {
            event.preventDefault(); window.VitaPlanModal?.open(action.href, trigger, row); return;
        }
        if (action.dataset.action !== 'delete' || deleting || !row) return;
        deleting = true;
        try {
            const result = await Swal.fire({ title: '¿Eliminar plan?', text: 'Se quitará del catálogo. Las versiones publicadas y su historial se conservarán.',
                icon: 'warning', showCancelButton: true, confirmButtonText: 'Eliminar plan', cancelButtonText: 'Cancelar', confirmButtonColor: '#dc2626' });
            if (!result.isConfirmed) return;
            const response = await fetch(row.delete_url, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
            const data = await response.json(); if (!response.ok) throw new Error(data.message || 'No se pudo eliminar el plan.');
            table.ajax.reload(null, false);
            Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: data.message, showConfirmButton: false, timer: 3000 });
        } catch (error) { Swal.fire({ icon: 'error', title: 'No se pudo eliminar', text: error.message }); }
        finally { deleting = false; }
    });
    $(tableElement).on('search.dt', () => {
        const value = table.search();
        const url = new URL(location.href);
        if (value) url.searchParams.set('search', value); else url.searchParams.delete('search');
        url.searchParams.delete('page');
        history.replaceState(history.state, '', url.href);
    });
})();
