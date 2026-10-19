@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Citas Médicas') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded-lg">

                <!-- Encabezado -->
                <div class="flex flex-col md:flex-row md:items-end md:justify-between p-6 border-b border-gray-200">

                    <div class="flex gap-4 items-end">

                        <!-- Profesional -->
                        <div class="w-80">

                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ $isDoctor ? 'Mi calendario' : 'Profesional' }}
                            </label>

                            @if ($isDoctor)
                                <p class="rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-gray-800">{{ $currentProfessionalName }}</p>
                            @endif

                            <select id="professional" class="w-full rounded-lg border-gray-300 {{ $isDoctor ? 'hidden' : '' }}">
                                @if (!$isDoctor)
                                    <option value="">Seleccione un profesional</option>
                                @endif

                                @foreach ($professionals as $professional)
                                    <option value="{{ $professional->id }}" @selected($isDoctor && $professional->id === $currentProfessionalId)>
                                        {{ $professional->name }}
                                    </option>
                                @endforeach

                            </select>

                        </div>

                        <!-- Buscador -->
                        <div id="searchContainer" class="hidden w-80">

                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Buscar paciente
                            </label>

                            <input id="patientSearch" type="text" class="w-full rounded-lg border-gray-300"
                                placeholder="Nombre o RUT">

                        </div>

                    </div>

                    <div class="mt-4 md:mt-0">

                        <button onclick="openCreateAppointmentModal()" style="background-color: {{ themeColor(600) }}"
                            class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">

                            <i class="fa-solid fa-plus-circle mr-1"></i>

                            Nueva cita

                        </button>

                    </div>

                </div>

                <!-- Calendario -->
                <!-- Mensaje inicial -->
                <div id="calendarPlaceholder" class="p-12 text-center">

                    <i class="fa-solid fa-calendar-days text-6xl text-gray-300 mb-4"></i>

                    <h3 class="text-xl font-semibold text-gray-700">
                        Seleccione un profesional
                    </h3>

                    <p class="text-gray-500 mt-2">
                        Debe seleccionar un profesional para visualizar su agenda.
                    </p>

                </div>

                <!-- Calendario -->
                <div id="calendarContainer" class="hidden p-6">

                    <div id="calendar" class="min-h-[700px]"></div>

                </div>

            </div>

        </div>
    </div>


    <!-- MODAL CITA -->
    <div id="appointmentModal"
        class="fixed inset-0 hidden flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">

            <!-- HEADER -->
            <div class="px-6 py-4 border-b rounded-t-lg" style="background-color: {{ themeColor(100) }}">

                <h2 id="appointmentModalTitle" class="text-lg font-bold">
                    Nueva cita médica
                </h2>
                @if ($isDoctor)
                    <p class="mt-1 text-sm text-gray-600">Profesional: {{ $currentProfessionalName }}</p>
                @endif

            </div>


            <div class="p-6 space-y-4 overflow-y-auto flex-1">

                <!-- Paciente -->
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Paciente
                    </label>

                    <select id="appointmentPatient" class="w-full rounded-lg border-gray-300" name="patient_id">

                        <option value="">
                            Seleccione paciente
                        </option>


                        @foreach ($patients as $patient)
                            <option value="{{ $patient->id }}">
                                {{ $patient->rut }} - {{ $patient->name }}
                            </option>
                        @endforeach


                    </select>

                </div>
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Especialidad
                    </label>

                    <select id="appointmentSpecialty" class="w-full rounded-lg border-gray-300">

                        <option value="">
                            Seleccione especialidad
                        </option>

                        @foreach ($specialties as $specialty)
                            <option value="{{ $specialty->id }}">
                                {{ $specialty->name }}
                            </option>
                        @endforeach

                    </select>

                </div>
                <!-- Profesional manual -->
                <div id="professionalContainer" class="hidden">

                    <label class="block text-sm font-medium mb-1">
                        Profesional
                    </label>

                    <select id="appointmentProfessional" class="w-full rounded-lg border-gray-300" name="professional_id">


                        <option value="">
                            Seleccione profesional
                        </option>


                        @foreach ($professionals as $professional)
                            <option value="{{ $professional->id }}" @selected($isDoctor && $professional->id === $currentProfessionalId)>
                                {{ $professional->name }}

                            </option>
                        @endforeach


                    </select>

                </div>







                <!-- Fecha -->
                <div>

                    <label class="block text-sm font-medium mb-1">
                        Fecha
                    </label>


                    <input type="date" id="appointmentDate" class="w-full rounded-lg border-gray-300" name="date">


                </div>



                <div class="grid grid-cols-2 gap-4">




                    <div id="availableTimesContainer" class="hidden">

                        <label class="block text-sm font-medium mb-1">
                            Horario disponible
                        </label>


                        <select id="appointmentTime" class="w-full rounded-lg border-gray-300" name="time">

                            <option value="">
                                Seleccione horario
                            </option>

                        </select>
                        <p id="availabilityFeedback" class="mt-1 hidden text-xs text-amber-700"></p>

                    </div>


                </div>



                <div>

                    <label class="block text-sm font-medium mb-1">
                        Motivo
                    </label>


                    <input type="text" id="appointmentReason" class="w-full rounded-lg border-gray-300" name="reason">


                </div>



                <div>

                    <label class="block text-sm font-medium mb-1">
                        Notas
                    </label>


                    <textarea id="appointmentNotes" class="w-full rounded-lg border-gray-300" name="notes"></textarea>


                </div>


            </div>



            <!-- FOOTER -->
            <div class="border-t px-6 py-4 flex justify-end gap-2">


                <button onclick="closeAppointmentModal()" class="bg-gray-600 text-white px-4 py-2 rounded">

                    Cancelar

                </button>


                <button onclick="saveAppointment()" type="button" style="background-color: {{ themeColor(600) }}"
                    class="text-white px-4 py-2 rounded">

                    <span id="appointmentSaveLabel">Guardar</span>

                </button>


            </div>


        </div>


    </div>



    <!-- MODAL DETALLE CITA -->

    <div id="appointmentDetailModal"
        class="fixed inset-0 hidden flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">


        <div class="bg-white rounded-lg shadow-lg w-full max-w-lg max-h-[90vh] flex flex-col">


            <div class="px-6 py-4 border-b rounded-t-lg flex items-center justify-between"
                style="background-color: {{ themeColor(100) }}">

                <h2 class="font-bold text-lg">
                    Detalle de cita
                </h2>

                <span id="specialtyDetail" class="px-3 py-1 rounded-full text-sm font-semibold bg-white"
                    style="color: {{ themeColor(700) }}">
                </span>

            </div>


            <div class="p-6 space-y-3">


                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-500">
                        Paciente
                    </label>

                    <span id="detailPatientName"></span>
                </div>


                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-500">
                        RUT
                    </label>

                    <span id="detailPatientRut"></span>
                </div>


                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="block text-sm font-medium mb-1 text-gray-500">
                            Fecha
                        </label>

                        <span id="detailDate"></span>
                    </div>


                    <div>
                        <label class="block text-sm font-medium mb-1 text-gray-500">
                            Hora
                        </label>

                        <span id="detailTime"></span>
                    </div>

                </div>


                <div>
                    <label class="block text-sm font-medium mb-1  text-gray-500">
                        Estado
                    </label>

                    <span id="detailStatus" class="px-3 py-1 rounded-full text-sm font-semibold">
                    </span>
                </div>


                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-500">
                        Motivo
                    </label>

                    <span id="detailReason"></span>
                </div>


                <div>
                    <label class="block text-sm font-medium mb-1 text-gray-500">
                        Notas
                    </label>

                    <p id="detailNotes"></p>
                </div>

                <p id="detailAttentionBlockReason" class="hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"></p>


            </div>


            <div class="border-t px-6 py-4 flex justify-end gap-2">

                <a id="btnStartAttention" href="#" class="hidden rounded px-4 py-2 font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">
                    <i class="fa-solid fa-stethoscope mr-1"></i> Iniciar atención
                </a>

                <button onclick="" style="background-color: {{ themeColor(600) }}"
                    class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded"
                    id="btnUpdateAppointment">

                    <i class="fa-solid fa-pen-to-square mr-1"></i>

                    Actualizar cita

                </button>

                <a id="btnViewAttention" href="#" class="hidden order-first mr-auto rounded px-4 py-2 text-sm font-semibold text-white hover:brightness-90" style="background-color: {{ themeColor(600) }}">
                    <i class="fa-solid fa-eye mr-1"></i> Ver atención
                </a>

                <button onclick="closeDetailModal()" class="bg-gray-600 text-white px-4 py-2 rounded">

                    Cerrar

                </button>

            </div>


        </div>


    </div>
