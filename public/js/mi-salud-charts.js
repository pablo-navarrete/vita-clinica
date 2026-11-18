(() => {
    const color = '#6366F1'; // Dashboard de clínicas: misma paleta y opciones de Chart.js.
    window.PortalCharts = {
        dispose(root = document) {
            root.querySelectorAll('[data-attention-chart]').forEach(canvas => window.Chart?.getChart(canvas)?.destroy());
        },
        mount(root = document) {
            if (!window.Chart) return;
            root.querySelectorAll('[data-attention-chart]').forEach(canvas => {
                if (Chart.getChart(canvas)) return;
                const data = JSON.parse(canvas.dataset.attentionChart);
                const ranking = data.ranking === true;
                new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: data.labels,
                        datasets: [{label: 'Atenciones realizadas', data: data.values,
                            backgroundColor: data.values.map((_, index) => !data.month || data.month === index + 1 ? color : '#6366F140')}],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: matchMedia('(prefers-reduced-motion: reduce)').matches ? false : undefined,
                        indexAxis: ranking ? 'y' : 'x',
                        plugins: {legend: {position: 'bottom', display: !ranking}},
                        scales: ranking
                            ? {x: {beginAtZero: true, ticks: {precision: 0}}}
                            : {x: {stacked: false}, y: {beginAtZero: true, ticks: {precision: 0}}},
                    },
                });
            });
        },
    };
    window.PortalCharts.mount();
})();
