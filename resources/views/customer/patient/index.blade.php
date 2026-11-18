@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Pacientes') }}
    </h2>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #patient-appointment-modal .select2-container {
            width: 100% !important;
        }
        #patient-appointment-modal .select2-container .select2-selection--single {
            min-height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }
        #patient-appointment-modal .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }
        #patient-appointment-modal .select2-container .select2-selection__arrow {
            height: 40px;
        }
        #patient-appointment-modal .select2-dropdown {
            z-index: 70;
        }
        #patient-table_wrapper .dataTables_length,
        #patient-table_wrapper .dataTables_filter { margin-bottom: 1rem; }
        #patient-table_wrapper .dataTables_length label,
        #patient-table_wrapper .dataTables_filter label { color: #374151; font-weight: 500; }
        #patient-table_wrapper .dataTables_length select,
        #patient-table_wrapper .dataTables_filter input {
            min-height: 2.5rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            background-color: #fff;
        }
        #patient-table_wrapper .dataTables_filter input { margin-left: 0.5rem; padding: 0.4rem 0.75rem; }
        #patient-table_wrapper .dataTables_length select { margin: 0 0.4rem; padding: 0.35rem 1.75rem 0.35rem 0.65rem; }
        #patient-table_wrapper .dataTables_info { padding-top: 0.75rem; color: #6b7280; }
        #patient-table_wrapper .dataTables_paginate { padding-top: 0.5rem; }
        #patient-table_wrapper .dataTables_paginate .paginate_button {
            min-width: 2.25rem;
            margin: 0 0.15rem;
            padding: 0.4rem 0.65rem;
            border: 1px solid transparent !important;
            border-radius: 0.5rem;
            color: #374151 !important;
        }
        #patient-table_wrapper .dataTables_paginate .paginate_button:hover {
            border-color: #d1d5db !important;
            background: #f3f4f6 !important;
            color: #111827 !important;
        }
        #patient-table_wrapper .dataTables_paginate .paginate_button.current {
            border-color: {{ themeColor(600) }} !important;
            background: {{ themeColor(600) }} !important;
            color: #fff !important;
        }
        #patient-table_wrapper .dataTables_paginate .paginate_button.disabled,
        #patient-table_wrapper .dataTables_paginate .paginate_button.disabled:hover {
            border-color: transparent !important;
            background: transparent !important;
            color: #9ca3af !important;
            cursor: not-allowed;
        }
        @media (max-width: 640px) {
            #patient-table_wrapper .dataTables_length,
            #patient-table_wrapper .dataTables_filter { float: none; text-align: left; }
            #patient-table_wrapper .dataTables_filter input { width: min(14rem, 60vw); }
            #patient-table_wrapper .dataTables_info,
            #patient-table_wrapper .dataTables_paginate { float: none; text-align: center; }
        }
    </style>
@endpush

@section('content')

<div class="pb-12">

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

        <div class="bg-white shadow-sm sm:rounded-lg">

            <div class="p-6 text-gray-900">

                <!-- ========================================================= -->
                <!-- BOTÓN NUEVO -->
                <!-- ========================================================= -->

                <div class="flex justify-end mb-4">

                    <button
                        type="button"
                        id="new-patient-btn"
                        style="background-color: {{ themeColor(600) }}"
                        class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">

                        <i class="fa-solid fa-circle-plus mr-1"></i>

                        Nuevo

                    </button>

                </div>


                <!-- ========================================================= -->
                <!-- TABLA -->
                <!-- ========================================================= -->

                <div class="overflow-x-auto">

