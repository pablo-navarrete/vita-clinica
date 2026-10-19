@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Pacientes') }}
    </h2>
@endsection

@push('styles')
    <style>
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

<div class="py-12">

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

                <div
                    id="patient-modal"
                    class="hidden fixed inset-0 items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

                    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl max-h-[90vh] flex flex-col">

                        <!-- HEADER -->

                        <div
                            class="px-6 py-4 border-b rounded-t-lg"
                            style="background-color: {{ themeColor(100) }}">

                            <h2
                                id="patient-modal-title"
                                class="text-lg font-bold">

                                Nuevo Paciente

                            </h2>

                        </div>


                        <!-- BODY -->

                        <div class="p-6 space-y-4 overflow-y-auto flex-1">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">


                                <!-- NOMBRE -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre Completo *
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-name"
                                        name="name"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-name"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- RUT -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        RUT *
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-rut"
                                        name="rut"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-rut"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- FECHA NACIMIENTO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha Nacimiento
                                    </label>

                                    <input
                                        type="date"
                                        id="patient-birth-date"
                                        name="birth_date"
                                        max="{{ now()->format('Y-m-d') }}"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-birth-date"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- SEXO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Sexo
                                    </label>

                                    <select
                                        id="patient-gender"
                                        name="gender"
                                        class="w-full rounded-lg border-gray-300">

                                        <option value="Masculino">
                                            Masculino
                                        </option>

                                        <option value="Femenino">
                                            Femenino
                                        </option>

                                        <option value="Otro">
                                            Otro
                                        </option>

                                    </select>

                                </div>


                                <!-- TELÉFONO -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Teléfono
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-phone"
                                        name="phone"
                                        maxlength="20"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-phone"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- EMAIL -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Correo Electrónico
                                    </label>

                                    <input
                                        type="email"
                                        id="patient-email"
                                        name="email"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-email"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- DIRECCIÓN -->

                                <div class="md:col-span-2">

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Dirección
                                    </label>

                                    <input
                                        type="text"
                                        id="patient-address"
                                        name="address"
                                        maxlength="255"
                                        class="w-full rounded-lg border-gray-300">

                                    <p
                                        id="error-address"
                                        class="text-xs text-red-500 mt-1 hidden">
                                    </p>

                                </div>


                                <!-- PREVISIÓN -->

                                <div>

                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Previsión
                                    </label>

                                    <select
                                        id="patient-insurance"
                                        name="insurance_id"
                                        class="w-full rounded-lg border-gray-300">

                                        @foreach ($insurances as $insurance)

                                            <option value="{{ $insurance->id }}">
                                                {{ $insurance->name }}
                                            </option>

                                        @endforeach

                                    </select>

                                </div>


                            </div>

                        </div>


                        <!-- FOOTER -->

                        <div class="px-6 py-4 border-t flex justify-end gap-2">

                            <button
                                type="button"
                                id="patient-modal-cancel"
                                class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded">

                                Cancelar

                            </button>


                            <button
                                type="button"
                                id="patient-modal-save"
                                style="background-color: {{ themeColor(600) }}"
                                class="hover:brightness-90 transition text-white px-4 py-2 rounded">

                                Guardar

                            </button>

                        </div>

                    </div>

                </div>


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
                    <div class="bg-white rounded-lg shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">
                        <div class="px-6 py-4 border-b rounded-t-lg" style="background-color: {{ themeColor(100) }}">
                            <h2 class="text-lg font-bold">Nueva cita médica</h2>
                            <p id="patient-appointment-name" class="mt-1 text-sm text-gray-600"></p>
                            <p id="patient-future-appointments-status" role="status"
                                class="hidden mt-3 rounded-lg border px-3 py-2 text-sm"></p>
                        </div>

                        <div class="p-6 space-y-4 overflow-y-auto flex-1">
                            <div>
                                <label for="patient-appointment-specialty" class="block text-sm font-medium mb-1">Especialidad</label>
                                <select id="patient-appointment-specialty" class="w-full rounded-lg border-gray-300">
                                    <option value="">Seleccione especialidad</option>
                                    @foreach ($specialties as $specialty)
                                        <option value="{{ $specialty->id }}">{{ $specialty->name }}</option>
                                    @endforeach
                                </select>
                            </div>

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

                            <div>
                                <label for="patient-appointment-reason" class="block text-sm font-medium mb-1">Motivo</label>
                                <input type="text" id="patient-appointment-reason" class="w-full rounded-lg border-gray-300">
                            </div>

                            <div>
                                <label for="patient-appointment-notes" class="block text-sm font-medium mb-1">Notas</label>
                                <textarea id="patient-appointment-notes" class="w-full rounded-lg border-gray-300"></textarea>
                            </div>
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
    const patientAppointmentsDetailModal = $('#patient-appointments-detail-modal');
    const patientAppointmentsUrlTemplate = @json($patientAppointmentsUrlTemplate);

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
        $('#patient-appointments-detail-name').text(`${patient.name} · RUT: ${patient.rut}`);
        const statusLabels = {
            0: 'Agendada', 1: 'Confirmada', 2: 'En atención',
            3: 'Atendida', 4: 'No asistió', 5: 'Cancelada'
        };
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

        $('#patient-appointment-name').text(`${patient.name} · RUT: ${patient.rut}`);
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
        appointmentProfessional.html('<option value="">Seleccione profesional</option>');
        $('#patient-appointment-professional-container').addClass('hidden');
        appointmentDate.val(localToday);
        $('#patient-appointment-time').html('<option value="">Seleccione horario</option>');
        $('#patient-appointment-times-container').addClass('hidden');
        $('#patient-appointment-reason, #patient-appointment-notes').val('');
        appointmentModal.removeClass('hidden').addClass('flex');
        $('body').addClass('overflow-hidden');
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

        fetch(`/appointments/availability?professional_id=${encodeURIComponent(professionalId)}&date=${encodeURIComponent(date)}`)
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

        if (!specialtyId) return;

        fetch(`/professionals/by-specialty?specialty_id=${encodeURIComponent(specialtyId)}`)
            .then(response => response.json())
            .then(professionals => {
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

    appointmentProfessional.add(appointmentDate).on('change', loadPatientAppointmentAvailability);

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
            reason: $('#patient-appointment-reason').val(),
            notes: $('#patient-appointment-notes').val()
        };

        if (!payload.specialty_id || !payload.professional_id || !payload.date || !payload.time) {
            Swal.fire({ icon: 'warning', title: 'Datos incompletos', text: 'Selecciona especialidad, profesional, fecha y horario.' });
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

    tableActive = $('#patient-table').DataTable({

        processing: true,

        serverSide: true,

        ajax: '/patient-data',

        pageLength: 10,

        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],

        pagingType: 'full_numbers',

        order: [[1, 'asc']],

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


            /*
            |--------------------------------------------------------------------------
            | RUT
            |--------------------------------------------------------------------------
            */

            {
                data: 'rut'
            },


            /*
            |--------------------------------------------------------------------------
            | TELÉFONO
            |--------------------------------------------------------------------------
            */

            {
                data: 'phone'
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

            if (!modal.hasClass('hidden')) {

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


        window.location.href =
            `/patients/${patientId}/medical-record`;

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


        $('#patient-rut')
            .val(data.rut ?? '');


        $('#patient-birth-date')
            .val(String(data.birth_date ?? '').slice(0, 10));


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

        if (e.target === this) {

            closeModal();

        }

    });


    /*
    |--------------------------------------------------------------------------
    | LIMPIAR FORMULARIO
    |--------------------------------------------------------------------------
    */

    function clearForm() {

        $('#patient-name').val('');

        $('#patient-rut').val('');

        $('#patient-birth-date').val('');

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

        clearErrors();


        const patientData = {

            name: $('#patient-name').val(),

            rut: $('#patient-rut').val(),

            birth_date: $('#patient-birth-date').val(),

            gender: $('#patient-gender').val(),

            phone: $('#patient-phone').val(),

            email: $('#patient-email').val(),

            address: $('#patient-address').val(),

            insurance_id: $('#patient-insurance').val(),

        };


        let url;

        let method;


        if (isEdit) {

            url = `/patient/${editingPatientId}`;

            method = 'PUT';

        } else {

            url = '/patient';

            method = 'POST';

        }


        const saveButton =
            $('#patient-modal-save');


        saveButton
            .prop('disabled', true)
            .text('Guardando...');


        fetch(url, {

            method: method,

            headers: {

                'Content-Type': 'application/json',

                'Accept': 'application/json',

                'X-CSRF-TOKEN': csrfToken

            },

            body: JSON.stringify(patientData)

        })

        .then(async response => {

            const data =
                await response.json();


            if (!response.ok) {

                if (response.status === 422) {

                    showErrors(
                        data.errors || {}
                    );


                    throw new Error(
                        'validation'
                    );

                }


                throw new Error(
                    data.message ||
                    'Error al guardar paciente'
                );

            }


            return data;

        })

        .then(() => {

            Swal.fire({

                toast: true,

                position: 'top-end',

                icon: 'success',

                title: isEdit
                    ? 'Paciente actualizado correctamente'
                    : 'Paciente creado correctamente',

                showConfirmButton: false,

                timer: 2000

            });


            closeModal();


            tableActive
                .ajax
                .reload(null, false);

        })

        .catch(error => {

            console.error(error);


            if (
                error.message !==
                'validation'
            ) {

                Swal.fire({

                    icon: 'error',

                    title: 'Error',

                    text:
                        error.message ||
                        'No se pudo guardar el paciente'

                });

            }

        })

        .finally(() => {

            saveButton
                .prop('disabled', false)
                .text('Guardar');

        });

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
                'Esto lo eliminará del sistema permanentemente.',

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

@endpush
