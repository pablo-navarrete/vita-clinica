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
                                Dashboard
                            </h2>

                            <p class="text-sm text-gray-500 mt-1">
                                Estadísticas Según período seleccionado
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
                                    @for ($year = now()->year; $year >= now()->year - 5; $year--)
                                        <option value="{{ $year }}">
                                            {{ $year }}
                                        </option>
                                    @endfor
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

                        </div>

                    </div>


                </div>
                <!-- Separador -->
                <div class="border-t border-gray-200 my-6"></div>

                <div class="p-6">
                    <!-- Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">

                        <!-- Pacientes -->
                        <div class="bg-gray-50 rounded-lg p-5">
                            <p class="text-sm font-medium text-gray-500">
                                Pacientes atendidos
                            </p>

                            <p id="totalPatients" class="text-3xl font-bold text-gray-800 mt-2">
                                0
                            </p>


                        </div>

                        <!-- Atenciones -->
                        <div class="bg-gray-50 rounded-lg p-5">
                            <p class="text-sm font-medium text-gray-500">
                                Atenciones Realizadas
                            </p>

                            <p id="totalAppointments" class="text-3xl font-bold text-gray-800 mt-2">
                                0
                            </p>


                        </div>

                        <!-- Citas agendadas-->
                        <div class="bg-gray-50 rounded-lg p-5">
                            <p class="text-sm font-medium text-gray-500">
                                Citas Agendadas
                            </p>

                            <p id="totalBookings" class="text-3xl font-bold text-gray-800 mt-2">
                                0
                            </p>


                        </div>

                        <!-- Citas confirmadas-->
                        <div class="bg-gray-50 rounded-lg p-5">
                            <p class="text-sm font-medium text-gray-500">
                                Citas Confirmadas
                            </p>

                            <p id="ConfirmBookings" class="text-3xl font-bold text-gray-800 mt-2">
                                0
                            </p>


                        </div>

                    </div>


                    <!-- Gráficos -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">

                        <!-- Gráfico: Estado de citas -->
                        <div>

                            <div class="mb-4">
                                <h3 id="appointmentsChartTitle" class="text-lg font-semibold text-gray-800">
                                    Estado de citas por mes
                                </h3>
                            </div>

                            <div class="relative w-full h-[500px]">
                                <canvas id="appointmentsChart"></canvas>
                            </div>

                        </div>

<!-- Gráfico: Pacientes ingresados -->
<div>

    <div class="mb-4">
        <h3 id="patientsRegisteredChartTitle"
            class="text-lg font-semibold text-gray-800">
            Pacientes ingresados por mes
        </h3>
    </div>

    <div class="relative w-full h-[500px]">
        <canvas id="patientsRegisteredChart"></canvas>
    </div>

</div>
                       

                        <!-- Atenciones por especialidad -->
                        <div>

                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Atenciones por especialidad
                                </h3>
                            </div>

                            <div class="relative w-full h-[500px]">
                                <canvas id="specialtyChart"></canvas>
                            </div>

                        </div>


                         <!-- Gráfico: Pacientes por previsión -->
                        <div>

                            <div class="mb-4">
                                <h3 class="text-lg font-semibold text-gray-800">
                                    Pacientes por previsión
                                </h3>
                            </div>

                            <div id="insuranceChartContainer" class="relative w-full h-[500px]">

                                <canvas id="insuranceChart"></canvas>

                                <!-- Mensaje cuando no hay datos -->
                                <div id="insuranceNoData"
                                    class="hidden absolute inset-0 flex items-center justify-center text-center">

                                    <div>
                                        <p class="text-gray-500 font-medium">
                                            No hay pacientes atendidos
                                        </p>

                                        <p class="text-sm text-gray-400 mt-1">
                                            en el período seleccionado
                                        </p>
                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                </div>

            </div>

        </div>
    @endsection

    @push('scripts')
        <script>
            const yearSelect = document.getElementById('year');
            const monthSelect = document.getElementById('month');

            let appointmentsChart = null;
            let insuranceChart = null;
            let specialtyChart = null;
            let patientsRegisteredChart = null;

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


                /*
                |--------------------------------------------------------------------------
                | Obtener datos
                |--------------------------------------------------------------------------
                */

                const response = await fetch(url);

                const data = await response.json();


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

                } else {

                    insuranceNoData.classList.add('hidden');

                    insuranceChart = new Chart(
                        document.getElementById('insuranceChart'), {
                            type: 'pie',

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
                                maintainAspectRatio: true,

                                plugins: {
                                    legend: {
                                        position: 'bottom'
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
    document.getElementById('specialtyChart'),
    {
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

let patientsRegisteredTitle = 'Pacientes ingresados por mes';


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


    patientsRegisteredTitle =
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


    patientsRegisteredPeriods = Array.from(
        { length: 12 },
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
    document.getElementById('patientsRegisteredChart'),
    {
        type: 'line',

        data: {
            labels: patientsRegisteredLabels,

            datasets: [{
                label: 'Pacientes ingresados',

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


            /*
            |--------------------------------------------------------------------------
            | Cargar al entrar
            |--------------------------------------------------------------------------
            */

            loadStatistics();
        </script>
    @endpush
