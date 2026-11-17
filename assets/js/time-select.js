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
