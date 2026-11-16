// Tema (DESIGN.md sekcija 6): svetla / tamna / kao sistem. Početno stanje postavlja inline skript u <head>.
// Ovde: prekidač, čuvanje preko AJAX-a i događaj kt:themechange.
import $ from 'jquery';
import { ajax } from './ajax.js';

const root = document.documentElement;
const media = window.matchMedia('(prefers-color-scheme: dark)');

const resolve = (pref) => (pref === 'dark' || (pref === 'system' && media.matches) ? 'dark' : 'light');

export function applyTheme(pref, animate = false) {
    if (animate) {
        root.classList.add('kt-theme-transition');
        window.setTimeout(() => root.classList.remove('kt-theme-transition'), 260);
    }
    const mode = resolve(pref);
    root.setAttribute('data-bs-theme', mode);
    root.setAttribute('data-kt-pref', pref);
    document.dispatchEvent(new CustomEvent('kt:themechange', { detail: { mode, preference: pref } }));
}

$(document).on('click', '[data-theme-choice]', function (e) {
    e.preventDefault();
    const pref = $(this).data('themeChoice');
    try {
        localStorage.setItem('kt-theme', pref);
    } catch (err) { /* privatni mod */ }
    applyTheme(pref, true);
    // dok traje "Prikaži kao korisnik" tema se menja samo lokalno: čuvanje bi menjalo profil tuđeg naloga (SPEC 8s)
    if ($('meta[name="csrf-token"]').length && !document.body.hasAttribute('data-kt-impersonating')) {
        ajax('POST', '/ajax/theme', { theme: pref });
    }
});

// "Kao sistem" prati promenu podešavanja operativnog sistema
media.addEventListener('change', () => {
    if ((root.getAttribute('data-kt-pref') || 'system') === 'system') {
        applyTheme('system');
    }
});

// swatch-evi boje tima: boja radio dugmeta dolazi iz data-swatch
$('[data-swatch]').each(function () {
    $(this).css('background-color', $(this).data('swatch'));
});
