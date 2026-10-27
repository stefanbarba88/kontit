// Tema (DESIGN.md sekcija 6): svetla / tamna / kao sistem. Početno stanje postavlja inline skript u <head>.
import $ from 'jquery';
// Ovde: prekidač, čuvanje preko AJAX-a, događaj kt:themechange i pamćenje skupljenog sidebara.
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
    root.setAttribute('data-color-theme', mode);
    const meta = document.querySelector('meta[name="theme-color"]:not([media])');
    if (meta) {
        meta.setAttribute('content', mode === 'dark' ? '#0B0F14' : '#F5F6F8');
    }
    document.dispatchEvent(new CustomEvent('kt:themechange', { detail: { mode, preference: pref } }));
}

const stored = () => {
    try {
        return localStorage.getItem('kt-theme');
    } catch (e) {
        return null;
    }
};

const markActive = (pref) => {
    $('[data-theme-choice]').removeClass('active').filter(`[data-theme-choice="${pref}"]`).addClass('active');
    const icon = { light: 'ph-sun', dark: 'ph-moon', system: 'ph-desktop' }[pref];
    $('#theme-toggle i').attr('class', icon);
};

$(document).on('click', '[data-theme-choice]', function (e) {
    e.preventDefault();
    const pref = $(this).data('themeChoice');
    try {
        localStorage.setItem('kt-theme', pref);
    } catch (err) { /* privatni mod */ }
    applyTheme(pref, true);
    markActive(pref);
    if ($('meta[name="csrf-token"]').length) {
        ajax('POST', '/ajax/theme', { theme: pref });
    }
});

// "Kao sistem" prati promenu podešavanja operativnog sistema
media.addEventListener('change', () => {
    if ((stored() || 'system') === 'system') {
        applyTheme('system');
    }
});

markActive(stored() || document.documentElement.dataset.ktPref || 'system');

// Skupljen sidebar se pamti
const sidebar = document.querySelector('.sidebar-main');
if (sidebar) {
    try {
        if (localStorage.getItem('kt-sidebar') === 'collapsed') {
            sidebar.classList.add('sidebar-main-resized');
        }
    } catch (e) { /* nema localStorage */ }
    $(document).on('click', '.sidebar-main-resize', () => {
        window.setTimeout(() => {
            try {
                localStorage.setItem('kt-sidebar', sidebar.classList.contains('sidebar-main-resized') ? 'collapsed' : 'expanded');
            } catch (e) { /* nema localStorage */ }
        }, 0);
    });
}

// swatch-evi boje tima: boja radio dugmeta dolazi iz data-swatch
$('[data-swatch]').each(function () {
    $(this).css('background-color', $(this).data('swatch'));
});
