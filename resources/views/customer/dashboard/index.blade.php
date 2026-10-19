@extends('layouts.menu-customer')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ __('Panel de Administración') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">

                <div class="p-6">

                    <!-- Encabezado -->
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">

                        <div>
                            <h2 class="text-xl font-semibold text-gray-800">
                                {{ $isDoctor ? 'Panel del profesional' : 'Dashboard' }}
                            </h2>

                            <p id="dashboard-statistics-description" class="text-sm text-gray-500 mt-1">
                                @if ($isDoctor)
                                    Estadísticas de {{ $professionalName }} según el período seleccionado
                                @else
                                    Estadísticas según período seleccionado
                                @endif
                            </p>
                        </div>

                        <!-- Filtros -->
                        <div class="flex flex-col sm:flex-row gap-3">

                            <!-- Año -->
                            <div>
                                <label for="year" class="block text-sm font-medium text-gray-700 mb-1">
                                    Año
                                </label>

                                <select id="year"
                                    class="border-gray-300 rounded-md shadow-sm
                                       focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach ($years as $year)
                                        <option value="{{ $year }}" @selected($year === $currentYear)>
                                            {{ $year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Mes -->
                            <div>
                                <label for="month" class="block text-sm font-medium text-gray-700 mb-1">
                                    Mes
                                </label>

                                <select id="month"
                                    class="border-gray-300 rounded-md shadow-sm
                                       focus:border-indigo-500 focus:ring-indigo-500">

                                    <option value="">Todos los meses</option>
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>

                                </select>
                            </div>

                            @unless ($isDoctor)
                                <div>
                                    <label for="professional-filter" class="block text-sm font-medium text-gray-700 mb-1">
                                        Profesional
                                    </label>
                                    <select id="professional-filter"
                                        class="border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Todos los profesionales</option>
                                        @foreach ($professionals as $professional)
                                            <option value="{{ $professional->id }}">
                                                {{ $professional->name }}{{ (int) $professional->status === 1 ? '' : ' (Inactivo)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endunless

                        </div>

                    </div>


                </div>
                <!-- Separador -->
                <div class="border-t border-gray-200 my-6"></div>

                <div class="p-6">
                    <!-- Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

                        <!-- Pacientes -->
                        <div class="rounded-lg border border-sky-100 bg-sky-50 p-5">
                            <p class="text-sm font-medium text-sky-700">
                                Pacientes atendidos
                            </p>

                            <p id="totalPatients" class="mt-2 text-3xl font-bold text-sky-800">
                                0
                            </p>


                        </div>

                        <!-- Atenciones -->
                        <div class="rounded-lg border border-violet-100 bg-violet-50 p-5">
                            <p class="text-sm font-medium text-violet-700">
                                Atenciones Realizadas
                            </p>

                            <p id="totalAppointments" class="mt-2 text-3xl font-bold text-violet-800">
                                0
                            </p>


                        </div>

                        <!-- Citas agendadas-->
                        <div class="rounded-lg border border-blue-100 bg-blue-50 p-5">
                            <p class="text-sm font-medium text-blue-700">
                                Citas Agendadas
                            </p>

                            <p id="totalBookings" class="mt-2 text-3xl font-bold text-blue-800">
                                0
                            </p>


                        </div>

                        <!-- Citas confirmadas-->
                        <div class="rounded-lg border border-emerald-100 bg-emerald-50 p-5">
                            <p class="text-sm font-medium text-emerald-700">
                                Citas Confirmadas
                            </p>

                            <p id="ConfirmBookings" class="mt-2 text-3xl font-bold text-emerald-800">
                                0
                            </p>


                        </div>

                    </div>


                    <!-- Gráficos -->

<!-- Gráficos del dashboard -->
<section class="mt-8 rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-gray-800">Resumen de hoy</h3>
            <p class="text-sm text-gray-500">Citas de la clínica para este día</p>
        </div>
        <div class="rounded-lg bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700">
            Total <span id="today-total" class="ml-1 text-lg font-bold">0</span>
        </div>
    </div>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-md bg-blue-50 p-3"><p class="text-xs text-blue-700">Agendadas</p><p id="today-scheduled" class="mt-1 text-xl font-bold text-blue-800">0</p></div>
        <div class="rounded-md bg-emerald-50 p-3"><p class="text-xs text-emerald-700">Confirmadas</p><p id="today-confirmed" class="mt-1 text-xl font-bold text-emerald-800">0</p></div>
        <div class="rounded-md bg-amber-50 p-3"><p class="text-xs text-amber-700">En atención</p><p id="today-in-progress" class="mt-1 text-xl font-bold text-amber-800">0</p></div>
        <div class="rounded-md bg-violet-50 p-3"><p class="text-xs text-violet-700">Atendidas</p><p id="today-attended" class="mt-1 text-xl font-bold text-violet-800">0</p></div>
        <div class="rounded-md bg-rose-50 p-3"><p class="text-xs text-rose-700">No asistieron</p><p id="today-no-show" class="mt-1 text-xl font-bold text-rose-800">0</p></div>
        <div class="rounded-md bg-gray-100 p-3"><p class="text-xs text-gray-600">Canceladas</p><p id="today-cancelled" class="mt-1 text-xl font-bold text-gray-800">0</p></div>
    </div>
</section>

<div class="mt-8 space-y-8">

    <!-- Fila 1: gráficos mensuales -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <!-- Estado de citas por mes -->
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h3 id="appointmentsChartTitle"
                class="mb-4 text-lg font-semibold text-gray-800">
                Estado de citas por mes
            </h3>

            <div class="relative h-[380px] w-full">
                <canvas id="appointmentsChart"></canvas>
            </div>
        </section>

        <!-- Pacientes ingresados por mes -->
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h3 id="patientsRegisteredChartTitle"
                class="mb-4 text-lg font-semibold text-gray-800">
                {{ $isDoctor ? 'Pacientes atendidos por mes' : 'Pacientes ingresados por mes' }}
            </h3>

            <div class="relative h-[380px] w-full">
                <canvas id="patientsRegisteredChart"></canvas>
            </div>
        </section>

    </div>

    <!-- Fila 2: especialidades y gráficos circulares -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-4">

        <!-- Atenciones por especialidad: 2/3 del ancho -->
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">
                Atenciones por especialidad
            </h3>

            <div class="relative h-[420px] w-full">
                <canvas id="specialtyChart"></canvas>
            </div>
        </section>

        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">Asistencia e inasistencias</h3>
            <div id="attendanceChartContainer" class="flex h-[420px] w-full flex-col">
                <div class="relative h-[340px] w-full shrink-0">
                    <canvas id="attendanceChart"></canvas>
                <div id="attendanceNoData" class="absolute inset-0 hidden items-center justify-center text-center">
                    <p class="text-sm font-medium text-gray-500">Sin citas atendidas o inasistencias en el período</p>
                    </div>
                </div>
                <div id="attendanceChartLegend" class="flex h-20 shrink-0 flex-wrap content-center items-center justify-center gap-x-4 gap-y-1 overflow-y-auto px-1"></div>
            </div>
        </section>

        <!-- Pacientes por previsión: 1/3 del ancho -->
        <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
            <h3 class="mb-4 text-lg font-semibold text-gray-800">
                Pacientes por previsión
            </h3>

            <div id="insuranceChartContainer" class="flex h-[420px] w-full flex-col">
                <div class="relative h-[340px] w-full shrink-0">
                    <canvas id="insuranceChart"></canvas>

                <!-- Mensaje cuando no hay datos -->
                <div id="insuranceNoData"
                    class="absolute inset-0 hidden items-center justify-center text-center">

                    <div>
                        <p class="font-medium text-gray-500">
                            No hay pacientes atendidos
                        </p>

                        <p class="mt-1 text-sm text-gray-400">
                            en el período seleccionado
                        </p>
                    </div>

                    </div>
                </div>
                <div id="insuranceChartLegend" class="flex h-20 shrink-0 flex-wrap content-center items-center justify-center gap-x-4 gap-y-1 overflow-y-auto px-1"></div>
            </div>
        </section>

    </div>

</div>



                </div>

            </div>

        </div>
    @endsection

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css">
    <style>
        #professional-filter + .select2-container {
            min-width: 14rem;
        }

        #professional-filter + .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            display: flex;
            align-items: center;
        }

        #professional-filter + .select2-container .select2-selection__rendered {
            padding-left: 0.75rem;
            color: #111827;
        }

        #professional-filter + .select2-container .select2-selection__arrow {
            height: 40px;
        }
    </style>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
        <script>
            const yearSelect = document.getElementById('year');
            const monthSelect = document.getElementById('month');
            const professionalSelect = document.getElementById('professional-filter');

            if (professionalSelect && window.jQuery?.fn.select2) {
                $('#professional-filter').select2({
                    width: 'resolve',
                    minimumResultsForSearch: 0,
                    language: {
                        noResults: () => 'No se encontraron profesionales',
                        searching: () => 'Buscando...'
                    }
                });
            }

            let appointmentsChart = null;
            let insuranceChart = null;
            let attendanceChart = null;
            let specialtyChart = null;
            let patientsRegisteredChart = null;
            const isDoctor = @json($isDoctor);

            const percentageLabels = {
    id: 'percentageLabels',

    afterDatasetsDraw(chart) {
        if (
            chart.config.type !== 'pie' &&
            chart.config.type !== 'doughnut'
        ) {
            return;
        }

        const { ctx } = chart;

        chart.data.datasets.forEach((dataset, datasetIndex) => {
            const meta = chart.getDatasetMeta(datasetIndex);
            const values = dataset.data.map(Number);
            const total = values.reduce((sum, value) => sum + value, 0);

            if (total <= 0) return;

            meta.data.forEach((arc, index) => {
                const value = values[index];

                if (value <= 0) return;

                const percentage = (value / total) * 100;

                // Evita mostrar etiquetas demasiado pequeñas
                if (percentage < 3) return;

                const position = arc.tooltipPosition();

                ctx.save();
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 12px sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';

                ctx.fillText(
                    `${percentage.toFixed(1)}%`,
                    position.x,
                    position.y
                );

                ctx.restore();
            });
        });
    }
};

