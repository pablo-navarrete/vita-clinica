@push('styles')
<style>
        .ongoing-encounters table.dataTable {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.75rem;
            overflow: hidden;
        }
        .ongoing-encounters .dataTables_wrapper .dataTables_length,
        .ongoing-encounters .dataTables_wrapper .dataTables_filter { margin-bottom: 1rem; }
        .ongoing-encounters .dataTables_wrapper .dataTables_length label,
        .ongoing-encounters .dataTables_wrapper .dataTables_filter label { color: #374151; font-weight: 500; }
        .ongoing-encounters .dataTables_wrapper .dataTables_length select,
        .ongoing-encounters .dataTables_wrapper .dataTables_filter input {
            min-height: 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background-color: #fff;
        }
        .ongoing-encounters .dataTables_wrapper .dataTables_filter input { margin-left: 0.5rem; padding: 0.4rem 0.75rem; }
        .ongoing-encounters .dataTables_wrapper .dataTables_length select { margin: 0 0.4rem; padding: 0.35rem 1.75rem 0.35rem 0.65rem; }
        .ongoing-encounters .dataTables_wrapper .dataTables_info { padding-top: 0.75rem; color: #6b7280; }
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate { padding-top: 0.5rem; }
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate .paginate_button {
            min-width: 2.25rem;
            margin: 0 0.15rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid transparent !important;
            border-radius: 0.5rem;
            color: #374151 !important;
        }
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #d1d5db !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
        }
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            border-color: {{ themeColor(600) }} !important;
            background: {{ themeColor(600) }} !important;
            color: #fff !important;
        }
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate .paginate_button.disabled,
        .ongoing-encounters .dataTables_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            border-color: transparent !important;
            background: transparent !important;
            color: #9ca3af !important;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            .ongoing-encounters .dataTables_wrapper .dataTables_length,
            .ongoing-encounters .dataTables_wrapper .dataTables_filter { float: none; text-align: left; }
            .ongoing-encounters .dataTables_wrapper .dataTables_filter input { width: min(14rem, 60vw); }
            .ongoing-encounters .dataTables_wrapper .dataTables_info,
            .ongoing-encounters .dataTables_wrapper .dataTables_paginate { float: none; text-align: center; }
        }
</style>
@endpush

