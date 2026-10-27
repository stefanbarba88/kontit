// KPI brojevi se pri prvom učitavanju "izbroje" od 0 do vrednosti (600 ms, ease-out).
// Upotreba: <span data-countup="1234">1.234</span>; tekst oko broja ostaje kakav jeste.
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

document.querySelectorAll('[data-countup]').forEach((el) => {
    const target = Number(el.dataset.countup);
    if (reduce || Number.isNaN(target) || !Number.isFinite(target) || target === 0) {
        return;
    }
    const decimals = Number(el.dataset.decimals || 0);
    const fmt = (v) => v.toLocaleString('sr-RS', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const started = performance.now();
    const duration = 600;
    const tick = (now) => {
        const t = Math.min(1, (now - started) / duration);
        const eased = 1 - Math.pow(1 - t, 3);
        el.textContent = fmt(target * eased);
        if (t < 1) {
            requestAnimationFrame(tick);
        } else {
            el.textContent = fmt(target);
        }
    };
    requestAnimationFrame(tick);
});
