// Grafikoni (DESIGN.md sekcija 10): ApexCharts, boje iz CSS promenljivih, osvežavanje na kt:themechange.
// Šabloni opisuju grafikon jednostavnim JSON-om u data-chart: {type: line|bar|doughnut, data: {labels, datasets: [{label, data}]}, options}.
// Ovaj modul ga prevodi u ApexCharts opcije, pa šabloni ne zavise od biblioteke.
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

export const palette = () => [1, 2, 3, 4, 5, 6].map((i) => css(`--kt-chart-${i}`));

const money = (v) => `${Number(v).toLocaleString('sr-RS', { maximumFractionDigits: 0 })} RSD`;
const plain = (v) => Number(v).toLocaleString('sr-RS', { maximumFractionDigits: 1 });

/**
 * @param {{type: string, data: {labels: string[], datasets: {label?: string, data: number[]}[]}, options?: object}} spec
 */
export function toApex(spec) {
    const opts = spec.options || {};
    const datasets = spec.data?.datasets || [];
    const labels = spec.data?.labels || [];
    const isMoney = datasets.some((d) => /RSD/.test(d.label || ''));
    const legendHidden = opts.plugins?.legend?.display === false;
    const horizontal = opts.indexAxis === 'y';
    const mode = document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'dark' : 'light';
    const text2 = css('--kt-text-2');

    const base = {
        chart: {
            fontFamily: css('--kt-font-ui') || 'Inter, sans-serif',
            foreColor: text2,
            background: 'transparent',
            toolbar: { show: false },
            zoom: { enabled: false },
            animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 240 },
            height: '100%',
            parentHeightOffset: 0,
        },
        theme: { mode },
        colors: palette(),
        legend: {
            show: !legendHidden && (datasets.length > 1 || spec.type === 'doughnut'),
            position: spec.type === 'doughnut' ? (opts.plugins?.legend?.position === 'right' ? 'right' : 'bottom') : 'top',
            horizontalAlign: 'left',
            fontSize: '12px',
            markers: { size: 5, shape: 'circle' },
            labels: { colors: text2 },
        },
        dataLabels: { enabled: false },
        tooltip: {
            theme: mode,
            y: { formatter: (v) => (isMoney ? money(v) : plain(v)) },
        },
    };

    if (spec.type === 'doughnut' || spec.type === 'pie') {
        return {
            ...base,
            chart: { ...base.chart, type: 'donut' },
            series: (datasets[0]?.data || []).map(Number),
            labels,
            stroke: { width: 2, colors: [css('--kt-surface')] },
            plotOptions: { pie: { donut: { size: '68%' } } },
        };
    }

    const isLine = spec.type === 'line';

    return {
        ...base,
        chart: { ...base.chart, type: isLine ? 'area' : 'bar' },
        series: datasets.map((d, i) => ({ name: d.label || `Serija ${i + 1}`, data: (d.data || []).map(Number) })),
        xaxis: {
            categories: labels,
            axisBorder: { show: false },
            axisTicks: { show: false },
            labels: { style: { fontSize: '12px' }, ...(horizontal ? { formatter: (v) => (isMoney ? money(v) : plain(v)) } : {}) },
        },
        yaxis: {
            min: opts.scales?.y?.beginAtZero || opts.scales?.x?.beginAtZero ? 0 : undefined,
            labels: { style: { fontSize: '12px' }, formatter: horizontal ? undefined : (v) => (isMoney ? money(v) : plain(v)) },
        },
        grid: {
            borderColor: css('--kt-border'),
            strokeDashArray: 0,
            xaxis: { lines: { show: horizontal } },
            yaxis: { lines: { show: !horizontal } },
            padding: { left: 4, right: 8 },
        },
        stroke: isLine ? { curve: 'smooth', width: 2, lineCap: 'round' } : { width: 0 },
        fill: isLine
            ? { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.18, opacityTo: 0, stops: [0, 100] } }
            : { opacity: 1 },
        markers: { size: 0, hover: { size: 4 } },
        plotOptions: { bar: { horizontal, borderRadius: 6, borderRadiusApplication: 'end', columnWidth: '45%', barHeight: '55%' } },
    };
}