<table
                        id="patient-table"
                        class="min-w-full display responsive nowrap rounded-xl overflow-hidden">

                        <thead style="background-color: {{ themeColor(100) }}">

                            <tr>

                                <th>#</th>

                                <th>Nombre Completo</th>

                                <th>Sede de ingreso</th>

                                <th>Rut</th>

                                <th>Teléfono</th>


                                <th>Previsión</th>

                                <th>Citas médicas</th>

                                <th>Acciones</th>

                            </tr>

                        </thead>

                    </table>

                </div>


                <!-- ========================================================= -->
                <!-- MODAL CREAR / EDITAR PACIENTE -->
                <!-- ========================================================= -->

                @include('customer.patient.partials.form-modal')

                <!-- ========================================================= -->
                <!-- MENÚ DE ACCIONES GLOBAL -->
                <!-- ========================================================= -->

                <div id="patient-appointments-detail-modal"
                    class="hidden fixed inset-0 items-center justify-center bg-black/50 z-[70] overflow-y-auto p-4">
                    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">
                        <div class="px-6 py-4 border-b rounded-t-lg flex items-start justify-between gap-4"
                            style="background-color: {{ themeColor(100) }}">
                            <div>
                                <h2 class="text-lg font-bold">Citas médicas del paciente</h2>
                                <p id="patient-appointments-detail-name" class="mt-1 text-sm text-gray-600"></p>
                            </div>
                            <button type="button" id="patient-appointments-detail-close"
                                class="text-gray-600 hover:text-gray-900" aria-label="Cerrar">&times;</button>
                        </div>
                        <div class="p-6 overflow-y-auto flex-1">
                            <ul id="patient-appointments-detail-list" class="space-y-3"></ul>
                            <a id="patient-appointments-detail-view-all" href="#"
                                class="hidden mt-4 inline-flex rounded px-4 py-2 text-sm font-semibold text-white hover:brightness-90"
                                style="background-color: {{ themeColor(600) }}">Ver todas las citas</a>
                        </div>
                    </div>
                </div>

                <div id="patient-appointment-modal"
                    class="hidden fixed inset-0 items-center justify-center bg-black/50 z-[60] overflow-y-auto p-4">
                    <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl max-h-[92vh] flex flex-col">
                        <div class="px-6 py-4 border-b rounded-t-lg" style="background-color: {{ themeColor(100) }}">
                            <h2 class="text-lg font-bold">Nueva cita médica</h2>
                            <p id="patient-appointment-name" class="mt-1 text-sm text-gray-600"></p>
                            <p id="patient-future-appointments-status" role="status"
                                class="hidden mt-3 rounded-lg border px-3 py-2 text-sm"></p>
                        </div>

                        <div class="grid grid-cols-1 gap-4 overflow-y-auto p-6 flex-1 md:grid-cols-2">
                            @if ($canChooseAppointmentBranch)
                                <div class="md:col-span-2">
                                    <label for="patient-appointment-branch" class="block text-sm font-medium mb-1">Sede de atención</label>
                                    <select id="patient-appointment-branch" class="w-full rounded-lg border-gray-300">
                                        @foreach ($appointmentBranches as $branch)
                                            <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $activeBranchId)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500">La cita y su disponibilidad quedarán asociadas a la sede seleccionada.</p>
                                </div>
                            @endif
                            <div>
                                <label for="patient-appointment-specialty" class="block text-sm font-medium mb-1">Especialidad</label>
                                <select id="patient-appointment-specialty" class="w-full rounded-lg border-gray-300">
                                    <option value="">Seleccione especialidad</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            @if ($isDoctor)
                                <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <span class="block text-xs font-medium uppercase tracking-wide text-gray-500">Profesional</span>
                                    <span class="mt-1 block font-semibold text-gray-800">{{ $currentProfessionalName ?? 'Tu cuenta no tiene un profesional habilitado' }}</span>
                                </div>
                            @endif

                            <div id="patient-appointment-professional-container" class="hidden">
                                <label for="patient-appointment-professional" class="block text-sm font-medium mb-1">Profesional</label>
                                <select id="patient-appointment-professional" class="w-full rounded-lg border-gray-300">
                                    <option value="">Seleccione profesional</option>
                                </select>
                            </div>

                            <div>
                                <label for="patient-appointment-date" class="block text-sm font-medium mb-1">Fecha</label>
                                <input type="date" id="patient-appointment-date" min="{{ now()->format('Y-m-d') }}"
                                    class="w-full rounded-lg border-gray-300">
                            </div>

                            <div id="patient-appointment-times-container" class="hidden">
                                <label for="patient-appointment-time" class="block text-sm font-medium mb-1">Horario disponible</label>
                                <select id="patient-appointment-time" class="w-full rounded-lg border-gray-300">
                                    <option value="">Seleccione horario</option>
                                </select>
                            </div>

                            <section id="patient-appointment-weekly-panel" data-weekly-url="{{ route('appointments.availability.week', ['customer' => request()->route('customer')]) }}"
                                data-accent="{{ themeColor(600) }}" data-accent-soft="{{ themeColor(50) }}"
                                class="rounded-xl border border-gray-200 bg-gray-50 p-4 md:col-span-2">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-800">Disponibilidad semanal</h3>
                                        <p id="patient-appointment-weekly-status" class="mt-1 text-xs text-gray-500">{{ $isDoctor ? 'Selecciona una especialidad para consultar tus horarios.' : 'Selecciona un profesional para consultar sus horarios.' }}</p>
                                        <p id="patient-appointment-weekly-selected" class="hidden mt-1 text-sm font-semibold text-gray-800"></p>
                                    </div>
                                    <div class="flex shrink-0 gap-2">
                                        <button type="button" id="patient-appointment-week-prev" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100" aria-label="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>
                                        <button type="button" id="patient-appointment-week-next" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100" aria-label="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div id="patient-appointment-weekly-days" class="flex gap-2 overflow-x-auto pb-2" aria-live="polite"></div>
                            </section>

                            <div>
                                <label for="patient-appointment-modality" class="block text-sm font-medium mb-1">Modalidad de atención</label>
                                <select id="patient-appointment-modality" class="w-full rounded-lg border-gray-300">
                                    <option value="in_person">Presencial</option>
                                    <option value="telemedicine">Remota (telemedicina)</option>
                                </select>
                            </div>

                            <div>
                                <label for="patient-appointment-reason" class="block text-sm font-medium mb-1">Motivo</label>
                                <input type="text" id="patient-appointment-reason" class="w-full rounded-lg border-gray-300">
                            </div>

                            <div>
                                <label for="patient-appointment-notes" class="block text-sm font-medium mb-1">Notas</label>
                                <textarea id="patient-appointment-notes" class="w-full rounded-lg border-gray-300"></textarea>
<label class="mt-3 flex items-start gap-2 text-sm text-gray-600"><input id="patient-appointment-whatsapp-consent" type="checkbox" value="1" class="mt-1 rounded border-gray-300"><span>El paciente autorizo recibir avisos de esta cita por WhatsApp.</span></label>                            </div>
                        </div>

                        <div class="border-t px-6 py-4 flex justify-end gap-2">
                            <button type="button" id="patient-appointment-cancel" class="bg-gray-600 text-white px-4 py-2 rounded">Cancelar</button>
                            <button type="button" id="patient-appointment-save" style="background-color: {{ themeColor(600) }}"
                                class="text-white px-4 py-2 rounded">Guardar</button>
                        </div>
                    </div>
                </div>

                <div
                    id="patient-actions-menu"
                    class="hidden fixed w-52 bg-white border border-gray-200 rounded-lg shadow-lg z-[9999]">

                    <!-- VER FICHA -->

                    <button
                        type="button"
                        id="action-view"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-regular fa-id-badge mr-2"></i>

                        Ver ficha

                    </button>


                    <!-- EDITAR -->

                    <button
                        type="button"
                        id="action-edit"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-solid fa-pen-to-square mr-2"></i>

                        Editar

                    </button>


                    <!-- AGENDAR -->

                    <button
                        type="button"
                        id="action-appointment"
                        class="w-full text-left px-4 py-2 hover:bg-gray-100">

                        <i class="fa-solid fa-calendar-days mr-2"></i>

                        Agendar cita

                    </button>


                    <hr>


                    <!-- ELIMINAR -->

                    <button
                        type="button"
                        id="action-delete"
                        class="w-full text-left px-4 py-2 text-red-600 hover:bg-red-50">

                        <i class="fa-solid fa-trash mr-2"></i>

                        Eliminar

                    </button>

                </div>

            </div>

        </div>

    </div>