@if ($ongoingPanel['visible'])
    @php
        $initialBranch = $ongoingPanel['groups']->firstWhere('id', (int) session('active_branch_id'))
            ?? $ongoingPanel['groups']->first();
    @endphp
    <section class="ongoing-encounters mt-6" x-data="{ branch: {{ (int) ($initialBranch['id'] ?? 0) }} }">
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 p-5">
                <h3 class="text-lg font-semibold text-gray-800">Atenciones en curso</h3>
                <p class="mt-1 text-sm text-gray-500">Atenciones abiertas, independientemente de los filtros de estadísticas.</p>
            </div>
            @if ($ongoingPanel['tabs'])
                <div class="flex gap-2 overflow-x-auto border-b border-gray-200 p-3" role="tablist" aria-label="Sedes con atenciones en curso">
                    @foreach ($ongoingPanel['groups'] as $group)
                        <button type="button" id="ongoing-tab-{{ $group['id'] }}" role="tab"
                            aria-controls="ongoing-panel-{{ $group['id'] }}" :aria-selected="branch === {{ $group['id'] }}"
                            @click="branch = {{ $group['id'] }}; $nextTick(() => $dispatch('ongoing-tab-changed'))"
                            @keydown.arrow-right.prevent="$el.nextElementSibling?.focus(); $el.nextElementSibling?.click()"
                            @keydown.arrow-left.prevent="$el.previousElementSibling?.focus(); $el.previousElementSibling?.click()"
                            :tabindex="branch === {{ $group['id'] }} ? 0 : -1"
                            :class="branch === {{ $group['id'] }} ? 'bg-gray-100 text-gray-900 border-gray-400' : 'bg-white text-gray-600 border-gray-200'"
                            class="shrink-0 rounded-lg border px-4 py-2 text-sm font-semibold">
                            {{ $group['name'] }} <span class="ml-1 rounded-full bg-gray-100 px-2 py-0.5 text-xs">{{ $group['encounters']->count() }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
            @forelse ($ongoingPanel['groups'] as $group)
                <div id="ongoing-panel-{{ $group['id'] }}" x-show="branch === {{ $group['id'] }}" x-cloak
                    @if($ongoingPanel['tabs']) role="tabpanel" aria-labelledby="ongoing-tab-{{ $group['id'] }}" @endif>
                    <p class="px-5 pt-4 text-sm font-medium text-gray-600">Sede: {{ $group['name'] }}</p>
                    <div class="overflow-x-auto p-5">
                        <table id="ongoing-table-{{ $group['id'] }}" class="ongoing-encounters-table min-w-full display nowrap rounded-xl overflow-hidden text-left text-sm" style="width: 100%">
                            <thead class="border-b text-xs uppercase text-gray-500" style="background-color: {{ themeColor(100) }}">
                                <tr><th scope="col" class="px-3 py-3">#</th><th class="px-3 py-3">Atención</th><th class="px-3 py-3">Paciente</th><th class="px-3 py-3">Profesional</th><th class="px-3 py-3">Especialidad</th><th class="px-3 py-3">Modalidad</th><th class="px-3 py-3">Inicio</th><th class="px-3 py-3">Acciones</th></tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($group['encounters'] as $encounter)
                                    <tr>
                                        <td class="px-3 py-3"></td>
                                        <td data-order="{{ $encounter->id }}" class="px-3 py-3 font-semibold">#{{ $encounter->id }} <span class="ml-1 rounded-full bg-green-100 px-2 py-1 text-xs text-green-800">En curso</span></td>
                                        <td class="px-3 py-3">{{ $encounter->patient?->name ?? 'Paciente no disponible' }}<span class="block text-xs text-gray-500">{{ $encounter->patient?->rut }}</span></td>
                                        <td class="px-3 py-3">{{ $encounter->professional?->name ?? $encounter->appointment?->professional?->name ?? 'Sin profesional registrado' }}</td>
                                        <td class="px-3 py-3">{{ $encounter->specialty?->name ?? $encounter->appointment?->specialty?->name ?? 'Sin especialidad registrada' }}</td>
                                        <td class="px-3 py-3">{{ $encounter->modality === 'telemedicine' ? 'Telemedicina' : 'Presencial' }}</td>
                                        <td data-order="{{ $encounter->started_at?->timestamp ?? 0 }}" class="px-3 py-3">{{ $encounter->started_at?->timezone('America/Santiago')->format('d/m/Y H:i') ?? 'Sin fecha registrada' }}</td>
                                        <td class="px-3 py-3">
                                            @php
                                                $canContinue = (int) ($encounter->professional_id ?? $encounter->appointment?->professional_id) === (int) auth()->id();
                                            @endphp
                                            <a href="{{ route('patients.medical-record.show', ['customer' => request()->route('customer'), 'id' => $encounter->patient_id, 'encounter_id' => $encounter->id]) }}#current-encounter-section"
                                                class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-semibold text-white transition hover:brightness-90"
                                                style="background-color: {{ themeColor(600) }}">
                                                <i class="fa-solid {{ $canContinue ? 'fa-play' : 'fa-eye' }}" aria-hidden="true"></i>
                                                {{ $canContinue ? 'Continuar atención' : 'Ver atención' }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @empty
                <p class="p-5 text-sm text-gray-500">No hay una sede activa disponible.</p>
            @endforelse
        </div>
    </section>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const tables = jQuery('.ongoing-encounters-table').DataTable({
        pageLength: 10,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
        pagingType: 'full_numbers',
        order: [[6, 'asc']],
        columnDefs: [{ targets: [0, 7], orderable: false, searchable: false }],
        drawCallback: function () {
            const api = this.api();
            const start = api.page.info().start;
            api.column(0, { page: 'current' }).nodes().each((cell, index) => {
                cell.textContent = start + index + 1;
            });
        },
        autoWidth: false,
        responsive: false,
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ atenciones',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ atenciones',
            infoEmpty: 'No hay atenciones para mostrar',
            infoFiltered: '(filtrado de _MAX_ atenciones)',
            zeroRecords: 'No se encontraron atenciones',
            emptyTable: 'No hay atenciones en curso en esta sede.',
            paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' },
        },
    });
    window.addEventListener('ongoing-tab-changed', () => tables.columns.adjust());
});
</script>
@endpush
