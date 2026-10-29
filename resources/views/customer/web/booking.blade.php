<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Agendar una cita | {{ app('customer')->name }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="{{ asset('css/style.css') }}" rel="stylesheet">
    @include('customer.web.theme-colors')
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">
    <style>
        :root { --date-field-accent: {{ themeColor(600) }}; }
        .flatpickr-calendar { width: 326px !important; border: 1px solid #e2e8f0 !important; border-radius: 16px !important; box-shadow: 0 18px 45px rgba(15, 23, 42, .18) !important; padding: 10px 12px !important; font-family: Poppins, sans-serif !important; overflow: visible; }
        .flatpickr-months { padding: 4px 4px 8px; }
        .flatpickr-current-month { font-size: 1rem !important; font-weight: 700; }
        .flatpickr-current-month .flatpickr-monthDropdown-months, .flatpickr-current-month input.cur-year { font-weight: 700 !important; }
        .flatpickr-weekday { color: #64748b !important; font-weight: 600 !important; }
        .flatpickr-day { border-radius: 9px !important; color: #334155 !important; }
        .flatpickr-day:hover { background: color-mix(in srgb, {{ themeColor(600) }} 12%, white) !important; border-color: transparent !important; }
        .flatpickr-day.today { border-color: var(--date-field-accent) !important; }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange, .flatpickr-day.selected:hover { background: var(--date-field-accent) !important; border-color: var(--date-field-accent) !important; color: #fff !important; }
        .flatpickr-day.disabled { color: #cbd5e1 !important; }
        .flatpickr-days, .dayContainer { width: 294px !important; min-width: 294px !important; max-width: 294px !important; }
        .flatpickr-weekdays, .flatpickr-weekdaycontainer { width: 294px !important; }
        .flatpickr-day.vita-holiday:not(.selected) { color: #dc2626 !important; background: #fff1f2; border-color: transparent; }
        .flatpickr-day.vita-holiday:not(.selected):hover { background: #ffe4e6 !important; }
        .flatpickr-prev-month:hover svg, .flatpickr-next-month:hover svg { fill: var(--date-field-accent) !important; }
        .date-picker-visible { min-height: 46px; width: 100%; cursor: pointer; }
        body { background: #f8fafc; font-family: Poppins, sans-serif; }
        .booking-card { max-width: 900px; margin: 3rem auto; border: 0; border-radius: 1rem; box-shadow: 0 12px 40px #0f172a14; }
        .booking-modal-view { background: #f8fafc; }
        .booking-modal-view main.container { max-width: none; padding: .85rem 1rem 1rem; }
        .booking-modal-view .booking-card { max-width: none; margin: 0; border: 1px solid #e2e8f0; border-radius: .85rem; box-shadow: 0 4px 18px rgba(15,23,42,.06); }
        .booking-modal-view .booking-card .card-body { padding: 1.25rem !important; }
        .booking-modal-view .booking-intro { margin-bottom: 1rem !important; text-align: left !important; }
        .booking-modal-view .booking-intro h1 { font-size: 1.1rem; font-weight: 700; }
        .booking-modal-view .booking-intro .booking-intro-icon { display: none !important; }
        .booking-modal-view .form-group { margin-bottom: .85rem; }
        .booking-modal-view label { margin-bottom: .3rem; font-size: .88rem; font-weight: 600; color: #334155; }
        .weekly-availability { border: 1px solid #e2e8f0; border-radius: .8rem; background: #fff; padding: 1rem; }
        .weekly-availability-header { display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-bottom:.85rem; }
        .weekly-availability-nav { width:2.1rem; height:2.1rem; display:inline-flex; align-items:center; justify-content:center; border:1px solid #dbe3ef; border-radius:.55rem; color:#334155; background:#fff; }
        .weekly-availability-nav:hover:not(:disabled) { border-color:{{ themeColor(600) }}; color:{{ themeColor(700) }}; background:{{ themeColor(50) }}; }
        .weekly-availability-nav:disabled { opacity:.4; cursor:not-allowed; }
        .weekly-days { display:grid; grid-template-columns:repeat(7,minmax(0,1fr)); gap:.5rem; }
        .weekly-day { min-width:0; border:1px solid #e2e8f0; border-radius:.65rem; padding:.55rem; background:#f8fafc; }
        .weekly-day.is-selected { border-color:{{ themeColor(600) }}; box-shadow:0 0 0 1px {{ themeColor(600) }} inset; background:{{ themeColor(50) }}; }
        .weekly-day-title { display:block; margin-bottom:.45rem; text-align:center; font-size:.72rem; font-weight:700; color:#475569; text-transform:capitalize; }
        .weekly-day-slots { display:flex; flex-direction:column; gap:.3rem; }
        .weekly-slot { width:100%; padding:.3rem .15rem; border:1px solid #cbd5e1; border-radius:.4rem; color:#334155; background:#fff; font-size:.72rem; line-height:1.15; }
        .weekly-slot:hover, .weekly-slot.is-selected { border-color:{{ themeColor(600) }}; color:#fff; background:{{ themeColor(600) }}; }
        .weekly-day-empty { display:block; padding:.3rem 0; color:#94a3b8; text-align:center; font-size:.68rem; }
        .weekly-availability-status { color:#64748b; font-size:.8rem; }
        .booking-modal-view .weekly-availability { padding:.8rem; }
        .booking-modal-view .weekly-day { padding:.45rem; }
        .booking-modal-view .weekly-slot { min-height:1.8rem; }
        @media(max-width:767.98px) { .weekly-days { grid-template-columns:repeat(2,minmax(0,1fr)); } .booking-modal-view .booking-card .card-body { padding:1rem !important; } }
        @media(max-width:420px) { .weekly-days { grid-template-columns:1fr; } }
        .booking-card .form-control, .booking-card .custom-select { min-height: 46px; border-radius: .5rem; }
        .booking-card .select2-container { width: 100% !important; }
        .booking-card .select2-container .select2-selection--single { height: 46px; border: 1px solid #ced4da; border-radius: .5rem; }
        .booking-card .select2-container .select2-selection__rendered { line-height: 44px; padding-left: .75rem; color: #495057; }
        .booking-card .select2-container .select2-selection__arrow { height: 44px; right: .35rem; }
        .booking-card input[type="date"] { min-height: 46px; border-color: #cbd5e1; border-radius: .5rem; accent-color: {{ themeColor(600) }}; color-scheme: light; transition: border-color .18s ease, box-shadow .18s ease; }
        .booking-card input[type="date"]:focus { border-color: {{ themeColor(600) }}; outline: none; box-shadow: 0 0 0 3px color-mix(in srgb, {{ themeColor(600) }} 18%, transparent); }
        .booking-card input[type="date"]::-webkit-calendar-picker-indicator { padding: .3rem; border-radius: .4rem; cursor: pointer; }
        .booking-submit { border: 0; border-radius: .5rem; padding: .8rem 1.3rem; color: #fff !important; background: {{ themeColor(600) }}; font-weight: 600; transition: background-color .2s ease, box-shadow .2s ease, transform .2s ease; }
        .booking-submit:hover, .booking-submit:focus-visible { color: #fff !important; background: {{ themeColor(800) }}; box-shadow: 0 8px 20px rgba(0,0,0,.2); transform: translateY(-2px); }
        @media(max-width: 575.98px) { .booking-card { margin: 1rem .5rem; } .booking-card .card-body { padding: 1.25rem !important; } }
    </style>
</head>
<body class="{{ $bookingModal ? 'booking-modal-view' : '' }}">
    @if (!$bookingModal)
        @include('customer.web.menu')
    @endif

    <main class="container py-3 py-md-4">
        <section class="card booking-card">
            <div class="card-body p-4 p-md-5">
                <div class="mb-4 text-center booking-intro">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle mb-3 booking-intro-icon" style="width:3.5rem;height:3.5rem;color:{{ themeColor(700) }};background:{{ themeColor(100) }}">
                        <i class="fa-regular fa-calendar-check fa-lg"></i>
                    </span>
                    <h1 class="h2 mb-2">{{ $bookingModal ? 'Completa tus datos para agendar' : 'Agenda tu cita' }}</h1>
                    @unless($bookingModal)<p class="text-muted mb-0">Completa tus datos y selecciona una hora disponible.</p>@endunless
                </div>

                @if (session('booking_success'))
                    <div class="alert alert-success" role="status"><i class="fa-solid fa-circle-check mr-2"></i>{{ session('booking_success') }}</div>
                @endif

                <form method="POST" action="{{ route('customer.public-booking.store', array_filter(['customer' => request()->route('customer'), 'modal' => $bookingModal ? 1 : null])) }}" id="public-booking-form"
                    data-rut-check-url="{{ route('customer.public-booking.check-rut', ['customer' => request()->route('customer')]) }}"
                    data-professionals-url="{{ route('customer.public-booking.professionals', ['customer' => request()->route('customer')]) }}"
                    data-availability-url="{{ route('customer.public-booking.availability', ['customer' => request()->route('customer')]) }}"
                    data-weekly-availability-url="{{ route('customer.public-booking.weekly-availability', ['customer' => request()->route('customer')]) }}">
                    @csrf
                    <div class="row">
                        <div class="col-12 form-group">
                            <label for="booking-rut">RUT</label>
                            <input id="booking-rut" class="form-control" name="rut" maxlength="20" value="{{ old('rut') }}" required placeholder="12.345.678-9">
                            <small id="booking-rut-feedback" class="d-block mt-1" aria-live="polite"></small>
                            @error('rut') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div id="booking-patient-details" class="col-12">
                        <div class="row">
                        <div class="col-md-6 form-group">
                            <label for="booking-name">Nombre completo</label>
                            <input id="booking-name" class="form-control" name="name" maxlength="255" value="{{ old('name') }}" required autocomplete="name">
                            @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-gender">Sexo</label>
                            <select id="booking-gender" class="custom-select" name="gender" required>
                                <option value="">Selecciona una opción</option>
                                @foreach (['Masculino', 'Femenino', 'Otro'] as $gender)
                                    <option value="{{ $gender }}" @selected(old('gender') === $gender)>{{ $gender }}</option>
                                @endforeach
                            </select>
                            @error('gender') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-birth-date">Fecha de nacimiento <span class="text-muted">(opcional)</span></label>
                            <input id="booking-birth-date" class="form-control" type="date" name="birth_date" max="{{ now()->format('Y-m-d') }}" value="{{ old('birth_date') }}" autocomplete="bday">
                            @error('birth_date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-address">Dirección <span class="text-muted">(opcional)</span></label>
                            <input id="booking-address" class="form-control" name="address" maxlength="255" value="{{ old('address') }}" autocomplete="street-address">
                            @error('address') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-insurance">Previsión <span class="text-muted">(opcional)</span></label>
                            <select id="booking-insurance" class="custom-select" name="insurance_id">
                                <option value="">Selecciona una previsión</option>
                                @foreach ($publicInsurances as $insurance)
                                    <option value="{{ $insurance->id }}" @selected((string) old('insurance_id') === (string) $insurance->id)>{{ $insurance->name }}</option>
                                @endforeach
                            </select>
                            @error('insurance_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-phone">Teléfono</label>
                            <input id="booking-phone" class="form-control" name="phone" type="tel" maxlength="20" value="{{ old('phone') }}" required autocomplete="tel">
                            @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-email">Correo electrónico <span class="text-muted">(opcional)</span></label>
                            <input id="booking-email" class="form-control" name="email" type="email" maxlength="255" value="{{ old('email') }}" autocomplete="email">
                            @error('email') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        </div>
                        </div>
                        <div class="col-12 form-group">
                            <label class="d-flex align-items-start" style="gap:.5rem;font-weight:400">
                                <input type="checkbox" name="whatsapp_consent" value="1" @checked(old('whatsapp_consent')) style="margin-top:.25rem">
                                <span>Acepto recibir por WhatsApp avisos relacionados con esta cita. Puedo retirar este permiso cuando quiera.</span>
                            </label>
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-specialty">Especialidad</label>
                            <select id="booking-specialty" class="custom-select" name="specialty_id" required>
                                <option value="">Selecciona una especialidad</option>
                                @foreach ($publicSpecialties as $specialty)
                                    <option value="{{ $specialty->id }}" @selected((string) old('specialty_id') === (string) $specialty->id)>{{ $specialty->name }}</option>
                                @endforeach
                            </select>
                            @error('specialty_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-professional">Profesional</label>
                            <select id="booking-professional" class="custom-select" name="professional_id" required disabled>
                                <option value="">Primero selecciona una especialidad</option>
                            </select>
                            @error('professional_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-date">Fecha</label>
                            <input id="booking-date" class="form-control" type="date" name="date" min="{{ $minimumDate }}" value="{{ old('date') }}" required>
                            @error('date') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label for="booking-time">Hora disponible</label>
                            <select id="booking-time" class="custom-select" name="time" required disabled>
                                <option value="">Selecciona profesional y fecha</option>
                            </select>
                            @error('time') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="col-12 form-group">
                            <section class="weekly-availability" aria-labelledby="weekly-availability-title">
                                <div class="weekly-availability-header">
                                    <div>
                                        <h2 id="weekly-availability-title" class="mb-1" style="font-size:.95rem;font-weight:700;color:#1e293b">Disponibilidad semanal</h2>
                                        <p id="weekly-availability-status" class="weekly-availability-status mb-0">Selecciona especialidad y profesional para consultar.</p>
                                    </div>
                                    <div class="d-flex align-items-center" style="gap:.4rem;flex:0 0 auto">
                                        <button type="button" id="weekly-availability-prev" class="weekly-availability-nav" aria-label="Semana anterior"><i class="fa-solid fa-chevron-left"></i></button>
                                        <button type="button" id="weekly-availability-next" class="weekly-availability-nav" aria-label="Semana siguiente"><i class="fa-solid fa-chevron-right"></i></button>
                                    </div>
                                </div>
                                <div id="weekly-availability-days" class="weekly-days" aria-live="polite"></div>
                            </section>
                        </div>
                        <div class="col-12 form-group">
                            <label for="booking-modality">Modalidad de atención</label>
                            <select id="booking-modality" class="custom-select" name="modality" required>
                                <option value="in_person" @selected(old('modality', 'in_person') === 'in_person')>Presencial</option>
                                <option value="telemedicine" @selected(old('modality') === 'telemedicine')>Remota (telemedicina)</option>
                            </select>
                            @error('modality') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        @if ($publicBranches->isNotEmpty())
                            <div id="booking-branch-group" class="col-12 form-group">
                                <label for="booking-branch">Sede para atención presencial</label>
                                <select id="booking-branch" class="custom-select" name="clinic_branch_id">
                                    <option value="">Selecciona una sede</option>
                                    @foreach ($publicBranches as $branch)
                                        <option value="{{ $branch->id }}" @selected((string) old('clinic_branch_id') === (string) $branch->id)>
                                            {{ $branch->name }}{{ $branch->address ? ' · ' . $branch->address : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('clinic_branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        @endif
                        <div class="col-12 form-group">
                            <label for="booking-reason">Motivo de la consulta <span class="text-muted">(opcional)</span></label>
                            <textarea id="booking-reason" class="form-control" name="reason" rows="3" maxlength="500">{{ old('reason') }}</textarea>
                            @error('reason') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="position-absolute" style="left:-10000px" aria-hidden="true">
                            <label for="booking-website">Sitio web</label>
                            <input id="booking-website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between mt-3" style="gap:.75rem">
                        <small class="text-muted">La duración de cada cita es de 30 minutos. Las horas se muestran según disponibilidad real.</small>
                        <button type="submit" class="booking-submit"><i class="fa-regular fa-calendar-check mr-2"></i>Agendar cita</button>
                    </div>
                </form>
            </div>
        </section>
    </main>

    @if (!$bookingModal)
        @include('customer.web.footer')
    @endif

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/l10n/es.js"></script>
    <script>
        const vitaChileHolidays = (year) => {
            const dates = {
                [`${year}-01-01`]: 'Año Nuevo', [`${year}-05-01`]: 'Día del Trabajo',
                [`${year}-05-21`]: 'Día de las Glorias Navales', [`${year}-06-29`]: 'San Pedro y San Pablo',
                [`${year}-07-16`]: 'Día de la Virgen del Carmen', [`${year}-08-15`]: 'Asunción de la Virgen',
                [`${year}-09-18`]: 'Independencia Nacional', [`${year}-09-19`]: 'Día de las Glorias del Ejército',
                [`${year}-10-12`]: 'Encuentro de Dos Mundos', [`${year}-10-31`]: 'Día de las Iglesias Evangélicas y Protestantes',
                [`${year}-11-01`]: 'Día de Todos los Santos', [`${year}-12-08`]: 'Inmaculada Concepción', [`${year}-12-25`]: 'Navidad',
            };
            const a = year % 19, b = Math.floor(year / 100), c = year % 100;
            const d = Math.floor(b / 4), e = b % 4, f = Math.floor((b + 8) / 25);
            const g = Math.floor((b - f + 1) / 3), h = (19 * a + b - d - g + 15) % 30;
            const i = Math.floor(c / 4), k = c % 4, l = (32 + 2 * e + 2 * i - h - k) % 7;
            const m = Math.floor((a + 11 * h + 22 * l) / 451);
            const easter = new Date(year, Math.floor((h + l - 7 * m + 114) / 31) - 1, ((h + l - 7 * m + 114) % 31) + 1);
            [-2, -1].forEach((offset, index) => {
                const date = new Date(easter); date.setDate(date.getDate() + offset);
                dates[`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`] = index === 0 ? 'Viernes Santo' : 'Sábado Santo';
            });
            return dates;
        };
        (() => {
            document.querySelectorAll('input[type="date"]').forEach(input => {
                if (input._flatpickr) return;
                flatpickr(input, {
                    locale: 'es', dateFormat: 'Y-m-d', altInput: true, altFormat: 'd/m/Y',
                    altInputClass: `${input.className} date-picker-visible`, allowInput: true, disableMobile: true,
                    onDayCreate: (_dates, _dateString, _instance, dayElement) => {
                        const date = dayElement.dateObj;
                        const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
                        const holiday = vitaChileHolidays(date.getFullYear())[key];
                        if (holiday) { dayElement.classList.add('vita-holiday'); dayElement.title = holiday; }
                    },
                });
            });

            const form = document.getElementById('public-booking-form');
            const specialty = document.getElementById('booking-specialty');
            const professional = document.getElementById('booking-professional');
            const date = document.getElementById('booking-date');
            const time = document.getElementById('booking-time');
            const rut = document.getElementById('booking-rut');
            const modality = document.getElementById('booking-modality');
            const branchGroup = document.getElementById('booking-branch-group');
            const branch = document.getElementById('booking-branch');
            const rutFeedback = document.getElementById('booking-rut-feedback');
            const patientDetails = document.getElementById('booking-patient-details');
            const weeklyDays = document.getElementById('weekly-availability-days');
            const weeklyStatus = document.getElementById('weekly-availability-status');
            const weeklyPrevious = document.getElementById('weekly-availability-prev');
            const weeklyNext = document.getElementById('weekly-availability-next');
            const oldProfessional = @json(old('professional_id'));
            const oldTime = @json(old('time'));
            const updateBranchVisibility = () => {
                if (!branchGroup || !branch) return;
                const inPerson = modality.value === 'in_person';
                branchGroup.hidden = !inPerson;
                branch.required = inPerson;
                if (!inPerson) branch.value = '';
            };
            if (modality) {
                modality.addEventListener('change', () => {
                    updateBranchVisibility();
                    if (specialty.value) loadProfessionals(false);
                });
                updateBranchVisibility();
            }
            let rutCheckTimer;
            let lastCheckedRut = '';
            let autoFilledRut = '';
            let weeklyRequestId = 0;
            let weekAnchor = date.value || date.min;

            const parseLocalDate = value => {
                const [year, month, day] = value.split('-').map(Number);
                return new Date(year, month - 1, day, 12);
            };
            const formatLocalDate = value => {
                const day = parseLocalDate(value);
                return `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
            };
            const getWeekDates = anchor => {
                const first = parseLocalDate(anchor);
                first.setDate(first.getDate() - ((first.getDay() + 6) % 7));
                return Array.from({ length: 7 }, (_, index) => {
                    const day = new Date(first);
                    day.setDate(first.getDate() + index);
                    return formatLocalDate(`${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`);
                });
            };
            const setWeeklyStatus = message => { weeklyStatus.textContent = message; };
            const highlightWeeklySelection = () => {
                const selectedDate = date.value;
                const selectedTime = time.value;
                const selectedDateElement = parseLocalDate(selectedDate || date.min);
                weeklyDays.querySelectorAll('.weekly-day').forEach(card => {
                    const isSelectedDay = card.dataset.date === selectedDate;
                    card.classList.toggle('is-selected', isSelectedDay);
                    card.querySelectorAll('.weekly-slot').forEach(button => {
                        button.classList.toggle('is-selected', isSelectedDay && button.dataset.time === selectedTime);
                    });
                });
                if (selectedDate && selectedTime) {
                    setWeeklyStatus(`${selectedDateElement.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' })} a las ${selectedTime}.`);
                }
            };
            const updateWeekNavigation = dates => {
                const today = date.min;
                const previousWeekEnd = parseLocalDate(dates[0]);
                previousWeekEnd.setDate(previousWeekEnd.getDate() - 1);
                weeklyPrevious.disabled = formatLocalDate(`${previousWeekEnd.getFullYear()}-${String(previousWeekEnd.getMonth() + 1).padStart(2, '0')}-${String(previousWeekEnd.getDate()).padStart(2, '0')}`) < today;
                weeklyNext.disabled = false;
            };
            const renderWeeklyAvailability = (dates, availability, requestId) => {
                if (requestId !== weeklyRequestId) return;
                weeklyDays.innerHTML = '';
                const selectedDate = date.value;
                dates.forEach(day => {
                    const dayDate = parseLocalDate(day);
                    const label = dayDate.toLocaleDateString('es-CL', { weekday: 'short', day: '2-digit', month: 'short' }).replace('.', '');
                    const card = document.createElement('div');
                    card.className = `weekly-day${day === selectedDate ? ' is-selected' : ''}`;
                    card.dataset.date = day;
                    const heading = document.createElement('span');
                    heading.className = 'weekly-day-title';
                    heading.textContent = label;
                    card.appendChild(heading);
                    const slotsContainer = document.createElement('div');
                    slotsContainer.className = 'weekly-day-slots';
                    const slots = availability[day] || [];
                    if (day < date.min || !slots.length) {
                        const empty = document.createElement('span');
                        empty.className = 'weekly-day-empty';
                        empty.textContent = day < date.min ? '—' : 'Sin horas';
                        slotsContainer.appendChild(empty);
                    } else {
                        slots.forEach(slot => {
                            const button = document.createElement('button');
                            button.type = 'button';
                            button.className = `weekly-slot${day === selectedDate && slot.value === time.value ? ' is-selected' : ''}`;
                            button.dataset.time = slot.value;
                            button.textContent = slot.value;
                            button.title = slot.label;
                            button.addEventListener('click', () => {
                                if (date._flatpickr) date._flatpickr.setDate(day, false);
                                else date.value = day;
                                setOptions(time, 'Selecciona una hora', slots);
                                time.value = slot.value;
                                highlightWeeklySelection();
                            });
                            slotsContainer.appendChild(button);
                        });
                    }
                    card.appendChild(slotsContainer);
                    weeklyDays.appendChild(card);
                });
                updateWeekNavigation(dates);
            };
            const loadWeeklyAvailability = async () => {
                const requestId = ++weeklyRequestId;
                const dates = getWeekDates(weekAnchor);
                weeklyDays.innerHTML = '<div class="weekly-availability-status" style="grid-column:1/-1">Consultando disponibilidad de la semana…</div>';
                if (!specialty.value || !professional.value) {
                    weeklyDays.innerHTML = '';
                    setWeeklyStatus('Selecciona especialidad y profesional para consultar.');
                    updateWeekNavigation(dates);
                    return;
                }
                setWeeklyStatus('Cargando horarios disponibles…');
                try {
                    const params = new URLSearchParams({ specialty_id: specialty.value, professional_id: professional.value, date: weekAnchor });
                    if (modality.value === 'in_person' && branch?.value) params.set('clinic_branch_id', branch.value);
                    const response = await fetch(`${form.dataset.weeklyAvailabilityUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error('No se pudo consultar la semana.');
                    const result = await response.json();
                    const availability = result.availability || {};
                    renderWeeklyAvailability(dates, availability, requestId);
                    const count = Object.values(availability).reduce((total, slots) => total + slots.length, 0);
                    setWeeklyStatus(count ? `${count} horario(s) disponible(s) en el período seleccionado. Elige uno para agendar.` : 'No hay horarios disponibles en este período. Usa las flechas para revisar otra semana.');
                } catch (error) {
                    if (requestId !== weeklyRequestId) return;
                    weeklyDays.innerHTML = '';
                    setWeeklyStatus('No se pudo consultar la disponibilidad semanal.');
                }
            };

            const hasSelect2 = Boolean(window.jQuery && jQuery.fn && jQuery.fn.select2);
            if (hasSelect2) {
                $('#booking-specialty, #booking-professional').select2({
                    width: '100%',
                    dropdownParent: $(document.body),
                    minimumResultsForSearch: 0,
                    language: { noResults: () => 'No se encontraron resultados', searching: () => 'Buscando...' },
                });
            }

            const applyPatientDetails = patient => {
                const values = {
                    'booking-name': patient.name || '',
                    'booking-gender': patient.gender || '',
                    'booking-birth-date': patient.birth_date || '',
                };
                Object.entries(values).forEach(([id, value]) => {
                    const field = document.getElementById(id);
                    if (!field) return;
                    if (field._flatpickr) field._flatpickr.setDate(value || null, false);
                    else field.value = value;
                    if (hasSelect2 && field.tagName === 'SELECT') $(field).trigger('change.select2');
                });
            };
            const setPatientDetailsVisible = (visible, existingPatient = false) => {
                patientDetails.hidden = !visible;
                patientDetails.querySelectorAll('input, select, textarea').forEach(field => { field.disabled = !visible; });
                document.getElementById('booking-phone').required = visible && !existingPatient;
            };

            const checkRut = async () => {
                const value = rut.value.trim();
                const normalized = value.toUpperCase().replace(/[^0-9K]/g, '');
                if (normalized.length < 7) {
                    rutFeedback.textContent = '';
                    rutFeedback.className = 'd-block mt-1';
                    lastCheckedRut = '';
                    setPatientDetailsVisible(true);
                    return;
                }
                if (normalized === lastCheckedRut) return;

                rutFeedback.textContent = 'Verificando RUT...';
                rutFeedback.className = 'd-block mt-1 text-muted';
                try {
                    const response = await fetch(`${form.dataset.rutCheckUrl}?rut=${encodeURIComponent(value)}`, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error();
                    const result = await response.json();
                    if (rut.value.trim().toUpperCase().replace(/[^0-9K]/g, '') !== normalized) return;
                    lastCheckedRut = normalized;
                    if (!result.exists) {
                        setPatientDetailsVisible(true);
                        rutFeedback.textContent = 'No encontramos una ficha con este RUT. Completa tus datos para crearla al agendar.';
                        rutFeedback.className = 'd-block mt-1 text-muted';
                    } else if (result.patient) {
                        applyPatientDetails(result.patient);
                        setPatientDetailsVisible(true, true);
                        autoFilledRut = normalized;
                        rutFeedback.textContent = 'Encontramos tu ficha y completamos nombre, fecha de nacimiento y sexo.';
                        rutFeedback.className = 'd-block mt-1 text-success';
                    }
                } catch (error) {
                    setPatientDetailsVisible(true);
                    rutFeedback.textContent = 'No se pudo verificar el RUT ahora. Se validara al agendar.';
                    rutFeedback.className = 'd-block mt-1 text-muted';
                }
            };

            const setOptions = (select, placeholder, options) => {
                select.innerHTML = '';
                const first = document.createElement('option');
                first.value = '';
                first.textContent = placeholder;
                select.appendChild(first);
                options.forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id ?? item.value;
                    option.textContent = item.name ?? item.label;
                    if (String(option.value) === String(select.dataset.selected || '')) option.selected = true;
                    select.appendChild(option);
                });
                select.disabled = options.length === 0;
                if (hasSelect2) $(select).trigger('change.select2');
            };

            const loadProfessionals = async (restore = false) => {
                setOptions(professional, 'Cargando profesionales...', []);
                professional.disabled = true;
                time.disabled = true;
                setOptions(time, 'Selecciona profesional y fecha', []);
                if (!specialty.value) {
                    setOptions(professional, 'Primero selecciona una especialidad', []);
                    loadWeeklyAvailability();
                    return;
                }
                try {
                    const params = new URLSearchParams({ specialty_id: specialty.value });
                    if (modality.value === 'in_person' && branch?.value) params.set('clinic_branch_id', branch.value);
                    const response = await fetch(`${form.dataset.professionalsUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error();
                    const items = await response.json();
                    professional.dataset.selected = restore ? (oldProfessional || '') : '';
                    setOptions(professional, items.length ? 'Selecciona un profesional' : 'No hay profesionales disponibles', items);
                    if (professional.value) {
                        await loadAvailability(restore);
                        loadWeeklyAvailability();
                    } else {
                        loadWeeklyAvailability();
                    }
                } catch (error) {
                    console.error('Error cargando profesionales para agendamiento público:', error);
                    setOptions(professional, 'No se pudieron cargar los profesionales', []);
                }
            };

            const loadAvailability = async (restore = false) => {
                time.disabled = true;
                setOptions(time, 'Buscando horas disponibles...', []);
                if (!specialty.value || !professional.value || !date.value) {
                    setOptions(time, 'Selecciona profesional y fecha', []);
                    return;
                }
                try {
                    const params = new URLSearchParams({ specialty_id: specialty.value, professional_id: professional.value, date: date.value });
                    if (modality.value === 'in_person' && branch?.value) params.set('clinic_branch_id', branch.value);
                    const response = await fetch(`${form.dataset.availabilityUrl}?${params}`, { headers: { 'Accept': 'application/json' } });
                    if (!response.ok) throw new Error();
                    const items = await response.json();
                    time.dataset.selected = restore ? (oldTime || '') : '';
                    setOptions(time, items.length ? 'Selecciona una hora' : 'No hay horas disponibles para esta fecha', items);
                } catch (error) {
                    setOptions(time, 'No se pudieron consultar las horas', []);
                }
            };

            if (window.jQuery) {
                $(specialty).on('change.publicBooking', () => loadProfessionals(false));
                $(professional).on('change.publicBooking', () => { loadAvailability(false); weekAnchor = date.value || date.min; loadWeeklyAvailability(); });
            } else {
                specialty.addEventListener('change', () => loadProfessionals(false));
                professional.addEventListener('change', () => { loadAvailability(false); weekAnchor = date.value || date.min; loadWeeklyAvailability(); });
            }
            branch?.addEventListener('change', () => loadProfessionals(false));
            date.addEventListener('change', () => { loadAvailability(false); weekAnchor = date.value || date.min; loadWeeklyAvailability(); });
            time.addEventListener('change', highlightWeeklySelection);
            weeklyPrevious.addEventListener('click', () => {
                const previous = parseLocalDate(weekAnchor); previous.setDate(previous.getDate() - 7);
                weekAnchor = formatLocalDate(`${previous.getFullYear()}-${String(previous.getMonth() + 1).padStart(2, '0')}-${String(previous.getDate()).padStart(2, '0')}`);
                if (getWeekDates(weekAnchor).at(-1) < date.min) return;
                loadWeeklyAvailability();
            });
            weeklyNext.addEventListener('click', () => {
                const next = parseLocalDate(weekAnchor); next.setDate(next.getDate() + 7);
                weekAnchor = formatLocalDate(`${next.getFullYear()}-${String(next.getMonth() + 1).padStart(2, '0')}-${String(next.getDate()).padStart(2, '0')}`);
                loadWeeklyAvailability();
            });
            rut.addEventListener('input', () => {
                clearTimeout(rutCheckTimer);
                lastCheckedRut = '';
                rutFeedback.textContent = '';
                const normalized = rut.value.trim().toUpperCase().replace(/[^0-9K]/g, '');
                if (autoFilledRut && normalized !== autoFilledRut) {
                    ['booking-name', 'booking-gender', 'booking-birth-date', 'booking-address', 'booking-insurance', 'booking-phone', 'booking-email'].forEach(id => {
                        const field = document.getElementById(id);
                        if (field) field.value = '';
                    });
                    autoFilledRut = '';
                }
                setPatientDetailsVisible(true);
                rutCheckTimer = setTimeout(checkRut, 500);
            });
            rut.addEventListener('blur', checkRut);
            if (rut.value.trim()) checkRut();
            if (specialty.value) loadProfessionals(true);
        })();
    </script>
    @if (!$bookingModal)
        <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.bundle.min.js"></script>
    @endif
</body>
</html>
