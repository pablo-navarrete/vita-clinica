@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Citas médicas de {{ $patient->name }}
    </h2>
@endsection

@section('content')
    @php
        $appointmentsDataUrl = route('customers.patients.appointments.data', [
            'customer' => request()->route('customer'),
            'id' => $patient->id,
            'scope' => $scope,
        ]);
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg bg-white shadow-sm">
                <div class="flex flex-col gap-3 border-b p-6 sm:flex-row sm:items-center sm:justify-between"
                    style="background-color: {{ themeColor(100) }}">
                    <div>
                        <p class="font-semibold text-gray-900">{{ $patient->name }} · RUT: {{ $patient->rut }}</p>
                        <p class="mt-1 text-sm text-gray-600">
                            {{ $scope === 'future' ? 'Citas futuras agendadas' : 'Historial completo de citas' }}
                        </p>
                    </div>
                    <a href="{{ route('customers.patient.index', ['customer' => request()->route('customer')]) }}"
                        class="inline-flex items-center justify-center rounded px-4 py-2 text-white transition hover:brightness-90"
                        style="background-color: {{ themeColor(600) }}">
                        Volver a pacientes
                    </a>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table id="patient-appointments-table"
                            class="min-w-full display responsive nowrap rounded-xl overflow-hidden">
                            <thead style="background-color: {{ themeColor(100) }}">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha y hora</th>
                                    <th>Profesional</th>
                                    <th>Especialidad</th>
                                    <th>Estado</th>
                                    <th>Detalle</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="appointment-detail-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center overflow-y-auto bg-black/50 p-4">
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col rounded-lg bg-white shadow-lg">
            <div class="flex items-start justify-between gap-4 rounded-t-lg border-b px-6 py-4"
                style="background-color: {{ themeColor(100) }}">
                <div>
                    <h2 class="text-lg font-bold">Detalle de cita</h2>
                    <p class="mt-1 text-sm text-gray-700">{{ $patient->name }} · RUT: {{ $patient->rut }}</p>
                </div>
                <span id="appointment-detail-specialty"
                    class="shrink-0 rounded-full bg-white px-3 py-1 text-sm font-semibold"
                    style="color: {{ themeColor(700) }}"></span>
            </div>
            <div class="space-y-3 overflow-y-auto p-6">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500">Profesional</label>
                    <span id="appointment-detail-professional"></span>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-500">Fecha</label>
                        <span id="appointment-detail-date"></span>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-500">Hora</label>
                        <span id="appointment-detail-time"></span>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500">Estado</label>
                    <span id="appointment-detail-status" class="rounded-full px-3 py-1 text-sm font-semibold"></span>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500">Asunto / Motivo</label>
                    <p id="appointment-detail-reason" class="whitespace-pre-wrap break-words"></p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-500">Observaciones / Notas</label>
                    <p id="appointment-detail-notes" class="whitespace-pre-wrap break-words"></p>
                </div>
                <div id="appointment-detail-cancellation-section" class="hidden rounded-lg border border-red-200 bg-red-50 p-4">
                    <label class="mb-1 block text-sm font-semibold text-red-800">Motivo de cancelación</label>
                    <p id="appointment-detail-cancellation" class="whitespace-pre-wrap break-words text-red-900"></p>
                </div>
                <div id="appointment-detail-encounter-section" class="hidden space-y-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                    <h3 class="font-semibold text-indigo-900">Detalle de la atención</h3>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-indigo-700">Motivo de consulta</label>
                        <p id="appointment-detail-chief-complaint" class="whitespace-pre-wrap break-words text-gray-900"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-indigo-700">Diagnóstico / Evaluación clínica</label>
                        <p id="appointment-detail-assessment" class="whitespace-pre-wrap break-words text-gray-900"></p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-indigo-700">Tratamiento e indicaciones</label>
                        <p id="appointment-detail-treatment" class="whitespace-pre-wrap break-words text-gray-900"></p>
                    </div>
                    <p id="appointment-detail-encounter-dates" class="text-xs text-gray-600"></p>
                </div>
            </div>
            <div class="flex justify-end gap-2 border-t px-6 py-4">
                <button type="button" id="appointment-detail-close-footer"
                    class="rounded bg-gray-600 px-4 py-2 text-white hover:brightness-90">Cerrar</button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(function () {
            const appointmentsTable = $('#patient-appointments-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: @json($appointmentsDataUrl),
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                pagingType: 'full_numbers',
                order: [[1, 'desc']],
                language: {
                    processing: 'Cargando citas...',
                    search: 'Buscar:',
                    lengthMenu: 'Mostrar _MENU_ citas',
                    info: 'Mostrando _START_ a _END_ de _TOTAL_ citas',
                    infoEmpty: 'No hay citas para mostrar',
                    infoFiltered: '(filtrado de _MAX_ citas)',
                    zeroRecords: 'No se encontraron citas',
                    emptyTable: 'Este paciente todavía no tiene citas médicas',
                    paginate: { first: 'Primera', last: 'Última', next: 'Siguiente', previous: 'Anterior' }
                },
                responsive: false,
                autoWidth: false,
                columns: [
                    { data: 'DT_RowIndex', name: 'id', orderable: false, searchable: false },
                    {
                        data: 'start_at',
                        render: function (value, type, row) {
                            if (!value) return '';
                            const start = new Date(String(value).replace(' ', 'T'));
                            const date = Number.isNaN(start.getTime()) ? value : start.toLocaleDateString('es-CL');
                            const time = Number.isNaN(start.getTime()) ? '' : start.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' });
                            const end = row.end_at ? String(row.end_at).split(/[ T]/)[1]?.slice(0, 5) : '';
                            return `${date} ${time}${end ? ` - ${end}` : ''}`;
                        }
                    },
                    { data: 'professional_name', orderable: false },
                    { data: 'specialty_name', orderable: false },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false,
                        render: function (value) {
                            const labels = { 0: 'Agendada', 1: 'Confirmada', 2: 'En atención', 3: 'Atendida', 4: 'No asistió', 5: 'Cancelada' };
                            const colors = {
                                0: 'bg-blue-100 text-blue-700', 1: 'bg-green-100 text-green-700',
                                2: 'bg-yellow-100 text-yellow-800', 3: 'bg-indigo-100 text-indigo-700',
                                4: 'bg-red-100 text-red-700', 5: 'bg-gray-100 text-gray-700'
                            };
                            const status = Number(value);
                            return `<span class="inline-flex rounded-full px-2 py-1 text-xs font-medium ${colors[status] || 'bg-gray-100 text-gray-700'}">${labels[status] || 'Sin estado'}</span>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function () {
                            return '<button type="button" class="view-appointment-detail text-yellow-500 hover:text-yellow-600" title="Ver detalle de la cita" aria-label="Ver detalle de la cita"><i class="fa-regular fa-eye"></i></button>';
                        }
                    }
                ]
            });

            const detailModal = $('#appointment-detail-modal');
            const closeDetailModal = function () {
                detailModal.removeClass('flex').addClass('hidden');
                $('body').removeClass('overflow-hidden');
            };

            $('#patient-appointments-table').on('click', '.view-appointment-detail', function () {
                const appointment = appointmentsTable.row($(this).closest('tr')).data();
                if (!appointment) return;

                const start = new Date(String(appointment.start_at || '').replace(' ', 'T'));
                const end = appointment.end_at ? new Date(String(appointment.end_at).replace(' ', 'T')) : null;
                $('#appointment-detail-date').text(Number.isNaN(start.getTime()) ? '' : start.toLocaleDateString('es'));
                $('#appointment-detail-time').text(Number.isNaN(start.getTime()) ? '' :
                    `${start.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })}${end && !Number.isNaN(end.getTime()) ? ` - ${end.toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })}` : ''}`);
                $('#appointment-detail-professional').text(appointment.professional_name || 'Sin profesional');
                $('#appointment-detail-specialty').text(appointment.specialty_name || 'Sin especialidad');

                const statusLabels = {
                    0: 'Agendada', 1: 'Confirmada', 2: 'En atención',
                    3: 'Atendida', 4: 'No asistió', 5: 'Cancelada'
                };
                const statusColors = {
                    0: 'bg-blue-100 text-blue-700', 1: 'bg-green-100 text-green-700',
                    2: 'bg-yellow-100 text-yellow-800', 3: 'bg-indigo-100 text-indigo-700',
                    4: 'bg-red-100 text-red-700', 5: 'bg-gray-100 text-gray-700'
                };
                const status = Number(appointment.status);
                $('#appointment-detail-status')
                    .attr('class', `rounded-full px-3 py-1 text-sm font-semibold ${statusColors[status] || 'bg-gray-100 text-gray-700'}`)
                    .text(statusLabels[status] || 'Sin estado');
                $('#appointment-detail-reason').text(appointment.reason || 'Sin asunto registrado.');
                $('#appointment-detail-notes').text(appointment.notes || 'Sin observaciones registradas.');

                const encounter = appointment.encounter_details || {};
                const cancellationReason = encounter.cancellation_reason;
                const cancellationSection = $('#appointment-detail-cancellation-section');
                const encounterSection = $('#appointment-detail-encounter-section');
                cancellationSection.addClass('hidden');
                encounterSection.addClass('hidden');

                if (status === 5) {
                    $('#appointment-detail-cancellation').text(cancellationReason || 'No se registró un motivo de cancelación.');
                    cancellationSection.removeClass('hidden');
                }

                if (status === 3 || encounter.chief_complaint || encounter.clinical_assessment || encounter.treatment_plan) {
                    $('#appointment-detail-chief-complaint').text(encounter.chief_complaint || 'Sin motivo de consulta registrado.');
                    $('#appointment-detail-assessment').text(encounter.clinical_assessment || 'Sin diagnóstico o evaluación clínica registrada.');
                    $('#appointment-detail-treatment').text(encounter.treatment_plan || 'Sin tratamiento o indicaciones registradas.');
                    const startedAt = encounter.started_at ? new Date(String(encounter.started_at).replace(' ', 'T')) : null;
                    const endedAt = encounter.ended_at ? new Date(String(encounter.ended_at).replace(' ', 'T')) : null;
                    const formatDateTime = date => date && !Number.isNaN(date.getTime())
                        ? `${date.toLocaleDateString('es-CL')} ${date.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })}`
                        : '';
                    const dates = [
                        startedAt ? `Iniciada: ${formatDateTime(startedAt)}` : '',
                        endedAt ? `Finalizada: ${formatDateTime(endedAt)}` : ''
                    ].filter(Boolean).join(' · ');
                    $('#appointment-detail-encounter-dates').text(dates);
                    encounterSection.removeClass('hidden');
                }

                detailModal.removeClass('hidden').addClass('flex');
                $('body').addClass('overflow-hidden');
            });

            $('#appointment-detail-close-footer').on('click', closeDetailModal);
            detailModal.on('click', function (event) {
                if (event.target === this) closeDetailModal();
            });
            $(document).on('keydown', function (event) {
                if (event.key === 'Escape' && !detailModal.hasClass('hidden')) closeDetailModal();
            });
        });
    </script>
@endpush
