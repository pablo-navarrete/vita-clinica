<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Panel de Vita Clínica</h2>
                <p class="mt-1 text-sm text-gray-500">Resumen de clínicas registradas en la plataforma</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700" for="dashboard-year">
                    Año
                    <select id="dashboard-year" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach ($years as $year)
                            <option value="{{ $year }}" @selected($year === $currentYear)>{{ $year }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-2 text-sm font-medium text-gray-700" for="dashboard-month">
                    Mes
                    <select id="dashboard-month" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
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
                </label>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 xl:grid-cols-4">
                <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-medium text-gray-500">Total de clínicas</p>
                    <p id="total-clinics" class="mt-2 text-3xl font-bold text-gray-900">—</p>
                </article>
                <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-medium text-gray-500">Nuevas este mes</p>
                    <p id="new-clinics-month" class="mt-2 text-3xl font-bold text-indigo-600">—</p>
                </article>
                <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-medium text-gray-500">Clínicas activas</p>
                    <p id="active-clinics" class="mt-2 text-3xl font-bold text-emerald-600">—</p>
                </article>
                <article class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <p class="text-sm font-medium text-gray-500">Clínicas inactivas</p>
                    <p id="inactive-clinics" class="mt-2 text-3xl font-bold text-rose-600">—</p>
                </article>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200 lg:col-span-2">
                    <h3 id="clinics-monthly-title" class="mb-4 text-lg font-semibold text-gray-800">Clínicas nuevas por mes</h3>
                    <div class="relative h-80"><canvas id="clinics-monthly-chart"></canvas></div>
                </section>

                <section class="rounded-lg bg-white p-5 shadow-sm ring-1 ring-gray-200">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800">Estado de las clínicas</h3>
                    <div class="relative h-80"><canvas id="clinics-status-chart"></canvas></div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const yearSelect = document.getElementById('dashboard-year');
            const monthSelect = document.getElementById('dashboard-month');
            const monthLabels = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            let monthlyChart;
            let statusChart;


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
            async function loadClinicStatistics() {
                const url = new URL(@json(route('superadmin.dashboard.statistics')), window.location.origin);
                url.searchParams.set('year', yearSelect.value);
                if (monthSelect.value) url.searchParams.set('month', monthSelect.value);

                const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) return;
                const data = await response.json();

                document.getElementById('total-clinics').textContent = data.total;
                document.getElementById('new-clinics-month').textContent = data.newThisMonth;
                document.getElementById('active-clinics').textContent = data.active;
                document.getElementById('inactive-clinics').textContent = data.inactive;

                const labels = monthSelect.value
                    ? Array.from({ length: data.periods.length }, (_, index) => index + 1)
                    : monthLabels;
                const chartTitle = monthSelect.value
                    ? `Clínicas nuevas por día — ${monthLabels[Number(monthSelect.value) - 1]} ${yearSelect.value}`
                    : `Clínicas nuevas por mes — ${yearSelect.value}`;
                document.getElementById('clinics-monthly-title').textContent = chartTitle;

                if (monthlyChart) monthlyChart.destroy();
                monthlyChart = new Chart(document.getElementById('clinics-monthly-chart'), {
                    type: 'bar',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: chartTitle,
                            data: data.periods,
                            backgroundColor: '#6366f1',
                            borderRadius: 5,
                            maxBarThickness: 42
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } },
                        scales: {
                            x: { title: { display: true, text: monthSelect.value ? 'Día' : 'Mes' } },
                            y: { beginAtZero: true, ticks: { precision: 0 } }
                        }
                    }
                });

                if (statusChart) statusChart.destroy();
                statusChart = new Chart(document.getElementById('clinics-status-chart'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Activas', 'Inactivas'],
                        datasets: [{
                            data: [data.active, data.inactive],
                            backgroundColor: ['#10b981', '#f43f5e'],
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { position: 'bottom' } }
                    }
                });
            }

            yearSelect.addEventListener('change', loadClinicStatistics);
            monthSelect.addEventListener('change', loadClinicStatistics);
            loadClinicStatistics();
        </script>
    @endpush
</x-app-layout>
