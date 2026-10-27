// Grafikoni na stranama: svaki element sa data-chart='{...}' (vidi charts.js). ApexCharts se učitava
// tek ako na stranici ima grafikona, a pri promeni teme se grafikoni iscrtavaju ponovo.
const hosts = document.querySelectorAll('[data-chart]');

if (hosts.length) {
    Promise.all([import('apexcharts'), import('./charts.js')]).then(([{ default: ApexCharts }, { toApex }]) => {
        const charts = new Map();

        const draw = () => {
            hosts.forEach((host) => {
                // stari šabloni koriste <canvas>; ApexCharts crta u div, pa se canvas zameni
                let target = host;
                if (host.tagName === 'CANVAS') {
                    target = document.createElement('div');
                    target.dataset.chart = host.dataset.chart;
                    target.className = 'kt-chart';
                    host.replaceWith(target);
                }
                charts.get(target)?.destroy();
                const chart = new ApexCharts(target, toApex(JSON.parse(target.dataset.chart)));
                chart.render();
                charts.set(target, chart);
            });
        };

        draw();
        document.addEventListener('kt:themechange', () => {
            // posle zamene canvas -> div treba ponovo naći elemente
            const current = document.querySelectorAll('[data-chart]');
            current.forEach((el) => {
                charts.get(el)?.destroy();
                const chart = new ApexCharts(el, toApex(JSON.parse(el.dataset.chart)));
                chart.render();
                charts.set(el, chart);
            });
        });
    });
}
