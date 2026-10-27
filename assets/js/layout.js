// Raspored: skupljanje sidebara (pamti se), otvaranje na manjim ekranima, tooltip sa nazivom stavke kad je skupljen.
import $ from 'jquery';
import { Tooltip } from 'bootstrap';

const root = document.documentElement;
const tooltips = [];

const syncTooltips = () => {
    tooltips.splice(0).forEach((t) => t.dispose());
    if (!root.classList.contains('kt-sidebar-collapsed') || window.innerWidth < 992) {
        return;
    }
    document.querySelectorAll('.kt-nav-link').forEach((link) => {
        tooltips.push(new Tooltip(link, { placement: 'right', trigger: 'hover', delay: { show: 300, hide: 0 } }));
    });
};

$(document).on('click', '[data-kt-sidebar-collapse]', () => {
    root.classList.toggle('kt-sidebar-collapsed');
    try {
        localStorage.setItem('kt-sidebar', root.classList.contains('kt-sidebar-collapsed') ? 'collapsed' : 'expanded');
    } catch (e) { /* bez localStorage */ }
    syncTooltips();
});

$(document).on('click', '[data-kt-sidebar-open]', () => root.classList.add('kt-sidebar-open'));
$(document).on('click', '[data-kt-sidebar-close]', () => root.classList.remove('kt-sidebar-open'));
$(document).on('keydown', (e) => {
    if (e.key === 'Escape') {
        root.classList.remove('kt-sidebar-open');
    }
});

syncTooltips();
window.addEventListener('resize', () => window.requestAnimationFrame(syncTooltips));
