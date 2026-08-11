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
                                Profesional
                            </label>

                            <select id="professional" class="w-full rounded-lg border-gray-300">
                                <option value="">Seleccione un profesional</option>

                                @foreach ($professionals as $professional)
                                    <option value="{{ $professional->id }}">
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

                <h2 class="text-lg font-bold">
                    Nueva cita médica
                </h2>

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
                            <option value="{{ $professional->id }}">
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

                    Guardar

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


            </div>


            <div class="border-t px-6 py-4 flex justify-end gap-2">

                <button onclick="" style="background-color: {{ themeColor(600) }}"
                    class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded"
                    id="btnUpdateAppointment">

                    <i class="fa-solid fa-pen-to-square mr-1"></i>

                    Actualizar cita

                </button>

                <button onclick="closeDetailModal()" class="bg-gray-600 text-white px-4 py-2 rounded">

                    Cerrar

                </button>

            </div>


        </div>


    </div>
@endsection

@push('scripts')
    <script>
        let calendar;

        document.addEventListener('DOMContentLoaded', function() {

            const calendarEl = document.getElementById('calendar');
            const professionalSelect = document.getElementById('professional');

            calendar = new FullCalendar.Calendar(calendarEl, {

                locale: 'es',

                firstDay: 1, // Lunes

                initialView: 'timeGridWeek',

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
                            `/appointments/events?professional_id=${professionalId}&search=${encodeURIComponent(search)}&start=${fetchInfo.startStr}&end=${fetchInfo.endStr}`
                        )
                        .then(res => res.json())
                        .then(data => successCallback(data))
                        .catch(error => failureCallback(error));

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

                    if ([0, 1, 2].includes(event.extendedProps.status)) {
                        btnUpdate.classList.remove('hidden');
                    } else {
                        btnUpdate.classList.add('hidden');
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

            professionalSelect.addEventListener('change', function() {

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

        });


        function openCreateAppointmentModal() {

            document
                .getElementById('appointmentModal')
                .classList.remove('hidden');
            document.getElementById('appointmentDate').value = getTodayLocal();
            // limpiar formulario

            document.getElementById('appointmentSpecialty').value = '';

            document.getElementById('appointmentProfessional').innerHTML =
                '<option value="">Seleccione profesional</option>';

            document.getElementById('appointmentPatient').value = '';



            document.getElementById('appointmentTime').innerHTML =
                '<option value="">Seleccione horario</option>';

            document.getElementById('appointmentReason').value = '';

            document.getElementById('appointmentNotes').value = '';

            document.getElementById('professionalContainer').classList.add('hidden');
            document
                .getElementById('availableTimesContainer')
                .classList.add('hidden');

        }

        document.addEventListener('DOMContentLoaded', function() {

            const appointmentProfessional =
                document.getElementById('appointmentProfessional');


            const appointmentDate =
                document.getElementById('appointmentDate');


            appointmentProfessional.addEventListener('change', function() {

                loadAvailability();

            });


            appointmentDate.addEventListener('change', function() {

                loadAvailability();

            });


        });



        function loadAvailability() {

            const professionalId = appointmentProfessional.value;

            const date = appointmentDate.value;


            if (!professionalId || !date) {

                document
                    .getElementById('availableTimesContainer')
                    .classList.add('hidden');

                return;

            }



            fetch(`/appointments/availability?professional_id=${professionalId}&date=${date}`)

                .then(response => response.json())

                .then(data => {


                    const select =
                        document.getElementById('appointmentTime');


                    select.innerHTML =
                        `
        <option value="">
            Seleccione horario
        </option>
        `;



                    if (data.length === 0) {


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



                    data.forEach(slot => {

                        const selectedDate = appointmentDate.value;

                        const now = new Date();

                        const slotDateTime = new Date(
                            `${selectedDate}T${slot.value}:00`
                        );


                        // Si la fecha es hoy y la hora ya pasó, no mostrar
                        if (slotDateTime <= now) {

                            return;

                        }


                        select.innerHTML +=
                            `
                        <option value="${slot.value}">
                            ${slot.label}
                        </option>
                    `;

                    });



                    document.getElementById('availableTimesContainer').classList.remove('hidden');


                })

                .catch(error => {

                    console.error(error);

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

            fetch('/appointments', {

                    method: 'POST',

                    headers: {

                        'Content-Type': 'application/json',

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

                        title: 'Cita creada',

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

            const specialtySelect = document.getElementById('appointmentSpecialty');
            const professionalSelect = document.getElementById('appointmentProfessional');
            const professionalContainer = document.getElementById('professionalContainer');

            // Ocultar al iniciar
            professionalContainer.classList.add('hidden');

            specialtySelect.addEventListener('change', function() {

                const specialtyId = this.value;

                // Limpiar profesionales
                professionalSelect.innerHTML =
                    '<option value="">Seleccione profesional</option>';

                // Ocultar si no hay especialidad
                if (!specialtyId) {
                    professionalContainer.classList.add('hidden');
                    return;
                }

                fetch(`/professionals/by-specialty?specialty_id=${specialtyId}`)
                    .then(response => response.json())
                    .then(data => {

                        if (data.length === 0) {

                            professionalSelect.innerHTML =
                                '<option value="">No hay profesionales</option>';

                        } else {

                            data.forEach(professional => {

                                professionalSelect.innerHTML += `
                            <option value="${professional.id}">
                                ${professional.name}
                            </option>
                        `;

                            });

                        }

                        // Mostrar el select
                        professionalContainer.classList.remove('hidden');

                    })
                    .catch(error => {

                        console.error(error);

                        professionalContainer.classList.add('hidden');

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
                    class: 'bg-indigo-100 text-indigo-700'
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
