// Filteri liste zadataka (components/_task_filters.html.twig): forma se šalje sama.
// Izbor iz liste, datum i prekidač odmah; kucanje u "Naziv" posle 300 ms (debounce); X briše naziv.
// Promena filtera ne šalje "page" (forma ga nema), pa se vraća na stranu 1.
const KEY = 'kt-task-q-focus';

function init(form) {
    const q = form.querySelector('input[name="q"]');
    const clear = form.querySelector('[data-kt-clear-q]');
    let timer = null;
    const submit = () => {
        if (document.activeElement === q) {
            try { sessionStorage.setItem(KEY, '1'); } catch (e) { /* bez storage-a samo ne vraćamo fokus */ }
        }
        // prazna polja ne idu u URL
        form.querySelectorAll('input[name], select[name]').forEach((el) => {
            if (el.type !== 'checkbox' && el.value === '' && el.name !== 'tab') {
                el.disabled = true;
            }
        });
        form.submit();
    };

    if (q) {
        q.addEventListener('input', () => {
            if (clear) { clear.hidden = q.value === ''; }
            clearTimeout(timer);
            timer = setTimeout(submit, 300);
        });
        q.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); clearTimeout(timer); submit(); }
        });
        clear?.addEventListener('click', () => {
            q.value = '';
            clear.hidden = true;
            clearTimeout(timer);
            submit();
        });
        // posle osvežavanja vrati fokus u polje (kursor na kraj)
        try {
            if (sessionStorage.getItem(KEY)) {
                sessionStorage.removeItem(KEY);
                q.focus();
                q.setSelectionRange(q.value.length, q.value.length);
            }
        } catch (e) { /* ignorisi */ }
    }

    form.addEventListener('change', (e) => {
        if (e.target === q) { return; }
        // opseg datuma: čeka se drugi datum (jedan dan se šalje dugmetom "Pretraži")
        if (e.target.name === 'period' && (e.target.value.match(/\d{4}-\d{2}-\d{2}/g) || []).length === 1) { return; }
        clearTimeout(timer);
        submit();
    });
    // dugme "Pretraži" ostaje kao rezervni način
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name], select[name]').forEach((el) => {
            if (el.type !== 'checkbox' && el.value === '' && el.name !== 'tab') { el.disabled = true; }
        });
    });
}

document.querySelectorAll('form[data-kt-task-filters]').forEach(init);
