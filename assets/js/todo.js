// Podsetnik (SPEC 8p): bočni panel, cela strana /podsetnik, brzo dodavanje sa čipovima, štikliranje sa "Poništi",
// detalji stavke, prevlačenje redosleda i swipe na telefonu. Sve preko /ajax/todo, bez osvežavanja strane.
// Vremena šalje i prima kao LOKALNA (Europe/Belgrade) "Y-m-d" i "H:i"; zonu računa server (TodoClock), ne ovaj fajl.
import Sortable from 'sortablejs';
import TomSelect from 'tom-select';
import Swal from 'sweetalert2';
import { Modal, Offcanvas } from 'bootstrap';
import { ajax } from './ajax.js';
import { confirmDialog, reminderToast, snoozeDialog, swalFire, toast, undoToast } from './confirm.js';
import { initDatepickers } from './datepicker.js';
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

    // Dozvola za browser obaveštenja se traži JEDNOM, tek kad korisnik prvi put postavi podsetnik (nikad pri učitavanju strane).
    // Poziva se sinhrono iz klika/slanja forme, jer neki browseri traže korisnikov gest.
    const NOTIF_KEY = 'kt-todo-notif-asked';
    const requestNotifications = () => {
        if (!('Notification' in window) || Notification.permission !== 'default') {
            return;
        }
        try {
            if (localStorage.getItem(NOTIF_KEY)) {
                return;
            }
            localStorage.setItem(NOTIF_KEY, '1');
        } catch {
            // bez localStorage: pitamo najviše jednom po učitavanju
        }
        Notification.requestPermission();
    };

    // --- jedan SweetAlert prozor za dodavanje i izmenu (panel, /podsetnik, kartica na početnoj, taster "n") ---
    // Osnovni unos je samo naslov + Enter; rok je ispod, a napomena, podsetnik, ponavljanje, mail i klijent su iza "Više opcija".
    // Čipovi (Danas / Sutra / Ponedeljak) dolaze sa servera (data-chips na panelu), da zonu računa samo TodoClock.
    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const panelData = (name) => {
        try {
            return JSON.parse(document.querySelector('[data-kt-todo-panel]')?.dataset[name] || '[]');
        } catch {
            return [];
        }
    };
    const dueNames = { today: 'Danas', tomorrow: 'Sutra', monday: 'Ponedeljak' };
    const dialogHtml = (item, chips) => {
        const dueChips = chips.map((c) => `<button type="button" class="kt-tchip" data-chip-due="${esc(c.date)}">${esc(dueNames[c.key] || c.label)}</button>`).join('');
        const remindChips = chips.filter((c) => c.time).map((c) => `<button type="button" class="kt-tchip" data-chip-remind="${esc(c.date)}" data-time="${esc(c.time)}">${esc(c.label)}</button>`).join('');
        const repeats = panelData('repeats')
            .map((r) => `<option value="${esc(r.value)}" ${item.repeat === r.value ? 'selected' : ''}>${esc(r.label)}</option>`).join('');
        const more = Boolean(item.note || item.remindDate || (item.repeat && item.repeat !== 'NONE') || item.remindByEmail || item.clientId);

        return `
        <div class="kt-todo-dialog text-start">
            <label class="form-label kt-text-sm" for="kt-td-title">Naslov</label>
            <input type="text" class="form-control mb-3" id="kt-td-title" maxlength="200" value="${esc(item.title)}" placeholder="Šta treba da uradiš?" autocomplete="off">
            <div class="row g-2">
                <div class="col-7"><label class="form-label kt-text-sm" for="kt-td-due">Rok</label><input type="text" class="form-control" id="kt-td-due" data-datepicker value="${esc(item.dueDate)}" placeholder="Bez roka" autocomplete="off"></div>
                <div class="col-5"><label class="form-label kt-text-sm" for="kt-td-due-time">Vreme</label><input type="time" class="form-control" id="kt-td-due-time" value="${esc(item.dueTime)}"></div>
            </div>
            <div class="kt-todo-chips mb-2">${dueChips}</div>
            <button type="button" class="btn btn-link btn-sm px-0" data-td-more aria-expanded="${more}">Više opcija</button>
            <div class="kt-todo-more" ${more ? '' : 'hidden'}>
                <label class="form-label kt-text-sm" for="kt-td-note">Napomena</label>
                <textarea class="form-control mb-3" id="kt-td-note" rows="2" maxlength="5000">${esc(item.note)}</textarea>
                <div class="row g-2">
                    <div class="col-7"><label class="form-label kt-text-sm" for="kt-td-remind">Obavesti me</label><input type="text" class="form-control" id="kt-td-remind" data-datepicker value="${esc(item.remindDate)}" placeholder="Bez obaveštenja" autocomplete="off"></div>
                    <div class="col-5"><label class="form-label kt-text-sm" for="kt-td-remind-time">Vreme</label><input type="time" class="form-control" id="kt-td-remind-time" value="${esc(item.remindTime)}"></div>
                </div>
                <div class="kt-todo-chips mb-3">${remindChips}</div>
                <div class="row g-2 mb-3 align-items-end">
                    <div class="col-7"><label class="form-label kt-text-sm" for="kt-td-repeat">Ponavljanje</label><select class="form-select" id="kt-td-repeat" data-native>${repeats}</select></div>
                    <div class="col-5"><div class="form-check"><input class="form-check-input" type="checkbox" id="kt-td-mail" ${item.remindByEmail ? 'checked' : ''}><label class="form-check-label kt-text-sm" for="kt-td-mail">Obaveštenje na mejl</label></div></div>
                </div>
                <label class="form-label kt-text-sm" for="kt-td-client">Klijent (veza)</label>
                <select id="kt-td-client" class="form-select" placeholder="Bez veze">${item.clientId ? `<option value="${esc(item.clientId)}" selected>${esc(item.clientName)}</option>` : ''}</select>
            </div>
            ${item.convertUrl ? `<div class="mt-3"><a href="${esc(item.convertUrl)}" class="kt-text-sm">Pretvori u zadatak</a></div>` : ''}
        </div>`;
    };

    const blank = { title: '', note: '', dueDate: '', dueTime: '', remindDate: '', remindTime: '', repeat: 'NONE', remindByEmail: false, clientId: null, clientName: null };
    let dialogOpen = false;
    const openDialog = async (item = null) => {
        if (dialogOpen) {
            return;
        }
        dialogOpen = true;
        const editing = Boolean(item?.id);
        const chips = panelData('chips');
        let saved = '';
        let result;
        try {
            result = await swalFire({
                title: editing ? 'Izmena stavke' : 'Nova stavka',
                html: dialogHtml(item || blank, chips),
                showCancelButton: true,
                showDenyButton: editing,
                confirmButtonText: 'Sačuvaj',
                cancelButtonText: 'Odustani',
                denyButtonText: 'Obriši',
                focusCancel: false,
                focusConfirm: false,
                returnFocus: false,
                customClass: { popup: 'kt-swal text-start kt-todo-swal-popup', container: 'kt-todo-swal' },
                didOpen: (popup) => {
                    initDatepickers(popup);
                    const $ = (sel) => popup.querySelector(sel);
                    const title = $('#kt-td-title');
                    title.focus();
                    title.setSelectionRange(title.value.length, title.value.length);
                    $('[data-td-more]').addEventListener('click', (e) => {
                        const box = $('.kt-todo-more');
                        box.hidden = !box.hidden;
                        e.currentTarget.setAttribute('aria-expanded', String(!box.hidden));
                    });
                    new TomSelect($('#kt-td-client'), {
                        valueField: 'id',
                        labelField: 'name',
                        searchField: ['name'],
                        maxItems: 1,
                        create: false,
                        plugins: ['clear_button'],
                        dropdownParent: 'body',
                        preload: 'focus',
                        load: (q, cb) => ajax('GET', `${baseUrl}/clients?q=${encodeURIComponent(q)}`).then((r) => cb(r.data)).catch(() => cb()),
                        render: { no_results: () => '<div class="no-results">Nema rezultata</div>' },
                    });
                    const syncChips = () => {
                        popup.querySelectorAll('[data-chip-due]').forEach((c) => c.classList.toggle('is-selected', c.dataset.chipDue === $('#kt-td-due').value));
                        popup.querySelectorAll('[data-chip-remind]').forEach((c) => c.classList.toggle('is-selected', c.dataset.chipRemind === $('#kt-td-remind').value && c.dataset.time === $('#kt-td-remind-time').value));
                    };
                    popup.addEventListener('click', (e) => {
                        const due = e.target.closest('[data-chip-due]');
                        const remind = e.target.closest('[data-chip-remind]');
                        if (due) {
                            const same = $('#kt-td-due').value === due.dataset.chipDue;
                            $('#kt-td-due')._flatpickr.setDate(same ? null : due.dataset.chipDue, true);
                            $('#kt-td-due-time').value = '';
                        } else if (remind) {
                            const same = $('#kt-td-remind').value === remind.dataset.chipRemind && $('#kt-td-remind-time').value === remind.dataset.time;
                            $('#kt-td-remind')._flatpickr.setDate(same ? null : remind.dataset.chipRemind, true);
                            $('#kt-td-remind-time').value = same ? '' : remind.dataset.time;
                        } else {
                            return;
                        }
                        syncChips();
                    });
                    popup.addEventListener('change', syncChips);
                    syncChips();
                    // Enter u običnom polju potvrđuje (u napomeni i u izboru klijenta ne)
                    popup.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && e.target.matches('input:not([type="checkbox"])') && !e.target.closest('.ts-wrapper')) {
                            e.preventDefault();
                            Swal.clickConfirm();
                        }
                    });
                },
                preConfirm: async () => {
                    const popup = Swal.getPopup();
                    const val = (sel) => popup.querySelector(sel).value.trim();
                    const payload = {
                        title: val('#kt-td-title'),
                        note: val('#kt-td-note'),
                        dueDate: val('#kt-td-due'),
                        dueTime: val('#kt-td-due-time'),
                        remindDate: val('#kt-td-remind'),
                        remindTime: val('#kt-td-remind-time'),
                        repeat: popup.querySelector('#kt-td-repeat').value,
                        remindByEmail: popup.querySelector('#kt-td-mail').checked,
                        clientId: popup.querySelector('#kt-td-client').value || null,
                    };
                    if (!payload.title) {
                        Swal.showValidationMessage('Upiši naslov.');
                        popup.querySelector('#kt-td-title').focus();

                        return false;
                    }
                    if (payload.dueTime && !payload.dueDate) {
                        Swal.showValidationMessage('Izaberi datum roka ili obriši vreme.');

                        return false;
                    }
                    if (payload.repeat !== 'NONE' && !payload.dueDate && !payload.remindDate) {
                        Swal.showValidationMessage('Ponavljanje traži rok ili vreme obaveštenja.');

                        return false;
                    }
                    if (payload.remindDate) {
                        requestNotifications();
                    }
                    try {
                        const res = await ajax('POST', editing ? `${baseUrl}/${item.id}` : baseUrl, payload);
                        render(res.data);
                        saved = res.message || 'Sačuvano.';

                        return true;
                    } catch (e) {
                        // greška servera ili mreže ostaje u prozoru, da se unos ne izgubi
                        Swal.showValidationMessage(e.message || 'Greška.');

                        return false;
                    }
                },
            });
        } finally {
            dialogOpen = false;
        }
        if (saved) {
            toast(saved);
        } else if (result.isDenied) {
            remove({ dataset: { id: String(item.id), repeating: item.repeating ? '1' : '0' } });
        }
    };

    document.addEventListener('click', (e) => {
        if (e.target.closest('[data-todo-new]')) {
            openDialog();
        }
    });
    // taster "n" (kad fokus nije u polju) otvara prozor za dodavanje bilo gde u aplikaciji
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'n' || e.ctrlKey || e.metaKey || e.altKey || e.shiftKey || e.defaultPrevented || e.isComposing) {
            return;
        }
        if (e.target.closest?.('input, textarea, select, [contenteditable="true"]') || document.body.classList.contains('swal2-shown') || document.querySelector('.modal.show')) {
            return;
        }
        e.preventDefault();
        openDialog();
    });

    // --- akcije nad stavkom ---
    const itemOf = (el) => el.closest('[data-todo-item]');

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
            openDialog(JSON.parse(item.dataset.todo));
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

    // panel: sadržaj se učitava pri prvom otvaranju, a na svako otvaranje osvežava
    const panel = document.querySelector('[data-kt-todo-panel]');
    if (panel) {
        panel.addEventListener('show.bs.offcanvas', refresh);
    }
    // --- "Dodaj u podsetnik" sa zadatka / klijenta: mali formular koji pravi stavku vezanu za njih ---
    const remindModal = document.getElementById('kt-remind-modal');
    if (remindModal) {
        const form = remindModal.querySelector('form');
        const modal = Modal.getOrCreateInstance(remindModal);
        let context = {};
        document.addEventListener('click', (e) => {
            const btn = e.target.closest('[data-kt-remind]');
            if (!btn) {
                return;
            }
            context = { taskId: btn.dataset.taskId ? Number(btn.dataset.taskId) : undefined, clientId: btn.dataset.clientId ? Number(btn.dataset.clientId) : undefined };
            form.querySelector('[name="title"]').value = btn.dataset.title;
            const dateInput = form.querySelector('[name="date"]');
            initDatepickers(form);
            dateInput._flatpickr?.setDate(form.dataset.defaultDate, true);
            form.querySelector('[name="time"]').value = '09:00';
            form.querySelector('[name="remindByEmail"]').checked = false;
            modal.show();
        });
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const data = Object.fromEntries(new FormData(form).entries());
            const payload = { ...context, title: data.title, date: data.date, time: data.time, remindByEmail: form.querySelector('[name="remindByEmail"]').checked };
            if (!payload.date) {
                toast('Izaberi datum obaveštenja.', 'danger');

                return;
            }
            requestNotifications();
            const res = await call('POST', `${baseUrl}/remind`, payload);
            if (res) {
                modal.hide();
                toast(res.message);
            }
        });
    }

    // --- podsetnici dok je aplikacija otvorena ---
    // Brz poll (15 s) dok je tab vidljiv; zvono u topbaru ostaje na 60 s kao rezervni kanal. Skriven tab pita samo ako sme da pokaže
    // browser obaveštenje (inače ne preuzima podsetnik, pa ga korisnik vidi u zvonu). Server preuzima podsetnik atomski: toast dobija jedan tab.
    const POLL_MS = 15000;
    const showReminder = async (reminder) => {
        if (document.hidden && 'Notification' in window && Notification.permission === 'granted') {
            const note = new Notification('Podsetnik', { body: reminder.title, tag: `kt-todo-${reminder.id}` });
            note.onclick = () => {
                window.focus();
                window.ktTodo?.panel()?.show();
                note.close();
            };

            return;
        }
        const choice = await reminderToast(reminder.title);
        if (choice === 'done') {
            await complete({ dataset: { id: reminder.id } });
        } else if (choice === 'snooze') {
            const preset = await snoozeDialog();
            if (preset && await call('POST', `${baseUrl}/${reminder.id}/snooze`, { preset })) {
                toast('Obaveštenje je odloženo.');
            }
        }
    };
    let polling = false;
    const poll = async () => {
        if (polling || (document.hidden && !('Notification' in window && Notification.permission === 'granted'))) {
            return;
        }
        polling = true;
        try {
            const { data } = await ajax('GET', `${baseUrl}/due`);
            render({ count: data.count });
            for (const reminder of data.reminders) {
                await showReminder(reminder);
            }
            if (data.reminders.length) {
                refresh();
            }
        } catch {
            // poll ne sme da smeta: pokušava se opet na sledeći interval
        } finally {
            polling = false;
        }
    };
    setInterval(poll, POLL_MS);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) {
            poll();
        }
    });
    poll();

    window.ktTodo = { refresh, add: () => openDialog(), panel: () => (panel ? Offcanvas.getOrCreateInstance(panel) : null) };
}
