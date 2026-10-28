// Checklist editor (SPEC 8g.4): dodavanje, brisanje i redosled stavki u formi (Symfony CollectionType sa prototipom).
import Sortable from 'sortablejs';

const plural = (n) => {
    if (n % 10 === 1 && n % 100 !== 11) {
        return `${n} stavka`;
    }
    return n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 12 || n % 100 > 14) ? `${n} stavke` : `${n} stavki`;
};

export function initChecklistEditor(root = document) {
    root.querySelectorAll('[data-kt-checklist-editor]:not([data-kt-ready])').forEach((editor) => {
        editor.dataset.ktReady = '1';
        const list = editor.querySelector('.kt-cle-list');
        const prototype = editor.querySelector('template[data-kt-checklist-prototype]').innerHTML;
        const count = editor.querySelector('[data-kt-checklist-count]');
        const inputs = () => [...list.querySelectorAll('[data-kt-checklist-input]')];
        // sledeći indeks: veći od svih postojećih (posle brisanja se ključevi ne ponavljaju)
        let index = inputs().reduce((max, el) => Math.max(max, Number(el.name.match(/\[(\d+)\]\[text\]$/)?.[1] ?? -1)), -1) + 1;

        const refresh = () => {
            [...list.querySelectorAll('[data-kt-checklist-position]')].forEach((el, i) => { el.value = String(i); });
            const filled = inputs().filter((el) => el.value.trim() !== '').length;
            count.textContent = filled ? plural(filled) : '';
        };

        const add = (after = null) => {
            const wrap = document.createElement('template');
            wrap.innerHTML = prototype.replace(/__name__/g, String(index++)).trim();
            const row = wrap.content.firstElementChild;
            if (after) {
                after.after(row);
            } else {
                list.append(row);
            }
            refresh();
            row.querySelector('[data-kt-checklist-input]').focus();
            return row;
        };

        const remove = (row, focusPrevious) => {
            const previous = row.previousElementSibling?.querySelector('[data-kt-checklist-input]');
            const next = row.nextElementSibling?.querySelector('[data-kt-checklist-input]');
            row.remove();
            refresh();
            (focusPrevious ? previous || next : next || previous)?.focus();
        };

        editor.querySelector('[data-kt-checklist-add]').addEventListener('click', () => add());
        list.addEventListener('click', (e) => {
            const button = e.target.closest('[data-kt-checklist-remove]');
            if (button) {
                remove(button.closest('.kt-cle-row'), false);
            }
        });
        list.addEventListener('input', refresh);
        list.addEventListener('keydown', (e) => {
            const input = e.target.closest('[data-kt-checklist-input]');
            if (!input) {
                return;
            }
            const row = input.closest('.kt-cle-row');
            if (e.key === 'Enter') {
                // Enter nikad ne šalje formu; u poslednjem (ili praznom sledećem) polju dodaje novu stavku
                e.preventDefault();
                const nextInput = row.nextElementSibling?.querySelector('[data-kt-checklist-input]');
                if (nextInput) {
                    nextInput.focus();
                } else if (input.value.trim() !== '') {
                    add(row);
                }
            } else if (e.key === 'Backspace' && input.value === '' && inputs().length > 0) {
                e.preventDefault();
                remove(row, true);
            }
        });

        // popunjavanje iz koda (izbor posla u formi zadatka): zamenjuje sve stavke
        editor.ktSetItems = (texts) => {
            list.innerHTML = '';
            texts.forEach((text) => {
                const row = add();
                row.querySelector('[data-kt-checklist-input]').value = text;
            });
            refresh();
        };
        editor.ktHasItems = () => inputs().some((el) => el.value.trim() !== '');

        Sortable.create(list, { handle: '.kt-drag-handle', animation: 150, onEnd: refresh });
        refresh();
    });
}

initChecklistEditor();