@endsection

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #appointmentModal .select2-container {
            width: 100% !important;
        }

        #appointmentModal .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }

        #appointmentModal .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }

        #appointmentModal .select2-container .select2-selection__arrow {
            height: 40px;
        }

        #professional + .select2-container {
            width: 100% !important;
        }

        #professional + .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
        }

        #professional + .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }

        #professional + .select2-container .select2-selection__arrow {
            height: 40px;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
        let calendar;
        let editingAppointmentId = null;
        let editingProfessionalId = null;
        let editingOriginalDate = null;
        let editingTime = null;
        let availabilityRequestId = 0;
        let professionalLookupRequestId = 0;

        document.addEventListener('DOMContentLoaded', function() {
            const isDoctor = @json($isDoctor);
            const currentProfessionalId = @json($currentProfessionalId);

            $('#appointmentPatient').select2({
                width: '100%',
                placeholder: 'Seleccione paciente',
                allowClear: false,
                minimumResultsForSearch: 0,
                dropdownParent: $('#appointmentModal'),
                language: {
                    noResults: () => 'No se encontraron pacientes',
                    searching: () => 'Buscando...'
                }
            });

            $('#appointmentSpecialty, #appointmentProfessional').select2({
                width: '100%',
                placeholder: 'Seleccione una opción',
                allowClear: false,
                minimumResultsForSearch: 0,
                dropdownParent: $('#appointmentModal'),
                language: {
                    noResults: () => 'No se encontraron opciones',
                    searching: () => 'Buscando...'
                }
            });

            if (!isDoctor) {
                $('#professional').select2({
                    width: '100%',
                    placeholder: 'Seleccione un profesional',
                    minimumResultsForSearch: 0,
                    language: {
                        noResults: () => 'No se encontraron profesionales',
                        searching: () => 'Buscando...'
                    }
                });
            }

            const calendarEl = document.getElementById('calendar');
            const professionalSelect = document.getElementById('professional');

            calendar = new FullCalendar.Calendar(calendarEl, {

                locale: 'es',

                firstDay: 1, // Lunes

                initialView: 'timeGridWeek',

                initialDate: new URLSearchParams(window.location.search).get('date') || undefined,

                height: 'auto',

                nowIndicator: true,

                selectable: true,

                selectMirror: true,

                allDaySlot: false,

                slotMinTime: '00:00:00',

                slotMaxTime: '24:00:00',

                slotDuration: '00:30:00',

                expandRows: true,

                weekends: true,

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                buttonText: {
                    today: 'Hoy',
                    month: 'Mes',
                    week: 'Semana',
                    day: 'Día'
                },

                events: function(fetchInfo, successCallback, failureCallback) {

                    const professionalId = professionalSelect.value;
                    const search = document.getElementById('patientSearch').value;



                    if (!professionalId) {
                        successCallback([]);
                        return;
                    }


                    fetch(
                        `/appointments/events?professional_id=${professionalId}&search=${encodeURIComponent(search)}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`,
                        { cache: 'no-store' }
                        )
                        .then(res => res.json())
                        .then(data => successCallback(data))
                        .catch(error => failureCallback(error));

                },

                eventDidMount: function(info) {
                    const targetAppointmentId = new URLSearchParams(window.location.search).get('appointment_id');
                    if (!targetAppointmentId || window.openedNotificationAppointment) return;
                    if (String(info.event.id) !== String(targetAppointmentId)) return;

                    window.openedNotificationAppointment = true;
                    setTimeout(() => info.el.click(), 0);
                },

                // Seleccionar un bloque para crear una cita
                select: function(info) {

                    if (!professionalSelect.value) {

                        Swal.fire({
                            icon: 'warning',
                            title: 'Seleccione un profesional',
                            text: 'Debe seleccionar un profesional antes de crear una cita.'
                        });

                        calendar.unselect();
                        return;
                    }

                    console.log('Profesional:', professionalSelect.value);
                    console.log('Inicio:', info.startStr);
                    console.log('Fin:', info.endStr);

                    //openAppointmentModal('calendar', info);

                },

                // Click sobre una cita existente
                eventClick: function(info) {


                    const event = info.event;

                    const btnUpdate = document.getElementById('btnUpdateAppointment');
                    const btnStartAttention = document.getElementById('btnStartAttention');
                    const btnViewAttention = document.getElementById('btnViewAttention');
                    const attentionBlockReason = document.getElementById('detailAttentionBlockReason');

                    if (event.extendedProps.canStartAttention) {
                        btnStartAttention.href = `/patients/${event.extendedProps.patientId}/medical-record?start_encounter=${event.extendedProps.appointmentId}`;
                        btnStartAttention.classList.remove('hidden');
                        btnViewAttention.classList.add('hidden');
                        attentionBlockReason.classList.add('hidden');
                    } else {
                        btnStartAttention.classList.add('hidden');
                        if (event.extendedProps.encounterId) {
                            btnViewAttention.href = `/patients/${event.extendedProps.patientId}/medical-record#current-encounter-section`;
                            btnViewAttention.classList.remove('hidden');
                            attentionBlockReason.textContent = event.extendedProps.attentionBlockReason || 'Esta cita ya tiene una atención asociada.';
                            attentionBlockReason.classList.remove('hidden');
                            attentionBlockReason.textContent = 'Esta cita ya tiene una atenci\u00f3n iniciada. No se puede modificar.';
                        } else if (event.extendedProps.isDoctor && event.extendedProps.patientHasActiveAttention) {
                            btnViewAttention.classList.add('hidden');
                            attentionBlockReason.textContent = 'No se puede iniciar una atenci\u00f3n porque el paciente ya tiene una atenci\u00f3n abierta.';
                            attentionBlockReason.classList.remove('hidden');
                        } else if (event.extendedProps.attentionBlockReason) {
                            btnViewAttention.classList.add('hidden');
                            attentionBlockReason.textContent = event.extendedProps.attentionBlockReason;
                            attentionBlockReason.classList.remove('hidden');
                        } else {
                            btnViewAttention.classList.add('hidden');
                            attentionBlockReason.classList.add('hidden');
                        }
                    }

                    if (event.extendedProps.canUpdateAppointment) {
                        btnUpdate.classList.remove('hidden');
                        btnUpdate.dataset.appointmentId = event.extendedProps.appointmentId;
                        btnUpdate.dataset.patientId = event.extendedProps.patientId;
                        btnUpdate.dataset.professionalId = event.extendedProps.professionalId;
                        btnUpdate.dataset.specialtyId = event.extendedProps.specialtyId;
                        btnUpdate.dataset.date = formatLocalDate(event.start);
                        btnUpdate.dataset.time = formatLocalTime(event.start);
                        btnUpdate.dataset.reason = event.extendedProps.reason ?? '';
                        btnUpdate.dataset.notes = event.extendedProps.notes ?? '';
                        btnUpdate.onclick = openEditAppointmentModal;
                    } else {
                        btnUpdate.classList.add('hidden');
                        btnUpdate.onclick = null;
                    }


                    document.getElementById('detailPatientName').innerHTML =
                        event.title;

                    document.getElementById('detailPatientRut').innerHTML =
                        event.extendedProps.rut ?? '';

                    document.getElementById('specialtyDetail').innerHTML =
                        event.extendedProps.specialty ?? 'Sin especialidad';

                    showStatus(event.extendedProps.status);

                    let start = new Date(event.start);


                    document.getElementById('detailDate').innerHTML =
                        start.toLocaleDateString('es');


                    document.getElementById('detailTime').innerHTML =
                        start.toLocaleTimeString('es', {
                            hour: '2-digit',
                            minute: '2-digit'
                        }) +
                        ' - ' +
                        new Date(event.end).toLocaleTimeString('es', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });



                    document.getElementById('detailReason').innerHTML =
                        event.extendedProps.reason ?? '';



                    document.getElementById('detailNotes').innerHTML =
                        event.extendedProps.notes ?? '';



                    document
                        .getElementById('appointmentDetailModal')
                        .classList.remove('hidden');

                }

            });

            calendar.render();

            const refreshCalendarEvents = () => {
                if (calendar && !document.hidden) calendar.refetchEvents();
            };
            window.addEventListener('pageshow', event => {
                if (event.persisted) refreshCalendarEvents();
            });
            document.addEventListener('visibilitychange', refreshCalendarEvents);

            $(professionalSelect).on('change', function() {

                const calendarContainer = document.getElementById('calendarContainer');
                const calendarPlaceholder = document.getElementById('calendarPlaceholder');
                const searchContainer = document.getElementById('searchContainer');

                if (this.value) {

                    calendarPlaceholder.classList.add('hidden');
                    calendarContainer.classList.remove('hidden');
                    searchContainer.classList.remove('hidden');

                    calendar.refetchEvents();

                } else {

                    calendarContainer.classList.add('hidden');
                    calendarPlaceholder.classList.remove('hidden');
                    searchContainer.classList.add('hidden');

                }

                const patientSearch = document.getElementById('patientSearch');


                patientSearch.addEventListener('input', function() {

                    if (calendar) {

                        calendar.refetchEvents();

                    }

                });

            });

            if (isDoctor && currentProfessionalId) {
                $(professionalSelect).trigger('change');
            }

        });


        function openCreateAppointmentModal() {

            editingAppointmentId = null;
            editingProfessionalId = null;
            editingOriginalDate = null;
            editingTime = null;
            document.getElementById('appointmentModalTitle').textContent = 'Nueva cita médica';
            document.getElementById('appointmentSaveLabel').textContent = 'Guardar';

            document
                .getElementById('appointmentModal')
                .classList.remove('hidden');
            document.getElementById('appointmentDate').value = getTodayLocal();
            // limpiar formulario

            $('#appointmentSpecialty').val('').trigger('change');

            @if ($isDoctor)
                $('#appointmentProfessional').val(String(@json($currentProfessionalId))).trigger('change.select2');
            @else
                const professionalSelect = document.getElementById('appointmentProfessional');
                professionalSelect.innerHTML = '<option value="">Seleccione una especialidad primero</option>';
                $(professionalSelect).val('').trigger('change.select2');
            @endif

            $('#appointmentPatient').val('').trigger('change');



            document.getElementById('appointmentTime').innerHTML =
                '<option value="">Seleccione horario</option>';

            document.getElementById('appointmentReason').value = '';

            document.getElementById('appointmentNotes').value = '';

            @if ($isDoctor)
                loadAvailability();
            @endif

            @if ($isDoctor)
                document.getElementById('professionalContainer').classList.add('hidden');
            @else
                document.getElementById('professionalContainer').classList.add('hidden');
            @endif
            document
                .getElementById('availableTimesContainer')
                .classList.add('hidden');

        }

        document.addEventListener('DOMContentLoaded', function() {

            const appointmentProfessional =
                document.getElementById('appointmentProfessional');


            const appointmentDate =
                document.getElementById('appointmentDate');


            $(appointmentProfessional).on('change', loadAvailability);


            appointmentDate.addEventListener('input', loadAvailability);
            appointmentDate.addEventListener('change', loadAvailability);


        });



        function loadAvailability() {

            const professionalId = document.getElementById('appointmentProfessional').value;

            const date = document.getElementById('appointmentDate').value;
            const requestId = ++availabilityRequestId;
            const preservingOriginalSlot = Boolean(editingAppointmentId)
                && date === editingOriginalDate
                && String(professionalId) === String(editingProfessionalId);
            const feedback = document.getElementById('availabilityFeedback');
            feedback.classList.add('hidden');
            feedback.textContent = '';


            if (!professionalId || !date) {
                if (editingAppointmentId) {
                    document.getElementById('availableTimesContainer').classList.remove('hidden');
                } else {
                    document.getElementById('appointmentTime').innerHTML = '<option value="">Seleccione horario</option>';
                    document.getElementById('availableTimesContainer').classList.add('hidden');
                }

                return;

            }



            const availabilityUrl = new URL('/appointments/availability', window.location.origin);
            availabilityUrl.searchParams.set('professional_id', professionalId);
            availabilityUrl.searchParams.set('date', date);
            if (editingAppointmentId) availabilityUrl.searchParams.set('appointment_id', editingAppointmentId);
            document.getElementById('availableTimesContainer').classList.remove('hidden');
            document.getElementById('appointmentTime').innerHTML = '<option value="">Cargando horarios...</option>';

            fetch(availabilityUrl)

                .then(response => {
                    if (!response.ok) throw new Error('No se pudieron consultar los horarios.');
                    return response.json();
                })

                .then(data => {

                    if (requestId !== availabilityRequestId) return;


                    const select =
                        document.getElementById('appointmentTime');


                    select.innerHTML =
                        `
        <option value="">
            Seleccione horario
        </option>
        `;



                    // El servidor ya filtra las horas pasadas usando la zona horaria de Chile.
                    const availableSlots = data;

                    if (availableSlots.length === 0 && !preservingOriginalSlot) {


                        document
                            .getElementById('availableTimesContainer')
                            .classList.remove('hidden');


                        select.innerHTML =
                            `
            <option value="">
                No hay horarios disponibles
            </option>
            `;

                        return;

                    }



                    availableSlots.forEach(slot => {

                        if (preservingOriginalSlot && slot.value === editingTime) return;

                        select.innerHTML +=
                            `
                        <option value="${slot.value}">
                            ${slot.label}
                        </option>
                    `;

                    });

                    if (preservingOriginalSlot && editingTime) {
                        select.add(new Option(`${editingTime} - Hora agendada`, editingTime));
                        select.value = editingTime;
                        if (availableSlots.length === 0) {
                            feedback.textContent = 'No hay otros horarios disponibles para este profesional en esta fecha. Revisa sus horarios configurados.';
                            feedback.classList.remove('hidden');
                        }
                    }



                    document.getElementById('availableTimesContainer').classList.remove('hidden');


                })

                .catch(error => {

                    console.error(error);
                    if (requestId === availabilityRequestId) {
                        document.getElementById('appointmentTime').innerHTML = '<option value="">No se pudieron cargar horarios</option>';
                        document.getElementById('availableTimesContainer').classList.remove('hidden');
                    }

                });


        }

        function closeAppointmentModal() {
            document.getElementById('appointmentModal')
                .classList.add('hidden');
        }

        function getTodayLocal() {
            const today = new Date();

            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            return `${year}-${month}-${day}`;
        }

        function formatLocalDate(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }

        function formatLocalTime(date) {
            const hour = String(date.getHours()).padStart(2, '0');
            const minute = String(date.getMinutes()).padStart(2, '0');
            return `${hour}:${minute}`;
        }

        function openEditAppointmentModal() {
            const button = document.getElementById('btnUpdateAppointment');
            editingAppointmentId = button.dataset.appointmentId;
            editingProfessionalId = button.dataset.professionalId;
            editingOriginalDate = button.dataset.date;
            editingTime = button.dataset.time;

            document.getElementById('appointmentDetailModal').classList.add('hidden');
            document.getElementById('appointmentModalTitle').textContent = 'Actualizar cita médica';
            document.getElementById('appointmentSaveLabel').textContent = 'Actualizar cita';
            document.getElementById('appointmentModal').classList.remove('hidden');
            document.getElementById('appointmentDate').value = button.dataset.date;
            document.getElementById('appointmentTime').innerHTML = `<option value="${editingTime}">${editingTime} - hora agendada</option>`;
            document.getElementById('appointmentTime').value = editingTime;
            document.getElementById('availableTimesContainer').classList.remove('hidden');
            document.getElementById('appointmentReason').value = button.dataset.reason;
            document.getElementById('appointmentNotes').value = button.dataset.notes;
            $('#appointmentPatient').val(button.dataset.patientId).trigger('change');
            $('#appointmentSpecialty').val(button.dataset.specialtyId).trigger('change');
            @if ($isDoctor)
                $('#appointmentProfessional').val(String(@json($currentProfessionalId))).trigger('change.select2');
                loadAvailability();
            @endif
        }

        //no permitir fechas pasadas input 
        document.addEventListener('DOMContentLoaded', function() {

            const dateInput = document.getElementById('appointmentDate');

            const today = new Date();

            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');

            const minDate = `${year}-${month}-${day}`;

            dateInput.setAttribute('min', minDate);

        });


        function saveAppointment() {

            let data = {

                professional_id: document.getElementById('appointmentProfessional').value,

                specialty_id: document.getElementById('appointmentSpecialty').value,

                patient_id: document.getElementById('appointmentPatient').value,

                date: document.getElementById('appointmentDate').value,

                time: document.getElementById('appointmentTime').value,

                reason: document.getElementById('appointmentReason').value,

                notes: document.getElementById('appointmentNotes').value,

            };
            if (
                !data.professional_id ||
                !data.patient_id ||
                !data.date ||
                !data.time
            ) {

                Swal.fire({

                    icon: 'warning',

                    title: 'Datos incompletos',

                    text: 'Revise los datos de paciente, profesional, fecha y hora.'

                });

                return;

            }

            const isUpdatingAppointment = Boolean(editingAppointmentId);
            const appointmentUrl = isUpdatingAppointment ? `/appointments/${editingAppointmentId}` : '/appointments';

            fetch(appointmentUrl, {

                    method: isUpdatingAppointment ? 'PUT' : 'POST',

                    headers: {

                        'Content-Type': 'application/json',

                        'Accept': 'application/json',

                        'X-CSRF-TOKEN': document
                            .querySelector('meta[name="csrf-token"]')
                            .content

                    },

                    body: JSON.stringify(data)

                })

                .then(async response => {

                    let result = await response.json();


                    if (!response.ok) {

                        throw result;

                    }


                    return result;

                })


                .then(response => {


                    Swal.fire({

                        icon: 'success',

                        title: isUpdatingAppointment ? 'Cita actualizada' : 'Cita creada',

                        text: response.message,

                        timer: 2000,

                        showConfirmButton: false

                    });


                    closeAppointmentModal();


                    // refrescar calendario después

                    if (calendar) {

                        calendar.refetchEvents();

                    }


                })


                .catch(error => {


                    console.log(error);


                    Swal.fire({

                        icon: 'error',

                        title: 'Error',

                        text: error.message ??
                            'No se pudo crear la cita'

                    });


                });


        }


        function closeDetailModal() {
            document
                .getElementById('appointmentDetailModal')
                .classList.add('hidden');
        }


        document.addEventListener('DOMContentLoaded', function() {

            const isDoctor = @json($isDoctor);
            const currentProfessionalId = @json($currentProfessionalId);
            const specialtySelect = document.getElementById('appointmentSpecialty');
            const professionalSelect = document.getElementById('appointmentProfessional');
            const professionalContainer = document.getElementById('professionalContainer');

            // Ocultar al iniciar
            professionalContainer.classList.add('hidden');

            $(specialtySelect).on('change', function() {

                const specialtyId = $(this).val();
                const requestId = ++professionalLookupRequestId;

                if (isDoctor) {
                    $(professionalSelect).val(String(currentProfessionalId)).trigger('change.select2');
                    professionalContainer.classList.add('hidden');
                    if (specialtyId) loadAvailability();
                    return;
                }

                // Limpiar profesionales
                const professionalSelectedBeforeSpecialtyChange = $(professionalSelect).val();
                professionalSelect.innerHTML =
                    '<option value="">Seleccione profesional</option>';
                $(professionalSelect).val('').trigger('change');

                // Ocultar si no hay especialidad
                if (!specialtyId) {
                    professionalContainer.classList.add('hidden');
                    return;
                }

                fetch(`/professionals/by-specialty?specialty_id=${specialtyId}`)
                    .then(response => {
                        if (!response.ok) throw new Error('No se pudieron consultar los profesionales.');
                        return response.json();
                    })
                    .then(data => {
                        if (requestId !== professionalLookupRequestId) return;
                        if (!Array.isArray(data)) throw new Error('La respuesta de profesionales no es válida.');

                        if (data.length === 0) {

                            professionalSelect.innerHTML =
                                '<option value="">No hay profesionales</option>';

                        } else {

                            data.forEach(professional => {
                                professionalSelect.add(new Option(professional.name, professional.id));
                            });

                        }

                        // Mostrar el select
                        const professionalToSelect = editingAppointmentId
                            ? editingProfessionalId
                            : (professionalSelectedBeforeSpecialtyChange || '');
                        $(professionalSelect).val(professionalToSelect).trigger('change.select2');
                        if (professionalToSelect) loadAvailability();
                        professionalContainer.classList.remove('hidden');

                    })
                    .catch(error => {

                        console.error(error);
                        if (requestId !== professionalLookupRequestId) return;
                        professionalSelect.innerHTML = '<option value="">No se pudieron cargar profesionales</option>';
                        $(professionalSelect).val('').trigger('change.select2');
                        professionalContainer.classList.remove('hidden');

                    });

            });

        });

        //mostra estado show modal cita
        function showStatus(status) {

            const element = document.getElementById('detailStatus');

            const statuses = {

                0: {
                    text: 'Agendada',
                    class: 'bg-blue-100 text-blue-700'
                },

                1: {
                    text: 'Confirmada',
                    class: 'bg-green-100 text-green-700'
                },

                2: {
                    text: 'En atención',
                    class: 'bg-yellow-100 text-yellow-700'
                },

                3: {
                    text: 'Atendida',
                    class: 'bg-green-100 text-green-700'
                },

                4: {
                    text: 'No asistió',
                    class: 'bg-red-100 text-red-700'
                },

                5: {
                    text: 'Cancelada',
                    class: 'bg-gray-100 text-gray-700'
                }

            };


            const current = statuses[status] ?? statuses[0];


            element.innerHTML = current.text;

            element.className =
                `px-3 py-1 rounded-full text-sm font-semibold ${current.class}`;

        }
    </script>
@endpush