Chart.register(percentageLabels);

            function renderDoughnutLegend(containerId, labels, colors) {
                const legend = document.getElementById(containerId);
                legend.replaceChildren();

                labels.forEach((label, index) => {
                    const item = document.createElement('div');
                    item.className = 'flex items-center gap-1.5 text-xs text-gray-600';

                    const swatch = document.createElement('span');
                    swatch.className = 'h-2.5 w-2.5 shrink-0 rounded-full';
                    swatch.style.backgroundColor = colors[index % colors.length];

                    const text = document.createElement('span');
                    text.textContent = label;

                    item.append(swatch, text);
                    legend.append(item);
                });
            }

            async function loadStatistics() {

                const year = yearSelect.value;
                const month = monthSelect.value;


                /*
                |--------------------------------------------------------------------------
                | URL
                |--------------------------------------------------------------------------
                */

                const url = new URL(
                    '/dashboard/statistics',
                    window.location.origin
                );

                url.searchParams.append('year', year);

                if (month) {
                    url.searchParams.append('month', month);
                }

                if (professionalSelect?.value) {
                    url.searchParams.append('professional_id', professionalSelect.value);
                }


                /*
                |--------------------------------------------------------------------------
                | Obtener datos
                |--------------------------------------------------------------------------
                */

                const response = await fetch(url);

                const data = await response.json();

                if (!isDoctor) {
                    const selectedProfessional = professionalSelect?.selectedOptions[0];
                    const professionalLabel = selectedProfessional?.value
                        ? selectedProfessional.textContent.trim()
                        : 'todos los profesionales';
                    document.getElementById('dashboard-statistics-description').textContent =
                        `Estadísticas de ${professionalLabel} según el período seleccionado`;
                }


                /*
                |--------------------------------------------------------------------------
                | Cards
                |--------------------------------------------------------------------------
                */

                document.getElementById('totalPatients').textContent =
                    data.totalPatients;

                document.getElementById('totalAppointments').textContent =
                    data.totalAppointments;

                document.getElementById('totalBookings').textContent =
                    data.totalBookings;

                document.getElementById('ConfirmBookings').textContent =
                    data.ConfirmBookings;

                const today = data.todayAppointments;
                document.getElementById('today-total').textContent = today.total;
                document.getElementById('today-scheduled').textContent = today.scheduled;
                document.getElementById('today-confirmed').textContent = today.confirmed;
                document.getElementById('today-in-progress').textContent = today.inProgress;
                document.getElementById('today-attended').textContent = today.attended;
                document.getElementById('today-no-show').textContent = today.noShow;
                document.getElementById('today-cancelled').textContent = today.cancelled;

                /*
                |--------------------------------------------------------------------------
                | Estados
                |--------------------------------------------------------------------------
                */

                const statuses = {
                    0: 'Agendadas',
                    1: 'Confirmadas',
                    2: 'En atención',
                    3: 'Atendidas',
                    4: 'No asistió',
                    5: 'Canceladas'
                };
                const statusColors = {
                    0: '#3B82F6',
                    1: '#10B981',
                    2: '#F59E0B',
                    3: '#6366F1',
                    4: '#EF4444',
                    5: '#6B7280'
                };

                const attendedCount = data.chartData
                    .filter(item => Number(item.status) === 3)
                    .reduce((sum, item) => sum + Number(item.total), 0);
                const noShowCount = data.chartData
                    .filter(item => Number(item.status) === 4)
                    .reduce((sum, item) => sum + Number(item.total), 0);
                const attendanceNoData = document.getElementById('attendanceNoData');

                if (attendanceChart) {
                    attendanceChart.destroy();
                    attendanceChart = null;
                }

                if (attendedCount + noShowCount > 0) {
                    attendanceNoData.classList.add('hidden');
                    renderDoughnutLegend('attendanceChartLegend', ['Atendidas', 'No asistieron'], ['#10B981', '#EF4444']);
                    attendanceChart = new Chart(document.getElementById('attendanceChart'), {
                        type: 'doughnut',
                        data: {
                            labels: ['Atendidas', 'No asistieron'],
                            datasets: [{
                                data: [attendedCount, noShowCount],
                                backgroundColor: ['#10B981', '#EF4444'],
                                borderColor: '#ffffff',
                                borderWidth: 2
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { display: false } }
                        }
                    });
                } else {
                    attendanceNoData.classList.remove('hidden');
                    renderDoughnutLegend('attendanceChartLegend', [], []);
                }

                /*
                |--------------------------------------------------------------------------
                | Labels del gráfico
                |--------------------------------------------------------------------------
                */

                let labels = [];
                let periods = [];

                let chartTitle = 'Estado de citas por mes';


                if (month) {

                    /*
                    |--------------------------------------------------------------------------
                    | Vista diaria
                    |--------------------------------------------------------------------------
                    */

                    const selectedMonth = parseInt(month);

                    const daysInMonth = new Date(
                        year,
                        selectedMonth,
                        0
                    ).getDate();


                    for (let day = 1; day <= daysInMonth; day++) {

                        labels.push(day);
                        periods.push(day);

                    }


                    const monthName =
                        monthSelect.options[monthSelect.selectedIndex].text;


                    chartTitle =
                        `Estado de citas por día — ${monthName} ${year}`;


                } else {

                    /*
                    |--------------------------------------------------------------------------
                    | Vista mensual
                    |--------------------------------------------------------------------------
                    */

                    labels = [
                        'Enero',
                        'Febrero',
                        'Marzo',
                        'Abril',
                        'Mayo',
                        'Junio',
                        'Julio',
                        'Agosto',
                        'Septiembre',
                        'Octubre',
                        'Noviembre',
                        'Diciembre'
                    ];


                    periods = Array.from({
                            length: 12
                        },
                        (_, index) => index + 1
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Crear datasets
                |--------------------------------------------------------------------------
                */

                const datasets = Object.entries(statuses).map(
                    ([status, label]) => {

                        const values = periods.map(period => {

                            const item = data.chartData.find(
                                item =>
                                item.period == period &&
                                item.status == status
                            );

                            return item ? item.total : 0;

                        });


                        return {
                            label: label,
                            data: values,

                            backgroundColor: statusColors[status],
                            borderColor: statusColors[status],

                            borderWidth: 1,
                            borderRadius: 4
                        };

                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Actualizar título
                |--------------------------------------------------------------------------
                */

                document.getElementById('appointmentsChartTitle').textContent =
                    chartTitle;


                /*
                |--------------------------------------------------------------------------
                | Destruir gráfico anterior
                |--------------------------------------------------------------------------
                */

                if (appointmentsChart) {
                    appointmentsChart.destroy();
                }


                /*
                |--------------------------------------------------------------------------
                | Crear gráfico
                |--------------------------------------------------------------------------
                */

                appointmentsChart = new Chart(
                    document.getElementById('appointmentsChart'), {
                        type: 'bar',

                        data: {
                            labels: labels,
                            datasets: datasets
                        },

                        options: {
                            responsive: true,
                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    position: 'bottom'
                                }
                            },

                            scales: {

                                x: {
                                    stacked: false
                                },

                                y: {
                                    beginAtZero: true,

                                    ticks: {
                                        precision: 0
                                    }
                                }

                            }
                        }
                    }
                );


                /*
                |--------------------------------------------------------------------------
                | Gráfico de pacientes por previsión
                |--------------------------------------------------------------------------
                */

                const insuranceLabels = data.patientsByInsurance.map(
                    item => item.insurance
                );

                const insuranceValues = data.patientsByInsurance.map(
                    item => Number(item.total)
                );

                const insuranceColors = [
                    '#3B82F6',
                    '#10B981',
                    '#F59E0B',
                    '#6366F1',
                    '#EF4444',
                    '#8B5CF6',
                    '#EC4899',
                    '#14B8A6'
                ];


                /*
                |--------------------------------------------------------------------------
                | Verificar si existen pacientes
                |--------------------------------------------------------------------------
                */

                const totalInsurancePatients = insuranceValues.reduce(
                    (total, value) => total + value,
                    0
                );


                const insuranceNoData =
                    document.getElementById('insuranceNoData');


                if (insuranceChart) {
                    insuranceChart.destroy();
                    insuranceChart = null;
                }


                if (totalInsurancePatients === 0) {

                    insuranceNoData.classList.remove('hidden');
                    renderDoughnutLegend('insuranceChartLegend', [], []);

                } else {

                    insuranceNoData.classList.add('hidden');
                    renderDoughnutLegend('insuranceChartLegend', insuranceLabels, insuranceColors);

                    insuranceChart = new Chart(
                        document.getElementById('insuranceChart'), {
                            type: 'doughnut',

                            data: {
                                labels: insuranceLabels,

                                datasets: [{
                                    data: insuranceValues,

                                    backgroundColor: insuranceColors,

                                    borderWidth: 1
                                }]
                            },

                            options: {
                                responsive: true,
                                maintainAspectRatio: false,

                                plugins: {
                                    legend: {
                                        display: false
                                    }
                                }
                            }
                        }
                    );
                }



                /*
                |--------------------------------------------------------------------------
                | Gráfico de atenciones por especialidad
                |--------------------------------------------------------------------------
                */

                const specialtyLabels = data.appointmentsBySpecialty.map(
                    item => item.specialty
                );

                const specialtyValues = data.appointmentsBySpecialty.map(
                    item => Number(item.total)
                );


                /*
                |--------------------------------------------------------------------------
                | Destruir gráfico anterior
                |--------------------------------------------------------------------------
                */

                if (specialtyChart) {
                    specialtyChart.destroy();
                    specialtyChart = null;
                }


                /*
                |--------------------------------------------------------------------------
                | Crear gráfico
                |--------------------------------------------------------------------------
                */

                specialtyChart = new Chart(
                    document.getElementById('specialtyChart'), {
                        type: 'bar',

                        data: {
                            labels: specialtyLabels,

                            datasets: [{
                                label: 'Atenciones',

                                data: specialtyValues,

                                backgroundColor: '#6366F1',

                                borderColor: '#6366F1',

                                borderWidth: 1,

                                borderRadius: 5
                            }]
                        },

                        options: {
                            responsive: true,

                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    display: false
                                }
                            },

                            scales: {
                                y: {
                                    beginAtZero: true,

                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    }
                );

                /*
                |--------------------------------------------------------------------------
                | Gráfico: Pacientes ingresados al sistema
                |--------------------------------------------------------------------------
                */

                let patientsRegisteredLabels = [];
                let patientsRegisteredPeriods = [];

                const patientsAreProfessionalSpecific = isDoctor || Boolean(professionalSelect?.value);
                const patientsMetricLabel = patientsAreProfessionalSpecific ? 'Pacientes atendidos' : 'Pacientes ingresados';
                let patientsRegisteredTitle = patientsAreProfessionalSpecific ? 'Pacientes atendidos por mes' : 'Pacientes ingresados por mes';


                if (month) {

                    // Vista diaria

                    const selectedMonth = parseInt(month);

                    const daysInMonth = new Date(
                        year,
                        selectedMonth,
                        0
                    ).getDate();


                    for (let day = 1; day <= daysInMonth; day++) {

                        patientsRegisteredLabels.push(day);
                        patientsRegisteredPeriods.push(day);

                    }


                    const monthName =
                        monthSelect.options[monthSelect.selectedIndex].text;


                    patientsRegisteredTitle = patientsAreProfessionalSpecific ?
                        `Pacientes atendidos por día — ${monthName} ${year}` :
                        `Pacientes ingresados por día — ${monthName} ${year}`;


                } else {

                    // Vista mensual

                    patientsRegisteredLabels = [
                        'Enero',
                        'Febrero',
                        'Marzo',
                        'Abril',
                        'Mayo',
                        'Junio',
                        'Julio',
                        'Agosto',
                        'Septiembre',
                        'Octubre',
                        'Noviembre',
                        'Diciembre'
                    ];


                    patientsRegisteredPeriods = Array.from({
                            length: 12
                        },
                        (_, index) => index + 1
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | Valores
                |--------------------------------------------------------------------------
                */

                const patientsRegisteredValues =
                    patientsRegisteredPeriods.map(period => {

                        const item = data.patientsRegistered.find(
                            item => item.period == period
                        );

                        return item ? Number(item.total) : 0;

                    });


                /*
                |--------------------------------------------------------------------------
                | Título
                |--------------------------------------------------------------------------
                */

                document.getElementById(
                    'patientsRegisteredChartTitle'
                ).textContent = patientsRegisteredTitle;


                /*
                |--------------------------------------------------------------------------
                | Destruir gráfico anterior
                |--------------------------------------------------------------------------
                */

                if (patientsRegisteredChart) {
                    patientsRegisteredChart.destroy();
                }


                /*
                |--------------------------------------------------------------------------
                | Crear gráfico
                |--------------------------------------------------------------------------
                */

                patientsRegisteredChart = new Chart(
                    document.getElementById('patientsRegisteredChart'), {
                        type: 'line',

                        data: {
                            labels: patientsRegisteredLabels,

                            datasets: [{
                                label: patientsMetricLabel,

                                data: patientsRegisteredValues,

                                borderColor: '#6366F1',

                                backgroundColor: '#6366F1',

                                borderWidth: 2,

                                tension: 0.3,

                                pointRadius: 5,

                                pointHoverRadius: 7,

                                fill: false
                            }]
                        },

                        options: {
                            responsive: true,

                            maintainAspectRatio: false,

                            plugins: {
                                legend: {
                                    display: false
                                }
                            },

                            scales: {
                                y: {
                                    beginAtZero: true,

                                    ticks: {
                                        precision: 0
                                    }
                                }
                            }
                        }
                    }
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Eventos
            |--------------------------------------------------------------------------
            */

            yearSelect.addEventListener(
                'change',
                loadStatistics
            );

            monthSelect.addEventListener(
                'change',
                loadStatistics
            );

            if (professionalSelect && window.jQuery) {
                $('#professional-filter').on('change', loadStatistics);
            } else {
                professionalSelect?.addEventListener('change', loadStatistics);
            }


            /*
            |--------------------------------------------------------------------------
            | Cargar al entrar
            |--------------------------------------------------------------------------
            */

            loadStatistics();
        </script>
    @endpush
