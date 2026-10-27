// KPI brojevi se pri učitavanju "izbroje" od 0 do vrednosti (CountUp.js, 600 ms, ease-out).
// Upotreba: <span data-countup="1234">1.234</span>
import { CountUp } from 'countup.js';

if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('[data-countup]').forEach((el) => {
        const target = Number(el.dataset.countup);
        if (!Number.isFinite(target) || target === 0) {
            return;
        }
        const decimals = Number(el.dataset.decimals || 0);
        new CountUp(el, target, {
            duration: 0.6,
            decimalPlaces: decimals,
            separator: '.',
            decimal: ',',
            useEasing: true,
        }).start();
    });
}
