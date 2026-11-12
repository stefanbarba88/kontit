// Pozicije zaposlenih: redosled prevlačenjem (prva je najniža) i izmena u modalu.
import Sortable from 'sortablejs';
import { Modal } from 'bootstrap';
import { ajax } from './ajax.js';
import { toast } from './confirm.js';

const list = document.querySelector('.kt-position-list[data-kt-sortable-url]');
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

const modal = document.getElementById('position-modal');
const form = modal?.querySelector('[data-kt-position-form]');
if (modal && form) {
    const title = modal.querySelector('.modal-title');
    const field = (name) => form.querySelector(`[name$="[${name}]"]`);
    const setColor = (hex) => {
        const radio = [...form.querySelectorAll('input[type="radio"][name$="[color]"]')].find((r) => r.value.toLowerCase() === hex.toLowerCase());
        if (radio) {
            radio.checked = true;
        }
    };

    document.addEventListener('click', (e) => {
        const edit = e.target.closest('[data-kt-position-edit]');
        const create = e.target.closest('[data-kt-position-new]');
        if (!edit && !create) {
            return;
        }
        form.querySelectorAll('.is-invalid').forEach((el) => el.classList.remove('is-invalid'));
        form.querySelectorAll('.invalid-feedback').forEach((el) => el.remove());
        if (edit) {
            form.setAttribute('action', edit.dataset.ktPositionEdit);
            title.textContent = title.dataset.edit;
            field('name').value = edit.dataset.name;
            field('description').value = edit.dataset.description;
            field('monthlyCapacityHours').value = edit.dataset.capacity;
            field('suggestedAccessRole').value = edit.dataset.role;
            field('requiresTaskReview').checked = edit.dataset.review === '1';
            setColor(edit.dataset.color);
        } else {
            form.setAttribute('action', form.dataset.newAction);
            title.textContent = title.dataset.new;
            field('name').value = '';
            field('description').value = '';
            field('monthlyCapacityHours').value = '';
            field('suggestedAccessRole').value = '';
            field('requiresTaskReview').checked = false;
        }
    });
    modal.addEventListener('shown.bs.modal', () => field('name')?.focus());

    // posle greške u validaciji strana se vraća sa otvorenim modalom
    if (modal.hasAttribute('data-kt-open')) {
        Modal.getOrCreateInstance(modal).show();
    }
}

// Forma korisnika: predlog prava pristupa na osnovu izabrane pozicije. Dugme samo postavlja polje, ništa se ne čuva samo.
const positionSelect = document.querySelector('select[data-kt-position-select]');
const hint = document.querySelector('[data-kt-position-hint]');
const roleSelect = document.querySelector('select[name$="[role]"]');
if (positionSelect && hint && roleSelect) {
    const apply = hint.querySelector('[data-kt-position-hint-apply]');
    let suggested = '';
    const refresh = () => {
        suggested = positionSelect.selectedOptions[0]?.dataset.suggestedRole || '';
        const option = [...roleSelect.options].find((o) => o.value === suggested);
        const show = suggested !== '' && option && roleSelect.value !== suggested;
        hint.classList.toggle('d-none', !show);
        if (show) {
            hint.querySelector('[data-kt-position-hint-role]').textContent = option.textContent.trim();
        }
    };
    positionSelect.addEventListener('change', refresh);
    roleSelect.addEventListener('change', refresh);
    apply.addEventListener('click', () => {
        if (roleSelect.tomselect) {
            roleSelect.tomselect.setValue(suggested);
        } else {
            roleSelect.value = suggested;
            roleSelect.dispatchEvent(new Event('change'));
        }
        refresh();
    });
    refresh();
}
