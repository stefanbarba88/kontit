// Polja forme (DESIGN.md 17): Tom Select, IMask maske, autosize, lozinka (oko + Caps Lock), brisanje polja,
// brojač karaktera i tooltip-ovi. Sve se kači preko initForms(root), pa radi i za sadržaj dodat kasnije.
import TomSelect from 'tom-select';
import { initDatepickers } from './datepicker.js';
import { icon } from './icons.js';
import IMask from 'imask';
import autosize from 'autosize';
import { Tooltip } from 'bootstrap';


function initSelects(root) {
    root.querySelectorAll('select.form-select:not([data-native]):not(.tomselected)').forEach((el) => {
        const multiple = el.multiple;
        new TomSelect(el, {
            create: false,
            allowEmptyOption: true,
            maxOptions: null,
            plugins: multiple ? ['remove_button'] : [],
            hidePlaceholder: false,
            // data-kt-search: pretraga i po dodatnom tekstu opcije (data-data='{"search": "pun naziv, PIB"}')
            searchField: el.hasAttribute('data-kt-search') ? ['text', 'search'] : ['text'],
            render: {
                // opcije sa data-data '{"color": "#hex"}' dobijaju obojenu tačku (pozicije)
                option: (d, esc) => `<div>${d.color ? `<span class="kt-ts-dot" style="--kt-dot: ${esc(d.color)}"></span>` : ''}${esc(d.text)}</div>`,
                item: (d, esc) => `<div>${d.color ? `<span class="kt-ts-dot" style="--kt-dot: ${esc(d.color)}"></span>` : ''}${esc(d.text)}</div>`,
                no_results: () => '<div class="no-results">Nema rezultata</div>',
                loading: () => '<div class="kt-ts-loading"><span class="kt-loader kt-loader--sm"><i></i><i></i><i></i><i></i></span></div>',
            },
        });
    });
}

function initMasks(root) {
    const masks = [
        ['input[name$="[pib]"]', { mask: '000000000' }, 'numeric'],
        ['input[name$="[bankAccount]"], input[data-kt-mask="account"]', { mask: '000-0000000000000-00' }, 'numeric'],
        ['input[type="tel"], input[name$="[phone]"]', { mask: /^[+\d\s/-]*$/ }, 'tel'],
    ];
    masks.forEach(([selector, options, inputmode]) => {
        root.querySelectorAll(selector).forEach((el) => {
            if (el.dataset.ktMasked) {
                return;
            }
            el.dataset.ktMasked = '1';
            el.setAttribute('inputmode', inputmode);
            IMask(el, options);
        });
    });

    // iznosi u Symfony MoneyType poljima: prikaz i slanje kao 1.234,56 (server parsira po sr_Latn lokalu)
    root.querySelectorAll('input[data-kt-money], input[name$="[price]"], input[name$="[agreedPrice]"]').forEach((el) => {
        if (el.dataset.ktMasked) {
            return;
        }
        el.dataset.ktMasked = '1';
        el.setAttribute('inputmode', 'decimal');
        IMask(el, { mask: Number, scale: 2, thousandsSeparator: '.', radix: ',', mapToRadix: ['.'], normalizeZeros: true, padFractionalZeros: false, min: 0 });
    });
}

function initPasswords(root) {
    root.querySelectorAll('input[type="password"]:not([data-kt-password])').forEach((el) => {
        el.dataset.ktPassword = '1';
        const wrap = document.createElement('div');
        wrap.className = 'kt-password';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'kt-password-toggle';
        toggle.setAttribute('aria-label', 'Prikaži lozinku');
        toggle.innerHTML = icon('eye');
        toggle.addEventListener('click', () => {
            const show = el.type === 'password';
            el.type = show ? 'text' : 'password';
            toggle.classList.toggle('is-on', show);
            toggle.setAttribute('aria-label', show ? 'Sakrij lozinku' : 'Prikaži lozinku');
            el.focus();
        });
        wrap.appendChild(toggle);

        const caps = document.createElement('div');
        caps.className = 'kt-caps form-text';
        caps.textContent = 'Caps Lock je uključen';
        caps.hidden = true;
        wrap.after(caps);
        const check = (e) => {
            if (e.getModifierState) {
                caps.hidden = !e.getModifierState('CapsLock');
            }
        };
        el.addEventListener('keydown', check);
        el.addEventListener('keyup', check);
        el.addEventListener('blur', () => { caps.hidden = true; });
    });
}

function initClearable(root) {
    root.querySelectorAll('input[type="search"]:not([data-kt-clear]), input[data-kt-clearable]:not([data-kt-clear])').forEach((el) => {
        el.dataset.ktClear = '1';
        const wrap = document.createElement('div');
        wrap.className = 'kt-input-icon';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);
        wrap.insertAdjacentHTML('afterbegin', icon('search', 'kt-input-icon-start'));
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.className = 'kt-input-clear';
        clear.setAttribute('aria-label', 'Obriši');
        clear.innerHTML = icon('x');
        const sync = () => { clear.hidden = el.value === ''; };
        clear.addEventListener('click', () => { el.value = ''; sync(); el.focus(); el.dispatchEvent(new Event('input', { bubbles: true })); });
        el.addEventListener('input', sync);
        wrap.appendChild(clear);
        sync();
    });
}

function initTextareas(root) {
    const areas = root.querySelectorAll('textarea:not([data-kt-autosize])');
    areas.forEach((el) => {
        el.dataset.ktAutosize = '1';
        const max = Number(el.getAttribute('maxlength'));
        if (max > 0) {
            const counter = document.createElement('div');
            counter.className = 'kt-counter form-text';
            const sync = () => { counter.textContent = `${el.value.length} / ${max}`; counter.classList.toggle('is-near', el.value.length > max * 0.9); };
            el.addEventListener('input', sync);
            el.after(counter);
            sync();
        }
    });
    autosize(areas);
}

function initTooltips(root) {
    root.querySelectorAll('[data-bs-toggle="tooltip"], .kt-btn-icon[title], .kt-icon-btn[title]').forEach((el) => {
        if (!Tooltip.getInstance(el)) {
            new Tooltip(el, { delay: { show: 300, hide: 0 }, trigger: 'hover focus' });
        }
    });
}

export function initForms(root = document) {
    initSelects(root);
    initDatepickers(root);
    initMasks(root);
    initPasswords(root);
    initClearable(root);
    initTextareas(root);
    initTooltips(root);
}

initForms();
// sadržaj dodat kasnije (npr. red u job picker-u) javlja se događajem kt:content
document.addEventListener('kt:content', (e) => initForms(e.target));
