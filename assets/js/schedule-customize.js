// Prilagođen raspored posla za klijenta (SPEC 8g.5): prozor sa istim poljima rasporeda kao u katalogu poslova,
// red posla pri dodeli ("Podrazumevano: ... Prilagodi za ovog klijenta") i izmena na postojećem poslu, sa brojem zahvaćenih zadataka.
import Swal from 'sweetalert2';
import { ajax } from './ajax.js';
import { swalFire, toast } from './confirm.js';
import { initSchedule } from './schedule-form.js';

const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const parse = (raw) => {
    try {
        const value = JSON.parse(raw || 'null');

        return value && Object.keys(value).length ? value : null;
    } catch {
        return null;
    }
};

/**
 * Prozor sa poljima rasporeda, popunjen sa $initial. Vraća izabrani raspored ili null ako je korisnik odustao.
 */
export async function askSchedule({ title, initial, anchor }) {
    const tpl = document.getElementById('kt-schedule-dialog');
    if (!tpl) {
        return null;
    }
    let api = null;
    const result = await swalFire({
        title,
        html: tpl.innerHTML,
        width: 640,
        showCancelButton: true,
        confirmButtonText: 'Primeni',
        cancelButtonText: 'Odustani',
        focusCancel: false,
        didOpen: (popup) => {
            const box = popup.querySelector('[data-kt-schedule]');
            if (anchor) {
                box.dataset.anchor = anchor;
            }
            api = initSchedule(box);
            api.fill(initial || {});
        },
        preConfirm: async () => {
            const data = api.data();
            if (!data.type) {
                Swal.showValidationMessage('Izaberi raspored.');

                return false;
            }
            try {
                const { data: check } = await ajax('POST', document.querySelector('[data-kt-schedule]')?.dataset.previewUrl || '/ajax/schedule/preview', { schedule: data, anchor: anchor || null });
                if (check.invalid) {
                    Swal.showValidationMessage(check.describe);

                    return false;
                }
                data.short = check.short;
            } catch (e) {
                Swal.showValidationMessage(e.message);

                return false;
            }

            return data;
        },
    });

    return result.isConfirmed ? result.value : null;
}

// --- red posla pri dodeli paketa ili izboru poslova: raspored se šalje kao schedule[ID] (JSON) ---

const renderRow = (row, custom) => {
    const input = row.querySelector('input[type="hidden"]');
    input.value = custom ? JSON.stringify(custom) : '';
    row.classList.toggle('is-customized', Boolean(custom));
    row.querySelector('[data-state]').innerHTML = custom
        ? `<span class="kt-badge kt-badge-info" title="Raspored za ovog klijenta">Prilagođeno: ${escape(custom.short || '')}</span>`
        : `<span class="text-muted">Podrazumevano: ${escape(row.dataset.defaultText || '')}</span>`;
    row.querySelector('[data-act="customize"]').hidden = Boolean(custom);
    row.querySelector('[data-act="edit"]').hidden = !custom;
    row.querySelector('[data-act="revert"]').hidden = !custom;
};

export function initScheduleRows(root = document) {
    root.querySelectorAll('[data-kt-sched-row]:not([data-kt-ready])').forEach((row) => {
        row.dataset.ktReady = '1';
        row.classList.add('kt-sched-row');
        row.innerHTML = `<span data-state></span>
            <button type="button" class="btn btn-link btn-sm p-0" data-act="customize">Prilagodi za ovog klijenta</button>
            <button type="button" class="btn btn-link btn-sm p-0" data-act="edit" hidden>Izmeni</button>
            <button type="button" class="btn btn-link btn-sm p-0" data-act="revert" hidden>Vrati na podrazumevano</button>
            <input type="hidden" name="schedule[${escape(row.dataset.jobId)}]" value="">`;
        renderRow(row, null);
    });
}

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-kt-sched-row] [data-act]');
    if (!button) {
        return;
    }
    const row = button.closest('[data-kt-sched-row]');
    if (button.dataset.act === 'revert') {
        renderRow(row, null);

        return;
    }
    const current = parse(row.querySelector('input[type="hidden"]').value);
    const schedule = await askSchedule({
        title: `Raspored za ovog klijenta: ${row.dataset.jobName || ''}`,
        initial: current || parse(row.dataset.default),
        anchor: row.dataset.anchor,
    });
    if (schedule) {
        renderRow(row, schedule);
    }
});

document.addEventListener('kt:content', (e) => initScheduleRows(e.target));
initScheduleRows();

// --- postojeći posao klijenta: pregled uticaja na otvorene zadatke, potvrda, primena ---

const impactHtml = (d) => {
    const parts = [];
    parts.push(d.moved > 0
        ? `<p class="mb-2"><strong>Menja rok na ${d.moved} ${d.moved === 1 ? 'otvoren zadatak' : 'otvorenih zadataka'}.</strong> Zadaci u radu, na proveri i završeni, kao i oni kojima je rok ručno promenjen, ostaju kakvi jesu.</p>`
        : '<p class="mb-2">Nema otvorenih zadataka kojima se menja rok. Raspored važi od sledećeg perioda.</p>');
    if (d.stranded.length) {
        parts.push(`<p class="mb-1 text-warning"><strong>${d.stranded.length} ${d.stranded.length === 1 ? 'zadatak ostaje' : 'zadataka ostaje'} na starom roku</strong>, jer njihov period ne postoji u novom rasporedu. Otkaži ih ili završi ručno:</p>
            <ul class="text-start kt-text-sm mb-0">${d.stranded.map((t) => `<li>${escape(t.client)} — ${escape(t.job)} — ${escape(t.period)}${t.due ? ` (rok ${escape(t.due)})` : ''}</li>`).join('')}</ul>`);
    }

    return parts.join('');
};

async function changeExisting(button, schedule) {
    let impact;
    try {
        ({ data: impact } = await ajax('POST', button.dataset.impactUrl, { schedule }));
    } catch (error) {
        toast(error.message, 'danger');

        return;
    }
    const answer = await swalFire({
        icon: 'question',
        title: schedule ? `Primeniti raspored za ovog klijenta: ${impact.describe}?` : 'Vratiti na podrazumevani raspored?',
        html: impactHtml(impact),
        showCancelButton: true,
        confirmButtonText: schedule ? 'Da, primeni' : 'Da, vrati',
        cancelButtonText: 'Odustani',
    });
    if (!answer.isConfirmed) {
        return;
    }
    try {
        const { message } = await ajax('POST', button.dataset.applyUrl, { schedule });
        sessionStorage.setItem('kt-toast', message);
        window.location.reload();
    } catch (error) {
        toast(error.message, 'danger');
    }
}

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-kt-sched-job]');
    if (!button) {
        return;
    }
    if (button.dataset.ktSchedJob === 'revert') {
        await changeExisting(button, null);

        return;
    }
    const schedule = await askSchedule({
        title: `Raspored za ovog klijenta: ${button.dataset.jobName || ''}`,
        initial: parse(button.dataset.current) || parse(button.dataset.default),
        anchor: button.dataset.anchor,
    });
    if (schedule) {
        delete schedule.short;
        await changeExisting(button, schedule);
    }
});

// poruka posle ponovnog učitavanja stranice (promena je već sačuvana)
const pending = sessionStorage.getItem('kt-toast');
if (pending) {
    sessionStorage.removeItem('kt-toast');
    toast(pending, 'success');
}
