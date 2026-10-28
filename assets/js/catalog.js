// Kategorije poslova (SPEC 8g.1): redosled prevlačenjem i izmena u modalu.
import Sortable from 'sortablejs';
import { Modal } from 'bootstrap';
import { ajax } from './ajax.js';
import { toast } from './confirm.js';

const list = document.querySelector('.kt-category-list[data-kt-sortable-url]');
if (list) {
    Sortable.create(list, {
        handle: '.kt-drag-handle',
        animation: 150,
        onEnd: async () => {
            const ids = [...list.querySelectorAll('[data-id]')].map((li) => Number(li.dataset.id));
            try {
                const { message } = await ajax('POST', list.dataset.ktSortableUrl, { ids });
                toast(message);
            } catch (e) {
                toast(e.message, 'danger');
            }
        },
    });
}

const modal = document.getElementById('category-modal');
const form = modal?.querySelector('[data-kt-category-form]');
if (modal && form) {
    const title = modal.querySelector('.modal-title');
    const newAction = form.dataset.newAction;
    const setIcon = (value) => {
        const select = form.querySelector('[data-kt-icon-select]');
        if (select?.tomselect) {
            select.tomselect.setValue(value || '', true);
        } else if (select) {
            select.value = value || '';
        }
    };
    const setColor = (hex) => {
        const radio = [...form.querySelectorAll('input[type="radio"][name$="[color]"]')].find((r) => r.value.toLowerCase() === hex.toLowerCase());
        if (radio) {
            radio.checked = true;
        }
    };

    document.addEventListener('click', (e) => {
        const edit = e.target.closest('[data-kt-category-edit]');
        const create = e.target.closest('[data-kt-category-new]');
        if (!edit && !create) {
            return;
        }
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
        const name = form.querySelector('input[name$="[name]"]');
        if (edit) {
            form.setAttribute('action', edit.dataset.ktCategoryEdit);
            title.textContent = title.dataset.edit;
            name.value = edit.dataset.name;
            setColor(edit.dataset.color);
            setIcon(edit.dataset.icon);
        } else {
            form.setAttribute('action', newAction);
            title.textContent = title.dataset.new;
            name.value = '';
            setIcon('');
        }
    });
    modal.addEventListener('shown.bs.modal', () => form.querySelector('input[name$="[name]"]')?.focus());

    // posle greške u validaciji strana se vraća sa otvorenim modalom
    if (modal.hasAttribute('data-kt-open')) {
        Modal.getOrCreateInstance(modal).show();
    }
}
