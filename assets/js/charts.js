// Globalni Chart.js podaci (DESIGN.md sekcija 10): boje iz CSS varijabli, osvežavanje na kt:themechange.
const css = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

export const palette = () => [1, 2, 3, 4, 5, 6].map((i) => css(`--kt-chart-${i}`));

const fmtMoney = (v) => `${Number(v).toLocaleString('sr-RS', { maximumFractionDigits: 0 })} RSD`;

export function applyDefaults(Chart) {
    Chart.defaults.font.family = css('--kt-font-ui') || 'Inter, sans-serif';
    Chart.defaults.font.size = 12;
    Chart.defaults.color = css('--kt-text-2');
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.borderColor = css('--kt-border');
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.tooltip.backgroundColor = css('--kt-surface');
    Chart.defaults.plugins.tooltip.titleColor = css('--kt-text');
    Chart.defaults.plugins.tooltip.bodyColor = css('--kt-text-2');
    Chart.defaults.plugins.tooltip.borderColor = css('--kt-border-strong');
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 10;
    Chart.defaults.elements.line.borderWidth = 2;
    Chart.defaults.elements.line.tension = 0.3;
    Chart.defaults.elements.line.borderCapStyle = 'round';
    Chart.defaults.elements.point.radius = 0;
    Chart.defaults.elements.point.hoverRadius = 4;
    Chart.defaults.elements.bar.borderRadius = { topLeft: 6, topRight: 6 };
    Chart.defaults.datasets.bar.categoryPercentage = 0.7;
}

/**
 * Prilagođava konfiguraciju iz šablona našem izgledu: horizontalne tanke linije mreže, bez okvira,
 * serije u bojama palete, gradijent ispod linije, iznosi sa RSD.
 */
export function decorate(config) {
    const colors = palette();
    const cfg = JSON.parse(JSON.stringify(config));
    const type = cfg.type;
    cfg.options = cfg.options || {};
    cfg.options.scales = cfg.options.scales || {};

    if (type === 'bar' || type === 'line') {
        const x = (cfg.options.scales.x = cfg.options.scales.x || {});
        const y = (cfg.options.scales.y = cfg.options.scales.y || {});
        x.grid = { display: false };
        x.border = { display: false };
        y.grid = { color: css('--kt-border') };
        y.border = { display: false };
        (cfg.data.datasets || []).forEach((ds, i) => {
            const c = colors[i % colors.length];
            ds.borderColor = ds.borderColor && type === 'line' ? c : ds.borderColor;
            if (type === 'bar') {
                ds.backgroundColor = c;
            } else {
                ds.borderColor = c;
                ds.backgroundColor = (ctx) => {
                    const { chart } = ctx;
                    const area = chart.chartArea;
                    if (!area) {
                        return 'transparent';
                    }
                    const g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
                    g.addColorStop(0, `${c}2E`);
                    g.addColorStop(1, `${c}00`);
                    return g;
                };
                ds.fill = true;
            }
        });
    }
    if (type === 'doughnut' || type === 'pie') {
        (cfg.data.datasets || []).forEach((ds) => {
            ds.backgroundColor = (ds.data || []).map((_, i) => colors[i % colors.length]);
            ds.borderColor = css('--kt-surface');
            ds.borderWidth = 2;
        });
        cfg.options.cutout = cfg.options.cutout || '68%';
    }
    cfg.options.plugins = cfg.options.plugins || {};
    cfg.options.plugins.tooltip = Object.assign({ callbacks: { label: (c) => ` ${c.dataset.label ? `${c.dataset.label}: ` : ''}${/RSD/.test(c.dataset.label || '') ? fmtMoney(c.parsed.y ?? c.parsed) : (c.parsed.y ?? c.parsed)}` } }, cfg.options.plugins.tooltip || {});

    return cfg;
}
