// Raspored posla u formi (SPEC 8g.3): prikazuje samo polja izabranog tipa, a opis i narednih 5 rokova dolaze AJAX-om.
import { Tooltip } from 'bootstrap';
import { ajax } from './ajax.js';

const FIELDS = ['interval', 'dayOfWeek', 'dayOfMonth', 'monthOfQuarter', 'month', 'workingDaysOnly', 'leadDays', 'nonWorkingDayShift'];
const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/**
 * Pokreće jedan sklop polja rasporeda (i onaj koji je naknadno ubačen u stranicu, npr. u prozor "Prilagodi za ovog klijenta").
 * Vraća { data(), fill(values) }: trenutne vrednosti polja i popunjavanje iz niza (isti oblik kao Schedule::toArray()).
 */
export function initSchedule(box) {
    const preview = box.querySelector('.kt-schedule-preview');
    const describe = box.querySelector('[data-kt-schedule-describe]');
    const dates = box.querySelector('[data-kt-schedule-dates]');
    const field = (name) => box.querySelector(`[name$="[${name}]"]`);
    const type = () => box.querySelector('[data-kt-schedule-type]:checked')?.value || '';
    let timer = null;
    let seq = 0;

    const data = () => {
        const out = { type: type() };
        FIELDS.forEach((name) => {
            const el = field(name);
            if (el) {
                out[name] = el.type === 'checkbox' ? el.checked : el.value;
            }
        });
        return out;
    };

    const toggle = () => {
        const current = type();
        box.querySelectorAll('[data-kt-schedule-for]').forEach((el) => {
            const hide = !el.dataset.ktScheduleFor.split(' ').includes(current);
            el.hidden = hide;
            const col = el.parentElement?.matches('[class*="col-"]') ? el.parentElement : null;
            if (col) {
                col.hidden = hide;
            }
        });
        // prazan tip ("Kao u poslu") ili Po potrebi: nema polja; mreža se sakriva cela da ne ostane prazan prostor
        const grid = box.querySelector('.kt-schedule-fields');
        grid.hidden = ![...grid.querySelectorAll('[data-kt-schedule-for]')].some((el) => !el.hidden);
    };

    const render = async () => {
        const mine = ++seq;
        if (!type()) {
            preview.hidden = true;
            return;
        }
        try {
            const { data: result } = await ajax('POST', box.dataset.previewUrl, { schedule: data(), anchor: box.dataset.anchor || null });
            if (mine !== seq) {
                return;
            }
            describe.textContent = result.describe;
            describe.classList.toggle('text-danger', Boolean(result.invalid));
            dates.innerHTML = result.occurrences.map((o) => (o.shifted
                ? `<span class="kt-schedule-date is-shifted" data-bs-toggle="tooltip" title="${escape(o.tooltip)}"><svg viewBox="0 0 24 24" class="kt-icon" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4m4 4H4"/></g></svg>${escape(o.date)}</span>`
                : `<span class="kt-schedule-date">${escape(o.date)}</span>`)).join('<span class="kt-schedule-sep">·</span>');
            dates.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => new Tooltip(el));
            preview.hidden = result.describe === '';
        } catch {
            preview.hidden = true;
        }
    };

    const update = () => {
        toggle();
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 250);
    };

    const fill = (values = {}) => {
        const radio = box.querySelector(`[data-kt-schedule-type][value="${values.type || ''}"]`);
        if (radio) {
            radio.checked = true;
        }
        FIELDS.forEach((name) => {
            const el = field(name);
            if (!el || values[name] === undefined || values[name] === null) {
                return;
            }
            if (el.type === 'checkbox') {
                el.checked = Boolean(values[name]);
            } else {
                el.value = String(values[name]);
            }
        });
        // tip bez dana u mesecu (npr. nedeljni) kad se pređe na mesečni: razuman početni dan umesto "poslednji dan"
        if (values.dayOfMonth === undefined || values.dayOfMonth === null) {
            const day = field('dayOfMonth');
            if (day) {
                day.value = '15';
            }
        }
        update();
    };

    box.dataset.ktReady = '1';
    box.addEventListener('change', update);
    box.addEventListener('input', update);
    toggle();
    render();

    return { data, fill };
}

document.querySelectorAll('[data-kt-schedule]:not([data-kt-ready])').forEach((box) => {
    // sklop u <template> elementu nije deo stranice, pa ga querySelectorAll ne vidi; ovo su polja koja stoje u formi
    initSchedule(box);
});
