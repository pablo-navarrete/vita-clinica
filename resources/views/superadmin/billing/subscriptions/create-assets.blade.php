    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
        <style>
            .subscription-search-picker .select2-container { width:100% !important; }
            .subscription-search-picker .select2-selection--single { height:42px; border:1px solid #d1d5db; border-radius:.5rem; display:flex; align-items:center; }
            .subscription-search-picker .select2-selection__rendered { padding-left:.75rem !important; color:#111827 !important; }
            .subscription-search-picker .select2-selection__arrow { height:40px !important; }
            .subscription-search-picker .select2-container--focus .select2-selection--single,
            .subscription-search-picker .select2-container--open .select2-selection--single { border-color:{{ themeColor(600) }}; }
            .subscription-search-picker .select2-results__option--highlighted[aria-selected] { background-color:{{ themeColor(600) }}; }
            .subscription-search-picker .select2-search__field { border-radius:.375rem; }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
        <script>
            (() => {
                if (!window.jQuery?.fn.select2) return;
                jQuery('#subscription-customer, #subscription-price').each(function () {
                    const select = jQuery(this);
                    const isPlan = this.id === 'subscription-price';
                    const parent = select.closest('.subscription-search-picker');
                    select.select2({
                        width: '100%',
                        placeholder: isPlan ? 'Selecciona un plan' : 'Selecciona un cliente sin suscripción',
                        minimumResultsForSearch: 0,
                        dropdownParent: parent,
                        language: { noResults: () => isPlan ? 'No se encontraron planes' : 'No se encontraron clientes', searching: () => 'Buscando...' },
                    });
                    select.on('select2:open', () => {
                        const search = parent[0].querySelector('.select2-search__field');
                        if (search) { search.placeholder = isPlan ? 'Buscar plan...' : 'Buscar cliente...'; search.focus(); }
                    });
                });
            })();
        </script>
    @endpush