</div>

@endsection


@push('scripts')

@php
    $patientAppointmentsUrlTemplate = route('customers.patients.appointments.index', [
        'customer' => request()->route('customer'),
        'id' => '__PATIENT_ID__',
    ]);
@endphp

<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>

<script src="{{ asset('js/patient-form.js').'?v='.filemtime(public_path('js/patient-form.js')) }}"></script>
<script>

$(function () {

    /*
    |--------------------------------------------------------------------------
    | VARIABLES
    |--------------------------------------------------------------------------
    */

    let tableActive;

    let editingPatientId = null;

    let selectedPatient = null;

    let isEdit = false;

    const modal = $('#patient-modal');

    const actionsMenu = $('#patient-actions-menu');

    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const appointmentModal = $('#patient-appointment-modal');
    const appointmentSpecialty = $('#patient-appointment-specialty');
    const appointmentProfessional = $('#patient-appointment-professional');
    const appointmentDate = $('#patient-appointment-date');
    const isLoggedInDoctor = @json($isDoctor);
    const currentProfessionalId = @json($currentProfessionalId);
    const currentProfessionalName = @json($currentProfessionalName);
    let patientAppointmentWeekAnchor = '';
    let patientAppointmentWeekRequestId = 0;
    let patientAppointmentSpecialtiesRequestId = 0;
    const patientAppointmentsDetailModal = $('#patient-appointments-detail-modal');
    const patientAppointmentsUrlTemplate = @json($patientAppointmentsUrlTemplate);

    const appointmentSelectLanguage = {
        noResults: () => 'No se encontraron resultados',
        searching: () => 'Buscando…',
        inputTooShort: () => 'Escribe para buscar',
    };
    $('#patient-appointment-specialty, #patient-appointment-professional, #patient-appointment-time, #patient-appointment-modality, #patient-appointment-branch').select2({
        width: '100%',
        dropdownParent: appointmentModal,
        language: appointmentSelectLanguage,
        minimumResultsForSearch: 0,
    });

    function closePatientAppointmentsDetailModal() {
        patientAppointmentsDetailModal.removeClass('flex').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    }

    function openPatientAppointmentsDetailModal(patient) {
        let appointments = patient.patient_appointments ?? [];
        if (typeof appointments === 'string') {
            try { appointments = JSON.parse(appointments); } catch (_) { appointments = []; }
        }
        if (!Array.isArray(appointments) && appointments && typeof appointments === 'object') {
            appointments = Object.values(appointments);
        }

        const list = $('#patient-appointments-detail-list').empty();
        $('#patient-appointments-detail-name').text(`${patient.name} · Identificación: ${patient.identification_label}`);
        const statusLabels = {
            0: 'Agendada', 1: 'Confirmada', 2: 'En atención',
            3: 'Atendida', 4: 'No asistió', 5: 'Cancelada'
        };

    function patientAppointmentBranchId() {
        const branchSelect = document.getElementById('patient-appointment-branch');
        return branchSelect ? branchSelect.value : String(@json($activeBranchId ?? ''));
    }

    async function loadPatientAppointmentSpecialties() {
        const branchSelect = document.getElementById('patient-appointment-branch');
        if (!branchSelect) return;
        const requestId = ++patientAppointmentSpecialtiesRequestId;
        appointmentSpecialty.html('<option value="">Cargando especialidades…</option>').val('').trigger('change.select2');
        appointmentProfessional.html('<option value="">Seleccione especialidad</option>').val('').trigger('change.select2');
        $('#patient-appointment-professional-container, #patient-appointment-times-container').addClass('hidden');

        try {
            const params = new URLSearchParams({ clinic_branch_id: branchSelect.value });
            const response = await fetch(`/appointments/specialties?${params}`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('No se pudieron consultar las especialidades de la sede.');
            const specialties = await response.json();
            if (requestId !== patientAppointmentSpecialtiesRequestId) return;
            appointmentSpecialty.html('<option value="">Seleccione especialidad</option>');
            specialties.forEach(specialty => appointmentSpecialty.append(new Option(specialty.name, specialty.id)));
            appointmentSpecialty.val('').trigger('change.select2');
        } catch (error) {
            if (requestId !== patientAppointmentSpecialtiesRequestId) return;
            appointmentSpecialty.html('<option value="">No se pudieron cargar especialidades</option>').val('').trigger('change.select2');
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        }
    }

    $('#patient-appointment-branch').on('change', loadPatientAppointmentSpecialties);
        const statusColors = {
            0: 'bg-blue-100 text-blue-700',
            1: 'bg-green-100 text-green-700',
            2: 'bg-yellow-100 text-yellow-800',
            3: 'bg-indigo-100 text-indigo-700',
            4: 'bg-red-100 text-red-700',
            5: 'bg-gray-100 text-gray-700'
        };

        if (!appointments.length) {
            list.append($('<li>').addClass('rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600')
                .text('Este paciente todavía no tiene citas médicas.'));
        } else {
            appointments.forEach(appointment => {
                const start = String(appointment.start_at || '');
                const end = String(appointment.end_at || '');
                const [datePart = '', startTimePart = ''] = start.split(/[ T]/);
                const [, endTimePart = ''] = end.split(/[ T]/);
                const [year, month, day] = datePart.split('-');
                const date = year && month && day ? `${day}/${month}/${year}` : datePart;
                const time = `${startTimePart.slice(0, 5)}${endTimePart ? ` - ${endTimePart.slice(0, 5)}` : ''}`;
                const item = $('<li>').addClass('rounded-lg border border-gray-200 bg-gray-50 px-4 py-3');
                const header = $('<div>').addClass('flex items-start justify-between gap-3');
                header.append($('<p>').addClass('font-medium text-gray-800').text(`${date} · ${time}`));
                const status = Number(appointment.status);
                header.append($('<span>').addClass(`shrink-0 rounded-full px-2 py-1 text-xs ${statusColors[status] || 'bg-gray-100 text-gray-700'}`)
                    .text(statusLabels[appointment.status] || 'Sin estado'));
                item.append(header);
                item.append($('<p>').addClass('mt-1 text-sm text-gray-600')
                    .text(`${appointment.professional || 'Sin profesional'} · ${appointment.specialty || 'Sin especialidad'}`));
                list.append(item);
            });
        }

        const totalAppointments = Number(patient.total_appointments_count || 0);
        $('#patient-appointments-detail-view-all')
            .attr('href', patientAppointmentsUrlTemplate.replace('__PATIENT_ID__', encodeURIComponent(patient.id)))
            .toggleClass('hidden', totalAppointments <= 5);

        patientAppointmentsDetailModal.removeClass('hidden').addClass('flex');
        $('body').addClass('overflow-hidden');
    }

    function closePatientAppointmentModal() {
        appointmentModal.removeClass('flex').addClass('hidden');
        $('body').removeClass('overflow-hidden');
    }

    function openPatientAppointmentModal(patient) {
        const today = new Date();
        const localToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        const futureAppointmentsStatus = $('#patient-future-appointments-status');
        const futureAppointmentsCount = Number(patient.future_appointments_count || 0);

        $('#patient-appointment-name').text(`${patient.name} · Identificación: ${patient.identification_label}`);
        if (futureAppointmentsCount > 0) {
            futureAppointmentsStatus
                .attr('class', 'mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800')
                .text(`Tiene ${futureAppointmentsCount} cita(s) futura(s).`);
        } else {
            futureAppointmentsStatus
                .attr('class', 'mt-3 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800')
                .text('No tiene citas futuras.');
        }
        appointmentSpecialty.val('');
        if (document.getElementById('patient-appointment-branch')) {
            $('#patient-appointment-branch').val(String(@json($activeBranchId))).trigger('change.select2');
            loadPatientAppointmentSpecialties();
        }
        appointmentProfessional.html('<option value="">Seleccione profesional</option>');
        if (isLoggedInDoctor && currentProfessionalId) {
            appointmentProfessional.append(new Option(currentProfessionalName, currentProfessionalId, true, true));
        }
        $('#patient-appointment-professional-container').addClass('hidden');
        if (appointmentDate[0]?._flatpickr) appointmentDate[0]._flatpickr.setDate(localToday, false);
        else appointmentDate.val(localToday);
        patientAppointmentWeekAnchor = localToday;
        $('#patient-appointment-time').html('<option value="">Seleccione horario</option>');
        updatePatientSelectedAppointmentSummary();
        $('#patient-appointment-times-container').addClass('hidden');
        $('#patient-appointment-reason, #patient-appointment-notes').val('');
        $('#patient-appointment-whatsapp-consent').prop('checked', false);
        $('#patient-appointment-modality').val('in_person');
        appointmentModal.removeClass('hidden').addClass('flex');
        $('body').addClass('overflow-hidden');
        loadPatientWeeklyAvailability();
    }

    function patientDateValue(date) {
        return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
    }

    function parsePatientDate(value) {
        const [year, month, day] = value.split('-').map(Number);
        return new Date(year, month - 1, day, 12);
    }

    function updatePatientSelectedAppointmentSummary() {
        const summary = document.getElementById('patient-appointment-weekly-selected');
        const date = appointmentDate.val();
        const time = document.getElementById('patient-appointment-time').value;
        if (!date || !time) {
            summary.textContent = '';
            summary.classList.add('hidden');
            return;
        }

        const formattedDate = parsePatientDate(date)
            .toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });
        summary.textContent = `${formattedDate.charAt(0).toUpperCase()}${formattedDate.slice(1)} a las ${time}.`;
        summary.classList.remove('hidden');
    }

    function patientWeekDates(anchor) {
        const monday = parsePatientDate(anchor);
        monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
        return Array.from({ length: 7 }, (_, index) => {
            const day = new Date(monday);
            day.setDate(monday.getDate() + index);
            return patientDateValue(day);
        });
    }

    async function loadPatientWeeklyAvailability() {
        const panel = document.getElementById('patient-appointment-weekly-panel');
        const daysContainer = document.getElementById('patient-appointment-weekly-days');
        const status = document.getElementById('patient-appointment-weekly-status');
        const previousButton = document.getElementById('patient-appointment-week-prev');
        const nextButton = document.getElementById('patient-appointment-week-next');
        const dateInput = appointmentDate[0];
        const timeSelect = document.getElementById('patient-appointment-time');
        const professionalId = appointmentProfessional.val();
        const anchor = patientAppointmentWeekAnchor || appointmentDate.val() || dateInput.min;
        const dates = patientWeekDates(anchor);
        const requestId = ++patientAppointmentWeekRequestId;
        const today = dateInput.min;

        daysContainer.innerHTML = '';
        if (!professionalId) {
            status.textContent = 'Selecciona un profesional para consultar sus horarios.';
            previousButton.disabled = true;
            nextButton.disabled = true;
            return;
        }

        status.textContent = 'Cargando horarios disponibles…';
        const params = new URLSearchParams({ professional_id: professionalId, date: anchor });
        if (patientAppointmentBranchId()) params.set('clinic_branch_id', patientAppointmentBranchId());
        try {
            const response = await fetch(`${panel.dataset.weeklyUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
            if (!response.ok) throw new Error('No se pudieron consultar los horarios.');
            const result = await response.json();
            if (requestId !== patientAppointmentWeekRequestId) return;
            const availability = result.availability || {};
            const selectedDate = appointmentDate.val();
            const selectedTime = timeSelect.value;
            updatePatientSelectedAppointmentSummary();

            dates.forEach(value => {
                const dayDate = parsePatientDate(value);
                const card = document.createElement('div');
                card.dataset.date = value;
                card.className = `min-w-[110px] max-w-[130px] max-h-44 shrink-0 overflow-y-auto rounded-lg border p-2 ${value === selectedDate ? '' : 'border-gray-200 bg-white'}`;
                if (value === selectedDate) {
                    card.style.borderColor = panel.dataset.accent;
                    card.style.backgroundColor = panel.dataset.accentSoft;
                }
                const heading = document.createElement('p');
                heading.className = 'mb-2 text-center text-xs font-semibold capitalize text-gray-700';
                heading.textContent = dayDate.toLocaleDateString('es-CL', { weekday: 'short', day: '2-digit', month: 'short' }).replace('.', '');
                card.appendChild(heading);
                const slots = availability[value] || [];
                if (value < today || !slots.length) {
                    const empty = document.createElement('span');
                    empty.className = 'block py-1 text-center text-xs text-gray-400';
                    empty.textContent = value < today ? '—' : 'Sin horas';
                    card.appendChild(empty);
                } else {
                    slots.forEach(slot => {
                        const button = document.createElement('button');
                        button.type = 'button';
                        button.dataset.time = slot.value;
                        const selected = value === selectedDate && slot.value === selectedTime;
                        button.className = `mb-1 w-full rounded border px-1 py-1 text-xs transition ${selected ? 'text-white' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'}`;
                        if (selected) {
                            button.style.borderColor = panel.dataset.accent;
                            button.style.backgroundColor = panel.dataset.accent;
                        }
                        button.textContent = slot.value;
                        button.title = slot.label;
                        button.addEventListener('click', () => {
                            if (dateInput._flatpickr) dateInput._flatpickr.setDate(value, false);
                            else dateInput.value = value;
                            timeSelect.innerHTML = '<option value="">Seleccione horario</option>';
                            slots.forEach(item => timeSelect.add(new Option(item.label, item.value)));
                            timeSelect.value = slot.value;
                            patientAppointmentWeekAnchor = value;
                            timeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        card.appendChild(button);
                    });
                }
                daysContainer.appendChild(card);
            });
            const total = Object.values(availability).reduce((sum, slots) => sum + slots.length, 0);
            status.textContent = total ? `${total} horario(s) disponible(s). Selecciona uno para completar la cita.` : 'No hay horarios disponibles en este período. Revisa otra semana.';
            const previousEnd = parsePatientDate(dates[0]);
            previousEnd.setDate(previousEnd.getDate() - 1);
            previousButton.disabled = patientDateValue(previousEnd) < today;
            nextButton.disabled = false;
        } catch (error) {
            if (requestId !== patientAppointmentWeekRequestId) return;
            status.textContent = 'No se pudo consultar la disponibilidad semanal.';
        }
    }

    function loadPatientAppointmentAvailability() {
        const professionalId = appointmentProfessional.val();
        const date = appointmentDate.val();
        const timesContainer = $('#patient-appointment-times-container');
        const timeSelect = $('#patient-appointment-time');

        timeSelect.html('<option value="">Seleccione horario</option>');
        if (!professionalId || !date) {
            timesContainer.addClass('hidden');
            return;
        }

        const availabilityParams = new URLSearchParams({ professional_id: professionalId, date });
        if (patientAppointmentBranchId()) availabilityParams.set('clinic_branch_id', patientAppointmentBranchId());
        fetch(`/appointments/availability?${availabilityParams}`)
            .then(response => response.json())
            .then(slots => {
                timeSelect.html('<option value="">Seleccione horario</option>');
                const now = new Date();

                slots.forEach(slot => {
                    if (new Date(`${date}T${slot.value}:00`) <= now) return;
                    timeSelect.append(new Option(slot.label, slot.value));
                });

                if (timeSelect.children().length === 1) {
                    timeSelect.html('<option value="">No hay horarios disponibles</option>');
                }
                timesContainer.removeClass('hidden');
            })
            .catch(() => {
                timesContainer.addClass('hidden');
                Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudo cargar la disponibilidad.' });
            });
    }

    appointmentSpecialty.on('change', function () {
        const specialtyId = $(this).val();
        appointmentProfessional.html('<option value="">Seleccione profesional</option>');
        $('#patient-appointment-professional-container').addClass('hidden');
        $('#patient-appointment-times-container').addClass('hidden');

        if (!specialtyId) {
            loadPatientWeeklyAvailability();
            return;
        }

        const professionalParams = new URLSearchParams({ specialty_id: specialtyId });
        if (patientAppointmentBranchId()) professionalParams.set('clinic_branch_id', patientAppointmentBranchId());
        fetch(`/professionals/by-specialty?${professionalParams}`)
            .then(response => response.json())
            .then(professionals => {
                if (isLoggedInDoctor) {
                    const ownProfileIsAvailable = professionals.some(professional =>
                        String(professional.id) === String(currentProfessionalId)
                    );

                    if (!ownProfileIsAvailable) {
                        appointmentProfessional.html('<option value="">No tienes esta especialidad asignada</option>');
                        $('#patient-appointment-times-container').addClass('hidden');
                        loadPatientWeeklyAvailability();
                        return;
                    }

                    appointmentProfessional.html('');
                    appointmentProfessional.append(new Option(currentProfessionalName, currentProfessionalId, true, true));
                    appointmentProfessional.val(String(currentProfessionalId));
                    patientAppointmentWeekAnchor = appointmentDate.val() || appointmentDate[0].min;
                    loadPatientAppointmentAvailability();
                    loadPatientWeeklyAvailability();
                    return;
                }

                professionals.forEach(professional => {
                    appointmentProfessional.append(new Option(professional.name, professional.id));
                });
                if (!professionals.length) {
                    appointmentProfessional.html('<option value="">No hay profesionales disponibles</option>');
                }
                $('#patient-appointment-professional-container').removeClass('hidden');
            })
            .catch(() => Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar los profesionales.' }));
    });

    appointmentProfessional.on('change', function () {
        patientAppointmentWeekAnchor = appointmentDate.val() || appointmentDate[0].min;
        loadPatientAppointmentAvailability();
        loadPatientWeeklyAvailability();
    });
    appointmentDate.on('change', function () {
        patientAppointmentWeekAnchor = appointmentDate.val() || appointmentDate[0].min;
        loadPatientAppointmentAvailability();
        loadPatientWeeklyAvailability();
        updatePatientSelectedAppointmentSummary();
    });
    $('#patient-appointment-time').on('change', function () {
        updatePatientSelectedAppointmentSummary();
        const selectedDate = appointmentDate.val();
        const panel = document.getElementById('patient-appointment-weekly-panel');
        document.querySelectorAll('#patient-appointment-weekly-days [data-date]').forEach(card => {
            const selectedDay = card.dataset.date === selectedDate;
            card.style.borderColor = selectedDay ? panel.dataset.accent : '';
            card.style.backgroundColor = selectedDay ? panel.dataset.accentSoft : '';
            card.querySelectorAll('[data-time]').forEach(button => {
                const selected = selectedDay && button.dataset.time === this.value;
                button.classList.toggle('text-white', selected);
                button.style.backgroundColor = selected ? panel.dataset.accent : '';
                button.style.borderColor = selected ? panel.dataset.accent : '';
            });
        });
    });
    $('#patient-appointment-week-prev').on('click', function () {
        const previous = parsePatientDate(patientAppointmentWeekAnchor || appointmentDate.val() || appointmentDate[0].min);
        previous.setDate(previous.getDate() - 7);
        const nextAnchor = patientDateValue(previous);
        if (patientWeekDates(nextAnchor).at(-1) < appointmentDate[0].min) return;
        patientAppointmentWeekAnchor = nextAnchor;
        loadPatientWeeklyAvailability();
    });
    $('#patient-appointment-week-next').on('click', function () {
        const next = parsePatientDate(patientAppointmentWeekAnchor || appointmentDate.val() || appointmentDate[0].min);
        next.setDate(next.getDate() + 7);
        patientAppointmentWeekAnchor = patientDateValue(next);
        loadPatientWeeklyAvailability();
    });

    $('#patient-appointment-cancel').on('click', closePatientAppointmentModal);
    appointmentModal.on('click', function (event) {
        if (event.target === this) closePatientAppointmentModal();
    });

    $('#patient-appointment-save').on('click', async function () {
        if (!selectedPatient) return;

        const payload = {
            patient_id: selectedPatient.id,
            specialty_id: appointmentSpecialty.val(),
            professional_id: appointmentProfessional.val(),
            date: appointmentDate.val(),
            time: $('#patient-appointment-time').val(),
            modality: $('#patient-appointment-modality').val(),
            reason: $('#patient-appointment-reason').val(),
            notes: $('#patient-appointment-notes').val(),
            whatsapp_consent: $('#patient-appointment-whatsapp-consent').is(':checked')
        };
        if (patientAppointmentBranchId()) payload.clinic_branch_id = Number(patientAppointmentBranchId());

        if (isLoggedInDoctor) payload.professional_id = currentProfessionalId;

        if (!payload.specialty_id || !payload.professional_id || !payload.date || !payload.time) {
            const missingFields = isLoggedInDoctor ? 'especialidad, fecha y horario' : 'especialidad, profesional, fecha y horario';
            Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: `Selecciona ${missingFields}.` });
            return;
        }

        const saveButton = $(this).prop('disabled', true).text('Guardando...');
        try {
            const response = await fetch('/appointments', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const result = await response.json();
            if (!response.ok) {
                const validationMessage = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validationMessage || result.message || 'No se pudo crear la cita.');
            }

            closePatientAppointmentModal();
            tableActive.ajax.reload(null, false);
            await Swal.fire({ icon: 'success', title: 'Cita creada', text: result.message, timer: 1800, showConfirmButton: false });
        } catch (error) {
            Swal.fire({ icon: 'error', title: 'Error', text: error.message });
        } finally {
            saveButton.prop('disabled', false).text('Guardar');
        }
    });


    /*
    |--------------------------------------------------------------------------
    | DATATABLE
    |--------------------------------------------------------------------------
    */

    function newPatientRegistrationOperation() {
        if (typeof globalThis.crypto.randomUUID === 'function') return globalThis.crypto.randomUUID();
        // Local Laragon HTTP domains do not expose randomUUID (secure-context API).
        const bytes = globalThis.crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64;
        bytes[8] = (bytes[8] & 63) | 128;
        const hex = Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    let registrationOperation = newPatientRegistrationOperation();
    tableActive = $('#patient-table').DataTable({

        processing: true,

        serverSide: true,

        ajax: {url:'/patient-data/search',type:'POST',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content}},
        searchDelay: 350,
        initComplete: function () {
            const input=this.api().table().container().querySelector('.dataTables_filter input, .dt-search input');
            input.setAttribute('placeholder','Nombre o RUT');
            input.setAttribute('autocomplete','off');
        },

        pageLength: 10,

        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],

        pagingType: 'full_numbers',

        order: [],

        language: {
            processing: 'Cargando pacientes...',
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_ pacientes',
            info: 'Mostrando _START_ a _END_ de _TOTAL_ pacientes',
            infoEmpty: 'No hay pacientes para mostrar',
            infoFiltered: '(filtrado de _MAX_ pacientes)',
            zeroRecords: 'No se encontraron pacientes',
            emptyTable: 'No hay pacientes registrados',
            paginate: {
                first: 'Primera',
                last: 'Última',
                next: 'Siguiente',
                previous: 'Anterior'
            }
        },

        responsive: false,

        autoWidth: false,

        columns: [

            /*
            |--------------------------------------------------------------------------
            | #
            |--------------------------------------------------------------------------
            */

            {
                data: null,

                render: function (data, type, row, meta) {

                    return meta.row +
                        meta.settings._iDisplayStart +
                        1;

                },

                orderable: false,

                searchable: false
            },


            /*
            |--------------------------------------------------------------------------
            | NOMBRE
            |--------------------------------------------------------------------------
            */

            {
                data: 'name'
            },

            {
                data: 'branch_name'
            },


            /*
            |--------------------------------------------------------------------------
            | RUT
            |--------------------------------------------------------------------------
            */

            {
                data: 'identification_label', name: 'name'
            },


            /*
            |--------------------------------------------------------------------------
            | TELÉFONO
            |--------------------------------------------------------------------------
            */

            {
                data: 'phone',
                render(data, type) {
                    if (type !== 'display') return data;
                    const label = document.createElement('span');
                    label.setAttribute('data-phone-display', '');
                    label.textContent = data || '—';
                    return label.outerHTML;
                }
            },


            /*
            |--------------------------------------------------------------------------
            | PREVISIÓN
            |--------------------------------------------------------------------------
            */

            {
                data: 'insurance'
            },

            {
                data: null,
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    const count = Number(row.total_appointments_count || 0);
                    return `<button type="button" class="patient-appointments-count px-2 py-1 rounded text-xs text-white bg-green-500 hover:bg-green-600 transition"
                        data-id="${row.id}" aria-label="Ver ${count} citas médicas">${count}</button>`;
                }
            },


            /*
            |--------------------------------------------------------------------------
            | ACCIONES
            |--------------------------------------------------------------------------
            */

            {

                data: null,

                orderable: false,

                searchable: false,

                render: function (data, type, row) {

                    return `

                        <button
                            type="button"
                            class="patient-actions-btn px-2 py-1 rounded hover:bg-gray-100"
                            data-id="${row.id}">

                            <i class="fa-solid fa-ellipsis-vertical"></i>

                        </button>

                    `;

                }

            }

        ]

    });

    $('#patient-table').on('click', '.patient-appointments-count', function () {
        const patient = tableActive.row($(this).closest('tr')).data();
        if (patient) openPatientAppointmentsDetailModal(patient);
    });

    $('#patient-appointments-detail-close').on('click', closePatientAppointmentsDetailModal);
    $('#patient-appointments-detail-modal').on('click', function (event) {
        if (event.target === this) closePatientAppointmentsDetailModal();
    });


    /*
    |--------------------------------------------------------------------------
    | ABRIR MENÚ DE ACCIONES
    |--------------------------------------------------------------------------
    */

   $('#patient-table').on(
    'click',
    '.patient-actions-btn',
    function (e) {

        e.preventDefault();
        e.stopPropagation();

        // Si ya está abierto, cerrarlo y terminar
        if (!actionsMenu.hasClass('hidden')) {
            actionsMenu.addClass('hidden');
            return;
        }

        // Obtener paciente
        selectedPatient = tableActive
            .row($(this).closest('tr'))
            .data();

        if (!selectedPatient) {
            return;
        }

        // Posición del botón
        const button = this;
        const rect = button.getBoundingClientRect();

        // Mostrar menú
        actionsMenu
            .removeClass('hidden')
            .css({
                top: `${rect.bottom + 5}px`,
                left: `${rect.right - 208}px`
            });
    }
);


    /*
    |--------------------------------------------------------------------------
    | CERRAR MENÚ AL HACER CLICK AFUERA
    |--------------------------------------------------------------------------
    */

    $(document).on('click', function (e) {

        if (

            !$(e.target).closest('#patient-actions-menu').length &&

            !$(e.target).closest('.patient-actions-btn').length

        ) {

            actionsMenu.addClass('hidden');

        }

    });


    /*
    |--------------------------------------------------------------------------
    | ESC
    |--------------------------------------------------------------------------
    */

    $(document).on('keydown', function (e) {

        if (e.key === 'Escape') {

            actionsMenu.addClass('hidden');

            if (!appointmentModal.hasClass('hidden')) {
                closePatientAppointmentModal();
            }

            if (!modal.hasClass('hidden') && isEdit) {

                closeModal();

            }

        }

    });


    /*
    |--------------------------------------------------------------------------
    | VER FICHA
    |--------------------------------------------------------------------------
    */

    $('#action-view').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patientId = selectedPatient.id;


        actionsMenu.addClass('hidden');


        window.VitaNavigation.navigate(`/patients/${patientId}/medical-record`);

    });


    /*
    |--------------------------------------------------------------------------
    | EDITAR
    |--------------------------------------------------------------------------
    */

    $('#action-edit').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patient = selectedPatient;


        actionsMenu.addClass('hidden');


        openEditModal(patient);

    });


    /*
    |--------------------------------------------------------------------------
    | AGENDAR CITA
    |--------------------------------------------------------------------------
    */

    $('#action-appointment').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        actionsMenu.addClass('hidden');
        openPatientAppointmentModal(selectedPatient);

    });


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR
    |--------------------------------------------------------------------------
    */

    $('#action-delete').on('click', function () {

        if (!selectedPatient) {

            return;

        }


        const patient = selectedPatient;


        actionsMenu.addClass('hidden');


        deletePatient(patient);

    });


    /*
    |--------------------------------------------------------------------------
    | NUEVO PACIENTE
    |--------------------------------------------------------------------------
    */

    $('#new-patient-btn').on('click', function () {

        openCreateModal();

    });


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL CREAR
    |--------------------------------------------------------------------------
    */

    function openCreateModal() {
        registrationOperation = newPatientRegistrationOperation();

        isEdit = false;

        editingPatientId = null;


        $('#patient-modal-title')
            .text('Nuevo Paciente');


        clearForm();

        clearErrors();

        showModal();

    }


    /*
    |--------------------------------------------------------------------------
    | ABRIR MODAL EDITAR
    |--------------------------------------------------------------------------
    */

    function openEditModal(data) {

        isEdit = true;

        editingPatientId = data.id;


        $('#patient-modal-title')
            .text('Editar Paciente');


        clearErrors();


        $('#patient-name')
            .val(data.name ?? '');


        const identityState = Alpine.$data(document.getElementById('patient-modal'));
        identityState.isEdit = true;
        identityState.form = VitaIdentification.defaults();
        identityState.form.identification_enabled = false;
        identityState.errors = {};
        identityState.identityLoading = true;
        identityState.identityFailed = false;
        VitaIdentification.load(`/patient/${data.id}/identification`,identityState.form)
            .catch(error => {identityState.identityFailed=true;identityState.errors={identification_number:[error.message]};})
            .finally(()=>identityState.identityLoading=false);


        const birthDateInput = document.getElementById('patient-birth-date');
        const birthDate = String(data.birth_date ?? '').slice(0, 10);
        if (birthDateInput?._flatpickr) birthDateInput._flatpickr.setDate(birthDate, false);
        else $('#patient-birth-date').val(birthDate);


        $('#patient-gender')
            .val(data.gender ?? 'Masculino');


        $('#patient-phone')
            .val(data.phone ?? '');


        $('#patient-email')
            .val(data.email ?? '');


        $('#patient-address')
            .val(data.address ?? '');


        $('#patient-insurance')
            .val(data.insurance_id ?? '');


        showModal();

    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR MODAL
    |--------------------------------------------------------------------------
    */

    function showModal() {

        modal
            .removeClass('hidden')
            .addClass('flex');


        $('body')
            .addClass('overflow-hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | CERRAR MODAL
    |--------------------------------------------------------------------------
    */

    function closeModal() {

        modal
            .removeClass('flex')
            .addClass('hidden');


        $('body')
            .removeClass('overflow-hidden');


        clearErrors();

    }


    /*
    |--------------------------------------------------------------------------
    | CANCELAR
    |--------------------------------------------------------------------------
    */

    $('#patient-modal-cancel').on('click', function () {

        closeModal();

    });


    /*
    |--------------------------------------------------------------------------
    | CLICK FUERA DEL MODAL
    |--------------------------------------------------------------------------
    */

    $('#patient-modal').on('click', function (e) {

        if (e.target === this && isEdit) {

            closeModal();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    function clearForm() {
        document.getElementById('patient-modal').dispatchEvent(new Event('portal-autofill-reset'));

        $('#patient-name').val('');

        const identityState = Alpine.$data(document.getElementById('patient-modal'));
        identityState.isEdit = false;
        identityState.form = VitaIdentification.defaults();
        identityState.errors = {};
        identityState.identityFailed = false;
        identityState.identityLoading = false;

        const birthDateInput = document.getElementById('patient-birth-date');
        if (birthDateInput?._flatpickr) birthDateInput._flatpickr.clear();
        else $('#patient-birth-date').val('');

        $('#patient-gender').val('Masculino');

        $('#patient-phone').val('');

        $('#patient-email').val('');

        $('#patient-address').val('');

        $('#patient-insurance')
            .val('{{ $insurances->first()->id ?? '' }}');

    }


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR ERRORES
    |--------------------------------------------------------------------------
    */

    function clearErrors() {

        $('[id^="error-"]')
            .text('')
            .addClass('hidden');

    }


    /*
    |--------------------------------------------------------------------------
    | MOSTRAR ERRORES
    |--------------------------------------------------------------------------
    */

    function showErrors(errors) {

        clearErrors();


        $.each(errors, function (field, messages) {

            const errorId =
                '#error-' + field.replaceAll('_', '-');


            if ($(errorId).length) {

                $(errorId)
                    .text(messages[0])
                    .removeClass('hidden');

            }

        });

    }


    /*
    |--------------------------------------------------------------------------
    | GUARDAR
    |--------------------------------------------------------------------------
    */

    $('#patient-modal-save').on('click', function () {

        savePatient();

    });


    /*
    |--------------------------------------------------------------------------
    | GUARDAR PACIENTE
    |--------------------------------------------------------------------------
    */

    function savePatient() {
        return window.VitaPatientForm.save({isEdit,editingPatientId,registrationOperation,clearErrors,showErrors,csrfToken,
            onSaved() {closeModal();tableActive.ajax.reload(null,false);}});
    }


    /*
    |--------------------------------------------------------------------------
    | ELIMINAR PACIENTE
    |--------------------------------------------------------------------------
    */

    function deletePatient(patient) {

        Swal.fire({

            title: '¿Eliminar paciente?',

            text:
                'Se eliminará permanentemente solo si no tiene historial ni registros asociados que deban conservarse.',

            icon: 'warning',

            showCancelButton: true,

            confirmButtonText:
                'Sí, eliminar',

            confirmButtonColor:
                '#dc2626',

            cancelButtonText:
                'Cancelar'

        })

        .then(result => {

            if (!result.isConfirmed) {

                return;

            }


            fetch(
                `/patient/${patient.id}`,
                {

                    method: 'DELETE',

                    headers: {

                        'Content-Type':
                            'application/json',

                        'Accept':
                            'application/json',

                        'X-CSRF-TOKEN':
                            csrfToken

                    }

                }
            )

            .then(async response => {

                const data =
                    await response.json();


                if (!response.ok) {

                    throw new Error(
                        data.message ||
                        'No se pudo eliminar el paciente'
                    );

                }


                return data;

            })

            .then(() => {

                Swal.fire({

                    toast: true,

                    position: 'top-end',

                    icon: 'success',

                    title:
                        'Paciente eliminado',

                    showConfirmButton:
                        false,

                    timer: 1500

                });


                tableActive
                    .ajax
                    .reload(null, false);

            })

            .catch(error => {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text: error.message

                });

            });

        });

    }


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR ERRORES AL ESCRIBIR
    |--------------------------------------------------------------------------
    */

    $('#patient-name').on('input', function () {

        $('#error-name')
            .text('')
            .addClass('hidden');

    });


    $('#patient-rut').on('input', function () {

        $('#error-rut')
            .text('')
            .addClass('hidden');

    });


    $('#patient-birth-date').on('input', function () {

        $('#error-birth-date')
            .text('')
            .addClass('hidden');

    });


    $('#patient-phone').on('input', function () {

        $('#error-phone')
            .text('')
            .addClass('hidden');

    });


    $('#patient-email').on('input', function () {

        $('#error-email')
            .text('')
            .addClass('hidden');

    });


    $('#patient-address').on('input', function () {

        $('#error-address')
            .text('')
            .addClass('hidden');

    });


    /*
    |--------------------------------------------------------------------------
    | REPOSICIONAR MENÚ AL HACER SCROLL
    |--------------------------------------------------------------------------
    */

    $(window).on('scroll', function () {

        actionsMenu.addClass('hidden');

    });


});

</script>
<script src="{{ asset('js/patient-portal-autofill.js').'?v='.filemtime(public_path('js/patient-portal-autofill.js')) }}" defer></script>

@endpush
