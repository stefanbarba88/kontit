// Jedini date picker u aplikaciji (flatpickr). Kači se preko data atributa:
//   data-datepicker           jedan datum (Symfony DateType single_text dobija ga iz DateTypeExtension)
//   data-datepicker-range     opseg (vrednost "Y-m-d do Y-m-d")
//   data-datepicker-inline    kalendar je stalno otvoren (npr. /_design)
//   data-shortcuts            prečice Danas / Ovaj mesec / Prošli mesec (opseg ih ima uvek)
// Vrednost za server je 'Y-m-d', prikaz je 'd.m.Y.'. Locale je naš, kompletan (bez zavisnosti od flatpickr/dist/l10n).
import flatpickr from 'flatpickr';
import { info, remember, cache } from './non-working.js';

// flatpickr nizove dana UVEK čita od nedelje; ponedeljak kao prvi dan daje samo firstDayOfWeek: 1
export const srLatn = {
    weekdays: {
        shorthand: ['Ned', 'Pon', 'Uto', 'Sre', 'Čet', 'Pet', 'Sub'],
        longhand: ['Nedelja', 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota'],
    },
    months: {
        shorthand: ['Jan', 'Feb', 'Mar', 'Apr', 'Maj', 'Jun', 'Jul', 'Avg', 'Sep', 'Okt', 'Nov', 'Dec'],
        longhand: ['Januar', 'Februar', 'Mart', 'April', 'Maj', 'Jun', 'Jul', 'Avgust', 'Septembar', 'Oktobar', 'Novembar', 'Decembar'],
    },
    daysInMonth: [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31],
    firstDayOfWeek: 1,
    ordinal: () => '.',
    rangeSeparator: ' do ',
    weekAbbreviation: 'Ned.',
    scrollTitle: 'Skroluj za promenu',
    toggleTitle: 'Klikni za promenu',
    amPM: ['AM', 'PM'],
    yearAriaLabel: 'Godina',
    monthAriaLabel: 'Mesec',
    hourAriaLabel: 'Sat',
    minuteAriaLabel: 'Minut',
    time_24hr: true,
};

function addShortcuts(fp) {
    const bar = document.createElement('div');
    bar.className = 'kt-fp-shortcuts';
    const today = new Date();
    const shortcuts = {
        Danas: [today, today],
        'Ovaj mesec': [new Date(today.getFullYear(), today.getMonth(), 1), new Date(today.getFullYear(), today.getMonth() + 1, 0)],
        'Prošli mesec': [new Date(today.getFullYear(), today.getMonth() - 1, 1), new Date(today.getFullYear(), today.getMonth(), 0)],
    };
    Object.entries(shortcuts).forEach(([label, range]) => {
        const b = document.createElement('button');
        b.type = 'button';
        b.textContent = label;
        b.addEventListener('click', () => {
            fp.setDate(fp.config.mode === 'range' ? range : range[0], true);
            fp.close();
        });
        bar.appendChild(b);
    });
    fp.calendarContainer.appendChild(bar);
}

// učitava neradne dane prikazane godine (i susedne, zbog dana iz susednih meseci), pa ponovo crta kalendar
function showYear(fp) {
    const year = fp.currentYear;
    const missing = [year - 1, year, year + 1].filter((y) => !(y in cache));
    if (missing.length) {
        Promise.all(missing.map(remember)).then(() => fp.redraw());
    }
}

export function initDatepickers(root = document) {
    root.querySelectorAll('[data-datepicker]:not(.flatpickr-input), [data-datepicker-range]:not(.flatpickr-input)').forEach((el) => {
        const range = el.hasAttribute('data-datepicker-range');
        const inline = el.hasAttribute('data-datepicker-inline');
        flatpickr(el, {
            locale: srLatn,
            dateFormat: 'Y-m-d',
            altInput: !inline,
            altFormat: 'd.m.Y.',
            allowInput: !inline,
            disableMobile: true,
            inline,
            mode: range ? 'range' : 'single',
            minDate: el.dataset.minDate || null,
            defaultDate: el.dataset.defaultDate || undefined,
            onReady: (_, __, fp) => {
                fp.altInput?.classList.add('kt-date');
                showYear(fp);
                if (range || el.hasAttribute('data-shortcuts')) {
                    addShortcuts(fp);
                }
            },
            onYearChange: (_, __, fp) => showYear(fp),
            onMonthChange: (_, __, fp) => showYear(fp),
            onOpen: (_, __, fp) => showYear(fp),
            // neradni dani su prigušeni: dan u nedelji (vikend), praznik i kolektivni odmor sa nazivom u tooltip-u
            onDayCreate: (_, __, ___, dayElem) => {
                const date = dayElem.dateObj;
                const day = info(date, cache[date.getFullYear()] || {});
                if (day) {
                    dayElem.classList.add(day.type === 'WEEKLY' ? 'kt-weekend' : 'kt-holiday');
                    dayElem.title = day.name;
                }
            },
        });
    });
}
