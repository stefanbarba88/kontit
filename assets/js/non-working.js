// Neradni dani po godini (SPEC 8k) iz /ajax/calendar/non-working?year=, sa kešom u memoriji.
// Koriste ga flatpickr (prigušeni dani), upozorenje za rok na neradni dan i kalendar.
import { ajax } from './ajax.js';

const url = () => document.querySelector('meta[name="kt-non-working-url"]')?.content;
const years = new Map();
let weekdays = [6, 7];

export const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

/**
 * Učitava godinu (jednom). Rešava se kada su podaci tu.
 */
export function loadYear(year) {
    if (!url()) {
        return Promise.resolve();
    }
    if (!years.has(year)) {
        years.set(year, ajax('GET', `${url()}?year=${year}`)
            .then(({ data }) => {
                weekdays = data.weekdays;
                return Array.isArray(data.days) ? {} : data.days;
            })
            .catch(() => ({})));
    }
    return years.get(year);
}

/**
 * Sinhrono, za već učitanu godinu: null (radni dan) ili { name, type } (WEEKLY za dane u nedelji).
 */
export function info(date, loaded = {}) {
    const special = loaded[iso(date)];
    if (special) {
        return special;
    }
    const n = date.getDay() === 0 ? 7 : date.getDay();
    return weekdays.includes(n) ? { name: ['', 'Ponedeljak', 'Utorak', 'Sreda', 'Četvrtak', 'Petak', 'Subota', 'Nedelja'][n], type: 'WEEKLY' } : null;
}

/**
 * Asinhrono za bilo koji dan.
 */
export async function nonWorkingInfo(date) {
    return info(date, await loadYear(date.getFullYear()));
}

/** Već učitane godine, za sinhrono crtanje (flatpickr onDayCreate) */
export const cache = {};
export async function remember(year) {
    cache[year] = await loadYear(year);
    return cache[year];
}
