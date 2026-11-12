// Moja lista (SPEC 8p): bočni panel, cela strana /moja-lista, brzo dodavanje sa čipovima, štikliranje sa "Poništi",
// detalji stavke, prevlačenje redosleda i swipe na telefonu. Sve preko /ajax/todo, bez osvežavanja strane.
// Vremena šalje i prima kao LOKALNA (Europe/Belgrade) "Y-m-d" i "H:i"; zonu računa server (TodoClock), ne ovaj fajl.
import Sortable from 'sortablejs';
import flatpickr from 'flatpickr';
import { Offcanvas } from 'bootstrap';
import { ajax } from './ajax.js';
import { confirmDialog, toast, undoToast } from './confirm.js';
import { initDatepickers, srLatn } from './datepicker.js';
import { initSwipe } from './mobile.js';

const roots = () => [...document.querySelectorAll('[data-kt-todo]')];
if (roots().length) {
    const baseUrl = roots()[0].dataset.url;
    const pageRoot = () => roots().find((r) => r.closest('.kt-todo-page'));
    const fail = (e) => toast(e.message || 'Greška.', 'danger');

    // --- stanje liste: isto u svim korenima (panel + strana) ---
    const render = (data) => {
        document.querySelectorAll('.todo-count').forEach((el) => {
            el.textContent = data.count;
            el.classList.toggle('d-none', data.count === 0);
        });
        if (typeof data.html !== 'string') {
            return;
        }
        roots().forEach((root) => {
            const list = root.querySelector('[data-kt-todo-list]');
            // otvorene detalje ne diramo dok se kuca u njima
            if (list.querySelector('[data-todo-detail]:not([hidden]) :focus')) {
                return;
            }
            list.innerHTML = data.html;
            initSwipe(list);
            list.querySelectorAll('[data-todo-sortable]').forEach((box) => {
                Sortable.create(box, {
                    handle: '.kt-todo-handle',
                    animation: 150,
                    onEnd: () => reorder(box),
                });
            });
        });
    };
    const query = () => pageRoot()?.dataset.query || '';
    const refresh = () => ajax('GET', `${baseUrl}?q=${encodeURIComponent(query())}`).then(({ data }) => render(data)).catch(() => {});
    const call = async (method, url, payload) => {
        try {
            const res = await ajax(method, url, payload);
            render(res.data);

            return res;
        } catch (e) {
            fail(e);

            return null;
        }
    };
    const reorder = (box) => call('POST', `${baseUrl}/reorder`, { ids: [...box.querySelectorAll('[data-todo-item]')].map((el) => Number(el.dataset.id)) });

    // --- dodavanje: Enter dodaje, čip postavlja rok/podsetnik ---
    const selection = new Map(); // root -> { date, time, label }
    const setSelection = (root, value) => {
        const chipBox = root.querySelector('[data-todo-selected]');
        if (value) {
            selection.set(root, value);
            chipBox.hidden = false;
            chipBox.querySelector('[data-todo-selected-label]').textContent = value.label;
        } else {
            selection.delete(root);
            chipBox.hidden = true;
        }
        root.querySelectorAll('[data-todo-chip]').forEach((chip) => {
            const on = value && chip.dataset.date === value.date && (chip.dataset.time || null) === (value.time || null) && !value.picked;
            chip.classList.toggle('is-selected', Boolean(on));
        });
    };
    const askNotificationPermission = () => {
        // dozvola se traži JEDNOM, tek kad korisnik prvi put postavi podsetnik (nikad pri učitavanju)
        document.dispatchEvent(new CustomEvent('kt:todo-reminder-set'));
    };

    document.addEventListener('click', (e) => {
        const chip = e.target.closest('[data-todo-chip]');
        if (chip) {
            const root = chip.closest('[data-kt-todo]');
            const current = selection.get(root);
            const same = current && current.date === chip.dataset.date && (current.time || null) === (chip.dataset.time || null);
            setSelection(root, same ? null : { date: chip.dataset.date, time: chip.dataset.time || null, label: chip.textContent.trim() });
            root.querySelector('[data-todo-title]').focus();

            return;
        }
        const pick = e.target.closest('[data-todo-chip-pick]');
        if (pick) {
            const root = pick.closest('[data-kt-todo]');
            const input = root.querySelector('[data-todo-picker]');
            if (!input._flatpickr) {
                flatpickr(input, {
                    locale: srLatn,
                    enableTime: true,
                    time_24hr: true,
                    defaultHour: 9,
                    minuteIncrement: 5,
                    dateFormat: 'Y-m-d H:i',
                    disableMobile: true,
                    onChange: (dates, str) => {
                        if (!dates.length) {
                            return;
                        }
                        const [date, time] = str.split(' ');
                        const [y, m, d] = date.split('-');
                        setSelection(root, { date, time, label: `${d}.${m}. ${time}`, picked: true });
                        root.querySelector('[data-todo-title]').focus();
                    },
                });
            }
            input._flatpickr.open();

            return;
        }
        if (e.target.closest('[data-todo-clear-chip]')) {
            setSelection(e.target.closest('[data-kt-todo]'), null);
        }
    });

    document.addEventListener('submit', async (e) => {
        const form = e.target.closest('[data-todo-add]');
        if (!form) {
            return;
        }
        e.preventDefault();
        const root = form.closest('[data-kt-todo]');
        const input = form.querySelector('[data-todo-title]');
        const title = input.value.trim();
        if (!title) {
            return;
        }
        const sel = selection.get(root);
        const payload = { title };
        if (sel) {
            payload.dueDate = sel.date;
            payload.dueTime = sel.time;
            if (sel.time) {
                payload.remindDate = sel.date;
                payload.remindTime = sel.time;
            }
        }
        input.disabled = true;
        const res = await call('POST', baseUrl, payload);
        input.disabled = false;
        if (res) {
            input.value = '';
            setSelection(root, null);
            if (res.data.hasReminder) {
                askNotificationPermission();
            }
        }
        input.focus();
    });

    // --- akcije nad stavkom ---
    const itemOf = (el) => el.closest('[data-todo-item]');
    const fieldsOf = (item) => {
        const out = {};
        item.querySelectorAll('[data-todo-field]').forEach((el) => {
            out[el.dataset.todoField] = el.type === 'checkbox' ? el.checked : el.value;
        });

        return out;
    };

    const complete = async (item) => {
        const id = item.dataset.id;
        const res = await call('POST', `${baseUrl}/${id}/done`);
        if (res) {
            undoToast(res.data.repeated ? 'Završeno, sledeće ponavljanje je dodato.' : 'Završeno.', () => call('POST', `${baseUrl}/${id}/reopen`));
        } else {
            refresh();
        }
    };

    const remove = async (item) => {
        const id = item.dataset.id;
        if (item.dataset.repeating === '1') {
            const { confirmed } = await confirmDialog({
                message: 'Obrisati ponavljajuću stavku?',
                text: 'Brisanjem se zaustavlja ponavljanje: nova stavka neće biti napravljena.',
                type: 'danger',
                button: 'Da, obriši',
            });
            if (!confirmed) {
                return;
            }
            await call('DELETE', `${baseUrl}/${id}`);
            toast('Stavka je obrisana.');

            return;
        }
        const res = await call('DELETE', `${baseUrl}/${id}`);
        if (res) {
            undoToast('Stavka je obrisana.', () => call('POST', `${baseUrl}/restore`, res.data.snapshot));
        }
    };

    document.addEventListener('change', (e) => {
        const box = e.target.closest('[data-todo-act="toggle"]');
        if (!box) {
            return;
        }
        const item = itemOf(box);
        if (box.checked) {
            // animacija kvačice i precrtavanja (CSS) se vidi pre nego što se lista ponovo iscrta
            item.classList.add('is-done');
            setTimeout(() => complete(item), 450);
        } else {
            call('POST', `${baseUrl}/${item.dataset.id}/reopen`);
        }
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('button[data-todo-act]');
        if (!btn) {
            return;
        }
        const item = itemOf(btn);
        const act = btn.dataset.todoAct;
        if (act === 'open') {
            const detail = item.querySelector('[data-todo-detail]');
            detail.hidden = !detail.hidden;
            if (!detail.hidden) {
                initDatepickers(detail);
                detail.querySelector('[data-todo-field="title"]').focus();
            }
        } else if (act === 'save') {
            const fields = fieldsOf(item);
            const res = await call('POST', `${baseUrl}/${item.dataset.id}`, fields);
            if (res) {
                toast('Sačuvano.');
                if (res.data.hasReminder) {
                    askNotificationPermission();
                }
            }
        } else if (act === 'done') {
            complete(item);
        } else if (act === 'reopen') {
            call('POST', `${baseUrl}/${item.dataset.id}/reopen`);
        } else if (act === 'delete') {
            remove(item);
        } else if (act === 'pin') {
            call('POST', `${baseUrl}/${item.dataset.id}`, { pinned: item.dataset.pinned !== '1' });
        }
    });

    // detalji: Enter u naslovu čuva
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches('[data-todo-field="title"]')) {
            e.preventDefault();
            e.target.closest('[data-todo-item]').querySelector('[data-todo-act="save"]').click();
        }
    });

    // panel: sadržaj se učitava pri prvom otvaranju, a na svako otvaranje osvežava
    const panel = document.querySelector('[data-kt-todo-panel]');
    if (panel) {
        panel.addEventListener('show.bs.offcanvas', refresh);
        panel.addEventListener('shown.bs.offcanvas', () => panel.querySelector('[data-todo-title]')?.focus());
    }
    window.ktTodo = { refresh, panel: () => (panel ? Offcanvas.getOrCreateInstance(panel) : null) };
}
