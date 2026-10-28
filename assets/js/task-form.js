// Forma zadatka (SPEC 8i.3): klijent ili interni zadatak, ugovoreni poslovi i zaduženi raspoređeni na klijenta (AJAX),
// izbor posla popunjava checklist, prioritet i naslov, a rok na neradni dan daje upozorenje (ne zabranu).
import { ajax, withId } from './ajax.js';
import { toast, confirmDialog } from './confirm.js';
import { nonWorkingInfo } from './non-working.js';

const form = document.querySelector('form[data-kt-task-form]');
if (form) {
    const locked = form.dataset.locked === '1';
    const clientSelect = form.querySelector('[data-kt-task-client]');
    const jobSelect = form.querySelector('[data-kt-task-job]');
    const jobRow = form.querySelector('[data-kt-task-job-row]');
    const lockedNote = form.querySelector('[data-kt-task-locked-note]');
    const clientRow = form.querySelector('[data-kt-task-client-row]');
    const assigneeBox = form.querySelector('[data-kt-assignees]');
    const assigneesEmpty = form.querySelector('[data-kt-assignees-empty]');
    const hint = form.querySelector('[data-kt-assignee-hint]');
    const title = form.querySelector('input[name$="[title]"]');
    const editor = form.querySelector('[data-kt-checklist-editor]');
    const allJobOptions = jobSelect ? [...jobSelect.options].map((o) => o.cloneNode(true)) : [];
    let options = { jobs: [], assignees: [] };
    let autoTitle = '';

    const scope = () => form.querySelector('[data-kt-task-scope]:checked')?.value || 'client';
    const clientId = () => (locked ? form.querySelector('[data-kt-locked-client]').dataset.ktLockedClient : clientSelect?.value || '');
    const setValue = (name, value) => {
        const el = form.querySelector(`select[name$="[${name}]"]`);
        if (el?.tomselect) {
            el.tomselect.setValue(value, true);
        } else if (el) {
            el.value = value;
        }
    };

    // poslovi: samo ugovoreni poslovi izabranog klijenta
    const renderJobs = () => {
        if (!jobSelect) {
            return;
        }
        const current = jobSelect.value;
        jobSelect.innerHTML = '';
        allJobOptions.filter((o) => o.value === '' || o.dataset.client === clientId()).forEach((o) => jobSelect.append(o.cloneNode(true)));
        jobSelect.value = [...jobSelect.options].some((o) => o.value === current) ? current : '';
    };

    // zaduženi: ljudi raspoređeni na klijenta (sa ulogom); interni zadatak: svi koje korisnik sme da izabere
    const renderAssignees = (removeOthers) => {
        if (!assigneeBox) {
            return;
        }
        const internal = scope() === 'internal';
        const allowed = new Map(options.assignees.map((a) => [String(a.id), a.role]));
        const removed = [];
        assigneeBox.querySelectorAll('[data-kt-assignee]').forEach((row) => {
            const input = row.querySelector('input');
            const show = internal || allowed.has(row.dataset.ktAssignee);
            row.hidden = !show;
            row.querySelector('[data-kt-assignee-role]').textContent = !internal && allowed.has(row.dataset.ktAssignee) ? `(${allowed.get(row.dataset.ktAssignee)})` : '';
            if (!show && input.checked && removeOthers) {
                input.checked = false;
                removed.push(row.querySelector('label').firstChild.textContent.trim());
            }
        });
        if (removed.length) {
            toast(`Uklonjeno iz zaduženih (nisu na ovom klijentu): ${removed.join(', ')}`, 'info');
        }
        const noClient = !internal && clientId() === '';
        assigneeBox.classList.toggle('kt-is-locked', noClient);
        assigneeBox.querySelectorAll('input').forEach((i) => { i.disabled = noClient; });
        assigneesEmpty.hidden = internal || noClient || allowed.size > 0;
        hint.textContent = internal ? 'Interni zadatak: radnik sebi, šef svom timu, admin bilo kome.' : (noClient ? 'Prvo izaberi klijenta.' : 'Nude se samo ljudi raspoređeni na klijenta.');
        // ako niko nije izabran, predloži glavnog radnika
        if (!internal && !noClient && !assigneeBox.querySelector('input:checked') && options.client?.primary) {
            const primary = assigneeBox.querySelector(`[data-kt-assignee="${options.client.primary}"] input`);
            if (primary && !primary.closest('[data-kt-assignee]').hidden) {
                primary.checked = true;
            }
        }
    };

    const applyScope = (removeOthers = true) => {
        const internal = scope() === 'internal';
        if (clientRow) {
            clientRow.hidden = internal;
        }
        jobRow.hidden = internal;
        const noClient = !internal && clientId() === '';
        if (jobSelect) {
            jobSelect.disabled = noClient;
        }
        lockedNote.hidden = !noClient;
        renderAssignees(removeOthers);
    };

    const loadClient = async (removeOthers) => {
        const id = clientId();
        options = { jobs: [], assignees: [] };
        if (id && scope() === 'client') {
            try {
                ({ data: options } = await ajax('GET', withId(form.dataset.optionsUrl, id)));
            } catch (e) {
                toast(e.message, 'danger');
            }
        }
        renderJobs();
        applyScope(removeOthers);
    };

    // izbor posla: naslov (ako nije ručno menjan), prioritet, način završetka, checklist
    jobSelect?.addEventListener('change', async () => {
        const job = options.jobs.find((j) => String(j.id) === jobSelect.value);
        if (!job) {
            return;
        }
        if (title && (title.value.trim() === '' || title.value === autoTitle)) {
            title.value = job.title;
            autoTitle = job.title;
        }
        setValue('priority', job.priority);
        setValue('completionMode', job.completionMode);
        if (editor?.ktSetItems && job.checklist.length) {
            const replace = !editor.ktHasItems() || (await confirmDialog({ message: 'Zameniti checklist?', text: `Posao „${job.name}“ ima svoju checklistu (${job.checklist.length} stavki). Trenutne stavke se brišu.`, type: 'warning', button: 'Da, zameni' })).confirmed;
            if (replace) {
                editor.ktSetItems(job.checklist);
            }
        }
        if (job.assignee && assigneeBox) {
            const input = assigneeBox.querySelector(`[data-kt-assignee="${job.assignee}"] input`);
            if (input) {
                input.checked = true;
            }
        }
    });

    clientSelect?.addEventListener('change', () => loadClient(true));
    form.querySelectorAll('[data-kt-task-scope]').forEach((r) => r.addEventListener('change', () => applyScope(false)));

    // rok na neradni dan: upozorenje ispod polja
    const due = form.querySelector('[data-kt-workday-warning]');
    const warning = form.querySelector('[data-kt-due-warning]');
    const checkDue = async () => {
        if (!due.value) {
            warning.hidden = true;
            return;
        }
        const [y, m, d] = due.value.split('-').map(Number);
        const info = await nonWorkingInfo(new Date(y, m - 1, d));
        warning.hidden = !info;
        if (info) {
            warning.textContent = `${d}.${m}. je neradni dan (${info.type === 'WEEKLY' ? info.name.toLowerCase() : info.name}). Rok može da ostane, ali proveri da li je to namerno.`;
        }
    };
    due?.addEventListener('change', checkDue);

    // hitan zadatak: mail je podrazumevano uključen
    const priority = form.querySelector('select[name$="[priority]"]');
    const email = form.querySelector('input[name$="[notifyByEmail]"]');
    priority?.addEventListener('change', () => {
        if (priority.value === 'HITAN' && email) {
            email.checked = true;
        }
    });

    loadClient(false);
    if (due) {
        checkDue();
    }
}
