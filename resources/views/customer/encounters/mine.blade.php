@extends('layouts.menu-customer')

@section('header')
    <h2 class="text-xl font-semibold text-gray-800">{{ $isSuperAdmin ? 'Atenciones' : 'Mis atenciones' }}</h2>
@endsection

@section('content')
    <div class="mx-auto max-w-7xl px-3 py-8 sm:px-6 lg:px-8">
        <section class="rounded-xl bg-white p-6 shadow-sm">
            <h3 class="text-lg font-semibold text-gray-800">{{ $isSuperAdmin ? 'Atenciones' : 'Mis atenciones' }}</h3>
            <p class="mb-6 mt-1 text-sm text-gray-500">{{ $isSuperAdmin ? 'Atenciones de la clínica en' : 'Tus atenciones en' }} {{ $allBranches ? 'todas las sedes' : $branch->name }}.</p>
            @if ($allBranches)
                <div class="mb-5 max-w-xs">
                    <label for="my-encounters-branch" class="mb-1 block text-sm font-medium text-gray-700">Sede</label>
                    <select id="my-encounters-branch" class="w-full rounded-lg border-gray-300 text-sm">
                        <option value="">Todas las sedes</option>
                        @foreach ($filterBranches as $filterBranch)
                            <option value="{{ $filterBranch->id }}">{{ $filterBranch->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <div id="my-encounter-tabs" class="mb-5 flex gap-2 overflow-x-auto" role="tablist" aria-label="Estado de mis atenciones">
                @foreach (['' => 'Todas', 'scheduled' => 'Agendadas', '1' => 'En curso', '2' => 'Atendidas', '3' => 'Canceladas'] as $status => $label)
                    <button type="button" role="tab" data-status="{{ $status }}" id="my-encounters-tab-{{ $status === '' ? 'all' : $status }}"
                        aria-selected="{{ $status === '' ? 'true' : 'false' }}" aria-controls="my-encounters-panel" tabindex="{{ $status === '' ? '0' : '-1' }}"
                        class="my-encounter-tab shrink-0 rounded-lg border px-4 py-2 text-sm font-semibold">{{ $label }}</button>
                @endforeach
            </div>
            <div id="my-encounters-panel" role="tabpanel" aria-labelledby="my-encounters-tab-all" class="overflow-x-auto">
                <table id="my-encounters-table" class="min-w-full display nowrap rounded-xl overflow-hidden" style="width:100%">
                    <thead style="background-color: {{ themeColor(100) }}">
                        <tr><th scope="col">#</th><th>Atención</th><th>Paciente</th><th>RUT</th><th>Especialidad</th><th>Modalidad</th><th>Estado</th><th>Inicio</th><th>Sede</th><th>Acciones</th></tr>
                    </thead>
                </table>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const text = jQuery.fn.dataTable.render.text();
    let status = '';
    const table = jQuery('#my-encounters-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: @json(route('customers.encounters.mine', ['customer' => request()->route('customer')])),
            cache: false,
            data: data => {
                data.status = status;
                data.branch_id = document.getElementById('my-encounters-branch')?.value || '';
                data.columns[1].name = status === 'scheduled' ? 'appointments.id' : 'encounters.id';
                data.columns[7].name = status === 'scheduled' ? 'appointments.start_at' : 'encounters.started_at';
            },
        },
        pageLength: 10,
        lengthMenu: [[10,25,50,100],[10,25,50,100]],
        pagingType: 'full_numbers',
        order: [[7,'desc']],
        autoWidth: false,
        responsive: false,
        columns: [
            {data:null, orderable:false, searchable:false, render:(data,type,row,meta) => meta.settings._iDisplayStart + meta.row + 1},
            {data:'id', name:'encounters.id', render:text},
            {data:'patient.name', name:'patient.name', defaultContent:'Paciente no disponible', render:text},
            {data:'patient.rut', name:'patient.rut', defaultContent:'', render:text},
            {data:'specialty.name', name:'specialty.name', defaultContent:'Sin especialidad', render:text},
            {data:'modality_label', orderable:false, searchable:false, render:text},
            {data:'status_label', orderable:false, searchable:false, render:text},
            {data:'started_display', name:'encounters.started_at', searchable:false, render:text},
            {data:'clinic_branch.name', name:'clinicBranch.name', defaultContent:'Sin sede registrada', render:text},
            {data:'record_url', orderable:false, searchable:false, render:(url,type,row) => {
                if(type!=='display') return '';
                const link=document.createElement('a');
                link.href=url;
                link.className='inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white hover:brightness-90';
                link.style.backgroundColor=@json(themeColor(600));
                link.textContent=@json($isSuperAdmin)?'Ver ficha':row.is_scheduled?'Iniciar atención':(Number(row.status)===1?'Continuar atención':'Ver atención');
                return link.outerHTML;
            }},
        ],
        language: {
            processing:'Cargando atenciones...', search:'Buscar:', lengthMenu:'Mostrar _MENU_ atenciones',
            info:'Mostrando _START_ a _END_ de _TOTAL_ atenciones', infoEmpty:'No hay atenciones para mostrar',
            infoFiltered:'(filtrado de _MAX_ atenciones)', zeroRecords:'No se encontraron atenciones', emptyTable:'No hay atenciones registradas para estos filtros.',
            paginate:{first:'Primera',last:'Última',next:'Siguiente',previous:'Anterior'},
        },
    });
    const branchSelect = jQuery('#my-encounters-branch');
    if (branchSelect.length && jQuery.fn.select2) {
        branchSelect.select2({
            width: '100%',
            minimumResultsForSearch: 0,
            language: {
                noResults: () => 'No se encontraron sedes',
                searching: () => 'Buscando...',
            },
        });
    }
    branchSelect.on('change', () => table.ajax.reload());
    window.addEventListener('pageshow', event => { if (event.persisted) table.ajax.reload(null, false); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) table.ajax.reload(null, false); });
    const tabs = [...document.querySelectorAll('.my-encounter-tab')];
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => {
            status = tab.dataset.status;
            tabs.forEach(item => {
                item.setAttribute('aria-selected', String(item === tab));
                item.tabIndex = item === tab ? 0 : -1;
            });
            document.getElementById('my-encounters-panel').setAttribute('aria-labelledby', tab.id);
            table.ajax.reload();
        });
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = tabs[(index + 1) % tabs.length];
            if (event.key === 'ArrowLeft') next = tabs[(index + tabs.length - 1) % tabs.length];
            if (event.key === 'Home') next = tabs[0];
            if (event.key === 'End') next = tabs[tabs.length - 1];
            if (next) { event.preventDefault(); next.focus(); next.click(); }
        });
    });
});
</script>
@endpush

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
<style>
 #my-encounters-branch + .select2-container .select2-selection--single { height: 42px; border: 1px solid #d1d5db; border-radius: .5rem; }
 #my-encounters-branch + .select2-container .select2-selection__rendered { line-height: 40px; padding-left: .75rem; color: #374151; font-size: .875rem; }
 #my-encounters-branch + .select2-container .select2-selection__arrow { height: 40px; }
 .my-encounter-tab { border-color: #d1d5db; color: #4b5563; background: #fff; }
 .my-encounter-tab[aria-selected="true"] { border-color: {{ themeColor(600) }}; background: {{ themeColor(600) }}; color: #fff; }
#my-encounters-table { border-collapse: separate; border-spacing: 0; border-radius: .75rem; overflow: hidden; }
        #my-encounters-table_wrapper .dataTables_length,
        #my-encounters-table_wrapper .dataTables_filter { margin-bottom: 1rem; }
        #my-encounters-table_wrapper .dataTables_length label,
        #my-encounters-table_wrapper .dataTables_filter label { color: #374151; font-weight: 500; }
        #my-encounters-table_wrapper .dataTables_length select,
        #my-encounters-table_wrapper .dataTables_filter input {
            min-height: 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background-color: #fff;
        }
        #my-encounters-table_wrapper .dataTables_filter input { margin-left: 0.5rem; padding: 0.4rem 0.75rem; }
        #my-encounters-table_wrapper .dataTables_length select { margin: 0 0.4rem; padding: 0.35rem 1.75rem 0.35rem 0.65rem; }
        #my-encounters-table_wrapper .dataTables_info { padding-top: 0.75rem; color: #6b7280; }
        #my-encounters-table_wrapper .dataTables_paginate { padding-top: 0.5rem; }
        #my-encounters-table_wrapper .dataTables_paginate .paginate_button {
            min-width: 2.25rem;
            margin: 0 0.15rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid transparent !important;
            border-radius: 0.5rem;
            color: #374151 !important;
        }
        #my-encounters-table_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #d1d5db !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
        }
        #my-encounters-table_wrapper .dataTables_paginate .paginate_button.current {
            border-color: {{ themeColor(600) }} !important;
            background: {{ themeColor(600) }} !important;
            color: #fff !important;
        }
        #my-encounters-table_wrapper .dataTables_paginate .paginate_button.disabled,
        #my-encounters-table_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            border-color: transparent !important;
            background: transparent !important;
            color: #9ca3af !important;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            #my-encounters-table_wrapper .dataTables_length,
            #my-encounters-table_wrapper .dataTables_filter { float: none; text-align: left; }
            #my-encounters-table_wrapper .dataTables_filter input { width: min(14rem, 60vw); }
            #my-encounters-table_wrapper .dataTables_info,
            #my-encounters-table_wrapper .dataTables_paginate { float: none; text-align: center; }
        }
</style>
@endpush
