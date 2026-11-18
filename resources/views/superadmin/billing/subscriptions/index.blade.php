<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Suscripciones</h2></x-slot>
    <div class="p-6"><div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="min-w-0 max-w-full rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-500">Una suscripción por cliente. Administración local, sin cobros automáticos ni restricciones de acceso.</p>
                <button type="button" data-open-subscription class="shrink-0 rounded bg-blue-500 px-4 py-2 font-bold text-white hover:bg-blue-700"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Crear suscripción</button>
            </div>
            <table id="billing-subscriptions-table" data-url="{{ route('superadmin.billing.subscriptions.data') }}" class="display nowrap min-w-full rounded-xl overflow-hidden" style="width:100%">
                <thead style="background-color: {{ themeColor(100) }}"><tr><th>ID</th><th>Cliente</th><th>Plan</th><th>Periodicidad</th><th>Período</th><th>Modalidad</th><th>Contrato</th><th>Finanzas</th><th>Acceso</th><th>Pagado hasta</th><th>Próximo período</th><th>Acciones</th></tr></thead><tbody></tbody>
            </table>
        </div>
    </div></div>
    <div id="subscription-create-modal" hidden role="dialog" aria-modal="true" aria-labelledby="subscription-create-title" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" style="display:none">
        <div class="flex w-full max-w-2xl flex-col overflow-hidden rounded-lg bg-white shadow-lg" style="max-height:90vh">
            <div class="flex shrink-0 items-center justify-between rounded-t-lg border-b bg-blue-100 px-6 py-4">
                <h2 id="subscription-create-title" class="text-lg font-bold text-gray-800">Crear suscripción</h2>
                <button type="button" data-close-subscription aria-label="Cerrar" class="px-2 py-1 text-gray-500 hover:text-gray-800"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button>
            </div>
                @include('superadmin.billing.subscriptions.create-form', ['inModal'=>true])
        </div>
    </div>
    @include('superadmin.billing.subscriptions.create-assets')
    @push('scripts')<script src="{{ asset('js/billing-subscription-modal.js') }}?v={{ filemtime(public_path('js/billing-subscription-modal.js')) }}"></script>@endpush
    @push('styles')
    <style>
        #billing-subscriptions-table_wrapper { min-width:0; max-width:100%; }
        #billing-subscriptions-table_wrapper .billing-subscriptions-scroll { clear:both; width:100%; overflow-x:auto; border-radius:.75rem; }
        #billing-subscriptions-table_wrapper .dataTables_paginate { padding-top:.5rem; }
        #billing-subscriptions-table_wrapper .dataTables_paginate .paginate_button { min-width:2.25rem; margin:0 .15rem; padding:.4rem .65rem; border:1px solid transparent !important; border-radius:.5rem; color:#374151 !important; }
        #billing-subscriptions-table_wrapper .dataTables_paginate .paginate_button:hover { border-color:#d1d5db !important; background:#f3f4f6 !important; color:#111827 !important; }
        #billing-subscriptions-table_wrapper .dataTables_paginate .paginate_button.current { border-color:{{ themeColor(600) }} !important; background:{{ themeColor(600) }} !important; color:#fff !important; }
        #billing-subscriptions-table_wrapper .dataTables_paginate .paginate_button.disabled { background:transparent !important; color:#9ca3af !important; }
    </style>
    @endpush
</x-app-layout>
