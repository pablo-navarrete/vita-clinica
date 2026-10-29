@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Gestión de Citas Médicas') }}
    </h2>
@endsection

@push('styles')
    <style>
        #calendarContainer {
            --calendar-accent: {{ themeColor(600) }};
            --calendar-accent-soft: {{ themeColor(50) }};
            --calendar-accent-light: {{ themeColor(100) }};
        }
        #calendar {
            padding: 1.25rem;
            border: 1px solid #e5e7eb;
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 8px 28px rgba(15, 23, 42, .06);
        }
        #calendar .fc-toolbar {
            gap: 1rem;
            margin-bottom: 1.25rem !important;
        }
        #calendar .fc-toolbar-title {
            color: #111827;
            font-size: 1.35rem;
            font-weight: 700;
            letter-spacing: -.025em;
        }
        #calendar .fc-button-primary {
            border: 1px solid #e5e7eb !important;
            border-radius: .6rem !important;
            background: #fff !important;
            color: #374151 !important;
            font-weight: 600;
            box-shadow: none !important;
            transition: color .18s ease, background-color .18s ease, border-color .18s ease, transform .18s ease;
        }
        #calendar .fc-button-primary:hover {
            border-color: var(--calendar-accent) !important;
            background: var(--calendar-accent-soft) !important;
            color: var(--calendar-accent) !important;
            transform: translateY(-1px);
        }
        #calendar .fc-button-primary:focus,
        #calendar .fc-button-primary:active,
        #calendar .fc-button-primary:not(:disabled).fc-button-active {
            border-color: var(--calendar-accent) !important;
            background: var(--calendar-accent) !important;
            color: #fff !important;
        }
        #calendar .fc-today-button:not(:disabled) {
            border-color: var(--calendar-accent-light) !important;
            background: var(--calendar-accent-soft) !important;
            color: var(--calendar-accent) !important;
        }
        #calendar .fc-scrollgrid {
            overflow: hidden;
            border: 1px solid #e5e7eb;
            border-radius: .75rem;
        }
        #calendar .fc-col-header-cell {
            background: #f8fafc;
        }
        #calendar .fc-col-header-cell-cushion {
            padding: .75rem .35rem;
            color: #475569;
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: .035em;
        }
        #calendar .fc-timegrid-slot-label-cushion,
        #calendar .fc-daygrid-day-number {
            color: #64748b;
            font-size: .78rem;
            text-decoration: none;
        }
        #calendar .fc-timegrid-slot { height: 2.6rem; }
        #calendar .fc-day-today { background: var(--calendar-accent-soft) !important; }
        #calendar .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.8rem;
            min-height: 1.8rem;
            margin: .25rem;
            border-radius: 9999px;
            background: var(--calendar-accent);
            color: #fff;
            font-weight: 700;
        }
        #calendar .fc-event {
            overflow: hidden;
            border: 0;
            border-left: 3px solid rgba(255, 255, 255, .75);
            border-radius: .45rem;
            padding: 2px 4px;
            box-shadow: 0 2px 6px rgba(15, 23, 42, .12);
            cursor: pointer;
        }
        #calendar .fc-event:hover { filter: brightness(.94); }
        #calendar .fc-event-title,
        #calendar .fc-event-time { font-weight: 600; }
        #calendar .fc-now-indicator-line { border-color: #ef4444; border-width: 2px; }
        #calendar .fc-now-indicator-arrow { border-color: #ef4444; }
        @media (max-width: 640px) {
            #calendar { padding: .65rem; border-radius: .75rem; }
            #calendar .fc-toolbar { flex-direction: column; align-items: stretch; gap: .6rem; }
            #calendar .fc-toolbar-chunk { display: flex; justify-content: center; }
            #calendar .fc-toolbar-title { font-size: 1.05rem; }
            #calendar .fc-button { padding: .35rem .5rem; font-size: .78rem; }
            #calendar .fc-col-header-cell-cushion { padding: .55rem .15rem; font-size: .68rem; }
            #calendar .fc-timegrid-slot { height: 2.2rem; }
        }
    </style>
