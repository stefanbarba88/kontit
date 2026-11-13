// "Rok i raspored" na zadatku (SPEC 8g.4): promena roka samo za ovaj zadatak, opciono i za buduće periode posla klijenta,
// sa prikazom šta će tačno postati pre čuvanja. Povratak na podrazumevani rok.
import Swal from 'sweetalert2';
import { ajax } from './ajax.js';
import { swalFire, toast } from './confirm.js';
import { initDatepickers } from './datepicker.js';

const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const impactText = (impact) => {
    if (!impact) {
        return '';
    }
    let html = impact.moved > 0
        ? `<div>Menja rok na ${impact.moved} ${impact.moved === 1 ? 'otvoren zadatak' : 'otvorenih zadataka'} ovog posla.</div>`
        : '<div>Nijedan drugi otvoren zadatak ne menja rok.</div>';
    if (impact.stranded.length) {
        html += `<div class="text-warning mt-1">${impact.stranded.length} ${impact.stranded.length === 1 ? 'zadatak ostaje' : 'zadataka ostaje'} na starom roku: ${impact.stranded.map((t) => escape(`${t.client} — ${t.job} — ${t.period}`)).join('; ')}.</div>`;
    }

    return html;
};

async function openDialog(button) {
    const canFutureRight = button.dataset.canFuture === '1';
    let preview = { canFuture: false };
    let seq = 0;
    const result = await swalFire({
        title: 'Promena roka zadatka',
        html: `<div class="text-start">
            <label class="form-label kt-text-sm" for="kt-due-date">Novi rok</label>
            <input type="text" class="form-control" id="kt-due-date" data-datepicker value="${escape(button.dataset.due || '')}" autocomplete="off">
            <p class="kt-text-sm text-muted mt-2 mb-0">Menja se samo ovaj zadatak. Ni generator ni izmena rasporeda ga više neće preračunavati.</p>
            ${canFutureRight ? `<div id="kt-due-future-box" class="mt-3" hidden>
                <div class="form-check"><input class="form-check-input" type="checkbox" id="kt-due-future"><label class="form-check-label" for="kt-due-future">Primeni i na buduće periode za ovog klijenta</label></div>
                <div class="form-check mt-1" id="kt-due-last-box" hidden><input class="form-check-input" type="checkbox" id="kt-due-last"><label class="form-check-label" for="kt-due-last">Poslednji dan u mesecu</label></div>
                <div class="kt-text-sm mt-2" id="kt-due-preview" aria-live="polite"></div>
            </div>` : ''}
        </div>`,
        showCancelButton: true,
        confirmButtonText: 'Sačuvaj rok',
        cancelButtonText: 'Odustani',
        focusCancel: false,
        didOpen: (popup) => {
            initDatepickers(popup);
            const date = popup.querySelector('#kt-due-date');
            const box = popup.querySelector('#kt-due-future-box');
            if (!box) {
                return;
            }
            const future = popup.querySelector('#kt-due-future');
            const last = popup.querySelector('#kt-due-last');
            const refresh = async () => {
                const mine = ++seq;
                if (!date.value) {
                    return;
                }
                try {
                    const { data } = await ajax('POST', button.dataset.previewUrl, { dueDate: date.value, lastDay: last.checked });
                    if (mine !== seq) {
                        return;
                    }
                    preview = data;
                    box.hidden = !data.canFuture;
                    popup.querySelector('#kt-due-last-box').hidden = !data.lastDayOffer;
                    const lines = [];
                    if (future.checked && data.future) {
                        lines.push(`<strong>${escape(data.future)}</strong>`);
                        if (data.warning) {
                            lines.push(`<div class="text-warning">${escape(data.warning)}</div>`);
                        }
                        lines.push(impactText(data.impact));
                    }
                    popup.querySelector('#kt-due-preview').innerHTML = lines.join('');
                } catch {
                    box.hidden = true;
                }
            };
            date.addEventListener('change', refresh);
            future.addEventListener('change', refresh);
            last.addEventListener('change', refresh);
            refresh();
        },
        preConfirm: () => {
            const value = Swal.getPopup().querySelector('#kt-due-date').value;
            if (!value) {
                Swal.showValidationMessage('Izaberi datum roka.');

                return false;
            }

            return {
                dueDate: value,
                future: Boolean(Swal.getPopup().querySelector('#kt-due-future')?.checked && preview.canFuture),
                lastDay: Boolean(Swal.getPopup().querySelector('#kt-due-last')?.checked),
            };
        },
    });

    return result.isConfirmed ? result.value : null;
}

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-kt-task-due]');
    if (!button) {
        return;
    }
    let payload;
    if (button.dataset.ktTaskDue === 'reset') {
        const answer = await swalFire({
            icon: 'question',
            title: 'Vratiti rok na podrazumevani?',
            text: `Rok postaje ${button.dataset.default} i ponovo ga preračunava raspored.`,
            showCancelButton: true,
            confirmButtonText: 'Da, vrati',
            cancelButtonText: 'Odustani',
            customClass: { confirmButton: 'btn kt-btn-confirm', cancelButton: 'btn', popup: 'kt-swal text-start' },
        });
        if (!answer.isConfirmed) {
            return;
        }
        payload = { reset: true };
    } else {
        payload = await openDialog(button);
        if (!payload) {
            return;
        }
    }
    try {
        const { message } = await ajax('POST', button.dataset.url, payload);
        sessionStorage.setItem('kt-toast', message);
        window.location.reload();
    } catch (error) {
        toast(error.message, 'danger');
    }
});
