// Provera zadataka: forma posla (Zatraži potvrdu → proveravači) i forma zadatka (polje "Provera").
// Server uvek proverava šta je dozvoljeno (TaskReviewPolicy); ovo je samo pomoć u prikazu.

// Forma posla: izbor proveravača se vidi samo kad je "Zatraži potvrdu" uključeno
const toggle = document.querySelector('[data-kt-approval-toggle]');
const approversRow = document.querySelector('[data-kt-approvers-row]');
if (toggle && approversRow) {
    const sync = () => {
        approversRow.hidden = !toggle.checked;
    };
    toggle.addEventListener('change', sync);
    sync();
}

// Forma zadatka
const mode = document.querySelector('select[data-kt-review-mode]');
if (mode) {
    const form = mode.closest('form');
    const approvers = form.querySelector('[data-kt-review-approvers]');
    const hint = form.querySelector('[data-kt-review-hint]');
    const jobSelect = form.querySelector('[data-kt-task-job]');
    const noneOption = [...mode.options].find((o) => o.value === 'NONE');
    const currentUserId = form.dataset.ktMe || '';
    const checkboxes = () => [...form.querySelectorAll('[data-kt-assignees] input[type="checkbox"]')];

    const refresh = () => {
        const selected = checkboxes().filter((c) => c.checked);
        const onlyMe = selected.length > 0 && selected.every((c) => c.dataset.user === currentUserId);
        // zadatak sebi: bez izbora "Bez provere" (server isto ne dozvoljava)
        if (noneOption) {
            noneOption.hidden = onlyMe;
            noneOption.disabled = onlyMe;
            if (onlyMe && mode.value === 'NONE') {
                mode.value = 'AUTO';
            }
        }
        // posao koji traži potvrdu zaključava izbor (server ga svakako postavlja na posebnu potvrdu)
        const jobLocks = jobSelect?.selectedOptions[0]?.dataset.requiresApproval === '1';
        mode.closest('.mb-3, div')?.toggleAttribute('hidden', jobLocks);
        if (approvers) {
            approvers.hidden = jobLocks || mode.value !== 'SPECIAL';
        }
        if (hint) {
            if (jobLocks) {
                hint.textContent = 'Ovaj posao traži posebnu potvrdu: zadatak potvrđuju proveravači određeni na poslu.';
            } else if (mode.value === 'AUTO') {
                const needs = selected.filter((c) => c.dataset.review === '1');
                hint.textContent = needs.length > 0
                    ? 'Automatski, po poziciji: zaduženi sa pozicijom koja traži proveru (' + needs.length + ') idu na proveru šefa tima ili admina.'
                    : 'Automatski, po poziciji: za izabrane zadužene nema provere, zadatak se završava odmah.';
            } else if (mode.value === 'NONE') {
                hint.textContent = 'Zadatak se završava odmah, bez provere. Odluka se beleži u istoriji.';
            } else {
                hint.textContent = 'Zadatak čeka potvrdu izabranih admina (stranica Posebne potvrde).';
            }
        }
    };

    mode.addEventListener('change', refresh);
    form.addEventListener('change', refresh);
    refresh();
}
