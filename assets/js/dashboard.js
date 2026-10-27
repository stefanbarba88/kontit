// Grafikoni. Svaki <canvas data-chart='{...}'> nosi konfiguraciju; charts.js je prilagođava našem izgledu (DESIGN.md 10).
// Chart.js se učitava tek ako na stranici ima grafikona; pri promeni teme se iscrtava ponovo.
const canvases = document.querySelectorAll('canvas[data-chart]');

if (canvases.length) {
    Promise.all([import('chart.js'), import('./charts.js')]).then(([{ Chart, registerables }, { applyDefaults, decorate }]) => {
        Chart.register(...registerables);

        const draw = () => {
            applyDefaults(Chart);
            canvases.forEach((canvas) => {
                Chart.getChart(canvas)?.destroy();
                new Chart(canvas, decorate(JSON.parse(canvas.dataset.chart)));
            });
        };

        draw();
        document.addEventListener('kt:themechange', draw);
    });
}