@endpush

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white shadow-sm rounded-lg">

                <!-- Encabezado -->
                <div class="flex flex-col md:flex-row md:items-end md:justify-between p-6 border-b border-gray-200">

                    <div class="flex flex-wrap gap-4 items-end">

                        @if ($canFilterAppointmentsAcrossBranches)
                            <div class="w-64">
                                <label for="calendarBranch" class="block text-sm font-medium text-gray-700 mb-1">Sede de agenda</label>
                                <select id="calendarBranch" class="w-full rounded-lg border-gray-300">
                                    @foreach ($calendarBranches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $branchId)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

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

                        @if ($canCreateAppointments)
                        <button onclick="openCreateAppointmentModal()" style="background-color: {{ themeColor(600) }}"
                            class="hover:brightness-90 transition text-white font-bold py-2 px-4 rounded">

                            <i class="fa-solid fa-plus-circle mr-1"></i>

                            Nueva cita

                        </button>
                        @endif

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
                <div id="calendarContainer" class="hidden p-3 sm:p-5 lg:p-6">

                    <div id="availabilityCalendarLegend" class="mb-3 flex items-center gap-2 text-sm text-gray-600">
                        <span class="h-3 w-3 rounded-sm border border-green-300 bg-green-200"></span>
                        <span>Horario disponible</span>
                        <span id="calendarWorkingHoursLabel" class="ml-3 text-xs text-gray-500"></span>
                    </div>

                    <div id="calendar" class="min-h-[700px]"></div>

                </div>

            </div>

        </div>
    </div>


    <!-- MODAL CITA -->
    <div id="appointmentModal"
        class="fixed inset-0 hidden flex items-center justify-center bg-black/50 z-50 overflow-y-auto p-4">

        <div class="bg-white rounded-xl shadow-2xl w-full max-w-5xl"
            style="display: flex; flex-direction: column; height: 92vh; height: 92dvh; max-height: 92vh; max-height: 92dvh; overflow: hidden;">

            <!-- HEADER -->
            <div class="shrink-0 px-6 py-4 border-b rounded-t-lg" style="flex: 0 0 auto; background-color: {{ themeColor(100) }}">

                <h2 id="appointmentModalTitle" class="text-lg font-bold">
                    Nueva cita médica
                </h2>
                @if ($isDoctor)
                    <p class="mt-1 text-sm text-gray-600">Profesional: {{ $currentProfessionalName }}</p>
                @endif

            </div>


            <div class="grid min-h-0 grid-cols-1 content-start items-start gap-x-4 gap-y-2 p-5 md:grid-cols-2"
                style="min-height: 0; height: 0; flex: 1 1 0%; overflow-y: auto; overflow-x: hidden; overscroll-behavior-y: contain; -webkit-overflow-scrolling: touch;">

                @if ($canChooseAppointmentBranch)
                    <div id="appointmentBranchContainer" class="md:col-span-2">
                        <label for="appointmentClinicBranch" class="block text-sm font-medium mb-1">Sede de atención</label>
                        <select id="appointmentClinicBranch" class="w-full rounded-lg border-gray-300">
                            @foreach ($appointmentBranches as $branch)
                                <option value="{{ $branch->id }}" @selected((int) $branch->id === (int) $branchId)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-gray-500">Esta cita y su disponibilidad quedarán asociadas a la sede seleccionada.</p>
                    </div>
                @endif

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


                    <input type="date" id="appointmentDate" min="{{ now(config('app.timezone', 'America/Santiago'))->format('Y-m-d') }}" class="w-full rounded-lg border-gray-300" name="date">


                </div>



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

                    <section id="appointmentWeeklyPanel" data-weekly-url="{{ route('appointments.availability.week', ['customer' => request()->route('customer')]) }}" data-accent="{{ themeColor(600) }}" data-accent-soft="{{ themeColor(50) }}" class="rounded-xl border border-gray-200 bg-gray-50 p-4 md:col-span-2">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800">Disponibilidad semanal</h3>
                            <p id="appointmentWeeklyStatus" class="mt-1 text-xs text-gray-500">Selecciona profesional y fecha para consultar.</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <button type="button" id="appointmentWeekPrev" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100" aria-label="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>
                            <button type="button" id="appointmentWeekNext" class="rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-sm text-gray-700 hover:bg-gray-100" aria-label="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                        </div>
                    </div>
                    <div id="appointmentWeeklyDays" aria-live="polite"></div>
                </section>



                <div>

                    <label class="block text-sm font-medium mb-1">
                        Modalidad de atención
                    </label>

                    <select id="appointmentModality" class="w-full rounded-lg border-gray-300" name="modality" required>
                        <option value="in_person">Presencial</option>
                        <option value="telemedicine">Remota (telemedicina)</option>
                    </select>

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
<label class="mt-3 flex items-start gap-2 text-sm text-gray-600"><input id="appointmentWhatsappConsent" type="checkbox" value="1" class="mt-1 rounded border-gray-300"><span>El paciente autorizo recibir avisos de esta cita por WhatsApp.</span></label>

                </div>


            </div>



            <!-- FOOTER -->
            <div class="shrink-0 border-t px-6 py-4 flex justify-end gap-2" style="flex: 0 0 auto;">


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


            <div class="p-6 space-y-4 overflow-y-auto">
                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">Datos del paciente</h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Paciente</label>
                            <span id="detailPatientName"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">RUT</label>
                            <span id="detailPatientRut"></span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">Datos de la cita</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Fecha</label>
                            <span id="detailDate"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Hora</label>
                            <span id="detailTime"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Estado</label>
                            <span id="detailStatus" class="px-3 py-1 rounded-full text-sm font-semibold"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Modalidad agendada</label>
                            <span id="detailModality"></span>
                        </div>
                    </div>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-4">
                    <h3 class="mb-3 text-sm font-semibold text-gray-800">Detalles</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Motivo</label>
                            <span id="detailReason"></span>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1 text-gray-500">Notas</label>
                            <p id="detailNotes"></p>
                        </div>
                    </div>
                </section>

                <p id="detailAttentionBlockReason" class="hidden rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800"></p>
            </div>


            <div class="border-t px-6 py-4 flex flex-wrap items-center justify-end gap-2">

                <button id="btnTelemedicine" type="button" data-create-url="{{ route('customers.telemedicine.create', ['customer' => request()->route('customer'), 'appointmentId' => '__APPOINTMENT__']) }}" title="{{ $telemedicineConfigured ? 'Iniciar videollamada vinculada a esta cita' : 'Configura LiveKit en .env para habilitar teleconsultas' }}" class="hidden mr-auto rounded px-4 py-2 font-semibold text-white hover:brightness-90 disabled:cursor-not-allowed disabled:opacity-50" style="background-color: {{ themeColor(600) }}" {{ $telemedicineConfigured ? '' : 'disabled' }}>
                    <i class="fa-solid fa-video mr-1"></i> Iniciar teleconsulta
                </button>

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

        #calendarBranch + .select2-container {
            width: 100% !important;
        }

        #calendarBranch + .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            background-color: #fff;
            transition: border-color .15s ease, box-shadow .15s ease;
        }

        #calendarBranch + .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
            line-height: 40px;
        }

        #calendarBranch + .select2-container .select2-selection__arrow {
            height: 40px;
        }

        #calendarBranch + .select2-container--focus .select2-selection--single,
        #calendarBranch + .select2-container--open .select2-selection--single {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgb(99 102 241 / 15%);
            outline: none;
        }

        #appointmentWeeklyDays {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            gap: .5rem;
        }

        .appointment-week-day {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: .65rem;
            padding: .45rem;
            background: #f8fafc;
        }

        .appointment-week-day-title {
            display: block;
            margin-bottom: .45rem;
            text-align: center;
            font-size: .72rem;
            font-weight: 700;
            color: #475569;
            text-transform: capitalize;
        }

        .appointment-week-day-slots {
            display: flex;
            flex-direction: column;
            gap: .3rem;
        }

        .appointment-week-slot {
            width: 100%;
            min-height: 1.8rem;
            padding: .3rem .15rem;
            border: 1px solid #cbd5e1;
            border-radius: .4rem;
            background: #fff;
            font-size: .72rem;
            line-height: 1.15;
        }

        @media (max-width: 767.98px) {
            #appointmentWeeklyDays { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 420px) {
            #appointmentWeeklyDays { grid-template-columns: 1fr; }
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script>
        let calendar;
        let editingAppointmentId = null;
        let editingAppointmentBranchId = null;
        let editingSpecialtyId = null;
        let editingProfessionalId = null;
        let editingOriginalDate = null;
        let editingTime = null;
        let availabilityRequestId = 0;
        let professionalLookupRequestId = 0;
        let appointmentSpecialtiesRequestId = 0;
        let appointmentWeeklyRequestId = 0;
        let appointmentWeekAnchor = '';

        function localDateValue(date) {
            return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
        }

        function parseLocalDateValue(value) {
            const [year, month, day] = value.split('-').map(Number);
            return new Date(year, month - 1, day, 12);
        }

        function datesForWeek(anchor) {
            const monday = parseLocalDateValue(anchor);
            monday.setDate(monday.getDate() - ((monday.getDay() + 6) % 7));
            return Array.from({ length: 7 }, (_, index) => {
                const day = new Date(monday);
                day.setDate(monday.getDate() + index);
                return localDateValue(day);
            });
        }

        function appointmentClinicBranchId() {
            if (editingAppointmentId && editingAppointmentBranchId) return String(editingAppointmentBranchId);
            const branchSelect = document.getElementById('appointmentClinicBranch');
            return branchSelect ? branchSelect.value : String(@json($branchId ?? ''));
        }

        async function loadAppointmentWeeklyAvailability() {
            const panel = document.getElementById('appointmentWeeklyPanel');
            const daysContainer = document.getElementById('appointmentWeeklyDays');
            const status = document.getElementById('appointmentWeeklyStatus');
            const previousButton = document.getElementById('appointmentWeekPrev');
            const nextButton = document.getElementById('appointmentWeekNext');
            const dateInput = document.getElementById('appointmentDate');
            const timeSelect = document.getElementById('appointmentTime');
            const professionalId = document.getElementById('appointmentProfessional').value;
            const anchor = appointmentWeekAnchor || dateInput.value || dateInput.min || getTodayLocal();
            const dates = datesForWeek(anchor);
            const requestId = ++appointmentWeeklyRequestId;
            const today = dateInput.min || getTodayLocal();

            daysContainer.innerHTML = '';
            if (!professionalId) {
                status.textContent = 'Selecciona un profesional para consultar sus horarios.';
                previousButton.disabled = true;
                nextButton.disabled = true;
                return;
            }

            status.textContent = 'Cargando horarios disponibles…';
            const params = new URLSearchParams({ professional_id: professionalId, date: anchor });
            if (appointmentClinicBranchId()) params.set('clinic_branch_id', appointmentClinicBranchId());
            if (editingAppointmentId) params.set('appointment_id', editingAppointmentId);
            try {
                const response = await fetch(`${panel.dataset.weeklyUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('No se pudieron consultar los horarios.');
                const result = await response.json();
                if (requestId !== appointmentWeeklyRequestId) return;
                const availability = result.availability || {};
                const selectedDate = dateInput.value;
                const selectedTime = timeSelect.value;

                dates.forEach(value => {
                    const date = parseLocalDateValue(value);
                    const card = document.createElement('div');
                    card.dataset.date = value;
                    card.className = 'appointment-week-day';
                    if (value === selectedDate) {
                        card.style.borderColor = panel.dataset.accent;
                        card.style.backgroundColor = panel.dataset.accentSoft;
                    }
                    const title = document.createElement('p');
                    title.className = 'appointment-week-day-title';
                    title.textContent = date.toLocaleDateString('es-CL', { weekday: 'short', day: '2-digit', month: 'short' }).replace('.', '');
                    card.appendChild(title);
                    const slotsContainer = document.createElement('div');
                    slotsContainer.className = 'appointment-week-day-slots';
                    const slots = availability[value] || [];
                    if (value < today || !slots.length) {
                        const empty = document.createElement('span');
                        empty.className = 'block py-1 text-center text-xs text-gray-400';
                        empty.textContent = value < today ? '—' : 'Sin horas';
                        slotsContainer.appendChild(empty);
                    } else {
                        slots.forEach(slot => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.dataset.time = slot.value;
                            const isSelected = value === selectedDate && slot.value === selectedTime;
                            button.className = `appointment-week-slot transition ${isSelected ? 'text-white' : 'text-gray-700 hover:bg-gray-50'}`;
                            if (isSelected) {
                                button.style.borderColor = panel.dataset.accent;
                                button.style.backgroundColor = panel.dataset.accent;
                            }
                            button.textContent = slot.value;
                            button.title = slot.label;
                            button.addEventListener('click', () => {
                                dateInput._flatpickr ? dateInput._flatpickr.setDate(value, false) : dateInput.value = value;
                                timeSelect.innerHTML = '<option value="">Seleccione horario</option>';
                                slots.forEach(item => timeSelect.add(new Option(item.label, item.value)));
                                timeSelect.value = slot.value;
                                appointmentWeekAnchor = value;
                                timeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                            });
                            slotsContainer.appendChild(button);
                        });
                    }
                    card.appendChild(slotsContainer);
                    daysContainer.appendChild(card);
                });

                const total = Object.values(availability).reduce((sum, slots) => sum + slots.length, 0);
                status.textContent = total ? `${total} horario(s) disponible(s). Selecciona uno para completar la cita.` : 'No hay horarios disponibles en este período. Revisa otra semana.';
                const previousEnd = parseLocalDateValue(dates[0]);
                previousEnd.setDate(previousEnd.getDate() - 1);
                previousButton.disabled = localDateValue(previousEnd) < today;
                nextButton.disabled = false;
            } catch (error) {
                if (requestId !== appointmentWeeklyRequestId) return;
                status.textContent = 'No se pudo consultar la disponibilidad semanal.';
            }
        }

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

            $('#appointmentSpecialty, #appointmentProfessional, #appointmentClinicBranch').select2({
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

            @if ($canFilterAppointmentsAcrossBranches)
                $('#calendarBranch').select2({
                    width: '100%',
                    minimumResultsForSearch: 0,
                    language: { noResults: () => 'No se encontraron sedes' }
                });
            @endif

            const calendarEl = document.getElementById('calendar');
            const professionalSelect = document.getElementById('professional');
            let calendarWorkingHours = @json($calendarWorkingHours);
            const calendarBranchSelect = document.getElementById('calendarBranch');
            const selectedCalendarBranchId = () => calendarBranchSelect?.value || String(@json($branchId ?? ''));
            const query = new URLSearchParams(window.location.search);
            const targetProfessionalId = query.get('professional_id');
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

                slotLabelFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },

                eventTimeFormat: {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                },

                expandRows: true,

                weekends: true,

                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },

                datesSet: function(info) {
                    const legend = document.getElementById('availabilityCalendarLegend');
                    legend.classList.toggle('hidden', !['timeGridWeek', 'timeGridDay'].includes(info.view.type));
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
                    const viewType = fetchInfo.view?.type || calendar?.view?.type || '';



                    if (!professionalId) {
                        successCallback([]);
                        return;
                    }


                    fetch(
                        `/appointments/events?professional_id=${professionalId}&branch_id=${encodeURIComponent(selectedCalendarBranchId())}&search=${encodeURIComponent(search)}&start=${encodeURIComponent(fetchInfo.startStr)}&end=${encodeURIComponent(fetchInfo.endStr)}&view_type=${encodeURIComponent(viewType)}`,
                        { cache: 'no-store' }
                        )
                        .then(res => res.json())
                        .then(data => successCallback(data))
                        .catch(error => failureCallback(error));

                },

                eventDidMount: function(info) {
                    if (info.event.extendedProps.isAvailableSlot) {
                        info.el.title = 'Haz doble clic para crear una cita en este horario';
                        info.el.dataset.availableCalendarSlot = 'true';
                        info.el.dataset.slotDate = formatLocalDate(info.event.start);
                        info.el.dataset.slotTime = formatLocalTime(info.event.start);
                    }
                    if (info.event.extendedProps.isBranchConflict) {
                        info.el.title = 'Este horario está ocupado por una cita del profesional en otra sede.';
                    }

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
                    const btnTelemedicine = document.getElementById('btnTelemedicine');
                    const btnViewAttention = document.getElementById('btnViewAttention');
                    const attentionBlockReason = document.getElementById('detailAttentionBlockReason');

                    btnTelemedicine.dataset.appointmentId = event.extendedProps.appointmentId;
                    btnTelemedicine.classList.toggle('hidden', ![0, 1, 2].includes(Number(event.extendedProps.status)));

                    if (event.extendedProps.canStartAttention) {
                        btnStartAttention.href = `/patients/${event.extendedProps.patientId}/medical-record?start_encounter=${event.extendedProps.appointmentId}`;
                        btnStartAttention.classList.remove('hidden');
                        btnViewAttention.classList.add('hidden');
                        attentionBlockReason.classList.add('hidden');
                    } else {
                        btnStartAttention.classList.add('hidden');
                        if (event.extendedProps.encounterId) {
                            btnViewAttention.href = `/patients/${event.extendedProps.patientId}/medical-record${Number(event.extendedProps.encounterStatus) === 1 ? '?encounter_id=' + encodeURIComponent(event.extendedProps.encounterId) + '#current-encounter-section' : '#encounter-history'}`;
                            btnViewAttention.classList.remove('hidden');
                            attentionBlockReason.textContent = event.extendedProps.attentionBlockReason || 'Esta cita ya tiene una atención asociada.';
                            attentionBlockReason.classList.remove('hidden');
                            attentionBlockReason.textContent = 'Esta cita ya tiene una atenci\u00f3n iniciada. No se puede modificar.';
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
                        btnUpdate.dataset.modality = event.extendedProps.modality ?? 'in_person';
                    btnUpdate.dataset.branchId = event.extendedProps.branchId;
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

                    document.getElementById('detailModality').textContent =
                        event.extendedProps.modality === 'telemedicine'
                            ? 'Remota (telemedicina)'
                            : `Presencial${event.extendedProps.branchName ? ' · ' + event.extendedProps.branchName : ''}`;



                    document.getElementById('detailNotes').innerHTML =
                        event.extendedProps.notes ?? '';



                    document
                        .getElementById('appointmentDetailModal')
                        .classList.remove('hidden');

                }

            });

            document.addEventListener('click', event => {
                if (!calendarEl.contains(event.target)) return;
                if (!['timeGridWeek', 'timeGridDay'].includes(calendar.view.type)) return;

                const professionalId = professionalSelect.value;
                if (!professionalId) return;

                const availableBlock = Array.from(calendarEl.querySelectorAll('[data-available-calendar-slot="true"]'))
                    .find(block => {
                        const bounds = block.getBoundingClientRect();
                        return event.clientX >= bounds.left && event.clientX <= bounds.right
                            && event.clientY >= bounds.top && event.clientY <= bounds.bottom;
                    });
                if (!availableBlock) return;

                const { slotDate: date, slotTime: time } = availableBlock.dataset;
                openAvailableAppointmentSlot(professionalId, date, time);
            }, true);

            calendar.render();

            if (calendarBranchSelect && !isDoctor) {
                $(calendarBranchSelect).on('change', async function() {
                    professionalSelect.innerHTML = '<option value="">Cargando profesionales...</option>';
                    $(professionalSelect).val('').trigger('change');
                    try {
                        const response = await fetch(`/appointments/branch-professionals?branch_id=${encodeURIComponent(this.value)}`, { headers: { 'Accept': 'application/json' }, cache: 'no-store' });
                        if (!response.ok) throw new Error('No se pudieron cargar los profesionales de la sede.');
                        const data = await response.json();
                        professionalSelect.innerHTML = '<option value="">Seleccione un profesional</option>';
                        data.professionals.forEach(professional => professionalSelect.add(new Option(professional.name, professional.id)));
                        calendarWorkingHours = data.working_hours || {};
                        $(professionalSelect).val('').trigger('change.select2');
                        calendar.refetchEvents();
                    } catch (error) {
                        professionalSelect.innerHTML = '<option value="">No se pudieron cargar profesionales</option>';
                        $(professionalSelect).val('').trigger('change.select2');
                        Swal.fire({ icon: 'error', text: error.message || 'No se pudo cargar la sede seleccionada.' });
                    }
                });
            }

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

                    const hours = calendarWorkingHours[this.value];
                    if (hours?.start_time && hours?.end_time) {
                        const startTime = String(hours.start_time).slice(0, 8);
                        const endTime = String(hours.end_time).slice(0, 8);
                        calendar.setOption('slotMinTime', startTime);
                        calendar.setOption('slotMaxTime', endTime);
                        document.getElementById('calendarWorkingHoursLabel').textContent =
                            `Horario del profesional: ${startTime.slice(0, 5)} a ${endTime.slice(0, 5)}`;
                    } else {
                        calendar.setOption('slotMinTime', '00:00:00');
                        calendar.setOption('slotMaxTime', '24:00:00');
                        document.getElementById('calendarWorkingHoursLabel').textContent =
                            'Este profesional no tiene horario configurado en esta sede.';
                    }

                    calendarPlaceholder.classList.add('hidden');
                    calendarContainer.classList.remove('hidden');
                    searchContainer.classList.remove('hidden');

                    calendar.refetchEvents();

                } else {

                    document.getElementById('calendarWorkingHoursLabel').textContent = '';

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
            } else if (targetProfessionalId && professionalSelect.querySelector(`option[value="${CSS.escape(targetProfessionalId)}"]`)) {
                $(professionalSelect).val(targetProfessionalId).trigger('change');
            }

        });


        function openCreateAppointmentModal() {

            editingAppointmentId = null;
            editingAppointmentBranchId = null;
            editingSpecialtyId = null;
            editingProfessionalId = null;
            editingOriginalDate = null;
            editingTime = null;
            document.getElementById('appointmentModalTitle').textContent = 'Nueva cita médica';
            document.getElementById('appointmentSaveLabel').textContent = 'Guardar';

            @if ($canChooseAppointmentBranch)
                const branchSelect = document.getElementById('appointmentClinicBranch');
                const previousBranchId = branchSelect.value;
                branchSelect.disabled = false;
                branchSelect.value = String(@json($branchId));
                if (previousBranchId !== branchSelect.value) $(branchSelect).trigger('change');
                document.getElementById('appointmentBranchContainer').classList.remove('hidden');
            @endif

            document
                .getElementById('appointmentModal')
                .classList.remove('hidden');
            const appointmentDateInput = document.getElementById('appointmentDate');
            if (appointmentDateInput._flatpickr) appointmentDateInput._flatpickr.setDate(getTodayLocal(), false);
            else appointmentDateInput.value = getTodayLocal();
            appointmentWeekAnchor = appointmentDateInput.value;
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
            document.getElementById('appointmentModality').value = 'in_person';

            document.getElementById('appointmentNotes').value = '';
            document.getElementById('appointmentWhatsappConsent').checked = false;

            @if ($isDoctor)
                loadAvailability();
            @else
                loadAppointmentWeeklyAvailability();
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


            appointmentDate.addEventListener('change', function () {
                appointmentWeekAnchor = appointmentDate.value || appointmentDate.min || getTodayLocal();
                loadAvailability();
            });

            document.getElementById('appointmentTime').addEventListener('change', function () {
                document.querySelectorAll('#appointmentWeeklyDays [data-date]').forEach(card => {
                    const selectedDay = card.dataset.date === appointmentDate.value;
                    card.style.borderColor = selectedDay ? document.getElementById('appointmentWeeklyPanel').dataset.accent : '';
                    card.style.backgroundColor = selectedDay ? document.getElementById('appointmentWeeklyPanel').dataset.accentSoft : '';
                    card.querySelectorAll('[data-time]').forEach(button => {
                        const selected = selectedDay && button.dataset.time === this.value;
                        button.classList.toggle('text-white', selected);
                        button.style.backgroundColor = selected ? document.getElementById('appointmentWeeklyPanel').dataset.accent : '';
                        button.style.borderColor = selected ? document.getElementById('appointmentWeeklyPanel').dataset.accent : '';
                    });
                });
            });

            document.getElementById('appointmentWeekPrev').addEventListener('click', () => {
                const previous = parseLocalDateValue(appointmentWeekAnchor || appointmentDate.value || getTodayLocal());
                previous.setDate(previous.getDate() - 7);
                const nextAnchor = localDateValue(previous);
                if (datesForWeek(nextAnchor).at(-1) < (appointmentDate.min || getTodayLocal())) return;
                appointmentWeekAnchor = nextAnchor;
                loadAppointmentWeeklyAvailability();
            });
            document.getElementById('appointmentWeekNext').addEventListener('click', () => {
                const next = parseLocalDateValue(appointmentWeekAnchor || appointmentDate.value || getTodayLocal());
                next.setDate(next.getDate() + 7);
                appointmentWeekAnchor = localDateValue(next);
                loadAppointmentWeeklyAvailability();
            });


        });



        function loadAvailability() {

            const professionalId = document.getElementById('appointmentProfessional').value;

            const date = document.getElementById('appointmentDate').value;
            const requestId = ++availabilityRequestId;
            const preservingOriginalSlot = Boolean(editingAppointmentId)
                && date === editingOriginalDate
                && String(professionalId) === String(editingProfessionalId);
            const feedback = document.getElementById('availabilityFeedback');
            appointmentWeekAnchor = appointmentWeekAnchor || date || document.getElementById('appointmentDate').min || getTodayLocal();
            loadAppointmentWeeklyAvailability();
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
            if (appointmentClinicBranchId()) availabilityUrl.searchParams.set('clinic_branch_id', appointmentClinicBranchId());
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

        async function openAvailableAppointmentSlot(professionalId, date, time) {
            const availabilityUrl = new URL('/appointments/availability', window.location.origin);
            availabilityUrl.searchParams.set('professional_id', professionalId);
            availabilityUrl.searchParams.set('date', date);

            try {
                const response = await fetch(availabilityUrl, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('No se pudo validar el horario.');
                const availableSlots = await response.json();
                if (!availableSlots.some(slot => slot.value === time)) {
                    Swal.fire({ icon: 'warning', title: 'Horario no disponible', text: 'Selecciona uno de los bloques verdes disponibles.' });
                    return;
                }

                openCreateAppointmentModal();
                const dateInput = document.getElementById('appointmentDate');
                dateInput._flatpickr ? dateInput._flatpickr.setDate(date, false) : dateInput.value = date;
                appointmentWeekAnchor = date;

                @if ($isDoctor)
                    $('#appointmentProfessional').val(String(@json($currentProfessionalId))).trigger('change.select2');
                    loadAvailability();
                    selectAvailableAppointmentTime(date, time);
                @else
                    const professionalSpecialties = @json($professionals->mapWithKeys(fn ($professional) => [$professional->id => $professional->specialties->pluck('id')->values()]));
                    const specialtyId = professionalSpecialties[professionalId]?.[0];
                    if (!specialtyId) {
                        Swal.fire({ icon: 'info', title: 'Especialidad requerida', text: 'Este profesional no tiene una especialidad disponible para agendar.' });
                        return;
                    }

                    $('#appointmentSpecialty').val(String(specialtyId)).trigger('change');
                    let attempts = 0;
                    const waitForProfessional = setInterval(() => {
                        const optionExists = Array.from(document.getElementById('appointmentProfessional').options)
                            .some(option => option.value === String(professionalId));
                        if (optionExists) {
                            clearInterval(waitForProfessional);
                            $('#appointmentProfessional').val(String(professionalId)).trigger('change.select2');
                            loadAvailability();
                            selectAvailableAppointmentTime(date, time);
                        } else if (++attempts >= 50) {
                            clearInterval(waitForProfessional);
                            Swal.fire({ icon: 'error', title: 'No se pudo cargar el profesional', text: 'Vuelve a seleccionarlo en el formulario.' });
                        }
                    }, 100);
                @endif
            } catch (error) {
                Swal.fire({ icon: 'error', title: 'Error', text: error.message || 'No se pudo validar el horario.' });
            }
        }

        function selectAvailableAppointmentTime(date, time, attempt = 0) {
            const dateInput = document.getElementById('appointmentDate');
            const timeSelect = document.getElementById('appointmentTime');
            if (dateInput.value !== date) {
                dateInput._flatpickr ? dateInput._flatpickr.setDate(date, false) : dateInput.value = date;
                appointmentWeekAnchor = date;
                loadAvailability();
            }

            const availableOption = Array.from(timeSelect.options).find(option => option.value === time);
            if (availableOption) {
                timeSelect.value = time;
                timeSelect.dispatchEvent(new Event('change', { bubbles: true }));
                $('#appointmentPatient').select2('open');
                return;
            }

            if (attempt < 50) {
                setTimeout(() => selectAvailableAppointmentTime(date, time, attempt + 1), 100);
                return;
            }

            Swal.fire({
                icon: 'warning',
                title: 'Horario no disponible',
                text: 'Ese horario ya no está disponible. Selecciona otro para continuar.'
            });
        }

        function openEditAppointmentModal() {
            const button = document.getElementById('btnUpdateAppointment');
            editingAppointmentId = button.dataset.appointmentId;
            editingAppointmentBranchId = button.dataset.branchId;
            editingProfessionalId = button.dataset.professionalId;
            editingSpecialtyId = button.dataset.specialtyId;
            editingOriginalDate = button.dataset.date;
            editingTime = button.dataset.time;

            document.getElementById('appointmentDetailModal').classList.add('hidden');
            document.getElementById('appointmentModalTitle').textContent = 'Actualizar cita médica';
            document.getElementById('appointmentSaveLabel').textContent = 'Actualizar cita';
            @if ($canChooseAppointmentBranch)
                const branchSelect = document.getElementById('appointmentClinicBranch');
                branchSelect.disabled = true;
                branchSelect.value = String(editingAppointmentBranchId || @json($branchId));
                document.getElementById('appointmentBranchContainer').classList.add('hidden');
                $(branchSelect).trigger('change');
            @endif
            document.getElementById('appointmentModal').classList.remove('hidden');
            const appointmentDateInput = document.getElementById('appointmentDate');
            if (appointmentDateInput._flatpickr) appointmentDateInput._flatpickr.setDate(button.dataset.date, false);
            else appointmentDateInput.value = button.dataset.date;
            document.getElementById('appointmentTime').innerHTML = `<option value="${editingTime}">${editingTime} - hora agendada</option>`;
            document.getElementById('appointmentTime').value = editingTime;
            document.getElementById('availableTimesContainer').classList.remove('hidden');
            document.getElementById('appointmentReason').value = button.dataset.reason;
            document.getElementById('appointmentModality').value = button.dataset.modality || 'in_person';
            document.getElementById('appointmentNotes').value = button.dataset.notes;
            document.getElementById('appointmentWhatsappConsent').checked = false;
            $('#appointmentPatient').val(button.dataset.patientId).trigger('change');
            $('#appointmentSpecialty').val(button.dataset.specialtyId).trigger('change');
            @if ($isDoctor)
                $('#appointmentProfessional').val(String(@json($currentProfessionalId))).trigger('change.select2');
                loadAvailability();
            @endif
        }

        document.getElementById('btnTelemedicine').addEventListener('click', async function() {
            const appointmentId = this.dataset.appointmentId;
            if (!appointmentId) return;

            const roomWindow = window.open('about:blank', '_blank');
            const url = this.dataset.createUrl.replace('__APPOINTMENT__', encodeURIComponent(appointmentId));
            this.disabled = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
                    },
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'No se pudo preparar la teleconsulta.');

                if (roomWindow) roomWindow.location = result.room_url;
                else {
                    const roomUrl = new URL(result.room_url, location.href);
                    roomUrl.searchParams.set('embedded', '1');
                    window.VitaNavigation.openRoom({ dataset: {
                        vitaRoomUrl: roomUrl.href, vitaSessionId: String(result.session_id),
                        vitaPatient: result.patient_name, vitaRecordUrl: location.href,
                    } });
                }


                try {
                    await navigator.clipboard.writeText(result.patient_url);
                    const invitationTitle = result.delivery === 'whatsapp'
                        ? (result.whatsapp_sent ? 'Invitacion enviada por WhatsApp' : 'WhatsApp no pudo enviar el aviso; revisa su configuracion y el consentimiento')
                        : result.delivery === 'both'
                            ? (result.email_sent && result.whatsapp_sent ? 'Correo y WhatsApp enviados' : 'Uno o mas canales no pudieron enviar el aviso')
                            : result.email_sent
                                ? 'Sala abierta; enlace enviado al correo del paciente'
                                : result.email_missing
                                    ? 'Sala abierta; enlace copiado. Falta registrar el correo del paciente'
                                    : 'Sala abierta; enlace copiado. No se pudo enviar el correo';
                    if (window.Swal) {
                        Swal.fire({ toast: true, position: 'top-end', icon: (result.email_sent || result.whatsapp_sent) ? 'success' : 'warning', title: invitationTitle, showConfirmButton: false, timer: 5000 });
                    } else {
                        alert('La sala está lista. El enlace del paciente se copió al portapapeles.');
                    }
                } catch (_) {
                    window.prompt('Copia este enlace y envíaselo al paciente:', result.patient_url);
                }
            } catch (error) {
                roomWindow?.close();
                if (window.Swal) Swal.fire({ icon: 'error', text: error.message });
                else alert(error.message);
            } finally {
                this.disabled = false;
            }
        });

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
                modality: document.getElementById('appointmentModality').value,

                reason: document.getElementById('appointmentReason').value,

                notes: document.getElementById('appointmentNotes').value,
whatsapp_consent: document.getElementById('appointmentWhatsappConsent').checked,
            };
            if (appointmentClinicBranchId()) {
                data.clinic_branch_id = Number(appointmentClinicBranchId());
            }
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
            const branchSelect = document.getElementById('appointmentClinicBranch');

            if (branchSelect) {
                $(branchSelect).on('change', async function() {
                    const requestId = ++appointmentSpecialtiesRequestId;
                    specialtySelect.innerHTML = '<option value="">Cargando especialidades…</option>';
                    $(specialtySelect).val('').trigger('change.select2');
                    professionalSelect.innerHTML = '<option value="">Seleccione especialidad primero</option>';
                    $(professionalSelect).val('').trigger('change.select2');
                    professionalContainer.classList.add('hidden');
                    document.getElementById('availableTimesContainer').classList.add('hidden');
                    document.getElementById('appointmentTime').innerHTML = '<option value="">Seleccione horario</option>';
                    document.getElementById('appointmentWeeklyDays').innerHTML = '';

                    try {
                        const params = new URLSearchParams({ clinic_branch_id: this.value });
                        if (editingAppointmentId) params.set('appointment_id', editingAppointmentId);
                        const response = await fetch(`/appointments/specialties?${params}`, { headers: { 'Accept': 'application/json' } });
                        if (!response.ok) throw new Error('No se pudieron consultar las especialidades de la sede.');
                        const specialties = await response.json();
                        if (requestId !== appointmentSpecialtiesRequestId) return;
                        specialtySelect.innerHTML = '<option value="">Seleccione especialidad</option>';
                        specialties.forEach(specialty => specialtySelect.add(new Option(specialty.name, specialty.id)));
                        $(specialtySelect).val(editingAppointmentId ? String(editingSpecialtyId || '') : '').trigger('change');
                        document.getElementById('appointmentWeeklyStatus').textContent = 'Selecciona profesional y fecha para consultar.';
                    } catch (error) {
                        if (requestId !== appointmentSpecialtiesRequestId) return;
                        specialtySelect.innerHTML = '<option value="">No se pudieron cargar especialidades</option>';
                        $(specialtySelect).val('').trigger('change.select2');
                        Swal.fire({ icon: 'error', title: 'Error', text: error.message });
                    }
                });
            }

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

                const professionalParams = new URLSearchParams({ specialty_id: specialtyId });
            if (appointmentClinicBranchId()) professionalParams.set('clinic_branch_id', appointmentClinicBranchId());
                if (editingAppointmentId) professionalParams.set('appointment_id', editingAppointmentId);
                fetch(`/professionals/by-specialty?${professionalParams}`)
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
