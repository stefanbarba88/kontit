// Job picker (SPEC 8h.2): grupe po kategoriji, pretraga, "Izaberi sve u grupi" i lista izabranih sa redosledom.
// Šalju se skrivena polja name[] u redosledu liste izabranih; checkbox-ovi služe samo za izbor.
import Sortable from 'sortablejs';

const escape = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const fold = (s) => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'dj');

export function initJobPicker(root = document) {
    root.querySelectorAll('[data-kt-job-picker]:not([data-kt-ready])').forEach((picker) => {
        picker.dataset.ktReady = '1';
        const chosen = picker.querySelector('[data-kt-jp-chosen]');
        const empty = picker.querySelector('[data-kt-jp-empty]');
        const extra = picker.dataset.extra ? document.getElementById(picker.dataset.extra)?.innerHTML : null;
        const boxes = () => [...picker.querySelectorAll('.kt-jp-list input[type="checkbox"]')];
        const box = (id) => boxes().find((b) => b.value === String(id));

        const counts = () => {
            const n = chosen.children.length;
            picker.querySelector('[data-kt-jp-count]').textContent = String(n);
            picker.querySelector('[data-kt-jp-count-2]').textContent = n ? `(${n})` : '';
            empty.hidden = n > 0;
            picker.querySelectorAll('[data-kt-jp-group]').forEach((group) => {
                const all = group.querySelectorAll('input[type="checkbox"]');
                const on = group.querySelectorAll('input[type="checkbox"]:checked');
                group.querySelector('[data-kt-jp-group-count]').textContent = `${on.length} od ${all.length}`;
                group.querySelector('[data-kt-jp-all]').textContent = on.length === all.length ? 'Poništi grupu' : 'Izaberi sve u grupi';
            });
            picker.dispatchEvent(new CustomEvent('kt:jobpicker', { bubbles: true, detail: { ids: [...chosen.children].map((li) => li.dataset.id) } }));
        };

        const add = (input) => {
            if (chosen.querySelector(`[data-id="${input.value}"]`)) {
                return;
            }
            const li = document.createElement('li');
            li.className = 'kt-jp-chosen-row';
            li.dataset.id = input.value;
            li.innerHTML = `<div class="d-flex align-items-center gap-2">
                <span class="kt-drag-handle" aria-hidden="true"><svg viewBox="0 0 24 24" class="kt-icon"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></g></svg></span>
                <span class="flex-grow-1 min-w-0"><span class="d-block text-truncate">${escape(input.dataset.name)}</span><span class="kt-jp-desc">${escape(input.dataset.describe)}</span></span>
                <button type="button" class="btn kt-btn-icon btn-sm text-muted" data-kt-jp-remove title="Ukloni" aria-label="Ukloni"><svg viewBox="0 0 24 24" class="kt-icon"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12"/></g></svg></button>
                <input type="hidden" name="${escape(picker.dataset.name)}" value="${escape(input.value)}">
            </div>${extra ? extra.replace(/__ID__/g, escape(input.value)).replace(/__PRICE__/g, escape(input.dataset.price || '')).replace(/__SCHEDULE__/g, escape(input.dataset.schedule || '')).replace(/__DESCRIBE__/g, escape(input.dataset.describe || '')).replace(/__NAME__/g, escape(input.dataset.name || '')) : ''}`;
            chosen.append(li);
            li.dispatchEvent(new CustomEvent('kt:content', { bubbles: true }));
        };

        const remove = (id) => {
            chosen.querySelector(`[data-id="${id}"]`)?.remove();
            const b = box(id);
            if (b) {
                b.checked = false;
            }
        };

        picker.addEventListener('change', (e) => {
            const input = e.target.closest('.kt-jp-list input[type="checkbox"]');
            if (!input) {
                return;
            }
            if (input.checked) {
                add(input);
            } else {
                remove(input.value);
            }
            counts();
        });

        picker.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('[data-kt-jp-remove]');
            if (removeBtn) {
                remove(removeBtn.closest('[data-id]').dataset.id);
                counts();
                return;
            }
            const all = e.target.closest('[data-kt-jp-all]');
            if (all) {
                const inputs = [...all.closest('[data-kt-jp-group]').querySelectorAll('input[type="checkbox"]')]
                    .filter((b) => !b.closest('[data-kt-jp-item]').hidden);
                const on = inputs.every((b) => b.checked);
                inputs.forEach((b) => {
                    b.checked = !on;
                    if (on) {
                        remove(b.value);
                    } else {
                        add(b);
                    }
                });
                counts();
                return;
            }
            const toggle = e.target.closest('[data-kt-jp-toggle]');
            if (toggle) {
                const group = toggle.closest('[data-kt-jp-group]');
                const open = toggle.getAttribute('aria-expanded') !== 'true';
                toggle.setAttribute('aria-expanded', String(open));
                group.classList.toggle('is-collapsed', !open);
            }
        });

        // pretraga u letu (bez kvačica: "knjizenje" nalazi "Knjiženje")
        picker.querySelector('[data-kt-jp-search]').addEventListener('input', (e) => {
            const needle = fold(e.target.value.trim());
            let any = false;
            picker.querySelectorAll('[data-kt-jp-group]').forEach((group) => {
                let visible = 0;
                group.querySelectorAll('[data-kt-jp-item]').forEach((item) => {
                    item.hidden = needle !== '' && !fold(item.dataset.search).includes(needle);
                    visible += item.hidden ? 0 : 1;
                });
                group.hidden = visible === 0;
                if (needle !== '' && visible > 0) {
                    group.classList.remove('is-collapsed');
                    group.querySelector('[data-kt-jp-toggle]').setAttribute('aria-expanded', 'true');
                }
                any ||= visible > 0;
            });
            picker.querySelector('[data-kt-jp-none]').hidden = any || needle === '';
        });

        // početni izbor (redosled kao u paketu)
        JSON.parse(picker.dataset.selected || '[]').forEach((id) => {
            const b = box(id);
            if (b) {
                b.checked = true;
                add(b);
            }
        });
        Sortable.create(chosen, { handle: '.kt-drag-handle', animation: 150, onEnd: counts });
        // na telefonu je lista izabranih sklopljena
        if (window.matchMedia('(max-width: 575.98px)').matches) {
            picker.querySelector('.kt-jp-selected').open = false;
        }
        counts();
    });
}

initJobPicker();
document.addEventListener('shown.bs.modal', (e) => initJobPicker(e.target));
