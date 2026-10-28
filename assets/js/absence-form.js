// Zahtev za odsustvo (SPEC 8j.2): u letu broj radnih dana, preostali godišnji posle zahteva, ko odobrava
// i ko je još iz tima odsutan u tom periodu (upozorenje, ne zabrana). Odbrojavanje do automatskog odobrenja (8m).
import { ajax } from './ajax.js';

const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const num = (n) => String(Math.round(n * 10) / 10).replace('.', ',');
const daysWord = (n) => {
    if (n !== Math.floor(n)) {
        return 'radnih dana';
    }
    if (n % 10 === 1 && n % 100 !== 11) {
        return 'radni dan';
    }
    return n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14) ? 'radna dana' : 'radnih dana';
};

const form = document.querySelector('form[data-kt-absence-form]');
if (form) {
    const box = form.querySelector('[data-kt-absence-info]');
    const type = form.querySelector('[data-kt-absence-type]');
    const from = form.querySelector('[data-kt-absence-from]');
    const to = form.querySelector('[data-kt-absence-to]');
    const half = form.querySelector('[data-kt-absence-half]');
    const halfRow = form.querySelector('[data-kt-absence-half-row]');
    const user = form.querySelector('[data-kt-absence-user]');
    const doc = form.querySelector('[data-kt-absence-document]');
    let timer = null;
    let seq = 0;

    const render = async () => {
        const mine = ++seq;
        const option = type.selectedOptions[0];
        doc.hidden = option?.dataset.document !== '1';
        if (from.value && (!to.value || to.value < from.value)) {
            to.value = from.value;
            to._flatpickr?.setDate(from.value, false);
        }
        const oneDay = from.value && from.value === to.value;
        halfRow.hidden = !oneDay;
        if (!oneDay) {
            half.checked = false;
        }
        if (!from.value) {
            box.innerHTML = '<p class="text-muted mb-0">Izaberi vrstu i period.</p>';
            return;
        }
        const params = new URLSearchParams({ from: from.value, to: to.value || from.value, type: type.value, half: half.checked ? '1' : '0', user: user?.value || form.dataset.me });
        try {
            const { data } = await ajax('GET', `${form.dataset.previewUrl}?${params}`);
            if (mine !== seq) {
                return;
            }
            const rows = [`<dt>Radnih dana</dt><dd><strong>${num(data.workingDays)}</strong> ${daysWord(data.workingDays)}</dd>`];
            const off = Object.entries(data.nonWorking || {});
            if (off.length) {
                rows.push(`<dt>Ne računa se</dt><dd>${off.slice(0, 6).map(([d, why]) => `${d.slice(8)}.${d.slice(5, 7)}. ${escape(why.toLowerCase())}`).join(', ')}${off.length > 6 ? ` i još ${off.length - 6}` : ''}</dd>`);
            }
            if (data.leave) {
                rows.push(`<dt>Godišnji odmor</dt><dd>preostalo ${num(data.leave.remaining)}, posle zahteva <strong class="${data.leave.after < 0 ? 'text-danger' : ''}">${num(data.leave.after)}</strong></dd>`);
            }
            if (option && option.value) {
                rows.push(`<dt>Odobrava</dt><dd>${option.dataset.approval === '1' && data.approvers.length ? escape(data.approvers.join(', ')) : 'nije potrebno odobrenje (odmah na snazi)'}</dd>`);
            }
            let html = `<dl>${rows.join('')}</dl>`;
            if (data.teamAbsent.length) {
                html += `<div class="mt-2 text-warning"><strong>Iz tima su odsutni u tom periodu:</strong><ul class="mb-0 ps-3">${data.teamAbsent.map((p) => `<li>${escape(p.name)} · ${escape(p.period)} · ${escape(p.type)}${p.pending ? ' (na odobrenju)' : ''}</li>`).join('')}</ul></div>`;
            }
            data.warnings.forEach((w) => { html += `<div class="mt-2 text-warning">${escape(w)}</div>`; });
            data.errors.forEach((e) => { html += `<div class="mt-2 text-danger fw-semibold">${escape(e)}</div>`; });
            box.innerHTML = html;
        } catch (e) {
            box.innerHTML = `<p class="text-danger mb-0">${escape(e.message)}</p>`;
        }
    };

    const update = () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(render, 200);
    };
    [type, from, to, half, user].filter(Boolean).forEach((el) => el.addEventListener('change', update));
    render();
}

// "Automatski se odobrava za 5 h" (SPEC 8m)
const countdown = () => {
    document.querySelectorAll('[data-kt-countdown]').forEach((el) => {
        const ms = new Date(el.dataset.ktCountdown) - new Date();
        if (ms <= 0) {
            el.textContent = 'Rok je istekao, odobrava se automatski';
            return;
        }
        const h = Math.floor(ms / 3600000);
        const m = Math.floor((ms % 3600000) / 60000);
        el.textContent = `Automatski se odobrava za ${h >= 24 ? `${Math.floor(h / 24)} d ${h % 24} h` : (h ? `${h} h ${m} min` : `${m} min`)}`;
    });
};
countdown();
window.setInterval(countdown, 60000);
