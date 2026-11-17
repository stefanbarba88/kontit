// Izbor vremena (SPEC 8p.6, DESIGN 7.17): select sa vremenima u koracima, 24h, bez slobodnog kucanja. Čiste funkcije (testira ih
// node --test tests/js/*.mjs); isti izlaz kao templates/components/time_select.html.twig. Korak dolazi iz <meta name="kt-time-step">
// (parametar app.time.step_minutes), 30 je rezerva.
export const DEFAULT_STEP = 30;
const VALID = /^([01]\d|2[0-3]):[0-5]\d$/;

export function timeStep(doc = globalThis.document) {
    const step = Number(doc?.querySelector?.('meta[name="kt-time-step"]')?.content);

    return Number.isInteger(step) && step >= 1 && step <= 720 ? step : DEFAULT_STEP;
}

/** Vremena "HH:MM" u koracima; postojeća vrednost van koraka se umeće na svoje mesto (ne zaokružuje se). */
export function timeOptions(step = DEFAULT_STEP, existing = '') {
    const times = [];
    for (let m = 0; m < 1440; m += step) {
        times.push(`${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`);
    }
    if (existing && VALID.test(existing) && !times.includes(existing)) {
        times.push(existing);
        times.sort();
    }

    return times;
}

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/** HTML selecta; emptyLabel === null znači da prazne opcije nema. */
export function timeSelectHtml({ id, name = '', value = '', emptyLabel = null, step = DEFAULT_STEP }) {
    const empty = emptyLabel === null ? '' : `<option value="">${esc(emptyLabel)}</option>`;
    const options = timeOptions(step, value).map((t) => `<option value="${t}"${t === value ? ' selected' : ''}>${t}</option>`).join('');

    return `<select class="form-select" id="${esc(id)}"${name ? ` name="${esc(name)}"` : ''} data-native data-time-select>${empty}${options}</select>`;
}

export const DEFAULT_ZONE = 'Europe/Belgrade';
export function timeZone(doc = globalThis.document) {
    return doc?.querySelector?.('meta[name="kt-time-zone"]')?.content || DEFAULT_ZONE;
}

/**
 * Datum ("Y-m-d") i vreme ("HH:MM") za "za N minuta" od datog trenutka, u zoni aplikacije (Europe/Belgrade), ne u zoni uređaja.
 * Tačan minut je namerno (brza relativna opcija); select ga prikazuje kao dodatnu opciju.
 */
export function relativeTime(minutes, zone = DEFAULT_ZONE, now = new Date()) {
    const parts = Object.fromEntries(new Intl.DateTimeFormat('en-GB', { timeZone: zone, hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' })
        .formatToParts(new Date(now.getTime() + minutes * 60000)).map((p) => [p.type, p.value]));

    return { date: `${parts.year}-${parts.month}-${parts.day}`, time: `${parts.hour}:${parts.minute}` };
}

/** Postavlja vrednost selecta; ako je van koraka, dodaje je kao opciju na pravo mesto (ništa se ne zaokružuje). */
export function setTimeValue(select, value) {
    if (value && ![...select.options].some((o) => o.value === value)) {
        const option = new Option(value, value);
        const after = [...select.options].find((o) => o.value !== '' && o.value > value);
        select.add(option, after || null);
    }
    select.value = value;
}
