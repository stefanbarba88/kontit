// Dropdown u tabeli ili kartici (npr. "tri tačke" u redu) ne sme da se seče o overflow roditelja (.table-responsive).
// Pre Bootstrap data-api handlera pravimo instancu sa Popper strategijom "fixed": meni se pozicionira prema ekranu,
// a flip ga sam okreće nagore za poslednje redove. Važi i za redove dodate kasnije (AJAX).
// Bootstrap svoj delegirani handler kači na document u capture fazi, zato se ovde sluša na window (okida se pre njega).
const SELECTOR = '[data-bs-toggle="dropdown"]';
const SCOPE = '.table-responsive, .table, .card, [data-kt-dropdown-fixed]';

const prepare = (event) => {
    const toggle = event.target.closest?.(SELECTOR);
    if (!toggle || !toggle.closest(SCOPE) || toggle.closest('.kt-topbar, .kt-sidebar') || !window.bootstrap) {
        return;
    }
    if (!window.bootstrap.Dropdown.getInstance(toggle)) {
        // granica je ekran (ne .table-responsive), uz 8px razmaka od ivice
        const bounds = { boundary: document.documentElement, rootBoundary: 'viewport', padding: 8 };
        window.bootstrap.Dropdown.getOrCreateInstance(toggle, {
            popperConfig: (config) => ({
                ...config,
                strategy: 'fixed',
                modifiers: [
                    ...config.modifiers.filter((m) => !['preventOverflow', 'flip'].includes(m.name)),
                    { name: 'preventOverflow', options: bounds },
                    { name: 'flip', options: bounds },
                ],
            }),
        });
    }
};

window.addEventListener('click', prepare, true);
window.addEventListener('keydown', prepare, true);
