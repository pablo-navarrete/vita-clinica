(() => {
    if (!window.jQuery?.fn.DataTable) return;
    const table = document.getElementById('subscription-payments-table');
    if (!table || jQuery.fn.dataTable.isDataTable(table)) return;
    jQuery(table).DataTable({
        pageLength: 10, lengthMenu: [10, 25, 50, 100], pagingType: 'full_numbers',
        order: [[0, 'desc']], dom: 'lfrtip',
        language: {
            lengthMenu: 'Mostrar _MENU_ registros', search: 'Buscar:',
            zeroRecords: 'No se encontraron pagos', emptyTable: 'No hay pagos registrados.',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ registros', infoEmpty: 'Mostrando 0 registros',
            infoFiltered: '(filtrado de _MAX_ registros)',
            paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
        },
    });
})();
