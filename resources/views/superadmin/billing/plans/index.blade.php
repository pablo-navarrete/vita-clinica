<x-app-layout>
    <x-slot name="header"><h2 class="text-xl font-semibold text-gray-800">Planes</h2></x-slot>
    <div class="p-6">
    <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <div class="overflow-x-auto rounded-xl bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-4">
                <p class="text-sm text-gray-500">Catálogo comercial. Esta etapa no realiza cobros ni modifica el acceso de las clínicas.</p>
                <a href="{{ route('superadmin.billing.plans.create') }}" class="shrink-0 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i> Crear plan</a>
            </div>
            <table id="billing-plans-table" data-initial-search="{{ $search }}" data-url="{{ route('superadmin.billing.plans.data') }}" class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                <thead style="background-color: {{ themeColor(100) }}"><tr><th>ID</th><th>Plan</th><th>Código</th><th>Estado</th><th>Actualizado</th><th>Acciones</th></tr></thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    </div>
    @push('scripts')
        <script src="{{ asset('js/billing-plan-modal.js') }}?v={{ filemtime(public_path('js/billing-plan-modal.js')) }}"></script>
        <script src="{{ asset('js/billing-plan-search.js') }}?v={{ filemtime(public_path('js/billing-plan-search.js')) }}"></script>
    @endpush
    @include('superadmin.billing.partials.plan-modal')
    @push('styles')
        <style>
            #billing-plans-table_wrapper .dataTables_info { padding-top:.75rem; color:#6b7280; }
            #billing-plans-table_wrapper .dataTables_paginate { padding-top:.5rem; }
            #billing-plans-table_wrapper .dataTables_paginate .paginate_button {
                min-width:2.25rem; margin:0 .15rem; padding:.4rem .65rem;
                border:1px solid transparent !important; border-radius:.5rem; color:#374151 !important;
            }
            #billing-plans-table_wrapper .dataTables_paginate .paginate_button:hover {
                border-color:#d1d5db !important; background:#f3f4f6 !important; color:#111827 !important;
            }
            #billing-plans-table_wrapper .dataTables_paginate .paginate_button.current {
                border-color:{{ themeColor(600) }} !important; background:{{ themeColor(600) }} !important; color:#fff !important;
            }
            #billing-plans-table_wrapper .dataTables_paginate .paginate_button.disabled,
            #billing-plans-table_wrapper .dataTables_paginate .paginate_button.disabled:hover {
                border-color:transparent !important; background:transparent !important; color:#9ca3af !important; cursor:not-allowed;
            }
            @media(max-width:640px) {
                #billing-plans-table_wrapper .dataTables_info,
                #billing-plans-table_wrapper .dataTables_paginate { float:none; text-align:center; }
            }
        </style>
    @endpush
</x-app-layout>
